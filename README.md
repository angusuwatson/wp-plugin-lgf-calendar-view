# LGF Calendar View Plugin

A WordPress plugin to display Motopress Hotel Booking data in a LibreOffice Calc-style spreadsheet layout.

## Features

### Calendar View
- **Spreadsheet-style layout**: Rooms as rows, days as columns, matching the LibreOffice Calc booking sheet format
- **Color-coded rooms**: Each room has a distinct background color
- **Month navigation**: Tab-based month navigation with URL persistence
- **REST API**: Dynamic AJAX loading of calendar data without page refreshes

### Booking Data Display
- **Guest names**: Automatically fetched from booking data, with manual override support
- **Channel tracking**: Website (W) vs Imported (I) bookings with creation date
- **Occupancy**: Adults (A) and Children (C) count with inline editing
- **Extras**: Trackable extras with formula evaluation (e.g., `=12.50+8.00`)
- **Tarif**: Room rate with currency formatting
- **Commission**: Booking.com commission tracking

### Daily Notes
- **Per-day notes**: Add notes to any calendar day
- **Auto-save**: Notes save automatically when edited

### Booking Overlays
- **Manual data entry**: Override guest name, occupancy, tarif, commission, and extras per booking
- **Persistent storage**: Overlay data stored in custom database tables
- **Caching**: Calendar data cached for performance (30 minutes)

### Invoice Ninja Integration
- **Invoice creation**: Create invoices directly from bookings
- **Settings page**: Configure Invoice Ninja API URL and token
- **Line items**: Automatically generates invoice with room charge, extras, and commission offset

### Admin Integration
- **Admin menu**: Access via WordPress admin sidebar
- **Shortcode**: Use `[lgf_calendar_view]` on any page or post
- **Capability check**: Only administrators can access

## Requirements

- WordPress 5.0+
- Motopress Hotel Booking plugin installed and active
- PHP 7.4+

## Installation

1. Upload the plugin files to the `/wp-content/plugins/lgf-calendar-view` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Ensure Motopress Hotel Booking plugin is installed and active.
4. Access the calendar via the "LGF Calendar" admin menu.

### Shortcode Usage

Basic usage:
```
[lgf_calendar_view]
```

With specific month/year:
```
[lgf_calendar_view month="3" year="2026"]
```

### Invoice Ninja Setup

1. Go to LGF Calendar > Settings
2. Enter your Invoice Ninja URL (e.g., `https://your-invoice-ninja.com`)
3. Enter your API token
4. Save settings

## Technical Notes

- **Database tables**: Creates `wp_lgf_calendar_daily_notes` and `wp_lgf_calendar_booking_overlays`
- **Transients**: Calendar data cached with key `lgf_calendar_{year}_{month}`
- **Polylang compatible**: Filters rooms by current language if Polylang is active
- **Template override**: Copy `templates/booking-view.php` to your theme's `lgf-calendar-view/` directory
- **All output escaped**: Security-first approach with proper escaping throughout

## Development

This plugin was co-developed by:
- Angus Watson
- Quinn (mistral/codestral)
- Kylie (stepfun/step-3.5-flash:free)

## License

GPL v2 or later (same as WordPress)
