<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpguard-wrap">
	<h1><?php esc_html_e( 'WP Guard Settings', 'wp-guard' ); ?></h1>
	<?php
	$notice_key   = isset( $_GET['wpguard_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['wpguard_notice'] ) ) : '';
	$notice_class = '';
	$notice_text  = '';
	if ( 'license_check_ok' === $notice_key ) {
		$notice_class = 'notice-success';
		$notice_text  = __( 'License check successful. Pro status refreshed.', 'wp-guard' );
	} elseif ( 'license_check_failed' === $notice_key ) {
		$notice_class = 'notice-warning';
		$notice_text  = __( 'License check did not pass. The key may be invalid or the server may be unavailable.', 'wp-guard' );
	} elseif ( 'network_lock_enabled' === $notice_key ) {
		$notice_class = 'notice-error';
		$notice_text  = __( 'Settings are locked by network super admin policy.', 'wp-guard' );
	} elseif ( 'settings_saved' === $notice_key ) {
		$notice_class = 'notice-success';
		$notice_text  = __( 'Settings saved successfully.', 'wp-guard' );
	}
	if ( '' !== $notice_text ) :
		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible"><p><?php echo esc_html( $notice_text ); ?></p></div>
	<?php endif; ?>

	<form method="post" class="wpguard-settings-form">
		<?php wp_nonce_field( 'wpguard_admin_action', 'wpguard_nonce' ); ?>
		<input type="hidden" name="wpguard_action" value="save_settings" />

		<div class="wpguard-card">
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Retention (days)', 'wp-guard' ); ?></th>
				<td><input type="number" min="7" name="retention_days" value="<?php echo esc_attr( isset( $settings['retention_days'] ) ? absint( $settings['retention_days'] ) : 30 ); ?>" /></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Client Safe Mode', 'wp-guard' ); ?></th>
				<td><label><input type="checkbox" name="safe_mode" value="1" <?php checked( ! empty( $settings['safe_mode'] ) ); ?> /> <?php esc_html_e( 'Restrict dangerous admin actions', 'wp-guard' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Include File Metadata Backup', 'wp-guard' ); ?></th>
				<td><label><input type="checkbox" name="allow_file_backup" value="1" <?php checked( ! empty( $settings['allow_file_backup'] ) ); ?> /> <?php esc_html_e( 'Store active theme and plugin state in full backups', 'wp-guard' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Admin Theme', 'wp-guard' ); ?></th>
				<td>
					<select name="ui_theme">
						<option value="auto" <?php selected( isset( $settings['ui_theme'] ) ? $settings['ui_theme'] : 'auto', 'auto' ); ?>><?php esc_html_e( 'Auto (System)', 'wp-guard' ); ?></option>
						<option value="light" <?php selected( isset( $settings['ui_theme'] ) ? $settings['ui_theme'] : 'auto', 'light' ); ?>><?php esc_html_e( 'Light', 'wp-guard' ); ?></option>
						<option value="dark" <?php selected( isset( $settings['ui_theme'] ) ? $settings['ui_theme'] : 'auto', 'dark' ); ?>><?php esc_html_e( 'Dark', 'wp-guard' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Plan', 'wp-guard' ); ?></th>
				<td>
					<?php if ( wpguard_License::is_pro_active() ) : ?>
						<span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Pro active', 'wp-guard' ); ?>
					<?php else : ?>
						<?php esc_html_e( 'Free version active. Pro hooks are ready.', 'wp-guard' ); ?>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'License Key', 'wp-guard' ); ?></th>
				<td>
					<input type="text" name="license_key" value="<?php echo esc_attr( isset( $settings['license_key'] ) ? $settings['license_key'] : '' ); ?>" class="regular-text" placeholder="WPG-XXXX-XXXX" />
					<p class="description">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: status, 2: message */
								__( 'License status: %1$s. %2$s', 'wp-guard' ),
								isset( $license['status'] ) ? $license['status'] : 'unknown',
								isset( $license['message'] ) ? $license['message'] : ''
							)
						);
						?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Failure Alerts', 'wp-guard' ); ?></th>
				<td>
					<label><input type="checkbox" name="alerts_enabled" value="1" <?php checked( ! empty( $settings['alerts_enabled'] ) ); ?> /> <?php esc_html_e( 'Email notifications on backup/restore failures', 'wp-guard' ); ?></label>
					<p><input type="email" name="alert_email" value="<?php echo esc_attr( isset( $settings['alert_email'] ) ? $settings['alert_email'] : get_option( 'admin_email' ) ); ?>" class="regular-text" /></p>
				</td>
			</tr>
		</table>
		</div>

		<?php submit_button( __( 'Save Settings', 'wp-guard' ) ); ?>
	</form>

	<div class="wpguard-card wpguard-license-health">
		<h2><?php esc_html_e( 'License Health', 'wp-guard' ); ?></h2>
		<p><strong><?php esc_html_e( 'Current Status:', 'wp-guard' ); ?></strong> <?php echo esc_html( isset( $license_health['status'] ) ? $license_health['status'] : 'unknown' ); ?></p>
		<p><strong><?php esc_html_e( 'Grace Mode:', 'wp-guard' ); ?></strong> <?php echo ! empty( $license_health['is_grace_mode'] ) ? esc_html__( 'Enabled', 'wp-guard' ) : esc_html__( 'Disabled', 'wp-guard' ); ?></p>
		<p><strong><?php esc_html_e( 'Last Checked:', 'wp-guard' ); ?></strong> <?php echo ! empty( $license_health['last_checked_at'] ) ? esc_html( gmdate( 'Y-m-d H:i:s', (int) $license_health['last_checked_at'] ) . ' UTC' ) : esc_html__( 'Never', 'wp-guard' ); ?></p>
		<p><strong><?php esc_html_e( 'Last Successful Verification:', 'wp-guard' ); ?></strong> <?php echo ! empty( $license_health['last_success_at'] ) ? esc_html( gmdate( 'Y-m-d H:i:s', (int) $license_health['last_success_at'] ) . ' UTC' ) : esc_html__( 'Never', 'wp-guard' ); ?></p>
		<p><strong><?php esc_html_e( 'Next Retry:', 'wp-guard' ); ?></strong> <?php echo ! empty( $license_health['next_retry_at'] ) ? esc_html( gmdate( 'Y-m-d H:i:s', (int) $license_health['next_retry_at'] ) . ' UTC' ) : esc_html__( 'Not scheduled', 'wp-guard' ); ?></p>
		<p><strong><?php esc_html_e( 'Retry Count:', 'wp-guard' ); ?></strong> <?php echo esc_html( (string) ( isset( $license_health['retry_count'] ) ? (int) $license_health['retry_count'] : 0 ) ); ?></p>
		<form method="post">
			<?php wp_nonce_field( 'wpguard_admin_action', 'wpguard_nonce' ); ?>
			<input type="hidden" name="wpguard_action" value="retry_license_check" />
			<button class="button button-secondary" type="submit"><?php esc_html_e( 'Retry License Check Now', 'wp-guard' ); ?></button>
		</form>
	</div>
</div>
