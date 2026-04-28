<?php
/**
 * Plugin Name: LGF Calendar View
 * Description: Displays Motopress Hotel Booking data in a LibreOffice Calc-style layout
 * Version: 1.4
 * Author: Angus Watson, Quinn (mistral/codestral) & Kylie (stepfun/step-3.5-flash:free)
 * Text Domain: lgf-calendar-view
 */

defined( 'ABSPATH' ) || exit;

define( 'LGF_CALENDAR_VIEW_VERSION', '1.6' );
define( 'LGF_CALENDAR_VIEW_DB_VERSION', '4' );

add_action( 'plugins_loaded', 'lgf_calendar_view_check_dependency' );
function lgf_calendar_view_check_dependency() {
    if ( 'motopress' !== lgf_calendar_view_get_data_source() ) {
        return;
    }

    if ( ! function_exists( 'MPHB' ) || ! class_exists( 'HotelBookingPlugin' ) ) {
        add_action( 'admin_notices', function() {
            ?>
            <div class="notice notice-error">
                <p><?php esc_html_e( 'LGF Calendar View requires MotoPress Hotel Booking when the MotoPress source is selected.', 'lgf-calendar-view' ); ?></p>
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
    $sync_rooms_table  = lgf_calendar_view_sync_rooms_table();
    $sync_bookings_table = lgf_calendar_view_sync_bookings_table();

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

    $sql_sync_rooms = "CREATE TABLE {$sync_rooms_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        external_room_id bigint(20) unsigned NOT NULL DEFAULT 0,
        room_code varchar(50) NOT NULL,
        room_name varchar(255) NOT NULL,
        sort_order int(11) NOT NULL DEFAULT 0,
        active tinyint(1) NOT NULL DEFAULT 1,
        synced_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY external_room_id (external_room_id),
        KEY room_code (room_code),
        KEY active (active)
    ) {$charset_collate};";

    $sql_sync_bookings = "CREATE TABLE {$sync_bookings_table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        external_booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
        external_booking_room_id bigint(20) unsigned NOT NULL DEFAULT 0,
        external_room_id bigint(20) unsigned NOT NULL DEFAULT 0,
        room_sync_id bigint(20) unsigned NOT NULL DEFAULT 0,
        status_code varchar(50) NOT NULL,
        check_in date NOT NULL,
        check_out date NOT NULL,
        stay_date date NOT NULL,
        guest_count int(11) NOT NULL DEFAULT 0,
        adults int(11) NOT NULL DEFAULT 0,
        children int(11) NOT NULL DEFAULT 0,
        babies int(11) NOT NULL DEFAULT 0,
        total_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        room_amount decimal(10,2) NOT NULL DEFAULT 0.00,
        extras_amount decimal(10,2) NULL,
        tourist_tax_amount decimal(10,2) NULL,
        room_count int(11) NOT NULL DEFAULT 1,
        source_channel varchar(50) NOT NULL,
        source_booking_id varchar(191) NULL,
        channel_label varchar(191) NULL,
        guest_name varchar(255) NOT NULL,
        phone varchar(100) NULL,
        import_notes longtext NULL,
        invoice_ninja_client_id varchar(191) NULL,
        invoice_ninja_invoice_id varchar(191) NULL,
        source_created_at datetime NULL,
        synced_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY external_booking_room_night (external_booking_room_id, stay_date),
        KEY room_sync_id (room_sync_id),
        KEY external_booking_id (external_booking_id),
        KEY external_room_id (external_room_id),
        KEY stay_date (stay_date),
        KEY booking_dates (check_in, check_out),
        KEY status_code (status_code)
    ) {$charset_collate};";

    dbDelta( $sql_daily_notes );
    dbDelta( $sql_overlays );
    dbDelta( $sql_sync_rooms );
    dbDelta( $sql_sync_bookings );

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

function lgf_calendar_view_sync_rooms_table() {
    global $wpdb;
    return $wpdb->prefix . 'lgf_calendar_sync_rooms';
}

function lgf_calendar_view_sync_bookings_table() {
    global $wpdb;
    return $wpdb->prefix . 'lgf_calendar_sync_bookings';
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

function lgf_calendar_view_extract_tarif_from_room_breakdown( $room_breakdown ) {
    if ( ! is_array( $room_breakdown ) ) {
        return '';
    }

    if ( isset( $room_breakdown['room']['discount_total'] ) && '' !== $room_breakdown['room']['discount_total'] ) {
        return (float) $room_breakdown['room']['discount_total'];
    }
    if ( isset( $room_breakdown['room']['total'] ) && '' !== $room_breakdown['room']['total'] ) {
        return (float) $room_breakdown['room']['total'];
    }
    if ( isset( $room_breakdown['discount_total'] ) && '' !== $room_breakdown['discount_total'] ) {
        return (float) $room_breakdown['discount_total'];
    }
    if ( isset( $room_breakdown['total'] ) && '' !== $room_breakdown['total'] ) {
        return (float) $room_breakdown['total'];
    }

    return '';
}

function lgf_calendar_view_extract_tarif( $booking, $reserved_room ) {
    $tarif = '';

    if ( $reserved_room && method_exists( $reserved_room, 'getLastRoomPriceBreakdown' ) ) {
        $tarif = lgf_calendar_view_extract_tarif_from_room_breakdown( $reserved_room->getLastRoomPriceBreakdown() );
        if ( '' !== $tarif ) {
            return $tarif;
        }
    }

    if ( class_exists( '\MPHB\Utils\PriceBreakdownHelper' ) && method_exists( '\MPHB\Utils\PriceBreakdownHelper', 'getLastRoomPriceBreakdown' ) ) {
        $tarif = lgf_calendar_view_extract_tarif_from_room_breakdown( \MPHB\Utils\PriceBreakdownHelper::getLastRoomPriceBreakdown( $reserved_room ) );
        if ( '' !== $tarif ) {
            return $tarif;
        }
    }

    if ( $reserved_room ) {
        $reserved_room_id = method_exists( $reserved_room, 'getId' ) ? (int) $reserved_room->getId() : 0;
        if ( $reserved_room_id > 0 ) {
            $reserved_room_price = get_post_meta( $reserved_room_id, '_mphb_reserved_room_price', true );
            if ( is_numeric( $reserved_room_price ) ) {
                return (float) $reserved_room_price;
            }
            $reserved_room_price = get_post_meta( $reserved_room_id, '_mphb_room_price', true );
            if ( is_numeric( $reserved_room_price ) ) {
                return (float) $reserved_room_price;
            }
        }
    }

    if ( $reserved_room && method_exists( $reserved_room, 'getPrice' ) ) {
        $price = $reserved_room->getPrice();
        if ( is_numeric( $price ) && $price > 0 ) {
            return (float) $price;
        }
    }

    if ( $reserved_room && method_exists( $reserved_room, 'getTotal' ) ) {
        $total = $reserved_room->getTotal();
        if ( is_numeric( $total ) && $total > 0 ) {
            return (float) $total;
        }
    }

    $breakdown = null;
    if ( $booking && method_exists( $booking, 'getLastPriceBreakdown' ) ) {
        $breakdown = $booking->getLastPriceBreakdown();
    }

    if ( ! is_array( $breakdown ) && $booking && method_exists( $booking, 'getId' ) ) {
        $breakdown = get_post_meta( $booking->getId(), '_mphb_booking_price_breakdown', true );
    }

    if ( is_array( $breakdown ) && ! empty( $breakdown['rooms'] ) && is_array( $breakdown['rooms'] ) ) {
        $reserved_room_id = $reserved_room && method_exists( $reserved_room, 'getId' ) ? (int) $reserved_room->getId() : 0;
        $room_id = $reserved_room && method_exists( $reserved_room, 'getRoomId' ) ? (int) $reserved_room->getRoomId() : 0;

        foreach ( $breakdown['rooms'] as $room_line ) {
            if ( isset( $room_line['reserved_room_id'] ) && (int) $room_line['reserved_room_id'] === $reserved_room_id ) {
                $tarif = lgf_calendar_view_extract_tarif_from_room_breakdown( $room_line );
                if ( '' !== $tarif ) {
                    return $tarif;
                }
            }
        }

        foreach ( $breakdown['rooms'] as $room_line ) {
            if ( isset( $room_line['room_id'] ) && (int) $room_line['room_id'] === $room_id ) {
                $tarif = lgf_calendar_view_extract_tarif_from_room_breakdown( $room_line );
                if ( '' !== $tarif ) {
                    return $tarif;
                }
            }
        }

        if ( 1 === count( $breakdown['rooms'] ) ) {
            $room_line = reset( $breakdown['rooms'] );
            $tarif = lgf_calendar_view_extract_tarif_from_room_breakdown( $room_line );
            if ( '' !== $tarif ) {
                return $tarif;
            }
        }
    }

    if ( $booking && method_exists( $booking, 'getId' ) ) {
        $booking_price = get_post_meta( $booking->getId(), '_mphb_booking_price', true );
        if ( is_numeric( $booking_price ) ) {
            return (float) $booking_price;
        }
    }

    return '';
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
    if ( ! $is_imported ) {
        $tarif = lgf_calendar_view_extract_tarif( $booking, $reserved_room );
    }

    $reserved_room_id = $reserved_room && method_exists( $reserved_room, 'getId' ) ? (int) $reserved_room->getId() : 0;
    $overlay          = lgf_calendar_view_get_booking_overlay( $reserved_room_id );
    $overlay_adults   = isset( $overlay['manual_adults'] ) && '' !== $overlay['manual_adults'] ? (int) $overlay['manual_adults'] : null;
    $overlay_children = isset( $overlay['manual_children'] ) && '' !== $overlay['manual_children'] ? (int) $overlay['manual_children'] : null;
    $effective_adults = null !== $overlay_adults ? $overlay_adults : $adults;
    $effective_children = null !== $overlay_children ? $overlay_children : $children;

    $extras_formula = isset( $overlay['extras_formula'] ) ? (string) $overlay['extras_formula'] : '';
    $extras_total   = isset( $overlay['extras_total'] ) && '' !== $overlay['extras_total'] ? (float) $overlay['extras_total'] : null;

    $created_date = '';
    if ( method_exists( $booking, 'getPostId' ) ) {
        $created_timestamp = get_post_time( 'U', false, $booking->getPostId() );
        if ( $created_timestamp ) {
            $created_date = date_i18n( 'Y-m-d', $created_timestamp );
        }
    }
    if ( '' === $created_date && method_exists( $booking, 'getId' ) ) {
        $created_timestamp = get_post_time( 'U', false, $booking->getId() );
        if ( $created_timestamp ) {
            $created_date = date_i18n( 'Y-m-d', $created_timestamp );
        }
    }

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
        'channel'           => trim( ( $is_imported ? 'I' : 'W' ) . ( $created_date ? ' ' . $created_date : '' ) ),
        'channel_label'     => $is_imported ? 'Imported' : 'Website',
        'created_date'      => $created_date,
        'is_imported'       => $is_imported,
        'tarif'             => isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] ? (float) $overlay['manual_tarif'] : $tarif,
        'commission'        => isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] ? (float) $overlay['manual_commission'] : '',
        'extras_formula'    => $extras_formula,
        'extras_total'      => $extras_total,
        'booking_note'      => isset( $overlay['booking_note'] ) ? (string) $overlay['booking_note'] : '',
    ];
}

