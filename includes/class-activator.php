<?php
/**
 * Plugin activation logic.
 *
 * @package wpguard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class wpguard_Activator {
	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::activate_for_current_site();
				restore_current_blog();
			}
			return;
		}

		self::activate_for_current_site();
	}

	/**
	 * Activate for current blog context.
	 *
	 * @return void
	 */
	private static function activate_for_current_site() {
		self::create_tables();
		self::create_backup_directory();
		self::set_default_settings();
		self::schedule_events();
	}

	/**
	 * Create custom tables.
	 *
	 * @return void
	 */
	private static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$logs_table      = $wpdb->prefix . 'guardian_logs';
		$backups_table   = $wpdb->prefix . 'guardian_backups';

		$sql_logs = "CREATE TABLE {$logs_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(120) NOT NULL,
			object_type VARCHAR(100) NOT NULL DEFAULT '',
			object_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			ip_address VARCHAR(45) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY action (action),
			KEY object_type (object_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_backups = "CREATE TABLE {$backups_table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			backup_type VARCHAR(20) NOT NULL,
			file_path TEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY backup_type (backup_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_logs );
		dbDelta( $sql_backups );
	}

	/**
	 * Ensure backup directory exists.
	 *
	 * @return void
	 */
	private static function create_backup_directory() {
		$backup_dir = wpguard_Backup::get_backup_dir();
		wp_mkdir_p( $backup_dir );

		$index_file = $backup_dir . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			self::write_file( $index_file, "<?php\n// Silence is golden.\n" );
		}

		$htaccess_file = $backup_dir . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			self::write_file( $htaccess_file, "Deny from all\n" );
		}
	}

	/**
	 * Write file content using WP_Filesystem when available.
	 *
	 * @param string $path File path.
	 * @param string $contents Contents.
	 * @return void
	 */
	private static function write_file( $path, $contents ) {
		global $wp_filesystem;

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		if ( $wp_filesystem && is_object( $wp_filesystem ) ) {
			$wp_filesystem->put_contents( $path, $contents, FS_CHMOD_FILE );
			return;
		}

		file_put_contents( $path, $contents );
	}

	/**
	 * Add default options.
	 *
	 * @return void
	 */
	private static function set_default_settings() {
		$defaults = array(
			'retention_days'      => 30,
			'safe_mode'           => 0,
			'allow_file_backup'   => 0,
			'ui_theme'            => 'auto',
			'pro_features_enabled'=> 0,
			'license_key'         => '',
			'license_status'      => 'inactive',
			'license_message'     => '',
			'license_expires_at'  => '',
			'license_retry_count' => 0,
			'license_next_retry_at' => 0,
			'license_last_checked_at' => 0,
			'license_last_success_at' => 0,
			'alerts_enabled'      => 0,
			'alert_email'         => get_option( 'admin_email' ),
			'last_backup_status'  => 'never',
		);

		add_option( 'wpguard_settings', $defaults );
	}

	/**
	 * Schedule cron events.
	 *
	 * @return void
	 */
	private static function schedule_events() {
		if ( ! wp_next_scheduled( 'wpguard_daily_backup_event' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'daily', 'wpguard_daily_backup_event' );
		}

		if ( ! wp_next_scheduled( 'wpguard_cleanup_event' ) ) {
			wp_schedule_event( time() + ( 2 * MINUTE_IN_SECONDS ), 'daily', 'wpguard_cleanup_event' );
		}

		if ( ! wp_next_scheduled( 'wpguard_license_health_event' ) ) {
			wp_schedule_event( time() + ( 3 * MINUTE_IN_SECONDS ), 'hourly', 'wpguard_license_health_event' );
		}
	}
}
