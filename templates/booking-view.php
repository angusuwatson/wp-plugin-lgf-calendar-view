<?php
/* @var $calendar_data array */
$rooms = $calendar_data['rooms'];
$matrix = $calendar_data['matrix'];
$month = $calendar_data['month'];
$year = $calendar_data['year'];
$days_in_month = $calendar_data['days_in_month'];
$days = $calendar_data['days'];

// Nav URLs
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) { $next_month = 1; $next_year++; }
$today_month = date('n');
$today_year = date('Y');
?>
<div class="lgf-calendar-container">
    <div class="calendar-nav">
        <a class="button" href="<?php echo esc_url( add_query_arg( ['month' => $prev_month, 'year' => $prev_year] ) ); ?>">&laquo; <?php esc_html_e( 'Previous', 'lgf-calendar-view' ); ?></a>
        <span class="current-month"><?php echo esc_html( date_i18n( 'F Y', mktime(0,0,0,$month,1,$year) ) ); ?></span>
        <a class="button" href="<?php echo esc_url( add_query_arg( ['month' => $next_month, 'year' => $next_year] ) ); ?>"><?php esc_html_e( 'Next', 'lgf-calendar-view' ); ?> &raquo;</a>
        <?php if ( $month != $today_month || $year != $today_year ): ?>
            <a class="button" href="<?php echo esc_url( add_query_arg( ['month' => $today_month, 'year' => $today_year] ) ); ?>"><?php esc_html_e( 'Today', 'lgf-calendar-view' ); ?></a>
        <?php endif; ?>
    </div>

    <div class="lgf-calendar-view">
        <table class="wp-list-table widefat fixed striped calendar-grid">
            <thead>
                <tr class="header-row">
                    <th class="label" style="position: sticky; left: 0; background:#eaeaea; border-right: 1px solid black;"></th>
                    <?php foreach ( $days as $day ) : ?>
                        <th><?php echo esc_html( $day ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $rooms ) ) : ?>
                    <tr>
                        <td colspan="<?php echo 1 + $days_in_month; ?>"><?php esc_html_e( 'No rooms found.', 'lgf-calendar-view' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $rooms as $room ) :
                        $room_id = $room->id;
                        $color = $room->color ?? '#ccc';
                        $rows = [
                            [
                                'label' => $room->title,
                                'class' => 'room-name-row',
                                'label_style' => 'background:#404040; color:#fff; border-right: 1px solid black;',
                                'cell_style' => 'background:#404040;',
                                'value' => null
                            ],
                            [
                                'label' => 'Guest',
                                'class' => 'guest-row',
                                'label_style' => "background:$color; border-right: 1px solid black;",
                                'cell_style' => "background:$color;",
                                'value_fn' => function($b) { return $b->guest_name ?? ''; }
                            ],
                            [
                                'label' => 'Platform',
                                'class' => 'platform-row',
                                'label_style' => "background:$color; border-right: 1px solid black;",
                                'cell_style' => "background:$color;",
                                'value_fn' => function($b) { return $b->platform_label ?? ''; }
                            ],
                            [
                                'label' => 'Occupancy',
                                'class' => 'occupancy-row',
                                'label_style' => "background:$color; border-right: 1px solid black;",
                                'cell_style' => "background:$color;",
                                'value_fn' => function($b) { return $b->occupancy_str ?? ''; }
                            ],
                            [
                                'label' => 'Dinner',
                                'class' => 'dinner-row',
                                'label_style' => "background:$color; border-right: 1px solid black;",
                                'cell_style' => "background:$color;",
                                'value_fn' => function($b) { return $b->dinner ?? ''; }
                            ],
                            [
                                'label' => 'Tarif',
                                'class' => 'tarif-row',
                                'label_style' => "background:$color; border-right: 1px solid black;",
                                'cell_style' => "background:$color;",
                                'value_fn' => function($b) { return $b->tarif !== '' ? number_format($b->tarif, 2) : ''; }
                            ],
                            [
                                'label' => 'Commission',
                                'class' => 'commission-row',
                                'label_style' => 'background:#fff; border-right: 1px solid black;',
                                'cell_style' => 'background:#fff;',
                                'value_fn' => function($b) { return $b->commission !== '' ? number_format($b->commission, 2) : ''; }
                            ],
                        ];
                        foreach ($rows as $row):
                    ?>
                        <tr class="<?php echo $row['class']; ?>">
                            <td class="label" style="position: sticky; left: 0; <?php echo $row['label_style']; ?>"><?php echo esc_html($row['label']); ?></td>
                            <?php foreach ( $days as $day ) :
                                $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                                $entry = $matrix[ $room_id ][ $date_str ] ?? null;
                                $booking = $entry && isset( $entry['booking'] ) && $entry['booking'] ? $entry['booking'] : null;
                                $value = '';
                                if ($booking && isset($row['value_fn'])) {
                                    $value = $row['value_fn']($booking);
                                }
                            ?>
                                <td style="<?php echo esc_attr($row['cell_style']); ?>">
                                    <?php echo esc_html( $value ); ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php
                        endforeach; // rows
                        endforeach; // rooms
                    ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
if ( current_user_can( 'manage_options' ) ) {
    echo '<h3>DEBUG: Matrix sample</h3>';
    echo '<pre>';
    print_r( array_slice( $matrix, 0, 2, true ) );
    echo '</pre>';
}
?>