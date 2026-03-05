<?php
/* @var $calendar_data array */
$rooms                = $calendar_data['rooms'];
$matrix               = $calendar_data['matrix'];
$month                = $calendar_data['month'];
$year                 = $calendar_data['year'];
$days_in_month        = $calendar_data['days_in_month'];
$days                 = $calendar_data['days'];

// Helper to get entry
$get_entry = function( $room_id, $date_str ) use ( $matrix ) {
    return $matrix[ $room_id ][ $date_str ] ?? null;
};

// Navigation URLs (simple full-page links, no AJAX)
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
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th class="room-header"><?php esc_html_e( 'Room', 'lgf-calendar-view' ); ?></th>
                    <?php foreach ( $days as $day ) : 
                        $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                        $day_of_week = date_i18n( 'D', strtotime( $date_str ) );
                    ?>
                        <th><?php echo esc_html( $day . ' ' . $day_of_week ); ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $rooms ) ) : ?>
                    <tr>
                        <td colspan="<?php echo $days_in_month + 1; ?>"><?php esc_html_e( 'No rooms found.', 'lgf-calendar-view' ); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ( $rooms as $room ) : 
                        $room_id = $room->id;
                    ?>
                        <tr>
                            <td class="room-name"><?php echo esc_html( $room->title ); ?></td>
                            <?php foreach ( $days as $day ) : 
                                $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                                $entry = $get_entry( $room_id, $date_str );
                                $booking = $entry && isset( $entry['booking'] ) ? $entry['booking'] : null;
                                $cell_class = $booking ? 'has-booking status-' . esc_attr( $booking->status ) : 'available';
                            ?>
                                <td class="<?php echo $cell_class; ?>">
                                    <?php if ( $booking ) : ?>
                                        <div class="booking-status"><?php echo esc_html( $booking->status ); ?></div>
                                        <?php if ( ! empty( $booking->guest_name ) ) : ?>
                                            <div class="guest-name"><?php echo esc_html( $booking->guest_name ); ?></div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        &nbsp;
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