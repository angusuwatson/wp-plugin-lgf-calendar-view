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

// Enqueue scripts and styles
add_action( 'wp_enqueue_scripts', 'lgf_calendar_view_enqueue_assets' );
function lgf_calendar_view_enqueue_assets() {
    if ( has_shortcode( get_post()->post_content ?? '', 'lgf_calendar_view' ) ) {
        wp_register_style( 'lgf-calendar-view', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
        wp_enqueue_style( 'lgf-calendar-view' );

        wp_register_script( 'lgf-calendar-view', plugin_dir_url( __FILE__ ) . 'assets/calendar-navigation.js', [ 'jquery' ], '1.0', true );
        wp_localize_script( 'lgf-calendar-view', 'lgfCalendar', [
            'restUrl' => esc_url_raw( rest_url( 'lgf-calendar/v1/table' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ] );
        wp_enqueue_script( 'lgf-calendar-view' );
    }
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
    if ( false !== $cached ) {
        return $cached;
    }

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

    $room_ids = get_posts( $args );

    // Determine if we're filtering by language (Polylang/WPML)
    $isLanguageFiltered = ! empty( $args['lang'] );

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
                room_id_meta.meta_value as room_id
            FROM {$wpdb->posts} AS b
            INNER JOIN {$wpdb->posts} AS rr ON rr.post_parent = b.ID AND rr.post_type = 'mphb_reserved_room'
            INNER JOIN {$wpdb->postmeta} AS room_id_meta ON room_id_meta.post_id = rr.ID AND room_id_meta.meta_key = '_mphb_room_id'
            INNER JOIN {$wpdb->postmeta} AS check_in ON check_in.post_id = b.ID AND check_in.meta_key = 'mphb_check_in_date'
            INNER JOIN {$wpdb->postmeta} AS check_out ON check_out.post_id = b.ID AND check_out.meta_key = 'mphb_check_out_date'
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
                "SELECT post_id, meta_key, meta_value FROM {$mphb_postmeta} WHERE post_id IN ($placeholders) AND meta_key IN ('_mphb_first_name', '_mphb_last_name')",
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
            }
        }
        foreach ( $matrix as &$room_matrix ) {
            foreach ( $room_matrix as &$entry ) {
                if ( $entry['booking'] && isset( $guest_names[ $entry['booking']->id ] ) ) {
                    $guest = $guest_names[ $entry['booking']->id ];
                    $entry['booking']->guest_name = trim( ($guest->first_name ?? '') . ' ' . ($guest->last_name ?? '') );
                } elseif ( $entry['booking'] ) {
                    $entry['booking']->guest_name = '';
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

    // Cache for 30 minutes
    set_transient( $transient_key, $result, 30 * MINUTE_IN_SECONDS );

    return $result;
}

// Shortcode to display the calendar view
add_shortcode( 'lgf_calendar_view', 'lgf_calendar_view_shortcode' );

function lgf_calendar_view_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'month' => date( 'n' ),
        'year'  => date( 'Y' ),
    ], $atts, 'lgf_calendar_view' );

    $month = intval( $atts['month'] );
    $year  = intval( $atts['year'] );

    $calendar_data = lgf_calendar_view_get_calendar_data( $month, $year );

    // Allow theme override via template hierarchy
    $template = locate_template( 'lgf-calendar-view/booking-view.php' );
    if ( ! $template ) {
        $template = plugin_dir_path( __FILE__ ) . 'templates/booking-view.php';
    }

    // Full output (nav + table)
    ob_start();
    include $template;
    return ob_get_clean();
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
            return current_user_can( 'read' ); // anyone who can read site content
        },
    ] );
} );

function lgf_calendar_rest_table( WP_REST_Request $request ) {
    $month = intval( $request->get_param( 'month' ) );
    $year  = intval( $request->get_param( 'year' ) );

    $calendar_data = lgf_calendar_view_get_calendar_data( $month, $year );

    // Render only the table part (include a minimal template that outputs just the table)
    $template = plugin_dir_path( __FILE__ ) . 'templates/table-fragment.php';
    if ( ! file_exists( $template ) ) {
        return new WP_Error( 'template_missing', 'Table fragment template not found', [ 'status' => 500 ] );
    }

    ob_start();
    include $template;
    $html = ob_get_clean();

    return rest_ensure_response( [ 'html' => $html ] );
}