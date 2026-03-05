<div class="lgf-calendar-view">
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Date', 'lgf-calendar-view' ); ?></th>
                <th><?php esc_html_e( 'Room', 'lgf-calendar-view' ); ?></th>
                <th><?php esc_html_e( 'Status', 'lgf-calendar-view' ); ?></th>
                <th><?php esc_html_e( 'Guest', 'lgf-calendar-view' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $bookings ) ) : ?>
                <tr>
                    <td colspan="4"><?php esc_html_e( 'No bookings found.', 'lgf-calendar-view' ); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $bookings as $booking ) : ?>
                    <tr>
                        <td><?php echo esc_html( $booking->check_in_date ?? '' ); ?> - <?php echo esc_html( $booking->check_out_date ?? '' ); ?></td>
                        <td><?php echo esc_html( $booking->room_name ?? '' ); ?></td>
                        <td><?php echo esc_html( $booking->status ?? '' ); ?></td>
                        <td><?php echo esc_html( $booking->guest_name ?? '' ); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
