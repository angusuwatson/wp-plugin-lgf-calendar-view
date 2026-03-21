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

function lgf_calendar_view_get_locked_booking_statuses() {
    if ( function_exists( 'MPHB' ) && method_exists( MPHB()->postTypes()->booking()->statuses(), 'getLockedRoomStatuses' ) ) {
        return MPHB()->postTypes()->booking()->statuses()->getLockedRoomStatuses();
    }

    return [ 'confirmed', 'pending', 'pending-user', 'pending-payment' ];
}

function lgf_calendar_view_build_booking_payload( $booking, $reserved_room ) {
    $customer = method_exists( $booking, 'getCustomer' ) ? $booking->getCustomer() : null;

    $guest_name = '';
    if ( $reserved_room && method_exists( $reserved_room, 'getGuestName' ) ) {
        $guest_name = trim( (string) $reserved_room->getGuestName() );
    }

    if ( '' === $guest_name && $customer ) {
        $first_name = method_exists( $customer, 'getFirstName' ) ? (string) $customer->getFirstName() : '';
        $last_name  = method_exists( $customer, 'getLastName' ) ? (string) $customer->getLastName() : '';
        $guest_name = trim( $first_name . ' ' . $last_name );
    }

    $adults = $reserved_room && method_exists( $reserved_room, 'getAdults' ) ? (int) $reserved_room->getAdults() : 0;
    $children = $reserved_room && method_exists( $reserved_room, 'getChildren' ) ? (int) $reserved_room->getChildren() : 0;

    $occupancy_parts = [];
    if ( $adults > 0 ) {
        $occupancy_parts[] = sprintf( '%dA', $adults );
    }
    if ( $children > 0 ) {
        $occupancy_parts[] = sprintf( '%dC', $children );
    }
    if ( empty( $occupancy_parts ) ) {
        $occupancy_parts[] = '0';
    }

    $is_imported = method_exists( $booking, 'isImported' ) ? (bool) $booking->isImported() : ! empty( get_post_meta( $booking->getId(), '_mphb_sync_id', true ) );

    $tarif = '';
    if ( ! $is_imported && $reserved_room && method_exists( $reserved_room, 'getLastRoomPriceBreakdown' ) ) {
        $room_breakdown = $reserved_room->getLastRoomPriceBreakdown();
        if ( is_array( $room_breakdown ) && isset( $room_breakdown['room']['discount_total'] ) ) {
            $tarif = (float) $room_breakdown['room']['discount_total'];
        }
    }

    return (object) [
        'id' => method_exists( $booking, 'getId' ) ? (int) $booking->getId() : 0,
        'status' => method_exists( $booking, 'getStatus' ) ? (string) $booking->getStatus() : '',
        'check_in' => method_exists( $booking, 'getCheckInDate' ) && $booking->getCheckInDate() ? $booking->getCheckInDate()->format( 'Y-m-d' ) : '',
        'check_out' => method_exists( $booking, 'getCheckOutDate' ) && $booking->getCheckOutDate() ? $booking->getCheckOutDate()->format( 'Y-m-d' ) : '',
        'room_id' => $reserved_room && method_exists( $reserved_room, 'getRoomId' ) ? (int) $reserved_room->getRoomId() : 0,
        'reserved_room_id' => $reserved_room && method_exists( $reserved_room, 'getId' ) ? (int) $reserved_room->getId() : 0,
        'guest_name' => $guest_name,
        'phone' => $customer && method_exists( $customer, 'getPhone' ) ? (string) $customer->getPhone() : '',
        'adults' => $adults,
        'children' => $children,
        'occupancy_str' => implode( ' ', $occupancy_parts ),
        'channel' => $is_imported ? 'I' : 'W',
        'channel_label' => $is_imported ? 'Imported' : 'Website',
        'is_imported' => $is_imported,
        'tarif' => $tarif,
        'commission' => '',
        'dinner' => '',
    ];
}

/**
 * Get cached calendar data or build and cache it.
 *
 * @param int $month 1-12
 * @param int $year
 * @return array Calendar data structure
 */
