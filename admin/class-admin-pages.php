<?php
/**
 * Admin page rendering and actions.
 *
 * @package WPGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGuardian_Admin_Pages {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'handle_post_actions' ) );
	}

	/**
	 * Handle admin POST actions.
	 *
	 * @return void
	 */
	public static function handle_post_actions() {
		$action = '';
		if ( ! empty( $_POST['wpguardian_action'] ) ) {
			$action = sanitize_text_field( wp_unslash( $_POST['wpguardian_action'] ) );
		}

		if ( 'save_network_settings' === $action ) {
			if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) {
				return;
			}
			check_admin_referer( 'wpguardian_admin_action', 'wpguardian_nonce' );
			$network_settings = get_site_option( 'wpguardian_network_settings', array() );
			$network_settings['force_safe_mode']        = isset( $_POST['force_safe_mode'] ) ? 1 : 0;
			$network_settings['lock_site_settings']     = isset( $_POST['lock_site_settings'] ) ? 1 : 0;
			$network_settings['force_license_override'] = isset( $_POST['force_license_override'] ) ? 1 : 0;
			$network_settings['override_license_key']   = isset( $_POST['override_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['override_license_key'] ) ) : '';
			update_site_option( 'wpguardian_network_settings', $network_settings );
			self::redirect_with_notice( 'network_settings_saved', true );
		}

		if ( is_multisite() ) {
			$network_settings = get_site_option( 'wpguardian_network_settings', array() );
			if ( ! empty( $network_settings['lock_site_settings'] ) && ! current_user_can( 'manage_network_options' ) ) {
				if ( in_array( $action, array( 'save_settings' ), true ) ) {
					self::redirect_with_notice( 'network_lock_enabled' );
				}
			}
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( '' === $action ) {
			return;
		}

		check_admin_referer( 'wpguardian_admin_action', 'wpguardian_nonce' );

		if ( 'create_full_backup' === $action ) {
			$result = WPGuardian_Backup::create_full_backup( true );
			self::redirect_with_notice( is_wp_error( $result ) ? 'backup_failed' : 'backup_created' );
		}

		if ( 'restore_backup' === $action ) {
			$backup_id = isset( $_POST['backup_id'] ) ? absint( $_POST['backup_id'] ) : 0;
			$result    = WPGuardian_Restore::restore_backup( $backup_id );
			self::redirect_with_notice( is_wp_error( $result ) ? 'restore_failed' : 'restore_ok' );
		}

		if ( 'resume_restore' === $action ) {
			if ( ! WPGuardian_License::is_pro_active() ) {
				self::redirect_with_notice( 'pro_required' );
			}
			$result = WPGuardian_Restore::resume_restore();
			self::redirect_with_notice( is_wp_error( $result ) ? 'restore_resume_failed' : 'restore_resumed' );
		}

		if ( 'download_backup' === $action ) {
			if ( ! WPGuardian_License::is_pro_active() ) {
				self::redirect_with_notice( 'pro_required' );
			}
			$backup_id = isset( $_POST['backup_id'] ) ? absint( $_POST['backup_id'] ) : 0;
			self::stream_backup_download( $backup_id );
		}

		if ( 'save_settings' === $action ) {
			$settings = get_option( 'wpguardian_settings', array() );
			$settings['retention_days']    = isset( $_POST['retention_days'] ) ? max( 7, absint( $_POST['retention_days'] ) ) : 30;
			$settings['safe_mode']         = isset( $_POST['safe_mode'] ) ? 1 : 0;
			$settings['allow_file_backup'] = isset( $_POST['allow_file_backup'] ) ? 1 : 0;
			$theme = isset( $_POST['ui_theme'] ) ? sanitize_key( wp_unslash( $_POST['ui_theme'] ) ) : 'auto';
			$settings['ui_theme'] = in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ? $theme : 'auto';
			$settings['alerts_enabled']    = isset( $_POST['alerts_enabled'] ) ? 1 : 0;
			$settings['alert_email']       = isset( $_POST['alert_email'] ) ? sanitize_email( wp_unslash( $_POST['alert_email'] ) ) : '';
			$settings['license_key']       = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
			$license_check = WPGuardian_License::verify_license( $settings['license_key'], true );
			$settings['pro_features_enabled'] = ! empty( $license_check['active'] ) ? 1 : 0;
			$settings['license_status']       = isset( $license_check['status'] ) ? sanitize_key( $license_check['status'] ) : 'inactive';
			$settings['license_message']      = isset( $license_check['message'] ) ? sanitize_text_field( $license_check['message'] ) : '';
			$settings['license_expires_at']   = isset( $license_check['expires_at'] ) ? sanitize_text_field( $license_check['expires_at'] ) : '';

			update_option( 'wpguardian_settings', $settings );
			self::redirect_with_notice( 'settings_saved' );
		}

		if ( 'retry_license_check' === $action ) {
			$settings = get_option( 'wpguardian_settings', array() );
			$key      = isset( $settings['license_key'] ) ? $settings['license_key'] : '';
			$check    = WPGuardian_License::verify_license( $key, true );

			$settings['pro_features_enabled'] = ! empty( $check['active'] ) ? 1 : 0;
			$settings['license_status']       = isset( $check['status'] ) ? sanitize_key( $check['status'] ) : 'inactive';
			$settings['license_message']      = isset( $check['message'] ) ? sanitize_text_field( $check['message'] ) : '';
			$settings['license_expires_at']   = isset( $check['expires_at'] ) ? sanitize_text_field( $check['expires_at'] ) : '';
			update_option( 'wpguardian_settings', $settings );

			self::redirect_with_notice( ! empty( $check['active'] ) ? 'license_check_ok' : 'license_check_failed' );
		}

	}

	/**
	 * Render dashboard.
	 *
	 * @return void
	 */
	public static function render_dashboard() {
		$logs    = WPGuardian_Activity_Log::fetch_logs( array( 'limit' => 10 ) );
		$backups = WPGuardian_Backup::list_backups( 5 );
		$status  = get_option( 'wpguardian_settings', array() );
		include WPGUARDIAN_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render backup page.
	 *
	 * @return void
	 */
	public static function render_backups() {
		$backups = WPGuardian_Backup::list_backups( 100 );
		include WPGUARDIAN_PLUGIN_DIR . 'admin/views/backups.php';
	}

	/**
	 * Render logs page.
	 *
	 * @return void
	 */
	public static function render_logs() {
		$filters = array(
			'action'    => isset( $_GET['action_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['action_filter'] ) ) : '',
			'user_id'   => isset( $_GET['user_filter'] ) ? absint( $_GET['user_filter'] ) : 0,
			'date_from' => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
			'date_to'   => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
			'limit'     => 100,
			'offset'    => 0,
		);
		$logs = WPGuardian_Activity_Log::fetch_logs( $filters );
		include WPGUARDIAN_PLUGIN_DIR . 'admin/views/logs.php';
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public static function render_settings() {
		$settings = get_option( 'wpguardian_settings', array() );
		$license  = WPGuardian_License::verify_license( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
		$license_health = array(
			'status'          => isset( $license['status'] ) ? $license['status'] : 'unknown',
			'is_grace_mode'   => isset( $license['status'] ) && 'degraded' === $license['status'],
			'last_checked_at' => isset( $settings['license_last_checked_at'] ) ? absint( $settings['license_last_checked_at'] ) : 0,
			'last_success_at' => isset( $settings['license_last_success_at'] ) ? absint( $settings['license_last_success_at'] ) : 0,
			'next_retry_at'   => isset( $settings['license_next_retry_at'] ) ? absint( $settings['license_next_retry_at'] ) : 0,
			'retry_count'     => isset( $settings['license_retry_count'] ) ? absint( $settings['license_retry_count'] ) : 0,
		);
		include WPGUARDIAN_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render multisite network dashboard.
	 *
	 * @return void
	 */
	public static function render_network_dashboard() {
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'wp-guard' ) );
		}

		global $wpdb;
		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$safe_mode_filter = isset( $_GET['safe_mode_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['safe_mode_filter'] ) ) : '';

		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 100,
				'search' => $search ? '*' . $search . '*' : '',
			)
		);

		$network_rows = array();
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			$blog_details = get_blog_details( (int) $site_id );

			$logs_table    = $wpdb->prefix . 'guardian_logs';
			$backups_table = $wpdb->prefix . 'guardian_backups';

			$logs_count    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$logs_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$backups_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$backups_table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$latest_backup = $wpdb->get_var( "SELECT created_at FROM {$backups_table} ORDER BY id DESC LIMIT 1" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$settings      = get_option( 'wpguardian_settings', array() );

			$safe_mode_enabled = ! empty( $settings['safe_mode'] );
			$row = array(
				'blog_id'       => (int) $site_id,
				'name'          => $blog_details ? $blog_details->blogname : ( 'Site ' . (int) $site_id ),
				'url'           => $blog_details ? $blog_details->siteurl : '',
				'logs_count'    => $logs_count,
				'backups_count' => $backups_count,
				'latest_backup' => $latest_backup ? $latest_backup : __( 'Never', 'wp-guard' ),
				'safe_mode'     => $safe_mode_enabled ? __( 'On', 'wp-guard' ) : __( 'Off', 'wp-guard' ),
			);

			if ( 'on' === $safe_mode_filter && ! $safe_mode_enabled ) {
				restore_current_blog();
				continue;
			}
			if ( 'off' === $safe_mode_filter && $safe_mode_enabled ) {
				restore_current_blog();
				continue;
			}

			$network_rows[] = $row;

			restore_current_blog();
		}

		include WPGUARDIAN_PLUGIN_DIR . 'admin/views/network-dashboard.php';
	}

	/**
	 * Render multisite network settings page.
	 *
	 * @return void
	 */
	public static function render_network_settings() {
		if ( ! is_multisite() || ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to access this page.', 'wp-guard' ) );
		}
		$network_settings = get_site_option( 'wpguardian_network_settings', array() );
		include WPGUARDIAN_PLUGIN_DIR . 'admin/views/network-settings.php';
	}

	/**
	 * Stream backup file download.
	 *
	 * @param int $backup_id Backup ID.
	 * @return void
	 */
	private static function stream_backup_download( $backup_id ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'guardian_backups';
		$backup = $wpdb->get_row( $wpdb->prepare( "SELECT file_path FROM {$table} WHERE id = %d", $backup_id ), ARRAY_A );
		if ( empty( $backup['file_path'] ) ) {
			self::redirect_with_notice( 'download_missing' );
		}

		$file = WPGuardian_Backup::get_backup_dir() . $backup['file_path'];
		if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
			self::redirect_with_notice( 'download_missing' );
		}

		nocache_headers();
		header( 'Content-Description: File Transfer' );
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . basename( $file ) . '"' );
		header( 'Content-Length: ' . filesize( $file ) );
		readfile( $file );
		exit;
	}

	/**
	 * Redirect with status notice.
	 *
	 * @param string $notice Notice key.
	 * @return void
	 */
	private static function redirect_with_notice( $notice, $network = false ) {
		$referrer = wp_get_referer();
		$target   = $referrer ? $referrer : ( $network ? network_admin_url( 'admin.php?page=wpguardian-network-settings' ) : admin_url( 'admin.php?page=wpguardian-dashboard' ) );
		$target   = add_query_arg( 'wpguardian_notice', sanitize_text_field( $notice ), $target );
		wp_safe_redirect( $target );
		exit;
	}
}
