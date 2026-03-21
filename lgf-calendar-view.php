<?php
/**
 * Plugin Name: LGF Calendar View
 * Description: Displays Motopress Hotel Booking data in a LibreOffice Calc-style layout
 * Version: 1.3
 * Author: Angus Watson, Quinn (mistral/codestral) & Kylie (stepfun/step-3.5-flash:free)
 * Text Domain: lgf-calendar-view
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

// Check for required Motopress Hotel Booking plugin
add_action( 'plugins_loaded', 'lgf_calendar_view_check_dependency' );
function lgf_calendar_view_check_dependency() {
    if ( ! function_exists( 'MPHB' ) || ! class_exists( 'HotelBookingPlugin' ) ) {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e( 'LGF Calendar View requires Motopress Hotel Booking plugin to be installed and active.', 'lgf-calendar-view' ); ?></p>
            </div>
            <?php
        } );
        return;
    }
}

function lgf_calendar_view_user_can_access() {
    return is_user_logged_in() && current_user_can( 'manage_options' );
}

function lgf_calendar_view_enqueue_shared_assets( $context = 'frontend' ) {
    wp_register_style( 'lgf-calendar-view', plugin_dir_url( __FILE__ ) . 'assets/style.css', [], '1.4' );
    wp_enqueue_style( 'lgf-calendar-view' );

    wp_register_script( 'lgf-calendar-view', plugin_dir_url( __FILE__ ) . 'assets/calendar-navigation.js', [ 'jquery' ], '1.2', true );
    wp_localize_script( 'lgf-calendar-view', 'lgfCalendar', [
        'restUrl' => esc_url_raw( rest_url( 'lgf-calendar/v1/table' ) ),
        'nonce'   => wp_create_nonce( 'wp_rest' ),
        'context' => $context,
        'adminPageUrl' => admin_url( 'admin.php?page=lgf-calendar-view' ),
    ] );
    wp_enqueue_script( 'lgf-calendar-view' );
}

// Enqueue scripts and styles
add_action( 'wp_enqueue_scripts', 'lgf_calendar_view_enqueue_assets' );
function lgf_calendar_view_enqueue_assets() {
    if ( ! lgf_calendar_view_user_can_access() ) {
        return;
    }

    $post = get_post();
    if ( ! $post || ! has_shortcode( $post->post_content, 'lgf_calendar_view' ) ) {
        return;
    }

    lgf_calendar_view_enqueue_shared_assets( 'frontend' );
}

add_action( 'admin_enqueue_scripts', 'lgf_calendar_view_enqueue_admin_assets' );
function lgf_calendar_view_enqueue_admin_assets( $hook_suffix ) {
    if ( ! lgf_calendar_view_user_can_access() ) {
        return;
    }

    if ( 'toplevel_page_lgf-calendar-view' !== $hook_suffix ) {
        return;
    }

    lgf_calendar_view_enqueue_shared_assets( 'admin' );
}

/**
 * Get cached calendar data or build and cache it.
 *
 * @param int $month 1-12
 * @param int $year
 * @return array Calendar data structure
 */