function lgf_calendar_view_get_data_source() {
    $source = get_option( 'lgf_calendar_booking_source', 'motopress' );
    return in_array( $source, [ 'motopress', 'wp_sync', 'external_pg' ], true ) ? $source : 'motopress';
}

function lgf_calendar_view_get_external_db_settings() {
    return [
        'host'     => (string) get_option( 'lgf_calendar_external_pg_host', '' ),
        'port'     => (string) get_option( 'lgf_calendar_external_pg_port', '5432' ),
        'dbname'   => (string) get_option( 'lgf_calendar_external_pg_dbname', '' ),
        'user'     => (string) get_option( 'lgf_calendar_external_pg_user', '' ),
        'password' => (string) get_option( 'lgf_calendar_external_pg_password', '' ),
        'sslmode'  => (string) get_option( 'lgf_calendar_external_pg_sslmode', 'disable' ),
    ];
}

function lgf_calendar_view_get_external_pg_connection() {
    static $connection = null;
    static $attempted = false;

    if ( $attempted ) {
        return $connection;
    }

    $attempted = true;

    if ( ! function_exists( 'pg_connect' ) ) {
        $connection = new WP_Error( 'missing_pgsql_extension', __( 'The pgsql PHP extension is not available on this WordPress server.', 'lgf-calendar-view' ) );
        return $connection;
    }

    $settings = lgf_calendar_view_get_external_db_settings();
    foreach ( [ 'host', 'port', 'dbname', 'user' ] as $required_key ) {
        if ( '' === $settings[ $required_key ] ) {
            $connection = new WP_Error( 'missing_pg_settings', __( 'External PostgreSQL settings are incomplete.', 'lgf-calendar-view' ) );
            return $connection;
        }
    }

    $connection_string = sprintf(
        'host=%s port=%s dbname=%s user=%s password=%s sslmode=%s connect_timeout=5',
        $settings['host'],
        $settings['port'],
        $settings['dbname'],
        $settings['user'],
        $settings['password'],
        $settings['sslmode'] ?: 'disable'
    );

    $connection = @pg_connect( $connection_string );
    if ( ! $connection ) {
        $connection = new WP_Error( 'pg_connect_failed', __( 'Could not connect to the external PostgreSQL database.', 'lgf-calendar-view' ) );
    }

    return $connection;
}

