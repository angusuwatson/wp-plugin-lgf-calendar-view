<?php
/* @var $calendar_data array */
$rooms = $calendar_data['rooms'];
$matrix = $calendar_data['matrix'];
$month = $calendar_data['month'];
$year = $calendar_data['year'];
$days_in_month = $calendar_data['days_in_month'];
$days = $calendar_data['days'];
$summary = lgf_calendar_view_build_daily_summary( $calendar_data );
$month_tabs = lgf_calendar_view_get_month_tabs( $month, $year );
?>
<div class="lgf-calendar-container">
    <div class="calendar-month-tabs" role="tablist" aria-label="Calendar months">
        <?php foreach ( $month_tabs as $tab ) : ?>
            <a
                class="calendar-month-tab<?php echo $tab['current'] ? ' is-current' : ''; ?>"
                href="<?php echo esc_url( add_query_arg( [ 'month' => $tab['month'], 'year' => $tab['year'] ] ) ); ?>"
                data-month="<?php echo esc_attr( $tab['month'] ); ?>"
                data-year="<?php echo esc_attr( $tab['year'] ); ?>"
            ><?php echo esc_html( $tab['label'] ); ?></a>
        <?php endforeach; ?>
    </div>

    <div class="calendar-nav">
        <?php
        $prev = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
        $prev->modify( '-1 month' );
        $next = new DateTime( sprintf( '%04d-%02d-01', $year, $month ) );
        $next->modify( '+1 month' );
        ?>
        <a class="button" href="<?php echo esc_url( add_query_arg( [ 'month' => $prev->format( 'n' ), 'year' => $prev->format( 'Y' ) ] ) ); ?>">&laquo; <?php esc_html_e( 'Previous', 'lgf-calendar-view' ); ?></a>
        <span class="current-month"><?php echo esc_html( date_i18n( 'F Y', mktime( 0, 0, 0, $month, 1, $year ) ) ); ?></span>
        <a class="button" href="<?php echo esc_url( add_query_arg( [ 'month' => $next->format( 'n' ), 'year' => $next->format( 'Y' ) ] ) ); ?>"><?php esc_html_e( 'Next', 'lgf-calendar-view' ); ?> &raquo;</a>
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
                    <?php foreach ( $days as $day ) :
                        $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                    ?>
                        <th><span class="calendar-weekday"><?php echo esc_html( date_i18n( 'l', strtotime( $date_str ) ) ); ?></span></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $rooms ) ) : ?>
                    <tr>
                        <td colspan="<?php echo esc_attr( 1 + $days_in_month ); ?>"><?php esc_html_e( 'No rooms found.', 'lgf-calendar-view' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $rooms as $index => $room ) :
                        $room_id = $room->id;
                        $color = $room->color ?? '#ccc';
                        $room_number = $index + 1;
                        $rows = [
                            [
                                'label' => $room_number . ' - ' . $room->title,
                                'class' => 'room-name-row',
                                'type'  => 'title',
                                'value_fn' => null,
                            ],
                            [
                                'label' => 'Name',
                                'class' => 'guest-row',
                                'type'  => 'detail',
                                'value_fn' => function( $b ) { return $b->guest_name ?? ''; },
                            ],
                            [
                                'label' => 'Telephone Number',
                                'class' => 'telephone-row',
                                'type'  => 'detail',
                                'value_fn' => function( $b ) { return $b->phone ?? ''; },
                            ],
                            [
                                'label' => 'Occupancy',
                                'class' => 'occupancy-row',
                                'type'  => 'detail',
                                'value_fn' => function( $b ) { return $b->occupancy_str ?? ''; },
                            ],
                            [
                                'label' => "Table d'hôte",
                                'class' => 'dinner-row',
                                'type'  => 'detail',
                                'value_fn' => function( $b ) { return ! empty( $b->dinner ) ? $b->dinner : ''; },
                            ],
                            [
                                'label' => 'Tarif',
                                'class' => 'tarif-row',
                                'type'  => 'detail detail-tarif',
                                'value_fn' => function( $b ) {
                                    if ( isset( $b->tarif ) && '' !== $b->tarif && null !== $b->tarif ) {
                                        return number_format( (float) $b->tarif, 2, ',', ' ' ) . ' €';
                                    }
                                    return '';
                                },
                            ],
                        ];

                        foreach ( $rows as $row_index => $row ) :
                            $row_classes = [ $row['class'] ];
                            $row_classes[] = 0 === $row_index ? 'room-block-start' : 'room-block-detail';
                            if ( count( $rows ) - 1 === $row_index ) {
                                $row_classes[] = 'room-block-end';
                            }
                    ?>
                        <tr class="<?php echo esc_attr( implode( ' ', $row_classes ) ); ?>">
                            <td class="label sticky-col room-label-cell <?php echo esc_attr( $row['type'] ); ?>" data-room-color="<?php echo esc_attr( $color ); ?>">
                                <span class="room-label-text"><?php echo esc_html( $row['label'] ); ?></span>
                            </td>
                            <?php foreach ( $days as $day ) :
                                $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                                $entry = $matrix[ $room_id ][ $date_str ] ?? null;
                                $booking = $entry['booking'] ?? null;
                                $value = '';
                                if ( $booking && isset( $row['value_fn'] ) && is_callable( $row['value_fn'] ) ) {
                                    $value = $row['value_fn']( $booking );
                                }
                                $cell_classes = [ 'calendar-cell', $row['type'] ];
                                if ( ! empty( $booking ) ) {
                                    $cell_classes[] = 'has-booking';
                                }
                            ?>
                                <td class="<?php echo esc_attr( implode( ' ', $cell_classes ) ); ?>" data-room-color="<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $value ); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php
                $footer_rows = [
                    'Income Day' => 'income_day',
                    'Income Accumulated' => 'income_accumulated',
                    "Table d'hôtes" => 'table_dhotes',
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
