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
    ROW_NUMBER() OVER (ORDER BY r.code, r.name) AS sort_order,
    CASE WHEN r.active THEN 1 ELSE 0 END AS active
FROM rooms r
ORDER BY r.code, r.name;
SQL

cat <<'SQL' | docker exec -i "$PG_CONTAINER" psql -U "$PG_USER" -d "$PG_DB" --csv > "$BOOKINGS_CSV"
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
    trim(concat_ws(' ', g.first_name, g.last_name)) AS guest_name,
    COALESCE(g.phone, '') AS phone,
    COALESCE(b.internal_notes, '') AS internal_notes,
    COALESCE(b.invoice_ninja_client_id, '') AS invoice_ninja_client_id,
    COALESCE(b.invoice_ninja_invoice_id, '') AS invoice_ninja_invoice_id,
    to_char(b.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI:SS') AS source_created_at
FROM bookings b
JOIN booking_rooms br ON br.booking_id = b.id
JOIN guests g ON g.id = b.guest_id
LEFT JOIN booking_channels bc ON bc.code = b.source_channel
JOIN booking_statuses bs ON bs.code = b.status_code
JOIN (
    SELECT booking_id, COUNT(*) AS room_count
    FROM booking_rooms
    GROUP BY booking_id
) room_counts ON room_counts.booking_id = b.id
WHERE bs.blocks_availability = true
ORDER BY b.check_in_date, b.id, br.id;
SQL

ROOMS_CONTAINER_CSV="/tmp/lgf-sync-rooms.csv"
BOOKINGS_CONTAINER_CSV="/tmp/lgf-sync-bookings.csv"

docker cp "$ROOMS_CSV" "$WP_DB_CONTAINER:$ROOMS_CONTAINER_CSV"
docker cp "$BOOKINGS_CSV" "$WP_DB_CONTAINER:$BOOKINGS_CONTAINER_CSV"

cat > "$IMPORT_SQL" <<SQL
SET FOREIGN_KEY_CHECKS=0;
TRUNCATE TABLE wp_lgf_calendar_sync_bookings;
TRUNCATE TABLE wp_lgf_calendar_sync_rooms;
LOAD DATA INFILE '${ROOMS_CONTAINER_CSV}'
INTO TABLE wp_lgf_calendar_sync_rooms
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(external_room_id, room_code, room_name, sort_order, active);
LOAD DATA INFILE '${BOOKINGS_CONTAINER_CSV}'
INTO TABLE wp_lgf_calendar_sync_bookings
FIELDS TERMINATED BY ',' OPTIONALLY ENCLOSED BY '"'
LINES TERMINATED BY '\n'
IGNORE 1 LINES
(external_booking_id, external_booking_room_id, external_room_id, status_code, check_in, check_out, adults, children, total_amount, room_amount, room_count, source_channel, source_booking_id, channel_label, guest_name, phone, internal_notes, invoice_ninja_client_id, invoice_ninja_invoice_id, source_created_at);
UPDATE wp_lgf_calendar_sync_bookings b
JOIN wp_lgf_calendar_sync_rooms r ON r.external_room_id = b.external_room_id
SET b.room_sync_id = r.id;
UPDATE wp_lgf_calendar_sync_rooms SET synced_at = NOW();
UPDATE wp_lgf_calendar_sync_bookings SET synced_at = NOW();
SET FOREIGN_KEY_CHECKS=1;
SQL

docker exec -i "$WP_DB_CONTAINER" mariadb -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DB" < "$IMPORT_SQL"
docker exec "$WP_DB_CONTAINER" rm -f "$ROOMS_CONTAINER_CSV" "$BOOKINGS_CONTAINER_CSV"

echo "Synced rooms: $(tail -n +2 "$ROOMS_CSV" | wc -l)"
echo "Synced booking-room rows: $(tail -n +2 "$BOOKINGS_CSV" | wc -l)"