function lgf_calendar_view_get_room_colors() {
    return [
        'ANE' => '#cc99ff',
        'DEL' => '#b4c7e7',
        'LYS' => '#a9d18e',
        'TOU' => '#ffe699',
        'TUL' => '#f4b183',
        'COQ' => '#cccccc',
    ];
}

function lgf_calendar_view_get_room_sort_order( $room_code, $room_name ) {
    $room_code = strtoupper( (string) $room_code );
    $map = [ 'ANE' => 1, 'DEL' => 2, 'LYS' => 3, 'TOU' => 4, 'TUL' => 5, 'COQ' => 6 ];

    if ( isset( $map[ $room_code ] ) ) {
        return $map[ $room_code ];
    }

    $room_name = remove_accents( strtolower( (string) $room_name ) );
    $name_map = [ 'anemone' => 1, 'delphinium' => 2, 'lys' => 3, 'tournesol' => 4, 'tulipe' => 5, 'coquelicot' => 6 ];
    return $name_map[ $room_name ] ?? 999;
}

function lgf_calendar_view_format_channel_code( $source_channel, $created_date = '' ) {
    $source_channel = (string) $source_channel;
    $created_date   = (string) $created_date;

    $prefix_map = [
        'booking_com' => 'B',
        'direct'      => 'W',
        'motopress'   => 'W',
        'website'     => 'W',
        'email'       => 'E',
        'telephone'   => 'T',
        'phone'       => 'T',
    ];

    $prefix = $prefix_map[ $source_channel ] ?? strtoupper( substr( preg_replace( '/[^a-z]/i', '', $source_channel ), 0, 1 ) );
    if ( '' === $prefix ) {
        $prefix = '?';
    }

    return trim( $prefix . ( $created_date ? ' ' . $created_date : '' ) );
}

function lgf_calendar_view_get_empty_calendar_result( $month, $year, $days_in_month = 0 ) {
    return [
        'rooms'         => [],
        'matrix'        => [],
        'month'         => $month,
        'year'          => $year,
        'days_in_month' => $days_in_month,
        'days'          => $days_in_month > 0 ? range( 1, $days_in_month ) : [],
        'daily_notes'   => $days_in_month > 0 ? lgf_calendar_view_get_daily_notes_for_month( $year, $month ) : [],
    ];
}

