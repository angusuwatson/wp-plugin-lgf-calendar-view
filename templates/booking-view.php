<?php
/* @var $calendar_data array */
$rooms = $calendar_data['rooms'];
$matrix = $calendar_data['matrix'];
$month = $calendar_data['month'];
$year = $calendar_data['year'];
$days_in_month = $calendar_data['days_in_month'];
$days = $calendar_data['days'];
$daily_notes = $calendar_data['daily_notes'] ?? [];
$summary = lgf_calendar_view_build_daily_summary( $calendar_data );
$month_tabs = lgf_calendar_view_get_month_tabs( $month, $year );
$calendar_base_url = $calendar_base_url ?? '';
?>
<div class="lgf-calendar-container">
    <div class="calendar-month-tabs" role="tablist" aria-label="Calendar months">
        <?php foreach ( $month_tabs as $tab ) : ?>
            <a class="calendar-month-tab<?php echo $tab['current'] ? ' is-current' : ''; ?>" href="<?php echo esc_url( add_query_arg( [ 'month' => $tab['month'], 'year' => $tab['year'] ], $calendar_base_url ) ); ?>" data-month="<?php echo esc_attr( $tab['month'] ); ?>" data-year="<?php echo esc_attr( $tab['year'] ); ?>"><?php echo esc_html( $tab['label'] ); ?></a>
        <?php endforeach; ?>
    </div>

    <div class="calendar-nav">
        <?php
        $prev = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
        $prev->modify( '-1 month' );
        $next = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
        $next->modify( '+1 month' );
        ?>
        <a class="button" href="<?php echo esc_url( add_query_arg( [ 'month' => $prev->format( 'n' ), 'year' => $prev->format( 'Y' ) ], $calendar_base_url ) ); ?>">&laquo; <?php esc_html_e( 'Previous', 'lgf-calendar-view' ); ?></a>
        <span class="current-month"><?php echo esc_html( date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) ) ); ?></span>
        <a class="button" href="<?php echo esc_url( add_query_arg( [ 'month' => $next->format( 'n' ), 'year' => $next->format( 'Y' ) ], $calendar_base_url ) ); ?>"><?php esc_html_e( 'Next', 'lgf-calendar-view' ); ?> &raquo;</a>
    </div>

    <div class="lgf-calendar-view">
        <table class="wp-list-table widefat fixed striped calendar-grid">
            <thead>
                <tr class="header-row month-row">
                    <th class="label sticky-col room-header-spacer"></th>
                    <?php foreach ( $days as $day ) : ?>
                        <th>
                            <span class="calendar-month-name"><?php echo esc_html( date_i18n( 'F', mktime( 0, 0, 0, $month, $day, $year ) ) ); ?></span>
                            <span class="calendar-day-number"><?php echo esc_html( $day ); ?></span>
                        </th>
                    <?php endforeach; ?>
                </tr>
                <tr class="dow-row weekday-row">
                    <th class="label sticky-col room-header-spacer"></th>
                    <?php foreach ( $days as $day ) : $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day ); ?>
                        <th><span class="calendar-weekday"><?php echo esc_html( date_i18n( 'l', strtotime( $date_str ) ) ); ?></span></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr class="notes-row">
                    <td class="label sticky-col notes-label-cell"><span class="room-label-text"><?php esc_html_e( 'Notes', 'lgf-calendar-view' ); ?></span></td>
                    <?php foreach ( $days as $day ) : $note_date = sprintf( '%04d-%02d-%02d', $year, $month, $day ); ?>
                        <td class="calendar-cell notes-cell">
                            <input type="text" class="calendar-note-input" data-note-date="<?php echo esc_attr( $note_date ); ?>" value="<?php echo esc_attr( $daily_notes[ $note_date ] ?? '' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Note for %s', 'lgf-calendar-view' ), $note_date ) ); ?>" />
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php if ( empty( $rooms ) ) : ?>
                    <tr><td colspan="<?php echo esc_attr( 1 + $days_in_month ); ?>"><?php esc_html_e( 'No rooms found.', 'lgf-calendar-view' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $rooms as $index => $room ) :
                        $room_id = $room->id;
                        $color = $room->color ?? '#ccc';
                        $room_number = $index + 1;
                        $rows = [
                            [ 'label' => $room_number . ' - ' . $room->title, 'class' => 'room-name-row', 'type' => 'title', 'field' => '' ],
                            [ 'label' => '', 'class' => 'booking-note-row', 'type' => 'detail editable-text', 'field' => 'booking_note' ],
                            [ 'label' => 'Name', 'class' => 'guest-row', 'type' => 'detail editable-text', 'field' => 'manual_guest_name', 'display_fn' => function( $b ) { return $b->guest_name ?? ''; } ],
                            [ 'label' => 'Channel', 'class' => 'channel-row', 'type' => 'detail', 'field' => '', 'display_fn' => function( $b ) { return $b->channel ?? ''; } ],
                            [ 'label' => 'Occupancy', 'class' => 'occupancy-row', 'type' => 'detail editable-occupancy', 'field' => 'occupancy', 'display_fn' => function( $b ) { return $b->occupancy_str ?? ''; } ],
                            [ 'label' => 'Extras', 'class' => 'extras-row', 'type' => 'detail editable-text', 'field' => 'extras_formula', 'display_fn' => function( $b ) { return $b->extras_formula ?? ''; } ],
                            [ 'label' => 'Tarif', 'class' => 'tarif-row', 'type' => 'detail editable-decimal detail-tarif', 'field' => 'manual_tarif', 'display_fn' => function( $b ) { return isset( $b->tarif ) && '' !== $b->tarif && null !== $b->tarif ? number_format( (float) $b->tarif, 2, ',', ' ' ) . ' €' : ''; } ],
                            [ 'label' => 'Commission', 'class' => 'commission-row', 'type' => 'detail editable-decimal detail-commission', 'field' => 'manual_commission', 'display_fn' => function( $b ) { return isset( $b->commission ) && '' !== $b->commission && null !== $b->commission ? number_format( (float) $b->commission, 2, ',', ' ' ) . ' €' : ''; } ],
                        ];

                        foreach ( $rows as $row_index => $row ) :
                            $row_classes = [ $row['class'] ];
                            $row_classes[] = 0 === $row_index ? 'room-block-start' : 'room-block-detail';
                            if ( count( $rows ) - 1 === $row_index ) {
                                $row_classes[] = 'room-block-end';
                            }
                    ?>
                        <tr class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
                            <td class="label sticky-col room-label-cell <?php echo esc_attr( $row['type'] ); ?>" data-room-color="<?php echo esc_attr( $color ); ?>"><span class="room-label-text"><?php echo esc_html( $row['label'] ); ?></span></td>
                            <?php foreach ( $days as $day ) :
                                $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                                $entry = $matrix[ $room_id ][ $date_str ] ?? null;
                                $booking = $entry['booking'] ?? null;
                                $display_value = '';
                                if ( $booking && isset( $row['display_fn'] ) && is_callable( $row['display_fn'] ) ) {
                                    $display_value = $row['display_fn']( $booking );
                                }
                                $cell_classes = [ 'calendar-cell', $row['type'] ];
                                if ( ! empty( $booking ) ) {
                                    $cell_classes[] = 'has-booking';
                                }
                            ?>
                                <td class="<?php echo esc_attr( implode( ' ', $cell_classes ) ); ?>" data-room-color="<?php echo esc_attr( $color ); ?>">
                                    <?php if ( $booking && 'booking_note-row' === $row['class'] ) : ?>
                                        <input type="text" class="calendar-booking-input calendar-booking-note-input" data-field="booking_note" data-booking-id="<?php echo esc_attr( $booking->id ); ?>" data-room-id="<?php echo esc_attr( $booking->room_id ); ?>" data-reserved-room-id="<?php echo esc_attr( $booking->reserved_room_id ); ?>" value="<?php echo esc_attr( $booking->booking_note ?? '' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Booking note for room %1$s on %2$s', 'lgf-calendar-view' ), $room->title, $date_str ) ); ?>" />
                                    <?php elseif ( $booking && 'guest-row' === $row['class'] ) : ?>
                                        <input type="text" class="calendar-booking-input" data-field="manual_guest_name" data-booking-id="<?php echo esc_attr( $booking->id ); ?>" data-room-id="<?php echo esc_attr( $booking->room_id ); ?>" data-reserved-room-id="<?php echo esc_attr( $booking->reserved_room_id ); ?>" value="<?php echo esc_attr( $booking->guest_name ?? '' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Guest name for room %1$s on %2$s', 'lgf-calendar-view' ), $room->title, $date_str ) ); ?>" />
                                    <?php elseif ( $booking && 'occupancy-row' === $row['class'] ) : ?>
                                        <div class="calendar-occupancy-editor" data-booking-id="<?php echo esc_attr( $booking->id ); ?>" data-room-id="<?php echo esc_attr( $booking->room_id ); ?>" data-reserved-room-id="<?php echo esc_attr( $booking->reserved_room_id ); ?>">
                                            <input type="number" min="0" step="1" class="calendar-booking-input occupancy-part-input" data-field="manual_adults" data-booking-id="<?php echo esc_attr( $booking->id ); ?>" data-room-id="<?php echo esc_attr( $booking->room_id ); ?>" data-reserved-room-id="<?php echo esc_attr( $booking->reserved_room_id ); ?>" value="<?php echo esc_attr( (string) ( $booking->adults ?? '' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Adults for room %1$s on %2$s', 'lgf-calendar-view' ), $room->title, $date_str ) ); ?>" />
                                            <span class="occupancy-separator">A</span>
                                            <input type="number" min="0" step="1" class="calendar-booking-input occupancy-part-input" data-field="manual_children" data-booking-id="<?php echo esc_attr( $booking->id ); ?>" data-room-id="<?php echo esc_attr( $booking->room_id ); ?>" data-reserved-room-id="<?php echo esc_attr( $booking->reserved_room_id ); ?>" value="<?php echo esc_attr( (string) ( $booking->children ?? '' ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Children for room %1$s on %2$s', 'lgf-calendar-view' ), $room->title, $date_str ) ); ?>" />
                                            <span class="occupancy-separator">C</span>
                                        </div>
                                    <?php elseif ( $booking && 'extras-row' === $row['class'] ) : ?>
                                        <div class="calendar-extras-editor">
                                            <input type="text" class="calendar-booking-input calendar-extras-input" data-field="extras_formula" data-booking-id="<?php echo esc_attr( $booking->id ); ?>" data-room-id="<?php echo esc_attr( $booking->room_id ); ?>" data-reserved-room-id="<?php echo esc_attr( $booking->reserved_room_id ); ?>" value="<?php echo esc_attr( $booking->extras_formula ?? '' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Extras formula for room %1$s on %2$s', 'lgf-calendar-view' ), $room->title, $date_str ) ); ?>" />
                                            <span class="calendar-extras-total"><?php echo null !== $booking->extras_total && '' !== $booking->extras_total ? esc_html( number_format( (float) $booking->extras_total, 2, ',', ' ' ) . ' €' ) : ''; ?></span>
                                        </div>
                                    <?php elseif ( $booking && 'tarif-row' === $row['class'] ) : ?>
                                        <input type="text" class="calendar-booking-input calendar-money-input" data-field="manual_tarif" data-booking-id="<?php echo esc_attr( $booking->id ); ?>" data-room-id="<?php echo esc_attr( $booking->room_id ); ?>" data-reserved-room-id="<?php echo esc_attr( $booking->reserved_room_id ); ?>" value="<?php echo esc_attr( isset( $booking->tarif ) && '' !== $booking->tarif ? number_format( (float) $booking->tarif, 2, '.', '' ) : '' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Tarif for room %1$s on %2$s', 'lgf-calendar-view' ), $room->title, $date_str ) ); ?>" />
                                    <?php elseif ( $booking && 'commission-row' === $row['class'] ) : ?>
                                        <input type="text" class="calendar-booking-input calendar-money-input" data-field="manual_commission" data-booking-id="<?php echo esc_attr( $booking->id ); ?>" data-room-id="<?php echo esc_attr( $booking->room_id ); ?>" data-reserved-room-id="<?php echo esc_attr( $booking->reserved_room_id ); ?>" value="<?php echo esc_attr( isset( $booking->commission ) && '' !== $booking->commission ? number_format( (float) $booking->commission, 2, '.', '' ) : '' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Commission for room %1$s on %2$s', 'lgf-calendar-view' ), $room->title, $date_str ) ); ?>" />
                                    <?php else : ?>
                                        <?php echo esc_html( $display_value ); ?>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; endforeach; ?>
                <?php endif; ?>

                <?php
                $footer_rows = [
                    'Income Day' => 'income_day',
                    'Income Accumulated' => 'income_accumulated',
                    'Extras Used' => 'table_dhotes',
                    'Rooms' => 'rooms',
                    'TOURIST TAX Adults' => 'tourist_tax_adults',
                    'Children' => 'tourist_tax_children',
                    'Payment Booking.com' => 'booking_payment',
                    'Accumulated Booking.com' => 'booking_accumulated',
                ];
                foreach ( $footer_rows as $label => $key ) :
                ?>
                    <tr class="summary-row summary-<?php echo esc_attr( sanitize_title( $key ) ); ?>">
                        <td class="label sticky-col summary-label-cell"><?php echo esc_html( $label ); ?></td>
                        <?php foreach ( $days as $day ) :
                            $value = $summary[ $day ][ $key ] ?? '';
                            $display = $value;
                            if ( in_array( $key, [ 'income_day', 'income_accumulated', 'booking_payment', 'booking_accumulated' ], true ) ) {
                                $display = number_format( (float) $value, 2, ',', ' ' ) . ' €';
                            }
                        ?>
                            <td><?php echo esc_html( (string) $display ); ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
