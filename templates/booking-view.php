<?php
/* @var $calendar_data array */
$rooms                = $calendar_data['rooms'];
$matrix               = $calendar_data['matrix'];
$month                = $calendar_data['month'];
$year                 = $calendar_data['year'];
$days_in_month        = $calendar_data['days_in_month'];
$days                 = $calendar_data['days'];

// Helper: get entry for room and date
$get_entry = function( $room_id, $date_str ) use ( $matrix ) {
    return $matrix[ $room_id ][ $date_str ] ?? null;
};
?>

<div class="lgf-calendar-view">
    <table class="wp-list-table widefat fixed striped calendar-grid">
        <thead>
            <tr>
                <th class="room-header" rowspan="2"><?php esc_html_e( 'Room', 'lgf-calendar-view' ); ?></th>
                <?php foreach ( $days as $day ) :
                    $date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
                    $day_of_week = date_i18n( 'D', strtotime( $date_str ) );
                    $header_class = in_array( $day_of_week, ['Sat','Sun'] ) ? 'weekend' : '';
                ?>
                    <th colspan="2" class="<?php echo $header_class; ?>">
                        <?php echo esc_html( $day . ' ' . $day_of_week ); ?>
                    </th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach ( $days as $day ) : ?>
                    <th class="half-header-out"><?php esc_html_e( 'Out', 'lgf-calendar-view' ); ?></th>
                    <th class="half-header-in"><?php esc_html_e( 'In', 'lgf-calendar-view' ); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $rooms ) ) : ?>
                <tr>
                    <td colspan="<?php echo 1 + $days_in_month * 2; ?>"><?php esc_html_e( 'No rooms found.', 'lgf-calendar-view' ); ?></td>
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
                            $has_data = $entry && isset( $entry['booking'] ) && $entry['booking'];
                            $is_locked = $entry && $entry['is_locked'];
                            $is_checkout = $entry && $entry['is_checkout'];
                            $is_checkin = $entry && $entry['is_checkin'];
                        ?>
                            <td class="half half-out
                                <?php
                                if ( $is_checkout ) echo 'checkout';
                                elseif ( $is_locked ) echo 'occupied';
                                else echo 'free';
                                echo $has_data ? ' has-booking' : '';
                                ?>">
                                <?php if ( $is_checkout && $has_data ) : ?>
                                    <div class="status-badge status-<?php echo esc_attr( $entry['booking']->status ); ?>">
                                        <?php echo esc_html( $entry['booking']->status ); ?>
                                    </div>
                                    <?php if ( ! empty( $entry['booking']->guest_name ) ) : ?>
                                        <div class="guest-name"><?php echo esc_html( $entry['booking']->guest_name ); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="half half-in
                                <?php
                                if ( $is_checkin ) echo 'checkin';
                                elseif ( $is_locked ) echo 'occupied';
                                else echo 'free';
                                echo $has_data ? ' has-booking' : '';
                                ?>">
                                <?php if ( $is_checkin && $has_data ) : ?>
                                    <div class="status-badge status-<?php echo esc_attr( $entry['booking']->status ); ?>">
                                        <?php echo esc_html( $entry['booking']->status ); ?>
                                    </div>
                                    <?php if ( ! empty( $entry['booking']->guest_name ) ) : ?>
                                        <div class="guest-name"><?php echo esc_html( $entry['booking']->guest_name ); ?></div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>