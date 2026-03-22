<?php
/**
 * Plugin Name: LGF Calendar View
 * Description: Displays Motopress Hotel Booking data in a LibreOffice Calc-style layout
 * Version: 1.4
 * Author: Angus Watson, Quinn (mistral/codestral) & Kylie (stepfun/step-3.5-flash:free)
 * Text Domain: lgf-calendar-view
 */

defined( 'ABSPATH' ) || exit;

define( 'LGF_CALENDAR_VIEW_VERSION', '1.4' );
define( 'LGF_CALENDAR_VIEW_DB_VERSION', '2' );

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

register_activation_hook( __FILE__, 'lgf_calendar_view_activate' );
add_action( 'plugins_loaded', 'lgf_calendar_view_maybe_upgrade' );

function lgf_calendar_view_activate() {
    lgf_calendar_view_install_tables();
}

function lgf_calendar_view_maybe_upgrade() {
    $installed_version = get_option( 'lgf_calendar_view_db_version' );
    if ( LGF_CALENDAR_VIEW_DB_VERSION !== (string) $installed_version ) {
        lgf_calendar_view_install_tables();
    }
}

function lgf_calendar_view_install_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $daily_notes_table = lgf_calendar_view_daily_notes_table();
    $overlay_table     = lgf_calendar_view_booking_overlay_table();

    $sql_daily_notes = "CREATE TABLE {$daily_notes_table} (
        note_date date NOT NULL,
        note_text longtext NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (note_date)
    ) {$charset_collate};";

    $sql_overlays = "CREATE TABLE {$overlay_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
        reserved_room_id bigint(20) unsigned NOT NULL DEFAULT 0,
        room_id bigint(20) unsigned NOT NULL DEFAULT 0,
        booking_note longtext NULL,
        extras_formula text NULL,
        extras_total decimal(10,2) NULL,
        manual_guest_name varchar(255) NULL,
        manual_adults int(11) NULL,
        manual_children int(11) NULL,
        manual_tarif decimal(10,2) NULL,
        manual_commission decimal(10,2) NULL,
        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY reserved_room_id (reserved_room_id),
        KEY booking_id (booking_id),
        KEY room_id (room_id)
    ) {$charset_collate};";

    dbDelta( $sql_daily_notes );
    dbDelta( $sql_overlays );

    update_option( 'lgf_calendar_view_db_version', LGF_CALENDAR_VIEW_DB_VERSION );
}

function lgf_calendar_view_daily_notes_table() {
    global $wpdb;
    return $wpdb->prefix . 'lgf_calendar_daily_notes';
}

function lgf_calendar_view_booking_overlay_table() {
    global $wpdb;
    return $wpdb->prefix . 'lgf_calendar_booking_overlays';
}

function lgf_calendar_view_user_can_access() {
    return is_user_logged_in() && current_user_can( 'manage_options' );
}

function lgf_calendar_view_enqueue_shared_assets( $context = 'frontend' ) {
    wp_register_style( 'lgf-calendar-view', plugin_dir_url( __FILE__ ) . 'assets/style.css', [], LGF_CALENDAR_VIEW_VERSION );
    wp_enqueue_style( 'lgf-calendar-view' );

    wp_register_script( 'lgf-calendar-view', plugin_dir_url( __FILE__ ) . 'assets/calendar-navigation.js', [ 'jquery' ], LGF_CALENDAR_VIEW_VERSION, true );
    wp_localize_script( 'lgf-calendar-view', 'lgfCalendar', [
        'restUrl'        => esc_url_raw( rest_url( 'lgf-calendar/v1/table' ) ),
        'dailyNotesUrl'  => esc_url_raw( rest_url( 'lgf-calendar/v1/daily-note' ) ),
        'bookingUrl'     => esc_url_raw( rest_url( 'lgf-calendar/v1/booking-overlay' ) ),
        'nonce'          => wp_create_nonce( 'wp_rest' ),
        'context'        => $context,
        'adminPageUrl'   => admin_url( 'admin.php?page=lgf-calendar-view' ),
    ] );
    wp_enqueue_script( 'lgf-calendar-view' );
}

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

