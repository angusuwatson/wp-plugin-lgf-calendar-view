# LGF Calendar View Plugin

A WordPress plugin to display Motopress Hotel Booking data in a LibreOffice Calc-style layout.

## Features

- Displays booking data in a clean, spreadsheet-like table
- Automatic dependency check (requires Motopress Hotel Booking)
- Theme override support via template hierarchy
- Internationalization ready
- Shortcode attributes for month/year filtering
- Lightweight and minimal

## Installation

1. Upload the plugin files to the `/wp-content/plugins/lgf-calendar-view` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Ensure Motopress Hotel Booking plugin is installed and active.
4. Use the shortcode `[lgf_calendar_view]` on any page or post.
5. Optionally, pass attributes: `[lgf_calendar_view month="3" year="2026"]`

## Technical Notes

- The plugin follows WordPress coding standards.
- All output is escaped for security.
- The template can be overridden by copying `templates/booking-view.php` to your theme's `lgf-calendar-view/` directory.
- Uses output buffering correctly to prevent layout breaks.

## Development

This plugin was co-developed by:
- Angus Watson (delegating)
- Quuin (mistral/codestral)
- Kylie (stepfun/step-3.5-flash:free)

## License

GPL v2 or later (same as WordPress)
