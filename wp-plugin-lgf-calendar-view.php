<?php
/**
 * Plugin Name: LGF Calendar View
 * Description: Displays Motopress Hotel Booking data in a LibreOffice Calc-style layout
 * Version: 1.0
 * Author: Angus Watson, Quinn (mistral/codestral) & Kylie (stepfun/step-3.5-flash:free)
 */

// Activation hook
register_activation_hook(__FILE__, 'lgf_calendar_view_activate');

function lgf_calendar_view_activate() {
    // Activation code here
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'lgf_calendar_view_deactivate');

function lgf_calendar_view_deactivate() {
    // Deactivation code here
}

// Shortcode to display the booking view
add_shortcode('lgf_calendar_view', 'lgf_calendar_view_shortcode');

function lgf_calendar_view_shortcode($atts) {
    // Include the template file
    include plugin_dir_path(__FILE__) . 'templates/booking-view.php';
}
?>
