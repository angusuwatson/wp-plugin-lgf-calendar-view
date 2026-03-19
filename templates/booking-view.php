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
                <tr class="header-row">
                    <th class="label sticky-col"></th>
                    <?php foreach ( $days as $day ) : ?>
                        <th><?php echo esc_html( date_i18n( 'F', mktime( 0, 0, 0, $month, $day, $year ) ) ); ?><br><strong><?php echo esc_html( $day ); ?></strong></th>
                    <?php endforeach; ?>
                </tr>
                <tr class="dow-row">
                    <th class="label sticky-col"></th>
                    <?php foreach ( $days as $day ) :
                        $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                    ?>
                        <th><?php echo esc_html( date_i18n( 'l', strtotime( $date_str ) ) ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $rooms ) ) : ?>
                    <tr>
                        <td colspan="<?php echo 1 + $days_in_month; ?>"><?php esc_html_e( 'No rooms found.', 'lgf-calendar-view' ); ?></td>
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
                                'label_style' => 'background:#404040; color:#fff;',
                                'cell_style' => 'background:#404040;',
                            ],
                            [
                                'label' => 'Name',
                                'class' => 'guest-row',
                                'label_style' => "background:$color;",
                                'cell_style' => "background:$color;",
                                'value_fn' => function( $b ) { return $b->guest_name ?? ''; },
                            ],
                            [
                                'label' => 'Telephone Number',
                                'class' => 'platform-row',
                                'label_style' => "background:$color;",
                                'cell_style' => "background:$color;",
                                'value_fn' => function( $b ) { return ''; },
                            ],
                            [
                                'label' => 'Occupancy',
                                'class' => 'occupancy-row',
                                'label_style' => "background:$color;",
                                'cell_style' => "background:$color;",
                                'value_fn' => function( $b ) { return $b->occupancy_str ?? ''; },
                            ],
                            [
                                'label' => "Table d'hôte",
                                'class' => 'dinner-row',
                                'label_style' => "background:$color;",
                                'cell_style' => "background:$color;",
                                'value_fn' => function( $b ) { return ! empty( $b->dinner ) ? $b->dinner : ''; },
                            ],
                            [
                                'label' => 'Tarif',
                                'class' => 'tarif-row',
                                'label_style' => "background:$color; text-align:right;",
                                'cell_style' => "background:$color; text-align:right;",
                                'value_fn' => function( $b ) {
                                    if ( $b->tarif !== '' && $b->tarif !== null ) {
                                        return number_format( (float) $b->tarif, 2, ',', ' ' ) . ' €';
                                    }
                                    return '';
                                },
                            ],
                        ];

                        foreach ( $rows as $row ) :
                    ?>
                        <tr class="<?php echo esc_attr( $row['class'] ); ?>">
                            <td class="label sticky-col" style="<?php echo esc_attr( $row['label_style'] ); ?>"><?php echo esc_html( $row['label'] ); ?></td>
                            <?php foreach ( $days as $day ) :
                                $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                                $entry = $matrix[ $room_id ][ $date_str ] ?? null;
                                $booking = $entry['booking'] ?? null;
                                $value = '';
                                if ( $booking && isset( $row['value_fn'] ) ) {
                                    $value = $row['value_fn']( $booking );
                                }
                            ?>
                                <td style="<?php echo esc_attr( $row['cell_style'] ); ?>"><?php echo esc_html( $value ); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="room-gap-row">
                        <td class="sticky-col gap-label"></td>
                        <?php foreach ( $days as $day ) : ?>
                            <td></td>
                        <?php endforeach; ?>
                    </tr>
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
                        <td class="label sticky-col"><?php echo esc_html( $label ); ?></td>
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
