#!/usr/bin/env python3
import csv
import io
import subprocess
import sys
from pathlib import Path

PG_CONTAINER = 'lgf-bookings-postgres'
WP_DB_CONTAINER = 'wp-db-dev'
WP_DB_NAME = 'wp_booking_dev'
WP_DB_USER = 'wp_user'
WP_DB_PASSWORD = 'wp_password'
ROOMS_TABLE = 'wp_lgf_calendar_sync_rooms'
BOOKINGS_TABLE = 'wp_lgf_calendar_sync_bookings'

ROOMS_SQL = r"""
COPY (
    SELECT
        id,
        code,
        name,
        ROW_NUMBER() OVER (ORDER BY code) AS sort_order,
        CASE WHEN active THEN 1 ELSE 0 END AS active
    FROM rooms
    ORDER BY code
) TO STDOUT WITH CSV HEADER
"""

BOOKINGS_SQL = r"""
COPY (
    SELECT
        b.id AS external_booking_id,
        br.id AS external_booking_room_id,
        br.room_id AS external_room_id,
        b.status_code,
        to_char(b.check_in_date, 'YYYY-MM-DD') AS check_in,
        to_char(b.check_out_date, 'YYYY-MM-DD') AS check_out,
        b.adults,
        b.children,
        b.total_amount,
        ROUND((b.total_amount / GREATEST(room_counts.room_count, 1))::numeric, 2) AS room_amount,
        room_counts.room_count,
        b.source_channel,
        COALESCE(b.source_booking_id, '') AS source_booking_id,
        COALESCE(bc.label, b.source_channel) AS channel_label,
        trim(concat(g.first_name, ' ', g.last_name)) AS guest_name,
        COALESCE(g.phone, '') AS phone,
        COALESCE(b.internal_notes, '') AS internal_notes,
        COALESCE(b.invoice_ninja_client_id, '') AS invoice_ninja_client_id,
        COALESCE(b.invoice_ninja_invoice_id, '') AS invoice_ninja_invoice_id,
        to_char(b.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') AS source_created_at
    FROM bookings b
    JOIN booking_rooms br ON br.booking_id = b.id
    JOIN guests g ON g.id = b.guest_id
    LEFT JOIN booking_channels bc ON bc.code = b.source_channel
    JOIN (
        SELECT booking_id, COUNT(*) AS room_count
        FROM booking_rooms
        GROUP BY booking_id
    ) room_counts ON room_counts.booking_id = b.id
    JOIN booking_statuses bs ON bs.code = b.status_code
    WHERE bs.blocks_availability = true
    ORDER BY b.check_in_date, b.id, br.id
) TO STDOUT WITH CSV HEADER
"""


def run(cmd, *, input_text=None):
    result = subprocess.run(cmd, input=input_text, text=True, capture_output=True)
    if result.returncode != 0:
        print(result.stderr.strip() or result.stdout.strip(), file=sys.stderr)
        raise SystemExit(result.returncode)
    return result.stdout


def fetch_csv(sql: str):
    out = run(['docker', 'exec', '-i', PG_CONTAINER, 'psql', '-U', 'lgf', '-d', 'lgf_bookings', '-c', sql])
    return list(csv.DictReader(io.StringIO(out)))


def sql_value(value):
    if value is None:
        return 'NULL'
    value = str(value)
    return "'" + value.replace('\\', '\\\\').replace("'", "''") + "'"


def build_sql(rooms, bookings):
    lines = [
        'SET FOREIGN_KEY_CHECKS=0;',
        f'TRUNCATE TABLE {BOOKINGS_TABLE};',
        f'TRUNCATE TABLE {ROOMS_TABLE};',
    ]

    if rooms:
        room_values = []
        for row in rooms:
            room_values.append('(' + ', '.join([
                sql_value(row['id']),
                sql_value(row['code']),
                sql_value(row['name']),
                sql_value(row['sort_order']),
                sql_value(row['active']),
                'NOW()'
            ]) + ')')
        lines.append(
            f"INSERT INTO {ROOMS_TABLE} (external_room_id, room_code, room_name, sort_order, active, synced_at) VALUES\n" + ',\n'.join(room_values) + ';'
        )

    room_id_map_sql = f"(SELECT id FROM {ROOMS_TABLE} WHERE external_room_id = %s LIMIT 1)"

    if bookings:
        booking_values = []
        for row in bookings:
            booking_values.append('(' + ', '.join([
                sql_value(row['external_booking_id']),
                sql_value(row['external_booking_room_id']),
                sql_value(row['external_room_id']),
                room_id_map_sql % sql_value(row['external_room_id']),
                sql_value(row['status_code']),
                sql_value(row['check_in']),
                sql_value(row['check_out']),
                sql_value(row['adults']),
                sql_value(row['children']),
                sql_value(row['total_amount']),
                sql_value(row['room_amount']),
                sql_value(row['room_count']),
                sql_value(row['source_channel']),
                sql_value(row['source_booking_id']),
                sql_value(row['channel_label']),
                sql_value(row['guest_name']),
                sql_value(row['phone']),
                sql_value(row['internal_notes']),
                sql_value(row['invoice_ninja_client_id']),
                sql_value(row['invoice_ninja_invoice_id']),
                sql_value(row['source_created_at']) if row['source_created_at'] else 'NULL',
                'NOW()'
            ]) + ')')
        lines.append(
            f"INSERT INTO {BOOKINGS_TABLE} (external_booking_id, external_booking_room_id, external_room_id, room_sync_id, status_code, check_in, check_out, adults, children, total_amount, room_amount, room_count, source_channel, source_booking_id, channel_label, guest_name, phone, internal_notes, invoice_ninja_client_id, invoice_ninja_invoice_id, source_created_at, synced_at) VALUES\n" + ',\n'.join(booking_values) + ';'
        )

    lines.append('SET FOREIGN_KEY_CHECKS=1;')
    return '\n'.join(lines) + '\n'


def main():
    print('Fetching rooms from PostgreSQL...')
    rooms = fetch_csv(ROOMS_SQL)
    print(f'Fetched {len(rooms)} rooms')

    print('Fetching booking-room rows from PostgreSQL...')
    bookings = fetch_csv(BOOKINGS_SQL)
    print(f'Fetched {len(bookings)} booking-room rows')

    sql = build_sql(rooms, bookings)
    sql_file = Path('/tmp/lgf_wp_sync.sql')
    sql_file.write_text(sql)

    print('Importing into local WordPress MySQL...')
    run(
        ['docker', 'exec', '-i', WP_DB_CONTAINER, 'mariadb', f'-u{WP_DB_USER}', f'-p{WP_DB_PASSWORD}', WP_DB_NAME],
        input_text=sql,
    )

    print('Sync complete.')
    print(f'Rooms synced: {len(rooms)}')
    print(f'Booking-room rows synced: {len(bookings)}')


if __name__ == '__main__':
    main()
