<?php
/**
 * Security and safe-mode controls.
 *
 * @package WPGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGuardian_Security {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'map_meta_cap', array( __CLASS__, 'restrict_admin_capabilities' ), 10, 4 );
		add_filter( 'file_mod_allowed', array( __CLASS__, 'restrict_file_modifications' ), 10, 2 );
		add_action( 'shutdown', array( __CLASS__, 'detect_fatal_errors' ) );
		add_action( 'admin_notices', array( __CLASS__, 'render_recovery_notice' ) );
	}

	/**
	 * Restrict dangerous capabilities in safe mode.
	 *
	 * @param array  $caps Primitive capabilities.
	 * @param string $cap Requested capability.
	 * @param int    $user_id User ID.
	 * @param array  $args Context args.
	 * @return array
	 */
	public static function restrict_admin_capabilities( $caps, $cap, $user_id, $args ) {
		if ( ! self::is_safe_mode_enabled() ) {
			return $caps;
		}

		$restricted = array(
			'install_plugins',
			'activate_plugins',
			'delete_plugins',
			'install_themes',
			'switch_themes',
			'edit_theme_options',
		);

		if ( in_array( $cap, $restricted, true ) && ! user_can( $user_id, 'manage_options' ) ) {
			return array( 'do_not_allow' );
		}

		return $caps;
	}

	/**
	 * Restrict file modifications if safe mode enabled.
	 *
	 * @param bool   $file_mod_allowed Current value.
	 * @param string $context Context.
	 * @return bool
	 */
	public static function restrict_file_modifications( $file_mod_allowed, $context ) {
		if ( ! self::is_safe_mode_enabled() ) {
			return $file_mod_allowed;
		}

		// In safe mode, block plugin/theme/core modifications from wp-admin UI flows.
		if ( in_array( $context, array( 'plugin', 'theme', 'core' ), true ) ) {
			return false;
		}

		return $file_mod_allowed;
	}

	/**
	 * Detect fatal error and store recovery state.
	 *
	 * @return void
	 */
	public static function detect_fatal_errors() {
		$error = error_get_last();
		if ( empty( $error ) || ! in_array( (int) $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ), true ) ) {
			return;
		}

		update_option(
			'wpguardian_recovery_notice',
			array(
				'time'    => time(),
				'message' => sanitize_text_field( $error['message'] ),
			)
		);
	}

	/**
	 * Render admin recovery notice.
	 *
	 * @return void
	 */
	public static function render_recovery_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$notice = get_option( 'wpguardian_recovery_notice', array() );
		if ( empty( $notice['time'] ) ) {
			return;
		}

		$backups = WPGuardian_Backup::list_backups( 1 );
		$link    = admin_url( 'admin.php?page=wpguardian-backups' );

		echo '<div class="notice notice-error"><p>';
		echo esc_html__( 'WP Guardian detected a recent fatal error.', 'wp-guardian' ) . ' ';
		if ( ! empty( $notice['message'] ) ) {
			echo esc_html( $notice['message'] ) . ' ';
		}
		if ( ! empty( $backups ) ) {
			echo '<a href="' . esc_url( $link ) . '">' . esc_html__( 'Review latest backup and restore quickly.', 'wp-guardian' ) . '</a>';
		}
		echo '</p></div>';
	}

	/**
	 * Check safe mode.
	 *
	 * @return bool
	 */
	public static function is_safe_mode_enabled() {
		if ( is_multisite() ) {
			$network_settings = get_site_option( 'wpguardian_network_settings', array() );
			if ( ! empty( $network_settings['force_safe_mode'] ) ) {
				return true;
			}
		}
		$settings = get_option( 'wpguardian_settings', array() );
		return ! empty( $settings['safe_mode'] );
	}
}