function lgf_calendar_view_get_wp_sync_calendar_data( $month, $year ) {
    global $wpdb;

    $first_day = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
    $days_in_month = (int) $first_day->format( 't' );
    $last_day = clone $first_day;
    $last_day->setDate( $year, $month, $days_in_month );

    $first_day_str = $first_day->format( 'Y-m-d' );
    $month_after_last_day_str = $last_day->modify( '+1 day' )->format( 'Y-m-d' );

    $rooms_table = lgf_calendar_view_sync_rooms_table();
    $bookings_table = lgf_calendar_view_sync_bookings_table();

    $rooms_rows = $wpdb->get_results( "SELECT id, external_room_id, room_code, room_name, sort_order FROM {$rooms_table} WHERE active = 1 ORDER BY sort_order ASC, room_name ASC", ARRAY_A );

    $rooms = [];
    $matrix = [];
    $room_colors = lgf_calendar_view_get_room_colors();
    foreach ( $rooms_rows as $index => $room_row ) {
        $room_code = (string) $room_row['room_code'];
        $room = (object) [
            'id' => (int) $room_row['id'],
            'external_room_id' => (int) $room_row['external_room_id'],
            'title' => (string) $room_row['room_name'],
            'code' => $room_code,
            'color' => $room_colors[ strtoupper( $room_code ) ] ?? '#cccccc',
        ];
        $rooms[] = $room;
        $matrix[ $room->id ] = [];
    }

    if ( empty( $rooms ) ) {
        return lgf_calendar_view_get_empty_calendar_result( $month, $year, $days_in_month );
    }

    $booking_rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT * FROM {$bookings_table} WHERE stay_date >= %s AND stay_date < %s ORDER BY stay_date ASC, external_booking_id ASC, external_booking_room_id ASC",
            $first_day_str,
            $month_after_last_day_str
        ),
        ARRAY_A
    );

    foreach ( $booking_rows as $row ) {
        $room_id = (int) $row['room_sync_id'];
        if ( ! isset( $matrix[ $room_id ] ) ) {
            continue;
        }

        $reserved_room_id = (int) $row['external_booking_room_id'];
        $overlay = lgf_calendar_view_get_booking_overlay( $reserved_room_id );
        $adults = isset( $overlay['manual_adults'] ) && '' !== $overlay['manual_adults'] ? (int) $overlay['manual_adults'] : (int) $row['adults'];
        $children = isset( $overlay['manual_children'] ) && '' !== $overlay['manual_children'] ? (int) $overlay['manual_children'] : (int) $row['children'];
        $guest_name = isset( $overlay['manual_guest_name'] ) && '' !== trim( (string) $overlay['manual_guest_name'] ) ? trim( (string) $overlay['manual_guest_name'] ) : (string) $row['guest_name'];
        $extras_formula = isset( $overlay['extras_formula'] ) ? (string) $overlay['extras_formula'] : '';
        $extras_total = isset( $overlay['extras_total'] ) && '' !== $overlay['extras_total'] ? (float) $overlay['extras_total'] : null;

        $date_str = (string) $row['stay_date'];
        if ( ! isset( $matrix[ $room_id ][ $date_str ] ) ) {
            $matrix[ $room_id ][ $date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
        }

        $booking_payload = (object) [
            'id' => (int) $row['external_booking_id'],
            'status' => (string) $row['status_code'],
            'check_in' => (string) $row['check_in'],
            'check_out' => (string) $row['check_out'],
            'stay_date' => $date_str,
            'room_id' => $room_id,
            'reserved_room_id' => $reserved_room_id,
            'guest_name' => $guest_name,
            'phone' => (string) $row['phone'],
            'guest_count' => isset( $row['guest_count'] ) ? (int) $row['guest_count'] : 0,
            'babies' => isset( $row['babies'] ) ? (int) $row['babies'] : 0,
            'adults' => $adults,
            'children' => $children,
            'occupancy_str' => lgf_calendar_view_format_occupancy( $adults, $children ),
            'channel' => lgf_calendar_view_format_channel_code( (string) $row['source_channel'], ! empty( $row['source_created_at'] ) ? substr( (string) $row['source_created_at'], 0, 10 ) : '' ),
            'channel_label' => (string) ( $row['channel_label'] ?: $row['source_channel'] ),
            'created_date' => ! empty( $row['source_created_at'] ) ? substr( (string) $row['source_created_at'], 0, 10 ) : '',
            'is_imported' => 'motopress' !== (string) $row['source_channel'],
            'tarif' => isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] ? (float) $overlay['manual_tarif'] : (float) $row['room_amount'],
            'commission' => isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] ? (float) $overlay['manual_commission'] : '',
            'extras_formula' => $extras_formula,
            'extras_total' => null !== $extras_total ? $extras_total : ( ( isset( $row['extras_amount'] ) && '' !== $row['extras_amount'] && (float) $row['extras_amount'] > 0 ) ? (float) $row['extras_amount'] : null ),
            'booking_note' => isset( $overlay['booking_note'] ) ? (string) $overlay['booking_note'] : '',
            'import_notes' => (string) ( $row['import_notes'] ?? '' ),
            'tourist_tax_amount' => isset( $row['tourist_tax_amount'] ) ? (float) $row['tourist_tax_amount'] : 0.0,
            'reservation_total_amount' => (float) $row['total_amount'],
            'invoice_ninja_client_id' => (string) $row['invoice_ninja_client_id'],
            'invoice_ninja_invoice_id' => (string) $row['invoice_ninja_invoice_id'],
            'source_booking_id' => (string) $row['source_booking_id'],
        ];

        $matrix[ $room_id ][ $date_str ]['booking'] = $booking_payload;
        if ( $date_str === $booking_payload->check_in ) {
            $matrix[ $room_id ][ $date_str ]['is_checkin'] = true;
        }

        $last_night_date = gmdate( 'Y-m-d', strtotime( $booking_payload->check_out . ' -1 day' ) );
        if ( $date_str === $last_night_date ) {
            $check_out_date_str = (string) $booking_payload->check_out;
            if ( ! isset( $matrix[ $room_id ][ $check_out_date_str ] ) ) {
                $matrix[ $room_id ][ $check_out_date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
            }
            $matrix[ $room_id ][ $check_out_date_str ]['is_checkout'] = true;
        }
    }

    return [
        'rooms' => $rooms,
        'matrix' => $matrix,
        'month' => $month,
        'year' => $year,
        'days_in_month' => $days_in_month,
        'days' => range( 1, $days_in_month ),
        'daily_notes' => lgf_calendar_view_get_daily_notes_for_month( $year, $month ),
    ];
}