function lgf_calendar_view_get_daily_notes_for_month( $year, $month ) {
    global $wpdb;

    $table = lgf_calendar_view_daily_notes_table();
    $start = sprintf( '%04d-%02d-01', $year, $month );
    $end   = gmdate( 'Y-m-t', strtotime( $start ) );

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT note_date, note_text FROM {$table} WHERE note_date BETWEEN %s AND %s",
            $start,
            $end
        ),
        ARRAY_A
    );

    $notes = [];
    foreach ( $rows as $row ) {
        $notes[ $row['note_date'] ] = (string) $row['note_text'];
    }

    return $notes;
}

function lgf_calendar_view_get_booking_overlay( $reserved_room_id ) {
    static $cache = [];

    $reserved_room_id = (int) $reserved_room_id;
    if ( $reserved_room_id <= 0 ) {
        return [];
    }

    if ( isset( $cache[ $reserved_room_id ] ) ) {
        return $cache[ $reserved_room_id ];
    }

    global $wpdb;
    $table = lgf_calendar_view_booking_overlay_table();
    $row   = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$table} WHERE reserved_room_id = %d LIMIT 1", $reserved_room_id ),
        ARRAY_A
    );

    $cache[ $reserved_room_id ] = is_array( $row ) ? $row : [];
    return $cache[ $reserved_room_id ];
}

function lgf_calendar_view_format_occupancy( $adults, $children ) {
    $adults   = max( 0, (int) $adults );
    $children = max( 0, (int) $children );
    $parts    = [];

    if ( $adults > 0 ) {
        $parts[] = sprintf( '%dA', $adults );
    }
    if ( $children > 0 ) {
        $parts[] = sprintf( '%dC', $children );
    }
    if ( empty( $parts ) ) {
        $parts[] = '0';
    }

    return implode( ' ', $parts );
}

function lgf_calendar_view_normalize_decimal( $value ) {
    if ( '' === $value || null === $value ) {
        return null;
    }

    $value = str_replace( [ '€', ' ' ], '', (string) $value );
    $value = str_replace( ',', '.', $value );

    return is_numeric( $value ) ? round( (float) $value, 2 ) : null;
}

