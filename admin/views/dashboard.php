<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpguardian-wrap">
	<h1><?php esc_html_e( 'WP Guard Dashboard', 'wp-guard' ); ?></h1>

	<?php if ( ! empty( $_GET['wpguardian_notice'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['wpguardian_notice'] ) ) ); ?></p></div>
	<?php endif; ?>

	<div class="wpguardian-grid">
		<div class="wpguardian-card">
			<h2><?php esc_html_e( 'Quick Actions', 'wp-guard' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( 'wpguardian_admin_action', 'wpguardian_nonce' ); ?>
				<input type="hidden" name="wpguardian_action" value="create_full_backup" />
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Full Backup', 'wp-guard' ); ?></button>
			</form>
		</div>

		<div class="wpguardian-card">
			<h2><?php esc_html_e( 'Backup Status', 'wp-guard' ); ?></h2>
			<p><?php echo esc_html( isset( $status['last_backup_status'] ) ? $status['last_backup_status'] : 'never' ); ?></p>
			<p><?php echo esc_html( sprintf( __( 'Stored backups: %d', 'wp-guard' ), count( $backups ) ) ); ?></p>
		</div>
	</div>

	<div class="wpguardian-card wpguardian-table">
		<h2><?php esc_html_e( 'Latest Activity', 'wp-guard' ); ?></h2>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Time', 'wp-guard' ); ?></th>
					<th><?php esc_html_e( 'Action', 'wp-guard' ); ?></th>
					<th><?php esc_html_e( 'Type', 'wp-guard' ); ?></th>
					<th><?php esc_html_e( 'Object ID', 'wp-guard' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $logs ) ) : ?>
					<tr><td colspan="4"><?php esc_html_e( 'No activity found.', 'wp-guard' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $logs as $log ) : ?>
						<tr>
							<td><?php echo esc_html( $log['created_at'] ); ?></td>
							<td><?php echo esc_html( $log['action'] ); ?></td>
							<td><?php echo esc_html( $log['object_type'] ); ?></td>
							<td><?php echo esc_html( $log['object_id'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