function lgf_calendar_view_get_external_calendar_data( $month, $year ) {
    $first_day = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
    $days_in_month = (int) $first_day->format( 't' );
    $last_day = clone $first_day;
    $last_day->setDate( $year, $month, $days_in_month );

    $first_day_str = $first_day->format( 'Y-m-d' );
    $month_after_last_day_str = $last_day->modify( '+1 day' )->format( 'Y-m-d' );

    $connection = lgf_calendar_view_get_external_pg_connection();
    if ( is_wp_error( $connection ) ) {
        add_action( 'admin_notices', function() use ( $connection ) {
            echo '<div class="notice notice-error"><p>' . esc_html( $connection->get_error_message() ) . '</p></div>';
        } );
        return lgf_calendar_view_get_empty_calendar_result( $month, $year, $days_in_month );
    }

    $rooms_result = pg_query( $connection, "SELECT id, code, name FROM rooms WHERE active = true ORDER BY code" );
    if ( ! $rooms_result ) {
        return lgf_calendar_view_get_empty_calendar_result( $month, $year, $days_in_month );
    }

    $rooms = [];
    $room_colors = lgf_calendar_view_get_room_colors();
    $matrix = [];

    while ( $room_row = pg_fetch_assoc( $rooms_result ) ) {
        $room_code = (string) $room_row['code'];
        $room = (object) [
            'id'    => (int) $room_row['id'],
            'title' => (string) $room_row['name'],
            'code'  => $room_code,
            'sort_order' => lgf_calendar_view_get_room_sort_order( $room_code, $room_row['name'] ),
            'color' => $room_colors[ strtoupper( $room_code ) ] ?? '#cccccc',
        ];
        $rooms[] = $room;
        $matrix[ $room->id ] = [];
    }

    usort( $rooms, function( $a, $b ) {
        return ( $a->sort_order <=> $b->sort_order ) ?: strcasecmp( $a->title, $b->title );
    } );

    if ( empty( $rooms ) ) {
        return lgf_calendar_view_get_empty_calendar_result( $month, $year, $days_in_month );
    }

    $bookings_sql = "
        SELECT
            b.id AS booking_id,
            b.status_code,
            b.check_in_date,
            b.check_out_date,
            br.guest_count,
            br.adults,
            br.children,
            br.babies,
            br.total_amount,
            br.room_rate_amount,
            br.extras_amount,
            br.tourist_tax_amount,
            brn.stay_date,
            brn.guest_count AS night_guest_count,
            brn.adults AS night_adults,
            brn.children AS night_children,
            brn.babies AS night_babies,
            brn.total_amount AS night_total_amount,
            brn.room_rate_amount AS night_room_rate_amount,
            brn.extras_amount AS night_extras_amount,
            brn.tourist_tax_amount AS night_tourist_tax_amount,
            b.total_amount AS booking_total_amount,
            b.source_channel,
            b.source_booking_id,
            b.internal_notes,
            b.invoice_ninja_client_id,
            b.invoice_ninja_invoice_id,
            b.created_at,
            br.id AS booking_room_id,
            br.room_id,
            r.name AS room_name,
            g.first_name,
            g.last_name,
            g.phone,
            bc.label AS channel_label,
            room_counts.room_count
        FROM bookings b
        JOIN booking_rooms br ON br.booking_id = b.id
        JOIN booking_room_nights brn ON brn.booking_room_id = br.id
        JOIN rooms r ON r.id = br.room_id
        JOIN guests g ON g.id = b.guest_id
        LEFT JOIN booking_channels bc ON bc.code = b.source_channel
        JOIN (
            SELECT booking_id, COUNT(*) AS room_count
            FROM booking_rooms
            GROUP BY booking_id
        ) room_counts ON room_counts.booking_id = b.id
        JOIN booking_statuses bs ON bs.code = b.status_code
        WHERE bs.blocks_availability = true
          AND brn.stay_date >= $1::date
          AND brn.stay_date < $2::date
        ORDER BY brn.stay_date ASC, b.id ASC, br.id ASC
    ";

    $bookings_result = pg_query_params( $connection, $bookings_sql, [ $first_day_str, $month_after_last_day_str ] );
    if ( ! $bookings_result ) {
        return lgf_calendar_view_get_empty_calendar_result( $month, $year, $days_in_month );
    }

    while ( $row = pg_fetch_assoc( $bookings_result ) ) {
        $room_id = (int) $row['room_id'];
        if ( ! isset( $matrix[ $room_id ] ) ) {
            continue;
        }

        $reserved_room_id = (int) $row['booking_room_id'];
        $overlay = lgf_calendar_view_get_booking_overlay( $reserved_room_id );
        $adults = isset( $overlay['manual_adults'] ) && '' !== $overlay['manual_adults'] ? (int) $overlay['manual_adults'] : (int) $row['night_adults'];
        $children = isset( $overlay['manual_children'] ) && '' !== $overlay['manual_children'] ? (int) $overlay['manual_children'] : (int) $row['night_children'];
        $guest_name = trim( (string) $row['first_name'] . ' ' . (string) $row['last_name'] );
        if ( isset( $overlay['manual_guest_name'] ) && '' !== trim( (string) $overlay['manual_guest_name'] ) ) {
            $guest_name = trim( (string) $overlay['manual_guest_name'] );
        }

        $extras_formula = isset( $overlay['extras_formula'] ) ? (string) $overlay['extras_formula'] : '';
        $extras_total   = isset( $overlay['extras_total'] ) && '' !== $overlay['extras_total'] ? (float) $overlay['extras_total'] : null;
        $date_str       = (string) $row['stay_date'];

        if ( ! isset( $matrix[ $room_id ][ $date_str ] ) ) {
            $matrix[ $room_id ][ $date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
        }

        $booking_payload = (object) [
            'id'                      => (int) $row['booking_id'],
            'status'                  => (string) $row['status_code'],
            'check_in'                => (string) $row['check_in_date'],
            'check_out'               => (string) $row['check_out_date'],
            'stay_date'               => $date_str,
            'room_id'                 => $room_id,
            'reserved_room_id'        => $reserved_room_id,
            'guest_name'              => $guest_name,
            'phone'                   => (string) $row['phone'],
            'guest_count'             => isset( $row['night_guest_count'] ) ? (int) $row['night_guest_count'] : 0,
            'babies'                  => isset( $row['night_babies'] ) ? (int) $row['night_babies'] : 0,
            'adults'                  => $adults,
            'children'                => $children,
            'occupancy_str'           => lgf_calendar_view_format_occupancy( $adults, $children ),
            'channel'                 => lgf_calendar_view_format_channel_code( (string) $row['source_channel'], substr( (string) $row['created_at'], 0, 10 ) ),
            'channel_label'           => (string) ( $row['channel_label'] ?: $row['source_channel'] ),
            'created_date'            => substr( (string) $row['created_at'], 0, 10 ),
            'is_imported'             => 'motopress' !== (string) $row['source_channel'],
            'tarif'                   => isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] ? (float) $overlay['manual_tarif'] : (float) $row['night_room_rate_amount'],
            'commission'              => isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] ? (float) $overlay['manual_commission'] : '',
            'extras_formula'          => $extras_formula,
            'extras_total'            => null !== $extras_total ? $extras_total : ( ( isset( $row['night_extras_amount'] ) && '' !== $row['night_extras_amount'] && (float) $row['night_extras_amount'] > 0 ) ? (float) $row['night_extras_amount'] : null ),
            'booking_note'            => isset( $overlay['booking_note'] ) ? (string) $overlay['booking_note'] : '',
            'import_notes'            => (string) ( $row['internal_notes'] ?? '' ),
            'tourist_tax_amount'      => isset( $row['night_tourist_tax_amount'] ) ? (float) $row['night_tourist_tax_amount'] : 0.0,
            'reservation_total_amount'=> isset( $row['booking_total_amount'] ) ? (float) $row['booking_total_amount'] : (float) $row['total_amount'],
            'room_stay_total_amount'  => isset( $row['total_amount'] ) ? (float) $row['total_amount'] : 0.0,
            'invoice_ninja_client_id' => (string) $row['invoice_ninja_client_id'],
            'invoice_ninja_invoice_id'=> (string) $row['invoice_ninja_invoice_id'],
            'source_booking_id'       => (string) $row['source_booking_id'],
        ];

        $matrix[ $room_id ][ $date_str ]['booking'] = clone $booking_payload;
        if ( $date_str === $booking_payload->check_in ) {
            $matrix[ $room_id ][ $date_str ]['is_checkin'] = true;
        }

        $last_night_str = gmdate( 'Y-m-d', strtotime( $booking_payload->check_out . ' -1 day' ) );
        if ( $date_str === $last_night_str ) {
            $check_out_date_str = $booking_payload->check_out;
            if ( $check_out_date_str >= $first_day_str && $check_out_date_str < $month_after_last_day_str ) {
                if ( ! isset( $matrix[ $room_id ][ $check_out_date_str ] ) ) {
                    $matrix[ $room_id ][ $check_out_date_str ] = [ 'booking' => null, 'is_checkin' => false, 'is_checkout' => false ];
                }
                $matrix[ $room_id ][ $check_out_date_str ]['is_checkout'] = true;
            }
        }
    }

    return [
        'rooms'         => $rooms,
        'matrix'        => $matrix,
        'month'         => $month,
        'year'          => $year,
        'days_in_month' => $days_in_month,
        'days'          => range( 1, $days_in_month ),
        'daily_notes'   => lgf_calendar_view_get_daily_notes_for_month( $year, $month ),
    ];
}