function lgf_calendar_view_evaluate_extras_formula( $formula ) {
    $formula = trim( (string) $formula );
    if ( '' === $formula ) {
        return [ 'formula' => '', 'total' => null, 'valid' => true ];
    }

    if ( '=' === substr( $formula, 0, 1 ) ) {
        $formula = substr( $formula, 1 );
    }

    $formula = preg_replace( '/\s+/', '', $formula );
    if ( '' === $formula ) {
        return [ 'formula' => '', 'total' => null, 'valid' => true ];
    }

    if ( ! preg_match( '/^\d+(?:[\.,]\d+)?(?:\+\d+(?:[\.,]\d+)?)*$/', $formula ) ) {
        return [ 'formula' => '=' . $formula, 'total' => null, 'valid' => false ];
    }

    $parts = explode( '+', str_replace( ',', '.', $formula ) );
    $total = 0.0;
    foreach ( $parts as $part ) {
        $total += (float) $part;
    }

    return [
        'formula' => '=' . $formula,
        'total'   => round( $total, 2 ),
        'valid'   => true,
    ];
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

    $adults   = $reserved_room && method_exists( $reserved_room, 'getAdults' ) ? (int) $reserved_room->getAdults() : 0;
    $children = $reserved_room && method_exists( $reserved_room, 'getChildren' ) ? (int) $reserved_room->getChildren() : 0;

    $is_imported = method_exists( $booking, 'isImported' ) ? (bool) $booking->isImported() : ! empty( get_post_meta( $booking->getId(), '_mphb_sync_id', true ) );

    $tarif = '';
    if ( ! $is_imported && $reserved_room && method_exists( $reserved_room, 'getLastRoomPriceBreakdown' ) ) {
        $room_breakdown = $reserved_room->getLastRoomPriceBreakdown();
        if ( is_array( $room_breakdown ) && isset( $room_breakdown['room']['discount_total'] ) ) {
            $tarif = (float) $room_breakdown['room']['discount_total'];
        }
    }

    $reserved_room_id = $reserved_room && method_exists( $reserved_room, 'getId' ) ? (int) $reserved_room->getId() : 0;
    $overlay          = lgf_calendar_view_get_booking_overlay( $reserved_room_id );
    $overlay_adults   = isset( $overlay['manual_adults'] ) && '' !== $overlay['manual_adults'] ? (int) $overlay['manual_adults'] : null;
    $overlay_children = isset( $overlay['manual_children'] ) && '' !== $overlay['manual_children'] ? (int) $overlay['manual_children'] : null;
    $effective_adults = null !== $overlay_adults ? $overlay_adults : $adults;
    $effective_children = null !== $overlay_children ? $overlay_children : $children;

    $extras_formula = isset( $overlay['extras_formula'] ) ? (string) $overlay['extras_formula'] : '';
    $extras_total   = isset( $overlay['extras_total'] ) && '' !== $overlay['extras_total'] ? (float) $overlay['extras_total'] : null;

    return (object) [
        'id'                => method_exists( $booking, 'getId' ) ? (int) $booking->getId() : 0,
        'status'            => method_exists( $booking, 'getStatus' ) ? (string) $booking->getStatus() : '',
        'check_in'          => method_exists( $booking, 'getCheckInDate' ) && $booking->getCheckInDate() ? $booking->getCheckInDate()->format( 'Y-m-d' ) : '',
        'check_out'         => method_exists( $booking, 'getCheckOutDate' ) && $booking->getCheckOutDate() ? $booking->getCheckOutDate()->format( 'Y-m-d' ) : '',
        'room_id'           => $reserved_room && method_exists( $reserved_room, 'getRoomId' ) ? (int) $reserved_room->getRoomId() : 0,
        'reserved_room_id'  => $reserved_room_id,
        'guest_name'        => isset( $overlay['manual_guest_name'] ) && '' !== trim( (string) $overlay['manual_guest_name'] ) ? trim( (string) $overlay['manual_guest_name'] ) : $guest_name,
        'phone'             => $customer && method_exists( $customer, 'getPhone' ) ? (string) $customer->getPhone() : '',
        'adults'            => $effective_adults,
        'children'          => $effective_children,
        'occupancy_str'     => lgf_calendar_view_format_occupancy( $effective_adults, $effective_children ),
        'channel'           => $is_imported ? 'I' : 'W',
        'channel_label'     => $is_imported ? 'Imported' : 'Website',
        'is_imported'       => $is_imported,
        'tarif'             => isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] ? (float) $overlay['manual_tarif'] : $tarif,
        'commission'        => isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] ? (float) $overlay['manual_commission'] : '',
        'extras_formula'    => $extras_formula,
        'extras_total'      => $extras_total,
        'booking_note'      => isset( $overlay['booking_note'] ) ? (string) $overlay['booking_note'] : '',
    ];
}

function lgf_calendar_view_get_calendar_data( $month = null, $year = null ) {
    if ( ! function_exists( 'MPHB' ) || ! function_exists( 'mphb_rooms_facade' ) ) {
        return [ 'rooms' => [], 'matrix' => [], 'month' => $month, 'year' => $year, 'days_in_month' => 0, 'days' => [], 'daily_notes' => [] ];
    }

    $month = $month ?: date( 'n' );
    $year  = $year ?: date( 'Y' );

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

        return (object) [ 'id' => (int) $room_id, 'title' => $title ];
    }, $room_ids );

    $room_colors = [ 1 => '#cc99ff', 2 => '#b4c7e7', 3 => '#a9d18e', 4 => '#ffe699', 5 => '#f4b183' ];
    foreach ( $rooms as $idx => $room ) {
        $rooms[ $idx ]->color = $room_colors[ $idx + 1 ] ?? '#cccccc';
    }

    if ( empty( $rooms ) ) {
        $result = [
            'rooms'         => [],
            'matrix'        => [],
            'month'         => $month,
            'year'          => $year,
            'days_in_month' => $days_in_month,
            'days'          => range( 1, $days_in_month ),
            'daily_notes'   => lgf_calendar_view_get_daily_notes_for_month( $year, $month ),
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
                    $matrix[ $room_id ][ $date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
                }

                $matrix[ $room_id ][ $date_str ]['booking'] = clone $booking_payload;
                if ( $date_str === $booking_payload->check_in ) {
                    $matrix[ $room_id ][ $date_str ]['is_checkin'] = true;
                }
            }

            $check_out_date_str = $check_out->format( 'Y-m-d' );
            if ( $check_out_date_str >= $first_day_str && $check_out_date_str <= $last_day_str ) {
                if ( ! isset( $matrix[ $room_id ][ $check_out_date_str ] ) ) {
                    $matrix[ $room_id ][ $check_out_date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
                }

                $matrix[ $room_id ][ $check_out_date_str ]['is_checkout'] = true;
            }
        }
    }

    $result = [
        'rooms'         => $rooms,
        'matrix'        => $matrix,
        'month'         => $month,
        'year'          => $year,
        'days_in_month' => $days_in_month,
        'days'          => range( 1, $days_in_month ),
        'daily_notes'   => lgf_calendar_view_get_daily_notes_for_month( $year, $month ),
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
        $extras_count = 0;
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
            $extras_count += ! empty( $booking->extras_formula ) ? 1 : 0;
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
            'table_dhotes' => $extras_count,
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

    add_menu_page( __( 'LGF Calendar View', 'lgf-calendar-view' ), __( 'LGF Calendar', 'lgf-calendar-view' ), 'manage_options', 'lgf-calendar-view', 'lgf_calendar_view_render_admin_page', 'dashicons-calendar-alt', 58 );
}

