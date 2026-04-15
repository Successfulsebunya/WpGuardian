<?php
/**
 * Cron tasks.
 *
 * @package wpguard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class wpguard_Cron {
	/**
	 * Init cron hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wpguard_daily_backup_event', array( __CLASS__, 'run_daily_backup' ) );
		add_action( 'wpguard_cleanup_event', array( __CLASS__, 'run_cleanup' ) );
	}

	/**
	 * Daily backup handler.
	 *
	 * @return void
	 */
	public static function run_daily_backup() {
		$result = wpguard_Backup::create_full_backup( false );
		if ( is_wp_error( $result ) ) {
			wpguard_Activity_Log::write( 'cron_backup_failed', 'cron', 0, 0 );
		}
	}

	/**
	 * Cleanup old logs and backup files.
	 *
	 * @return void
	 */
	public static function run_cleanup() {
		global $wpdb;
		$settings       = get_option( 'wpguard_settings', array() );
		$retention_days = isset( $settings['retention_days'] ) ? max( 7, absint( $settings['retention_days'] ) ) : 30;
		$cutoff         = gmdate( 'Y-m-d H:i:s', time() - ( DAY_IN_SECONDS * $retention_days ) );

		$logs_table    = $wpdb->prefix . 'guardian_logs';
		$backups_table = $wpdb->prefix . 'guardian_backups';

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$logs_table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$old_backups = $wpdb->get_results(
			$wpdb->prepare( "SELECT id, file_path FROM {$backups_table} WHERE created_at < %s", $cutoff ),
			ARRAY_A
		);

		if ( ! empty( $old_backups ) ) {
			foreach ( $old_backups as $backup ) {
				$path = wpguard_Backup::get_backup_dir() . $backup['file_path'];
				if ( file_exists( $path ) ) {
					wp_delete_file( $path );
				}
				$wpdb->delete( $backups_table, array( 'id' => absint( $backup['id'] ) ), array( '%d' ) );
			}
		}
	}
}