function lgf_calendar_view_get_calendar_data( $month = null, $year = null ) {
    // Use Motopress facades directly; they handle caching themselves
    if ( ! function_exists( 'mphb_rooms_facade' ) ) {
        return [
            'rooms' => [],
            'matrix' => [],
            'month' => $month,
            'year' => $year,
            'days_in_month' => 0,
            'days' => [],
        ];
    }

    $month = $month ?: date( 'n' );
    $year  = $year  ?: date( 'Y' );

    // Build transient key
    $transient_key = 'lgf_calendar_' . $year . '_' . $month;
    $cached = get_transient( $transient_key );
    // DEBUG: disable cache retrieval
    // if ( false !== $cached ) {
    //     return $cached;
    // }

    $first_day = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
    $days_in_month = intval( $first_day->format( 't' ) );
    $last_day = clone $first_day;
    $last_day->setDate( $year, $month, $days_in_month )->setTime( 23, 59, 59 );

    $first_day_str = $first_day->format( 'Y-m-d' );
    $last_day_str  = $last_day->format( 'Y-m-d' );

    // Fetch rooms for the current language (Polylang compatibility)
    $args = [
        'post_type'      => 'mphb_room',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ];

    // If Polylang is active, filter by current language to avoid duplicate rooms
    if ( function_exists( 'pll_current_language' ) ) {
        $args['lang'] = pll_current_language();
    } elseif ( function_exists( 'icl_object_id' ) && defined( 'ICL_LANGUAGE_CODE' ) ) {
        // WPML compatibility
        $args['lang'] = ICL_LANGUAGE_CODE;
    }

    error_log( 'LGF Calendar DEBUG: get_posts args=' . print_r( $args, true ) );
    $room_ids = get_posts( $args );
    error_log( 'LGF Calendar DEBUG: get_posts returned count=' . count( $room_ids ) . ' ids=' . implode( ',', $room_ids ) );

    // Determine if we're filtering by language (Polylang/WPML)
    $isLanguageFiltered = ! empty( $args['lang'] );

    // Fallback only if not language-filtered: language-filtered empty likely means no rooms for that language
    if ( empty( $room_ids ) && ! $isLanguageFiltered ) {
        error_log( 'LGF Calendar DEBUG: get_posts returned empty and not language-filtered, trying direct DB query fallback' );
        global $wpdb;
        $room_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY title",
                'mphb_room',
                'publish'
            )
        );
        error_log( 'LGF Calendar DEBUG: fallback DB query returned count=' . count( $room_ids ) . ' ids=' . implode( ',', $room_ids ) );
    }

    // Convert to objects with id and title for consistency
    $rooms = array_map( function( $room_id ) use ( $isLanguageFiltered ) {
        $title = get_the_title( $room_id );
        // If NOT language-filtered (i.e., duplicate rooms present), strip trailing " 1" from duplicate room titles
        if ( ! $isLanguageFiltered && preg_match( '/^(.*?)(?:\s+1)$/', $title, $matches ) ) {
            $title = $matches[1];
        }
        return (object) [
            'id' => $room_id,
            'title' => $title,
        ];
    }, $room_ids );

    // Debug: log how many rooms we fetched
    error_log( 'LGF Calendar: Fetched ' . count( $rooms ) . ' rooms. IDs: ' . implode( ',', wp_list_pluck( $rooms, 'id' ) ) );

    // Assign theme colors per room (1-indexed)
    $room_colors = [
        1 => '#cc99ff',
        2 => '#b4c7e7',
        3 => '#a9d18e',
        4 => '#ffe699',
        5 => '#f4b183',
    ];
    foreach ( $rooms as $idx => $room ) {
        $color = $room_colors[ $idx + 1 ] ?? '#cccccc';
        $rooms[ $idx ]->color = $color;
    }

    if ( empty( $rooms ) ) {
        $result = [
            'rooms' => [],
            'matrix' => [],
            'month' => $month,
            'year' => $year,
            'days_in_month' => $days_in_month,
            'days' => range(1, $days_in_month),
        ];
        set_transient( $transient_key, $result, 30 * MINUTE_IN_SECONDS );
        return $result;
    }

    // Query bookings and their assigned rooms using Motopress CPT structure
    global $wpdb;

    // Booking statuses that block rooms (from Motopress research)
    $locked_statuses = [ 'confirmed', 'pending', 'pending-user', 'pending-payment' ];

    $bookings = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT 
                b.ID as booking_id,
                b.post_status as booking_status,
                check_in.meta_value as check_in_date,
                check_out.meta_value as check_out_date,
                rr.ID as reserved_room_id,
                room_id_meta.meta_value as room_id,
                channel_meta.meta_value as channel,
                total_price_meta.meta_value as total_price,
                adults_meta.meta_value as adults,
                children_meta.meta_value as children,
                dinner_meta.meta_value as dinner
            FROM {$wpdb->posts} AS b
            INNER JOIN {$wpdb->posts} AS rr ON rr.post_parent = b.ID AND rr.post_type = 'mphb_reserved_room'
            INNER JOIN {$wpdb->postmeta} AS room_id_meta ON room_id_meta.post_id = rr.ID AND room_id_meta.meta_key = '_mphb_room_id'
            INNER JOIN {$wpdb->postmeta} AS check_in ON check_in.post_id = b.ID AND check_in.meta_key = 'mphb_check_in_date'
            INNER JOIN {$wpdb->postmeta} AS check_out ON check_out.post_id = b.ID AND check_out.meta_key = 'mphb_check_out_date'
            LEFT JOIN {$wpdb->postmeta} AS channel_meta ON channel_meta.post_id = b.ID AND channel_meta.meta_key = '_mphb_channel'
            LEFT JOIN {$wpdb->postmeta} AS total_price_meta ON total_price_meta.post_id = b.ID AND total_price_meta.meta_key = '_mphb_total_price'
            LEFT JOIN {$wpdb->postmeta} AS adults_meta ON adults_meta.post_id = rr.ID AND adults_meta.meta_key = '_mphb_adults'
            LEFT JOIN {$wpdb->postmeta} AS children_meta ON children_meta.post_id = rr.ID AND children_meta.meta_key = '_mphb_children'
            LEFT JOIN {$wpdb->postmeta} AS dinner_meta ON dinner_meta.post_id = rr.ID AND dinner_meta.meta_key = '_mphb_dinner'
            WHERE b.post_type = 'mphb_booking'
              AND b.post_status IN ('" . implode( "','", array_map( 'esc_sql', $locked_statuses ) ) . "')
              AND check_in.meta_value <= %s
              AND check_out.meta_value >= %s
            ",
            $last_day_str,
            $first_day_str
        )
    );

    error_log( 'LGF Calendar: Bookings found: ' . count( $bookings ) );

    // Build matrix: room_id => date_str => ['booking' => object, 'is_checkin' => bool, 'is_checkout' => bool]
    $matrix = [];
    foreach ( $rooms as $room ) {
        $matrix[ $room->id ] = [];
    }

    foreach ( $bookings as $b ) {
        $room_id = intval( $b->room_id );
        if ( ! isset( $matrix[ $room_id ] ) ) {
            continue;
        }

        $check_in = new DateTime( $b->check_in_date );
        $check_out = new DateTime( $b->check_out_date );

        // Mark stay nights (check-in inclusive, check-out exclusive)
        for ( $date = clone $check_in; $date < $check_out; $date->modify( '+1 day' ) ) {
            $date_str = $date->format( 'Y-m-d' );
            if ( $date_str < $first_day_str || $date_str > $last_day_str ) {
                continue;
            }
            if ( ! isset( $matrix[ $room_id ][ $date_str ] ) ) {
                $matrix[ $room_id ][ $date_str ] = [
                    'booking' => null,
                    'is_checkin' => false,
                    'is_checkout' => false,
                ];
            }
            $entry = &$matrix[ $room_id ][ $date_str ];
            $entry['booking'] = (object) [
                'id' => $b->booking_id,
                'status' => $b->booking_status,
                'check_in' => $b->check_in_date,
                'check_out' => $b->check_out_date,
                'room_id' => $room_id,
                'channel' => $b->channel,
                'total_price' => $b->total_price,
                'adults' => intval( $b->adults ),
                'children' => intval( $b->children ),
                'dinner' => $b->dinner,
            ];
            if ( $date_str === $b->check_in_date ) {
                $entry['is_checkin'] = true;
            }
        }

        // Mark check-out day separately (if within month)
        $check_out_date_str = $check_out->format( 'Y-m-d' );
        if ( $check_out_date_str >= $first_day_str && $check_out_date_str <= $last_day_str ) {
            if ( ! isset( $matrix[ $room_id ][ $check_out_date_str ] ) ) {
                $matrix[ $room_id ][ $check_out_date_str ] = [
                    'booking' => null,
                    'is_checkin' => false,
                    'is_checkout' => false,
                ];
            }
            $matrix[ $room_id ][ $check_out_date_str ]['is_checkout'] = true;
            if ( is_null( $matrix[ $room_id ][ $check_out_date_str ]['booking'] ) ) {
                $matrix[ $room_id ][ $check_out_date_str ]['booking'] = (object) [
                    'id' => $b->booking_id,
                    'status' => $b->booking_status,
                    'check_in' => $b->check_in_date,
                    'check_out' => $b->check_out_date,
                    'room_id' => $room_id,
                    'channel' => $b->channel,
                    'total_price' => $b->total_price,
                    'adults' => intval( $b->adults ),
                    'children' => intval( $b->children ),
                    'dinner' => $b->dinner,
                ];
            }
        }
    }

    // DEBUG: log counts early
    file_put_contents( '/tmp/lgf_calendar_debug.log', 'Rooms=' . count( $rooms ) . ', Bookings=' . count( $bookings ) . "\n", FILE_APPEND );

    // Fetch guest names for all unique booking IDs
    $booking_ids = array_unique( array_column( $bookings, 'booking_id' ) );
    $guest_names = [];
    if ( ! empty( $booking_ids ) ) {
        $placeholders = implode( ',', array_fill( 0, count( $booking_ids ), '%d' ) );
        $meta_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders) AND meta_key IN ('_mphb_first_name', '_mphb_last_name', '_mphb_phone', 'mphb_phone', '_billing_phone', 'billing_phone')",
                $booking_ids
            )
        );
        foreach ( $meta_rows as $row ) {
            $bid = $row->post_id;
            if ( ! isset( $guest_names[ $bid ] ) ) {
                $guest_names[ $bid ] = new stdClass();
            }
            if ( $row->meta_key == '_mphb_first_name' ) {
                $guest_names[ $bid ]->first_name = $row->meta_value;
            } elseif ( $row->meta_key == '_mphb_last_name' ) {
                $guest_names[ $bid ]->last_name = $row->meta_value;
            } elseif ( in_array( $row->meta_key, [ '_mphb_phone', 'mphb_phone', '_billing_phone', 'billing_phone' ], true ) ) {
                if ( empty( $guest_names[ $bid ]->phone ) ) {
                    $guest_names[ $bid ]->phone = $row->meta_value;
                }
            }
        }
        foreach ( $matrix as &$room_matrix ) {
            foreach ( $room_matrix as &$entry ) {
                if ( $entry['booking'] ) {
                    $booking = $entry['booking'];
                    // Set guest name if available
                    if ( isset( $guest_names[ $booking->id ] ) ) {
                        $guest = $guest_names[ $booking->id ];
                        $booking->guest_name = trim( ($guest->first_name ?? '') . ' ' . ($guest->last_name ?? '') );
                        $booking->phone = $guest->phone ?? '';
                    } else {
                        $booking->guest_name = '';
                        $booking->phone = '';
                    }
                    // Compute derived fields if not already set
                    if ( ! isset( $booking->platform_label ) ) {
                        $channel = $booking->channel ?? '';
                        $platform_map = [ 'W' => 'Website', 'B' => 'Booking.com', 'E' => 'Email', 'T' => 'Telephone' ];
                        $booking->platform_label = $platform_map[ $channel ] ?? $channel;
                        $adults = intval( $booking->adults ?? 0 );
                        $children = intval( $booking->children ?? 0 );
                        $booking->occupancy_str = $adults . 'A ' . $children . 'E';
                        $total = floatval( $booking->total_price ?? 0 );
                        $booking->tarif = $total;
                        $booking->commission = ( $channel === 'B' ) ? round( $total * 0.15, 2 ) : 0;
                    }
                }
            }
        }
    }

    $result = [
        'rooms' => $rooms,
        'matrix' => $matrix,
        'month' => $month,
        'year' => $year,
        'days_in_month' => $days_in_month,
        'days' => range(1, $days_in_month),
    ];

    // DEBUG: disable caching to avoid stale data
    // set_transient( $transient_key, $result, 30 * MINUTE_IN_SECONDS );

    return $result;
}