function lgf_calendar_view_render_admin_page() {
    if ( ! lgf_calendar_view_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'lgf-calendar-view' ) );
    }

    $month = isset( $_GET['month'] ) ? intval( $_GET['month'] ) : intval( date( 'n' ) );
    $year  = isset( $_GET['year'] ) ? intval( $_GET['year'] ) : intval( date( 'Y' ) );
    $calendar_data = lgf_calendar_view_get_calendar_data( $month, $year );

    echo '<div class="wrap">';
    echo lgf_calendar_view_render_calendar( $calendar_data, 'admin' );
    echo '</div>';
}

add_shortcode( 'lgf_calendar_view', 'lgf_calendar_view_shortcode' );
function lgf_calendar_view_render_calendar( $calendar_data, $context = 'frontend' ) {
    $template = locate_template( 'lgf-calendar-view/booking-view.php' );
    if ( ! $template ) {
        $template = plugin_dir_path( __FILE__ ) . 'templates/booking-view.php';
    }

    $calendar_context = $context;
    $calendar_base_url = 'admin' === $context ? admin_url( 'admin.php?page=lgf-calendar-view' ) : get_permalink();

    ob_start();
    include $template;
    return ob_get_clean();
}

function lgf_calendar_view_shortcode( $atts ) {
    if ( ! lgf_calendar_view_user_can_access() ) {
        return '';
    }

    $atts = shortcode_atts( [ 'month' => date( 'n' ), 'year' => date( 'Y' ) ], $atts, 'lgf_calendar_view' );
    $month = intval( $atts['month'] );
    $year  = intval( $atts['year'] );
    $calendar_data = lgf_calendar_view_get_calendar_data( $month, $year );

    return lgf_calendar_view_render_calendar( $calendar_data, 'frontend' );
}

function lgf_calendar_view_clear_calendar_cache() {
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_lgf_calendar_%' OR option_name LIKE '_transient_timeout_lgf_calendar_%'" );
}

add_action( 'rest_api_init', function() {
    register_rest_route( 'lgf-calendar/v1', '/table', [
        'methods'  => 'GET',
        'callback' => 'lgf_calendar_rest_table',
        'args'     => [
            'month' => [ 'validate_callback' => function( $param ) { return is_numeric( $param ) && $param >= 1 && $param <= 12; }, 'required' => true ],
            'year'  => [ 'validate_callback' => function( $param ) { return is_numeric( $param ) && $param >= 2000 && $param <= 2100; }, 'required' => true ],
        ],
        'permission_callback' => function() { return lgf_calendar_view_user_can_access(); },
    ] );

    register_rest_route( 'lgf-calendar/v1', '/daily-note', [
        'methods'  => 'POST',
        'callback' => 'lgf_calendar_rest_save_daily_note',
        'permission_callback' => function() { return lgf_calendar_view_user_can_access(); },
    ] );

    register_rest_route( 'lgf-calendar/v1', '/booking-overlay', [
        'methods'  => 'POST',
        'callback' => 'lgf_calendar_rest_save_booking_overlay',
        'permission_callback' => function() { return lgf_calendar_view_user_can_access(); },
    ] );
} );

