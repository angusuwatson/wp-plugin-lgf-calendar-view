#!/usr/bin/env bash
set -euo pipefail

PG_CONTAINER="${PG_CONTAINER:-lgf-bookings-postgres}"
WP_DB_CONTAINER="${WP_DB_CONTAINER:-wp-db-dev}"
PG_DB="${PG_DB:-lgf_bookings}"
PG_USER="${PG_USER:-lgf}"
MYSQL_DB="${MYSQL_DB:-wp_booking_dev}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-root_password}"

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

ROOMS_CSV="$TMP_DIR/rooms.csv"
BOOKINGS_CSV="$TMP_DIR/bookings.csv"
IMPORT_SQL="$TMP_DIR/import.sql"

cat <<'SQL' | docker exec -i "$PG_CONTAINER" psql -U "$PG_USER" -d "$PG_DB" --csv > "$ROOMS_CSV"
SELECT
    r.id AS external_room_id,
    r.code AS room_code,
    r.name AS room_name,
    CASE upper(r.code)
        WHEN 'ANE' THEN 1
        WHEN 'DEL' THEN 2
        WHEN 'LYS' THEN 3
        WHEN 'TOU' THEN 4
        WHEN 'TUL' THEN 5
        WHEN 'COQ' THEN 6
        ELSE 999
    END AS sort_order,
    CASE WHEN r.active THEN 1 ELSE 0 END AS active
FROM rooms r
ORDER BY sort_order, r.name;
SQL

cat <<'SQL' | docker exec -i "$PG_CONTAINER" psql -U "$PG_USER" -d "$PG_DB" --csv > "$BOOKINGS_CSV"
SELECT
    b.id AS external_booking_id,
    br.id AS external_booking_room_id,
    br.room_id AS external_room_id,
    b.status_code,
    to_char(b.check_in_date, 'YYYY-MM-DD') AS check_in,
    to_char(b.check_out_date, 'YYYY-MM-DD') AS check_out,
    to_char(brn.stay_date, 'YYYY-MM-DD') AS stay_date,
    brn.guest_count,
    brn.adults,
    brn.children,
    brn.babies,
    brn.total_amount,
    brn.room_rate_amount AS room_amount,
    brn.extras_amount,
    brn.tourist_tax_amount,
    room_counts.room_count,
    b.source_channel,
    COALESCE(b.source_booking_id, '') AS source_booking_id,
    COALESCE(bc.label, b.source_channel) AS channel_label,
    trim(concat_ws(' ', g.first_name, g.last_name)) AS guest_name,
    COALESCE(g.phone, '') AS phone,
    COALESCE(b.internal_notes, '') AS import_notes,
    COALESCE(b.invoice_ninja_client_id, '') AS invoice_ninja_client_id,
    COALESCE(b.invoice_ninja_invoice_id, '') AS invoice_ninja_invoice_id,
    to_char(b.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') AS source_created_at
FROM bookings b
JOIN booking_rooms br ON br.booking_id = b.id
JOIN booking_room_nights brn ON brn.booking_room_id = br.id
JOIN guests g ON g.id = b.guest_id
LEFT JOIN booking_channels bc ON bc.code = b.source_channel
JOIN booking_statuses bs ON bs.code = b.status_code
JOIN (
    SELECT booking_id, COUNT(*) AS room_count
    FROM booking_rooms
    GROUP BY booking_id
) room_counts ON room_counts.booking_id = b.id
WHERE bs.blocks_availability = true
ORDER BY brn.stay_date, b.id, br.id;
SQL

python3 - <<'PY' "$ROOMS_CSV" "$BOOKINGS_CSV" "$IMPORT_SQL"
import csv, sys
rooms_csv, bookings_csv, out_sql = sys.argv[1:4]

def sql_value(value):
    if value is None or value == '':
        return 'NULL'
    return "'" + str(value).replace('\\', '\\\\').replace("'", "''") + "'"

with open(out_sql, 'w', encoding='utf-8') as out:
    out.write("SET FOREIGN_KEY_CHECKS=0;\n")
    out.write("TRUNCATE TABLE wp_lgf_calendar_sync_bookings;\n")
    out.write("TRUNCATE TABLE wp_lgf_calendar_sync_rooms;\n")

    with open(rooms_csv, newline='', encoding='utf-8') as fh:
        for row in csv.DictReader(fh):
            out.write(
                "INSERT INTO wp_lgf_calendar_sync_rooms "
                "(external_room_id, room_code, room_name, sort_order, active) VALUES "
                f"({sql_value(row['external_room_id'])}, {sql_value(row['room_code'])}, {sql_value(row['room_name'])}, {sql_value(row['sort_order'])}, {sql_value(row['active'])});\n"
            )

    with open(bookings_csv, newline='', encoding='utf-8') as fh:
        for row in csv.DictReader(fh):
            out.write(
                "INSERT INTO wp_lgf_calendar_sync_bookings "
                "(external_booking_id, external_booking_room_id, external_room_id, status_code, check_in, check_out, stay_date, guest_count, adults, children, babies, total_amount, room_amount, extras_amount, tourist_tax_amount, room_count, source_channel, source_booking_id, channel_label, guest_name, phone, import_notes, invoice_ninja_client_id, invoice_ninja_invoice_id, source_created_at) VALUES "
                f"({sql_value(row['external_booking_id'])}, {sql_value(row['external_booking_room_id'])}, {sql_value(row['external_room_id'])}, {sql_value(row['status_code'])}, {sql_value(row['check_in'])}, {sql_value(row['check_out'])}, {sql_value(row['stay_date'])}, {sql_value(row['guest_count'])}, {sql_value(row['adults'])}, {sql_value(row['children'])}, {sql_value(row['babies'])}, {sql_value(row['total_amount'])}, {sql_value(row['room_amount'])}, {sql_value(row['extras_amount'])}, {sql_value(row['tourist_tax_amount'])}, {sql_value(row['room_count'])}, {sql_value(row['source_channel'])}, {sql_value(row['source_booking_id'])}, {sql_value(row['channel_label'])}, {sql_value(row['guest_name'])}, {sql_value(row['phone'])}, {sql_value(row['import_notes'])}, {sql_value(row['invoice_ninja_client_id'])}, {sql_value(row['invoice_ninja_invoice_id'])}, {sql_value(row['source_created_at'])});\n"
            )

    out.write("UPDATE wp_lgf_calendar_sync_bookings b JOIN wp_lgf_calendar_sync_rooms r ON r.external_room_id = b.external_room_id SET b.room_sync_id = r.id;\n")
    out.write("UPDATE wp_lgf_calendar_sync_rooms SET synced_at = NOW();\n")
    out.write("UPDATE wp_lgf_calendar_sync_bookings SET synced_at = NOW();\n")
    out.write("SET FOREIGN_KEY_CHECKS=1;\n")
PY

docker exec -i "$WP_DB_CONTAINER" mariadb -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DB" < "$IMPORT_SQL"

echo "Synced rooms: $(tail -n +2 "$ROOMS_CSV" | wc -l)"
echo "Synced booking-room rows: $(tail -n +2 "$BOOKINGS_CSV" | wc -l)"
