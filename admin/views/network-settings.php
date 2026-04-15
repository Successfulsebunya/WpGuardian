<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpguardian-wrap">
	<h1><?php esc_html_e( 'WP Guard Network Settings', 'wp-guard' ); ?></h1>
	<?php
	$notice_key = isset( $_GET['wpguardian_notice'] ) ? sanitize_text_field( wp_unslash( $_GET['wpguardian_notice'] ) ) : '';
	if ( 'network_settings_saved' === $notice_key ) :
		?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Network settings saved.', 'wp-guard' ); ?></p></div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'wpguardian_admin_action', 'wpguardian_nonce' ); ?>
		<input type="hidden" name="wpguardian_action" value="save_network_settings" />
		<div class="wpguardian-card">
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Force Safe Mode', 'wp-guard' ); ?></th>
				<td><label><input type="checkbox" name="force_safe_mode" value="1" <?php checked( ! empty( $network_settings['force_safe_mode'] ) ); ?> /> <?php esc_html_e( 'Enable safe mode globally for all sites', 'wp-guard' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Lock Site Settings', 'wp-guard' ); ?></th>
				<td><label><input type="checkbox" name="lock_site_settings" value="1" <?php checked( ! empty( $network_settings['lock_site_settings'] ) ); ?> /> <?php esc_html_e( 'Prevent site admins from changing plugin settings', 'wp-guard' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Force License Override', 'wp-guard' ); ?></th>
				<td><label><input type="checkbox" name="force_license_override" value="1" <?php checked( ! empty( $network_settings['force_license_override'] ) ); ?> /> <?php esc_html_e( 'Use one network-wide license key for all sites', 'wp-guard' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Override License Key', 'wp-guard' ); ?></th>
				<td><input type="text" class="regular-text" name="override_license_key" value="<?php echo esc_attr( isset( $network_settings['override_license_key'] ) ? $network_settings['override_license_key'] : '' ); ?>" /></td>
			</tr>
		</table>
		</div>
		<?php submit_button( __( 'Save Network Settings', 'wp-guard' ) ); ?>
	</form>
</div>
