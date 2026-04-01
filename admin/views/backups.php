<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpguardian-wrap">
	<h1><?php esc_html_e( 'WP Guardian Backups', 'wp-guardian' ); ?></h1>
	<div class="wpguardian-toolbar">
		<form method="post" class="wpguardian-inline-form">
			<?php wp_nonce_field( 'wpguardian_admin_action', 'wpguardian_nonce' ); ?>
			<input type="hidden" name="wpguardian_action" value="resume_restore" />
			<button class="button" type="submit" <?php disabled( ! WPGuardian_License::is_pro_active() ); ?>><?php esc_html_e( 'Resume Last Restore', 'wp-guardian' ); ?></button>
		</form>
		<?php if ( ! WPGuardian_License::is_pro_active() ) : ?>
			<span class="wpguardian-description"><?php esc_html_e( 'Pro feature: activate a valid license to enable resume/download actions.', 'wp-guardian' ); ?></span>
		<?php endif; ?>
	</div>
	<div class="wpguardian-table">
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'ID', 'wp-guardian' ); ?></th>
				<th><?php esc_html_e( 'Type', 'wp-guardian' ); ?></th>
				<th><?php esc_html_e( 'File', 'wp-guardian' ); ?></th>
				<th><?php esc_html_e( 'Created', 'wp-guardian' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'wp-guardian' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $backups ) ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No backups yet.', 'wp-guardian' ); ?></td></tr>
			<?php else : ?>
				<?php foreach ( $backups as $backup ) : ?>
					<tr>
						<td><?php echo esc_html( $backup['id'] ); ?></td>
						<td><?php echo esc_html( $backup['backup_type'] ); ?></td>
						<td><?php echo esc_html( $backup['file_path'] ); ?></td>
						<td><?php echo esc_html( $backup['created_at'] ); ?></td>
						<td>
							<form method="post" class="wpguardian-inline-form">
								<?php wp_nonce_field( 'wpguardian_admin_action', 'wpguardian_nonce' ); ?>
								<input type="hidden" name="wpguardian_action" value="restore_backup" />
								<input type="hidden" name="backup_id" value="<?php echo esc_attr( $backup['id'] ); ?>" />
								<button class="button button-secondary" type="submit"><?php esc_html_e( 'Restore', 'wp-guardian' ); ?></button>
							</form>
							<form method="post" class="wpguardian-inline-form">
								<?php wp_nonce_field( 'wpguardian_admin_action', 'wpguardian_nonce' ); ?>
								<input type="hidden" name="wpguardian_action" value="download_backup" />
								<input type="hidden" name="backup_id" value="<?php echo esc_attr( $backup['id'] ); ?>" />
								<button class="button" type="submit" <?php disabled( ! WPGuardian_License::is_pro_active() ); ?>><?php esc_html_e( 'Download', 'wp-guardian' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>
	</div>
</div>