function lgf_calendar_view_get_month_tabs( $month, $year ) {
    $tabs = [];

    for ( $month_index = 1; $month_index <= 12; $month_index++ ) {
        $date = new DateTime( sprintf( '%04d-%02d-01', $year, $month_index ) );

        $tabs[] = [
            'month'   => (int) $date->format( 'n' ),
            'year'    => (int) $date->format( 'Y' ),
            'label'   => date_i18n( 'M', $date->getTimestamp() ),
            'current' => (int) $date->format( 'n' ) === (int) $month && (int) $date->format( 'Y' ) === (int) $year,
        ];
    }

    return $tabs;
}

function lgf_calendar_view_build_daily_summary( $calendar_data ) {
    $summary = [];

    foreach ( $calendar_data['days'] as $day ) {
        $date_str = sprintf( '%04d-%02d-%02d', $calendar_data['year'], $calendar_data['month'], $day );
        $income_day = 0.0;
        $commission_day = 0.0;
        $rooms_count = 0;
        $table_dhotes = 0;
        $tax_adults = 0;
        $tax_children = 0;
        $seen_bookings = [];

        foreach ( $calendar_data['matrix'] as $room_matrix ) {
            if ( empty( $room_matrix[ $date_str ]['booking'] ) ) {
                continue;
            }

            $booking = $room_matrix[ $date_str ]['booking'];
            $booking_id = (int) $booking->id;

            if ( isset( $seen_bookings[ $booking_id ] ) ) {
                continue;
            }

            $seen_bookings[ $booking_id ] = true;
            $rooms_count++;
            $table_dhotes += ! empty( $booking->dinner ) ? 1 : 0;
            $tax_adults += (int) ( $booking->adults ?? 0 );
            $tax_children += (int) ( $booking->children ?? 0 );

            $nights = max( 1, (int) round( ( strtotime( $booking->check_out ) - strtotime( $booking->check_in ) ) / DAY_IN_SECONDS ) );
            $income_day += (float) ( $booking->tarif ?? 0 ) / $nights;
            $commission_day += (float) ( $booking->commission ?? 0 ) / $nights;
        }

        $previous_income = $day > 1 ? ( $summary[ $day - 1 ]['income_accumulated'] ?? 0 ) : 0;
        $previous_commission = $day > 1 ? ( $summary[ $day - 1 ]['booking_accumulated'] ?? 0 ) : 0;

        $summary[ $day ] = [
            'income_day' => $income_day,
            'income_accumulated' => $previous_income + $income_day,
            'table_dhotes' => $table_dhotes,
            'rooms' => $rooms_count,
            'tourist_tax_adults' => $tax_adults,
            'tourist_tax_children' => $tax_children,
            'booking_payment' => $commission_day,
            'booking_accumulated' => $previous_commission + $commission_day,
        ];
    }

    return $summary;
}