function lgf_calendar_rest_table( WP_REST_Request $request ) {
    $month = intval( $request->get_param( 'month' ) );
    $year  = intval( $request->get_param( 'year' ) );
    $context = 'admin' === $request->get_param( 'context' ) ? 'admin' : 'frontend';

    return rest_ensure_response( [ 'html' => lgf_calendar_view_render_calendar( lgf_calendar_view_get_calendar_data( $month, $year ), $context ) ] );
}

function lgf_calendar_rest_save_daily_note( WP_REST_Request $request ) {
    global $wpdb;

    $date = sanitize_text_field( (string) $request->get_param( 'date' ) );
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return new WP_Error( 'invalid_date', __( 'Invalid note date.', 'lgf-calendar-view' ), [ 'status' => 400 ] );
    }

    $note = sanitize_textarea_field( (string) $request->get_param( 'note' ) );
    $table = lgf_calendar_view_daily_notes_table();

    if ( '' === trim( $note ) ) {
        $wpdb->delete( $table, [ 'note_date' => $date ], [ '%s' ] );
    } else {
        $wpdb->replace( $table, [ 'note_date' => $date, 'note_text' => $note ], [ '%s', '%s' ] );
    }

    lgf_calendar_view_clear_calendar_cache();

    return rest_ensure_response( [ 'success' => true, 'date' => $date, 'note' => $note ] );
}

function lgf_calendar_rest_save_booking_overlay( WP_REST_Request $request ) {
    global $wpdb;

    $reserved_room_id = absint( $request->get_param( 'reserved_room_id' ) );
    $booking_id       = absint( $request->get_param( 'booking_id' ) );
    $room_id          = absint( $request->get_param( 'room_id' ) );

    if ( $reserved_room_id <= 0 ) {
        return new WP_Error( 'missing_reserved_room', __( 'Missing reserved room ID.', 'lgf-calendar-view' ), [ 'status' => 400 ] );
    }

    $extras = lgf_calendar_view_evaluate_extras_formula( $request->get_param( 'extras_formula' ) );
    if ( ! $extras['valid'] ) {
        return new WP_Error( 'invalid_extras_formula', __( 'Extras formula only supports numbers joined with +.', 'lgf-calendar-view' ), [ 'status' => 400 ] );
    }

    $payload = [
        'booking_id'         => $booking_id,
        'reserved_room_id'   => $reserved_room_id,
        'room_id'            => $room_id,
        'booking_note'       => sanitize_textarea_field( (string) $request->get_param( 'booking_note' ) ),
        'extras_formula'     => $extras['formula'],
        'extras_total'       => $extras['total'],
        'manual_guest_name'  => sanitize_text_field( (string) $request->get_param( 'manual_guest_name' ) ),
        'manual_adults'      => '' === (string) $request->get_param( 'manual_adults' ) ? null : max( 0, intval( $request->get_param( 'manual_adults' ) ) ),
        'manual_children'    => '' === (string) $request->get_param( 'manual_children' ) ? null : max( 0, intval( $request->get_param( 'manual_children' ) ) ),
        'manual_tarif'       => lgf_calendar_view_normalize_decimal( $request->get_param( 'manual_tarif' ) ),
        'manual_commission'  => lgf_calendar_view_normalize_decimal( $request->get_param( 'manual_commission' ) ),
    ];

    $table = lgf_calendar_view_booking_overlay_table();
    $existing = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE reserved_room_id = %d", $reserved_room_id ) );

    $formats = [ '%d', '%d', '%d', '%s', '%s', '%f', '%s', '%d', '%d', '%f', '%f' ];
    if ( $existing ) {
        $wpdb->update( $table, $payload, [ 'reserved_room_id' => $reserved_room_id ], $formats, [ '%d' ] );
    } else {
        $wpdb->insert( $table, $payload, $formats );
    }

    lgf_calendar_view_clear_calendar_cache();

    return rest_ensure_response( [
        'success'           => true,
        'reserved_room_id'  => $reserved_room_id,
        'extras_formula'    => $extras['formula'],
        'extras_total'      => $extras['total'],
        'occupancy_str'     => lgf_calendar_view_format_occupancy( $payload['manual_adults'] ?? 0, $payload['manual_children'] ?? 0 ),
    ] );
}