function lgf_calendar_view_get_motopress_calendar_data( $month, $year ) {
    if ( ! function_exists( 'MPHB' ) || ! function_exists( 'mphb_rooms_facade' ) ) {
        return lgf_calendar_view_get_empty_calendar_result( $month, $year );
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

    $room_colors = lgf_calendar_view_get_room_colors();
    foreach ( $rooms as $idx => $room ) {
        $room_code = '';
        if ( isset( $room->code ) ) {
            $room_code = (string) $room->code;
        } elseif ( property_exists( $room, 'title' ) ) {
            $title_map = [ 'Anémone' => 'ANE', 'Delphinium' => 'DEL', 'Lys' => 'LYS', 'Tournesol' => 'TOU', 'Tulipe' => 'TUL', 'Coquelicot' => 'COQ' ];
            $room_code = $title_map[ $room->title ] ?? '';
        }
        $rooms[ $idx ]->sort_order = lgf_calendar_view_get_room_sort_order( $room_code, $room->title );
        $rooms[ $idx ]->color = $room_colors[ strtoupper( $room_code ) ] ?? '#cccccc';
    }

    usort( $rooms, function( $a, $b ) {
        return ( $a->sort_order <=> $b->sort_order ) ?: strcasecmp( $a->title, $b->title );
    } );

    if ( empty( $rooms ) ) {
        return lgf_calendar_view_get_empty_calendar_result( $month, $year, $days_in_month );
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

    return [
        'rooms'         => $rooms,
        'matrix'        => $matrix,
        'month'         => $month,
        'year'          => $year,
        'days_in_month' => $days_in_month,
        'days'          => range( 1, $days_in_month ),
        'daily_notes'   => lgf_calendar_view_get_daily_notes_for_month( $year, $month ),
    ];
}

function lgf_calendar_view_get_calendar_data( $month = null, $year = null ) {
    $month = $month ?: date( 'n' );
    $year  = $year ?: date( 'Y' );

    $transient_key = 'lgf_calendar_' . lgf_calendar_view_get_data_source() . '_' . $year . '_' . $month;
    $cached = get_transient( $transient_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $source = lgf_calendar_view_get_data_source();
    if ( 'external_pg' === $source ) {
        $result = lgf_calendar_view_get_external_calendar_data( $month, $year );
    } elseif ( 'wp_sync' === $source ) {
        $result = lgf_calendar_view_get_wp_sync_calendar_data( $month, $year );
    } else {
        $result = lgf_calendar_view_get_motopress_calendar_data( $month, $year );
    }

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
    add_submenu_page( 'lgf-calendar-view', __( 'Invoice Ninja Settings', 'lgf-calendar-view' ), __( 'Settings', 'lgf-calendar-view' ), 'manage_options', 'lgf-calendar-settings', 'lgf_calendar_view_render_settings_page' );
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

/**
 * Render Invoice Ninja settings page.
 */
function lgf_calendar_view_render_settings_page() {
    if ( ! lgf_calendar_view_user_can_access() ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'lgf-calendar-view' ) );
    }

    if ( isset( $_POST['lgf_calendar_submit'] ) ) {
        check_admin_referer( 'lgf_calendar_settings', 'lgf_calendar_settings_nonce' );

        $api_url = isset( $_POST['lgf_calendar_invoice_ninja_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['lgf_calendar_invoice_ninja_url'] ) ) ) : '';
        $api_token = isset( $_POST['lgf_calendar_invoice_ninja_token'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['lgf_calendar_invoice_ninja_token'] ) ) ) : '';
        $booking_source = isset( $_POST['lgf_calendar_booking_source'] ) ? sanitize_text_field( wp_unslash( $_POST['lgf_calendar_booking_source'] ) ) : 'motopress';
        $booking_source = in_array( $booking_source, [ 'motopress', 'wp_sync', 'external_pg' ], true ) ? $booking_source : 'motopress';

        update_option( 'lgf_calendar_invoice_ninja_url', $api_url );
        update_option( 'lgf_calendar_invoice_ninja_token', $api_token );
        update_option( 'lgf_calendar_booking_source', $booking_source );
        update_option( 'lgf_calendar_external_pg_host', isset( $_POST['lgf_calendar_external_pg_host'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['lgf_calendar_external_pg_host'] ) ) ) : '' );
        update_option( 'lgf_calendar_external_pg_port', isset( $_POST['lgf_calendar_external_pg_port'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['lgf_calendar_external_pg_port'] ) ) ) : '5432' );
        update_option( 'lgf_calendar_external_pg_dbname', isset( $_POST['lgf_calendar_external_pg_dbname'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['lgf_calendar_external_pg_dbname'] ) ) ) : '' );
        update_option( 'lgf_calendar_external_pg_user', isset( $_POST['lgf_calendar_external_pg_user'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['lgf_calendar_external_pg_user'] ) ) ) : '' );
        update_option( 'lgf_calendar_external_pg_password', isset( $_POST['lgf_calendar_external_pg_password'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['lgf_calendar_external_pg_password'] ) ) ) : '' );
        update_option( 'lgf_calendar_external_pg_sslmode', isset( $_POST['lgf_calendar_external_pg_sslmode'] ) ? sanitize_text_field( trim( wp_unslash( $_POST['lgf_calendar_external_pg_sslmode'] ) ) ) : 'disable' );

        lgf_calendar_view_clear_calendar_cache();
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'lgf-calendar-view' ) . '</p></div>';
    }

    $api_url = get_option( 'lgf_calendar_invoice_ninja_url', '' );
    $api_token = get_option( 'lgf_calendar_invoice_ninja_token', '' );
    $booking_source = lgf_calendar_view_get_data_source();
    $external_db = lgf_calendar_view_get_external_db_settings();

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'LGF Calendar Settings', 'lgf-calendar-view' ) . '</h1>';
    echo '<form method="post">';
    wp_nonce_field( 'lgf_calendar_settings', 'lgf_calendar_settings_nonce' );
    echo '<h2>' . esc_html__( 'Booking Source', 'lgf-calendar-view' ) . '</h2>';
    echo '<table class="form-table">';
    echo '<tr><th scope="row"><label for="lgf_calendar_booking_source">' . esc_html__( 'Booking data source', 'lgf-calendar-view' ) . '</label></th>';
    echo '<td><select id="lgf_calendar_booking_source" name="lgf_calendar_booking_source">';
    echo '<option value="motopress"' . selected( $booking_source, 'motopress', false ) . '>' . esc_html__( 'MotoPress / WordPress', 'lgf-calendar-view' ) . '</option>';
    echo '<option value="wp_sync"' . selected( $booking_source, 'wp_sync', false ) . '>' . esc_html__( 'Local WordPress sync tables', 'lgf-calendar-view' ) . '</option>';
    echo '<option value="external_pg"' . selected( $booking_source, 'external_pg', false ) . '>' . esc_html__( 'External PostgreSQL (LGF database)', 'lgf-calendar-view' ) . '</option>';
    echo '</select><p class="description">' . esc_html__( 'Use local WordPress sync tables for production-friendly hosting, or external PostgreSQL for direct local development/testing.', 'lgf-calendar-view' ) . '</p></td></tr>';
    echo '</table>';

    echo '<h2>' . esc_html__( 'Local Sync', 'lgf-calendar-view' ) . '</h2>';
    echo '<p>' . esc_html__( 'To use Local WordPress sync tables, run the sync script from this project to copy rooms and bookings from the LGF PostgreSQL database into WordPress MySQL. Then select "Local WordPress sync tables" above.', 'lgf-calendar-view' ) . '</p>';

    echo '<h2>' . esc_html__( 'External PostgreSQL', 'lgf-calendar-view' ) . '</h2>';
    echo '<table class="form-table">';
    echo '<tr><th scope="row"><label for="lgf_calendar_external_pg_host">' . esc_html__( 'Host', 'lgf-calendar-view' ) . '</label></th><td><input type="text" id="lgf_calendar_external_pg_host" name="lgf_calendar_external_pg_host" value="' . esc_attr( $external_db['host'] ) . '" class="regular-text" placeholder="127.0.0.1 or postgres" /></td></tr>';
    echo '<tr><th scope="row"><label for="lgf_calendar_external_pg_port">' . esc_html__( 'Port', 'lgf-calendar-view' ) . '</label></th><td><input type="text" id="lgf_calendar_external_pg_port" name="lgf_calendar_external_pg_port" value="' . esc_attr( $external_db['port'] ) . '" class="small-text" placeholder="5432" /></td></tr>';
    echo '<tr><th scope="row"><label for="lgf_calendar_external_pg_dbname">' . esc_html__( 'Database name', 'lgf-calendar-view' ) . '</label></th><td><input type="text" id="lgf_calendar_external_pg_dbname" name="lgf_calendar_external_pg_dbname" value="' . esc_attr( $external_db['dbname'] ) . '" class="regular-text" placeholder="lgf_bookings" /></td></tr>';
    echo '<tr><th scope="row"><label for="lgf_calendar_external_pg_user">' . esc_html__( 'User', 'lgf-calendar-view' ) . '</label></th><td><input type="text" id="lgf_calendar_external_pg_user" name="lgf_calendar_external_pg_user" value="' . esc_attr( $external_db['user'] ) . '" class="regular-text" placeholder="lgf" /></td></tr>';
    echo '<tr><th scope="row"><label for="lgf_calendar_external_pg_password">' . esc_html__( 'Password', 'lgf-calendar-view' ) . '</label></th><td><input type="password" id="lgf_calendar_external_pg_password" name="lgf_calendar_external_pg_password" value="' . esc_attr( $external_db['password'] ) . '" class="regular-text" /></td></tr>';
    echo '<tr><th scope="row"><label for="lgf_calendar_external_pg_sslmode">' . esc_html__( 'SSL mode', 'lgf-calendar-view' ) . '</label></th><td><select id="lgf_calendar_external_pg_sslmode" name="lgf_calendar_external_pg_sslmode">';
    foreach ( [ 'disable', 'prefer', 'require' ] as $sslmode ) {
        echo '<option value="' . esc_attr( $sslmode ) . '"' . selected( $external_db['sslmode'], $sslmode, false ) . '>' . esc_html( $sslmode ) . '</option>';
    }
    echo '</select></td></tr>';
    echo '</table>';

    echo '<h2>' . esc_html__( 'Invoice Ninja', 'lgf-calendar-view' ) . '</h2>';
    echo '<table class="form-table">';
    echo '<tr><th scope="row"><label for="lgf_calendar_invoice_ninja_url">' . esc_html__( 'Invoice Ninja URL', 'lgf-calendar-view' ) . '</label></th>';
    echo '<td><input type="url" id="lgf_calendar_invoice_ninja_url" name="lgf_calendar_invoice_ninja_url" value="' . esc_attr( $api_url ) . '" class="regular-text" placeholder="https://your-invoice-ninja.com" /></td></tr>';
    echo '<tr><th scope="row"><label for="lgf_calendar_invoice_ninja_token">' . esc_html__( 'API Token', 'lgf-calendar-view' ) . '</label></th>';
    echo '<td><input type="password" id="lgf_calendar_invoice_ninja_token" name="lgf_calendar_invoice_ninja_token" value="' . esc_attr( $api_token ) . '" class="regular-text" /></td></tr>';
    echo '</table>';
    submit_button( __( 'Save Settings', 'lgf-calendar-view' ), 'primary', 'lgf_calendar_submit' );
    echo '</form>';
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

    register_rest_route( 'lgf-calendar/v1', '/create-invoice', [
        'methods'  => 'POST',
        'callback' => 'lgf_calendar_rest_create_invoice',
        'permission_callback' => function() { return lgf_calendar_view_user_can_access(); },
        'args'     => [
            'booking_id' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param > 0;
                },
                'required' => true,
            ],
            'reserved_room_id' => [
                'validate_callback' => function( $param ) {
                    return is_numeric( $param ) && $param > 0;
                },
                'required' => true,
            ],
        ],
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