add_action( 'admin_menu', 'lgf_calendar_view_register_admin_menu' );
function lgf_calendar_view_register_admin_menu() {
    if ( ! lgf_calendar_view_user_can_access() ) {
        return;
    }

    add_menu_page(
        __( 'LGF Calendar View', 'lgf-calendar-view' ),
        __( 'LGF Calendar', 'lgf-calendar-view' ),
        'manage_options',
        'lgf-calendar-view',
        'lgf_calendar_view_render_admin_page',
        'dashicons-calendar-alt',
        58
    );
}

function lgf_calendar_view_render_admin_page() {
    if ( ! lgf_calendar_view_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'lgf-calendar-view' ) );
    }

    $month = isset( $_GET['month'] ) ? intval( $_GET['month'] ) : intval( date( 'n' ) );
    $year  = isset( $_GET['year'] ) ? intval( $_GET['year'] ) : intval( date( 'Y' ) );

    $calendar_data = lgf_calendar_view_get_calendar_data( $month, $year );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'LGF Calendar View', 'lgf-calendar-view' ) . '</h1>';
    echo lgf_calendar_view_render_calendar( $calendar_data, 'admin' );
    echo '</div>';
}

// Shortcode to display the calendar view
add_shortcode( 'lgf_calendar_view', 'lgf_calendar_view_shortcode' );