function lgf_calendar_view_get_calendar_data( $month = null, $year = null ) {
    if ( ! function_exists( 'MPHB' ) || ! function_exists( 'mphb_rooms_facade' ) ) {
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

    $transient_key = 'lgf_calendar_' . $year . '_' . $month;
    $cached = get_transient( $transient_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $first_day = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
    $days_in_month = (int) $first_day->format( 't' );
    $last_day = clone $first_day;
    $last_day->setDate( $year, $month, $days_in_month )->setTime( 23, 59, 59 );

    $first_day_str = $first_day->format( 'Y-m-d' );
    $last_day_str  = $last_day->format( 'Y-m-d' );

    $room_args = [
        'post_type'      => 'mphb_room',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'fields'         => 'ids',
    ];

    if ( function_exists( 'pll_current_language' ) ) {
        $room_args['lang'] = pll_current_language();
    } elseif ( function_exists( 'icl_object_id' ) && defined( 'ICL_LANGUAGE_CODE' ) ) {
        $room_args['lang'] = ICL_LANGUAGE_CODE;
    }

    $is_language_filtered = ! empty( $room_args['lang'] );
    $room_ids = get_posts( $room_args );

    if ( empty( $room_ids ) && ! $is_language_filtered ) {
        global $wpdb;
        $room_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY title",
                'mphb_room',
                'publish'
            )
        );
    }

    $rooms = array_map( function( $room_id ) use ( $is_language_filtered ) {
        $title = get_the_title( $room_id );
        if ( ! $is_language_filtered && preg_match( '/^(.*?)(?:\s+1)$/', $title, $matches ) ) {
            $title = $matches[1];
        }

        return (object) [
            'id' => (int) $room_id,
            'title' => $title,
        ];
    }, $room_ids );

    $room_colors = [
        1 => '#cc99ff',
        2 => '#b4c7e7',
        3 => '#a9d18e',
        4 => '#ffe699',
        5 => '#f4b183',
    ];

    foreach ( $rooms as $idx => $room ) {
        $rooms[ $idx ]->color = $room_colors[ $idx + 1 ] ?? '#cccccc';
    }

    if ( empty( $rooms ) ) {
        $result = [
            'rooms' => [],
            'matrix' => [],
            'month' => $month,
            'year' => $year,
            'days_in_month' => $days_in_month,
            'days' => range( 1, $days_in_month ),
        ];
        set_transient( $transient_key, $result, 30 * MINUTE_IN_SECONDS );
        return $result;
    }

    $matrix = [];
    foreach ( $rooms as $room ) {
        $matrix[ $room->id ] = [];
    }

    $bookings = MPHB()->getBookingRepository()->findAllInPeriod(
        $first_day_str,
        $last_day_str,
        [
            'room_locked' => true,
            'post_status' => lgf_calendar_view_get_locked_booking_statuses(),
            'orderby'     => 'date',
            'order'       => 'ASC',
        ]
    );

    foreach ( $bookings as $booking ) {
        if ( ! $booking || ! method_exists( $booking, 'getReservedRooms' ) ) {
            continue;
        }

        $reserved_rooms = $booking->getReservedRooms();
        $check_in = $booking->getCheckInDate();
        $check_out = $booking->getCheckOutDate();

        if ( ! $check_in || ! $check_out ) {
            continue;
        }

        foreach ( $reserved_rooms as $reserved_room ) {
            if ( ! $reserved_room || ! method_exists( $reserved_room, 'getRoomId' ) ) {
                continue;
            }

            $room_id = (int) $reserved_room->getRoomId();
            if ( ! isset( $matrix[ $room_id ] ) ) {
                continue;
            }

            $booking_payload = lgf_calendar_view_build_booking_payload( $booking, $reserved_room );

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

                $matrix[ $room_id ][ $date_str ]['booking'] = clone $booking_payload;
                if ( $date_str === $booking_payload->check_in ) {
                    $matrix[ $room_id ][ $date_str ]['is_checkin'] = true;
                }
            }

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
            }
        }
    }

    $result = [
        'rooms' => $rooms,
        'matrix' => $matrix,
        'month' => $month,
        'year' => $year,
        'days_in_month' => $days_in_month,
        'days' => range( 1, $days_in_month ),
    ];

    set_transient( $transient_key, $result, 30 * MINUTE_IN_SECONDS );

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
            $booking_key = ! empty( $booking->reserved_room_id ) ? 'rr_' . (int) $booking->reserved_room_id : 'b_' . (int) $booking->id;

            if ( isset( $seen_bookings[ $booking_key ] ) ) {
                continue;
            }

            $seen_bookings[ $booking_key ] = true;
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

    $calendar_data = lgf_calendar_view_get_calendar_data( $month, $year );
    $html = lgf_calendar_view_render_calendar( $calendar_data, $context );

    return rest_ensure_response( [ 'html' => $html ] );
}
