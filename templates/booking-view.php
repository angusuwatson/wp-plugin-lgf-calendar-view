<?php
/* @var $calendar_data array */
$rooms                = $calendar_data['rooms'];
$matrix               = $calendar_data['matrix'];
$month                = $calendar_data['month'];
$year                 = $calendar_data['year'];
$days_in_month        = $calendar_data['days_in_month'];
$days                 = $calendar_data['days'];

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
                <tr>
                    <th class="room-header"><?php esc_html_e( 'Room', 'lgf-calendar-view' ); ?></th>
                    <?php foreach ( $days as $day ) :
                        $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                        $day_of_week = date_i18n( 'D', strtotime( $date_str ) );
                        $header_class = in_array( $day_of_week, ['Sat','Sun'] ) ? 'weekend' : '';
                    ?>
                        <th class="<?php echo $header_class; ?>">
                            <?php echo esc_html( $day . ' ' . $day_of_week ); ?>
                        </th>
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
                        $row_style = 'style="background-color:' . esc_attr( $room->color ?? '#ccc' ) . '"';
                    ?>
                        <tr <?php echo $row_style; ?>>
                            <td class="room-name" style="background-color: #404040; color: #fff;"><?php echo esc_html( $room->title ); ?></td>
                            <?php foreach ( $days as $day ) :
                                $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                                $entry = $matrix[ $room_id ][ $date_str ] ?? null;
                                $booking = $entry && isset( $entry['booking'] ) && $entry['booking'] ? $entry['booking'] : null;
                            ?>
                                <td class="day-cell">
                                    <?php if ( $booking ) : ?>
                                        <div class="line guest"><?php echo esc_html( $booking->guest_name ?? '' ); ?></div>
                                        <div class="line platform"><?php echo esc_html( $booking->platform_label ?? '' ); ?></div>
                                        <div class="line occupancy"><?php echo esc_html( $booking->occupancy_str ?? '' ); ?></div>
                                        <div class="line dinner"><?php echo esc_html( $booking->dinner ?? '' ); ?></div>
                                        <div class="line tarif"><?php echo esc_html( $booking->tarif ?? '' ); ?></div>
                                        <div class="line commission"><?php echo esc_html( $booking->commission ?? '' ); ?></div>
                                    <?php else : ?>
                                        <div class="line free">&nbsp;</div>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
// DEBUG: dump matrix sample for admin
if ( current_user_can( 'manage_options' ) ) {
    echo '<h3>DEBUG: Matrix sample</h3>';
    echo '<pre>';
    print_r( array_slice( $matrix, 0, 2, true ) );
    echo '</pre>';
}
?>