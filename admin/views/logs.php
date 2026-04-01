<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpguardian-wrap">
	<h1><?php esc_html_e( 'WP Guardian Activity Logs', 'wp-guardian' ); ?></h1>

	<form method="get" class="wpguardian-filter-row">
		<input type="hidden" name="page" value="wpguardian-logs" />
		<input type="text" name="action_filter" placeholder="<?php esc_attr_e( 'Action', 'wp-guardian' ); ?>" value="<?php echo isset( $_GET['action_filter'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['action_filter'] ) ) ) : ''; ?>" />
		<input type="number" name="user_filter" placeholder="<?php esc_attr_e( 'User ID', 'wp-guardian' ); ?>" value="<?php echo isset( $_GET['user_filter'] ) ? esc_attr( absint( $_GET['user_filter'] ) ) : ''; ?>" />
		<input type="date" name="date_from" value="<?php echo isset( $_GET['date_from'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) ) : ''; ?>" />
		<input type="date" name="date_to" value="<?php echo isset( $_GET['date_to'] ) ? esc_attr( sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) ) : ''; ?>" />
		<button class="button"><?php esc_html_e( 'Filter', 'wp-guardian' ); ?></button>
	</form>

	<div class="wpguardian-table">
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'wp-guardian' ); ?></th>
				<th><?php esc_html_e( 'User ID', 'wp-guardian' ); ?></th>
				<th><?php esc_html_e( 'Action', 'wp-guardian' ); ?></th>
				<th><?php esc_html_e( 'Object Type', 'wp-guardian' ); ?></th>
				<th><?php esc_html_e( 'Object ID', 'wp-guardian' ); ?></th>
				<th><?php esc_html_e( 'IP', 'wp-guardian' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr><td colspan="6"><?php esc_html_e( 'No logs found.', 'wp-guardian' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( $log['created_at'] ); ?></td>
						<td><?php echo esc_html( $log['user_id'] ); ?></td>
						<td><?php echo esc_html( $log['action'] ); ?></td>
						<td><?php echo esc_html( $log['object_type'] ); ?></td>
						<td><?php echo esc_html( $log['object_id'] ); ?></td>
						<td><?php echo esc_html( $log['ip_address'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>
</div>
