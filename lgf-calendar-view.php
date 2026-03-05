<?php
/**
 * Plugin Name: LGF Calendar View
 * Description: Displays Motopress Hotel Booking data in a LibreOffice Calc-style layout
 * Version: 1.1
 * Author: Angus Watson, Quinn (mistral/codestral) & Kylie (stepfun/step-3.5-flash:free)
 * Text Domain: lgf-calendar-view
 */

// Prevent direct access
defined( 'ABSPATH' ) || exit;

// Check for required Motopress Hotel Booking plugin
add_action( 'plugins_loaded', 'lgf_calendar_view_check_dependency' );
function lgf_calendar_view_check_dependency() {
    if ( ! class_exists( 'MPHB' ) ) {
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
    }
}

/**
 * Get calendar data for a month/year.
 *
 * Returns array with:
 * - rooms: array of room objects (id, title)
 * - bookings_by_room_date: [room_id][date_str] = booking object
 * - month, year, days_in_month, days (1-indexed array)
 */
function lgf_calendar_view_get_calendar_data( $month = null, $year = null ) {
    if ( ! class_exists( 'MPHB' ) ) {
        return [ 'rooms' => [], 'bookings_by_room_date' => [], 'month' => $month, 'year' => $year, 'days_in_month' => 0, 'days' => [] ];
    }

    $month = $month ?: date( 'n' );
    $year  = $year  ?: date( 'Y' );

    global $wpdb;
    $mphb_rooms_table    = $wpdb->prefix . 'mphb_rooms';
    $mphb_bookings_table = $wpdb->prefix . 'mphb_bookings';

    // Get all rooms (ordered by title)
    $rooms = $wpdb->get_results( "SELECT id, title FROM $mphb_rooms_table ORDER BY title" );

    // First and last day of the month
    $first_day_obj = DateTime::createFromFormat( '!Y-n-j', "$year-$month-1" );
    if ( ! $first_day_obj ) {
        $first_day_obj = new DateTime( "$year-$month-01" );
    }
    $first_day_str = $first_day_obj->format( 'Y-m-d' );
    $days_in_month = intval( $first_day_obj->format( 't' ) );
    $last_day_str = $first_day_obj->format( 'Y-m-t' );

    // Get bookings that overlap this month
    $bookings = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT b.*, r.title as room_name
            FROM $mphb_bookings_table b
            LEFT JOIN $mphb_rooms_table r ON b.room_id = r.id
            WHERE b.check_in_date <= %s
              AND b.check_out_date >= %s
            ",
            $last_day_str,
            $first_day_str
        )
    );

    // Build map: room_id => date_str => booking
    $bookings_by_room_date = [];
    foreach ( $bookings as $booking ) {
        $room_id = $booking->room_id;
        if ( ! isset( $bookings_by_room_date[ $room_id ] ) ) {
            $bookings_by_room_date[ $room_id ] = [];
        }

        $check_in = new DateTime( $booking->check_in_date );
        $check_out = new DateTime( $booking->check_out_date );
        for ( $date = clone $check_in; $date < $check_out; $date->modify( '+1 day' ) ) {
            $date_str = $date->format( 'Y-m-d' );
            if ( $date_str >= $first_day_str && $date_str <= $last_day_str ) {
                $bookings_by_room_date[ $room_id ][ $date_str ] = $booking;
            }
        }
    }

    $days = range( 1, $days_in_month );

    return [
        'rooms'                => $rooms,
        'bookings_by_room_date' => $bookings_by_room_date,
        'month'                => $month,
        'year'                 => $year,
        'days_in_month'        => $days_in_month,
        'days'                 => $days,
    ];
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

    ob_start();
    include $template;
    return ob_get_clean();
}