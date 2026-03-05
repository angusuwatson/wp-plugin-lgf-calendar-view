<?php
/**
 * Plugin Name: LGF Calendar View
 * Description: Displays Motopress Hotel Booking data in a LibreOffice Calc-style layout
 * Version: 1.0
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

// Enqueue scripts and styles ( future-proofing )
add_action( 'wp_enqueue_scripts', 'lgf_calendar_view_enqueue_assets' );
function lgf_calendar_view_enqueue_assets() {
    // Only load on pages with our shortcode (optimization)
    if ( has_shortcode( get_post()->post_content ?? '', 'lgf_calendar_view' ) ) {
        wp_register_style( 'lgf-calendar-view', plugin_dir_url( __FILE__ ) . 'assets/style.css' );
        wp_enqueue_style( 'lgf-calendar-view' );
    }
}

// Fetch Motopress Hotel Booking data
function lgf_calendar_view_get_bookings( $month = null, $year = null ) {
    if ( ! class_exists( 'MPHB' ) ) {
        return [];
    }

    $month = $month ?: date( 'n' );
    $year  = $year  ?: date( 'Y' );

    global $wpdb;
    $mphb_bookings_table = $wpdb->prefix . 'mphb_bookings';
    $mphb_rooms_table    = $wpdb->prefix . 'mphb_rooms';

    // Query bookings for the given month/year (basic implementation)
    $results = $wpdb->get_results(
        $wpdb->prepare(
            "
            SELECT 
                b.*,
                r.title as room_name
            FROM $mphb_bookings_table b
            LEFT JOIN $mphb_rooms_table r ON b.room_id = r.id
            WHERE MONTH(b.check_in_date) = %d
              AND YEAR(b.check_in_date) = %d
            ORDER BY b.check_in_date ASC
            ",
            $month,
            $year
        )
    );

    return $results ?: [];
}

// Shortcode to display the booking view
add_shortcode( 'lgf_calendar_view', 'lgf_calendar_view_shortcode' );

function lgf_calendar_view_shortcode( $atts ) {
    // Support shortcode attributes (easily extendable)
    $atts = shortcode_atts( [
        'month' => date( 'n' ),
        'year'  => date( 'Y' ),
    ], $atts, 'lgf_calendar_view' );

    $month = intval( $atts['month'] );
    $year  = intval( $atts['year'] );

    // Make data available to template
    $bookings = lgf_calendar_view_get_bookings( $month, $year );

    // Allow theme override via template hierarchy
    $template = locate_template( 'lgf-calendar-view/booking-view.php' );
    if ( ! $template ) {
        $template = plugin_dir_path( __FILE__ ) . 'templates/booking-view.php';
    }

    // Output buffering to return content (not echo)
    ob_start();
    include $template;
    return ob_get_clean();
}