function lgf_calendar_view_render_calendar( $calendar_data, $context = 'frontend' ) {
    $template = locate_template( 'lgf-calendar-view/booking-view.php' );
    if ( ! $template ) {
        $template = plugin_dir_path( __FILE__ ) . 'templates/booking-view.php';
    }

    $calendar_context = $context;
    $calendar_base_url = 'admin' === $context
        ? admin_url( 'admin.php?page=lgf-calendar-view' )
        : get_permalink();

    ob_start();
    include $template;
    return ob_get_clean();
}

function lgf_calendar_view_shortcode( $atts ) {
    if ( ! lgf_calendar_view_user_can_access() ) {
        return '';
    }

    $atts = shortcode_atts( [
        'month' => date( 'n' ),
        'year'  => date( 'Y' ),
    ], $atts, 'lgf_calendar_view' );

    $month = intval( $atts['month'] );
    $year  = intval( $atts['year'] );

    $calendar_data = lgf_calendar_view_get_calendar_data( $month, $year );

    return lgf_calendar_view_render_calendar( $calendar_data, 'frontend' );
}

/**
 * REST API endpoint to return just the table for AJAX navigation.
 */
add_action( 'rest_api_init', function() {
    register_rest_route( 'lgf-calendar/v1', '/table', [
        'methods'  => 'GET',
        'callback' => 'lgf_calendar_rest_table',
        'args'     => [
            'month' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param >= 1 && $param <= 12;
                },
                'required' => true,
            ],
            'year' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param >= 2000 && $param <= 2100;
                },
                'required' => true,
            ],
        ],
        'permission_callback' => function() {
            return lgf_calendar_view_user_can_access();
        },
    ] );
} );

function lgf_calendar_rest_table( WP_REST_Request $request ) {
    $month = intval( $request->get_param( 'month' ) );
    $year  = intval( $request->get_param( 'year' ) );
    $context = $request->get_param( 'context' );
    $context = 'admin' === $context ? 'admin' : 'frontend';

    error_log( "LGF REST: requested month=$month year=$year context=$context" );

    $calendar_data = lgf_calendar_view_get_calendar_data( $month, $year );

    error_log( 'LGF REST: rooms count=' . count( $calendar_data['rooms'] ) . ', matrix rooms=' . count( $calendar_data['matrix'] ) );

    $html = lgf_calendar_view_render_calendar( $calendar_data, $context );

    return rest_ensure_response( [ 'html' => $html ] );
}