/**
 * Create invoice in Invoice Ninja via REST API.
 */
function lgf_calendar_rest_create_invoice( WP_REST_Request $request ) {
    $booking_id = absint( $request->get_param( 'booking_id' ) );
    $reserved_room_id = absint( $request->get_param( 'reserved_room_id' ) );
    if ( $booking_id <= 0 || $reserved_room_id <= 0 ) {
        return new WP_Error( 'invalid_booking', __( 'Invalid booking or room reference.', 'lgf-calendar-view' ), [ 'status' => 400 ] );
    }

    $api_url = get_option( 'lgf_calendar_invoice_ninja_url', '' );
    $api_token = get_option( 'lgf_calendar_invoice_ninja_token', '' );

    if ( empty( $api_url ) || empty( $api_token ) ) {
        return new WP_Error( 'invoice_ninja_not_configured', __( 'Invoice Ninja API not configured. Please set URL and token in settings.', 'lgf-calendar-view' ), [ 'status' => 500 ] );
    }

    $overlay = lgf_calendar_view_get_booking_overlay( $reserved_room_id );
    if ( empty( $overlay ) ) {
        return new WP_Error( 'no_overlay_data', __( 'No overlay data for this room booking yet. Save the tariff/commission/extras first.', 'lgf-calendar-view' ), [ 'status' => 404 ] );
    }

    $client_id = '';
    $source = lgf_calendar_view_get_data_source();
    if ( 'external_pg' === $source ) {
        $connection = lgf_calendar_view_get_external_pg_connection();
        if ( is_wp_error( $connection ) ) {
            return $connection;
        }

        $client_result = pg_query_params( $connection, 'SELECT invoice_ninja_client_id FROM bookings WHERE id = $1 LIMIT 1', [ $booking_id ] );
        if ( ! $client_result || 0 === pg_num_rows( $client_result ) ) {
            return new WP_Error( 'booking_not_found', __( 'Booking not found in the external database.', 'lgf-calendar-view' ), [ 'status' => 404 ] );
        }

        $client_row = pg_fetch_assoc( $client_result );
        $client_id = (string) ( $client_row['invoice_ninja_client_id'] ?? '' );
    } elseif ( 'wp_sync' === $source ) {
        global $wpdb;
        $sync_bookings_table = lgf_calendar_view_sync_bookings_table();
        $client_id = (string) $wpdb->get_var( $wpdb->prepare( "SELECT invoice_ninja_client_id FROM {$sync_bookings_table} WHERE external_booking_id = %d LIMIT 1", $booking_id ) );
        if ( '' === $client_id ) {
            return new WP_Error( 'booking_not_found', __( 'Booking not found in local sync data, or it has no Invoice Ninja client ID.', 'lgf-calendar-view' ), [ 'status' => 404 ] );
        }
    } else {
        $booking = get_post( $booking_id );
        if ( ! $booking || 'mphb_booking' !== $booking->post_type ) {
            return new WP_Error( 'booking_not_found', __( 'Booking not found.', 'lgf-calendar-view' ), [ 'status' => 404 ] );
        }

        $client_id = (string) get_post_meta( $booking_id, '_mphb_customer_id', true );
    }

    if ( '' === $client_id ) {
        return new WP_Error( 'no_customer', __( 'This booking has no linked Invoice Ninja client ID.', 'lgf-calendar-view' ), [ 'status' => 400 ] );
    }

    $invoice_lines = [];
    $total = 0.0;

    if ( isset( $overlay['manual_tarif'] ) && '' !== $overlay['manual_tarif'] && null !== $overlay['manual_tarif'] ) {
        $tarif = (float) $overlay['manual_tarif'];
        $invoice_lines[] = [ 'product_key' => 'room-charge', 'quantity' => 1, 'rate' => $tarif ];
        $total += $tarif;
    }

    if ( isset( $overlay['extras_total'] ) && '' !== $overlay['extras_total'] && null !== $overlay['extras_total'] ) {
        $extras = (float) $overlay['extras_total'];
        $invoice_lines[] = [ 'product_key' => 'extras-charge', 'quantity' => 1, 'rate' => $extras ];
        $total += $extras;
    }

    if ( isset( $overlay['manual_commission'] ) && '' !== $overlay['manual_commission'] && null !== $overlay['manual_commission'] ) {
        $commission = (float) $overlay['manual_commission'];
        $invoice_lines[] = [ 'product_key' => 'booking-commission', 'quantity' => 1, 'rate' => -$commission ];
        $total -= $commission;
    }

    if ( empty( $invoice_lines ) ) {
        return new WP_Error( 'empty_invoice', __( 'No invoiceable lines were found in the saved overlay data.', 'lgf-calendar-view' ), [ 'status' => 400 ] );
    }

    $payload = [
        'client_id' => $client_id,
        'lines' => $invoice_lines,
        'amount' => round( $total, 2 ),
        'is_amount_discount' => false,
        'discount' => 0,
    ];

    $response = wp_remote_post( trailingslashit( $api_url ) . 'api/v1/invoices', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'body' => wp_json_encode( $payload ),
        'timeout' => 15,
    ] );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'invoice_ninja_request_failed', $response->get_error_message(), [ 'status' => 500 ] );
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( 201 !== $code || empty( $data['id'] ) ) {
        return new WP_Error( 'invoice_ninja_invalid_response', __( 'Failed to create invoice. Response:', 'lgf-calendar-view' ) . ' ' . $code . ' - ' . $body, [ 'status' => $code >= 500 ? 502 : 500 ] );
    }

    if ( 'external_pg' === $source ) {
        $connection = lgf_calendar_view_get_external_pg_connection();
        if ( ! is_wp_error( $connection ) ) {
            pg_query_params( $connection, 'UPDATE bookings SET invoice_ninja_invoice_id = $1, invoiced_at = NOW() WHERE id = $2', [ (string) $data['id'], $booking_id ] );
        }
    } elseif ( 'wp_sync' === $source ) {
        global $wpdb;
        $sync_bookings_table = lgf_calendar_view_sync_bookings_table();
        $wpdb->update(
            $sync_bookings_table,
            [ 'invoice_ninja_invoice_id' => (string) $data['id'] ],
            [ 'external_booking_id' => $booking_id ],
            [ '%s' ],
            [ '%d' ]
        );
    } else {
        update_post_meta( $booking_id, '_lgf_calendar_invoice_ninja_id', $data['id'] );
    }

    return rest_ensure_response( [
        'success' => true,
        'invoice_id' => $data['id'],
        'invoice_number' => $data['invoice_number'] ?? '',
        'amount' => $data['amount'] ?? $total,
    ] );
}
