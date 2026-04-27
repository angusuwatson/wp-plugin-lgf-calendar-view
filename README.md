# LGF Calendar View Plugin

A WordPress plugin to display MotoPress Hotel Booking data or bookings from the standalone LGF PostgreSQL database in a LibreOffice Calc-style spreadsheet layout.

## Features

### Calendar View
- Spreadsheet-style layout matching the LibreOffice Calc booking sheet format
- Color-coded rooms
- Month navigation with URL persistence
- REST API-powered dynamic loading

### Booking Data Display
- Guest names with manual override support
- Channel tracking
- Occupancy editing
- Extras formula support (for example `=12.50+8.00`)
- Tarif and commission tracking

### Data Sources
- MotoPress / WordPress booking source
- Local WordPress sync tables populated from the LGF PostgreSQL project
- External PostgreSQL booking source using the LGF database project schema
- Automatic MotoPress dependency checks only when that source is selected

### Daily Notes and Overlays
- Per-day notes with auto-save
- Manual overlay storage for guest name, occupancy, tarif, commission, extras, and booking notes
- Cached calendar rendering for performance

### Invoice Ninja Integration
- Settings page for Invoice Ninja credentials
- Per-room invoice creation button
- Room charge, extras, and commission line generation

### Admin Integration
- WordPress admin menu
- Shortcode support via `[lgf_calendar_view]`
- Administrator-only access

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MotoPress Hotel Booking plugin installed and active when using the MotoPress source
- PHP `pgsql` extension when using the external PostgreSQL source

## Installation

1. Upload the plugin files to the `/wp-content/plugins/lgf-calendar-view` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. If using the MotoPress source, ensure MotoPress Hotel Booking is installed and active.
4. If using the Local WordPress sync source, run `python3 scripts/sync_lgf_to_wp_local.py` to copy data from the LGF PostgreSQL database into the plugin's WordPress sync tables, then switch the booking source to **Local WordPress sync tables**.
5. If using the direct LGF PostgreSQL source, open **LGF Calendar → Settings** and enter the PostgreSQL connection details for your `lgf_bookings` database, then switch the booking source to **External PostgreSQL**.
6. Access the calendar via the **LGF Calendar** admin menu or use the shortcode `[lgf_calendar_view]` on any page or post.
7. Optionally, pass attributes: `[lgf_calendar_view month="3" year="2026"]`

### Invoice Ninja Setup

1. Go to **LGF Calendar → Settings**.
2. Enter your Invoice Ninja URL.
3. Enter your API token.
4. Save settings.

## Technical Notes

- Creates `wp_lgf_calendar_daily_notes`, `wp_lgf_calendar_booking_overlays`, `wp_lgf_calendar_sync_rooms`, and `wp_lgf_calendar_sync_bookings`.
- Caches calendar data with source-aware transient keys.
- Supports theme override via `templates/booking-view.php` copied into `your-theme/lgf-calendar-view/`.
- Local sync mode is the recommended production path for shared hosting.
- External PostgreSQL mode expects the schema from `/home/angus/.pi/projects/lgf-database`.
- All output is escaped for security.

## Development

This plugin was co-developed by:
- Angus Watson
- Quinn (mistral/codestral)
- Kylie (stepfun/step-3.5-flash:free)

## License

GPL v2 or later (same as WordPress)
