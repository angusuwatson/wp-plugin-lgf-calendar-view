<?php
/* @var $calendar_data array */
$rooms                = $calendar_data['rooms'];
$bookings_by_room_date = $calendar_data['bookings_by_room_date'];
$month                = $calendar_data['month'];
$year                 = $calendar_data['year'];
$days_in_month        = $calendar_data['days_in_month'];
$days                 = $calendar_data['days'];
?>

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
                            $booking = isset( $bookings_by_room_date[ $room_id ][ $date_str ] ) ? $bookings_by_room_date[ $room_id ][ $date_str ] : null;
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