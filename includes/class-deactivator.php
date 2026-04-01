<?php
/**
 * Plugin deactivation logic.
 *
 * @package WPGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGuardian_Deactivator {
	/**
	 * Deactivate plugin.
	 *
	 * @return void
	 */
	public static function deactivate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::clear_scheduled_hooks();
				restore_current_blog();
			}
			return;
		}

		self::clear_scheduled_hooks();
	}

	/**
	 * Clear plugin cron hooks for active blog context.
	 *
	 * @return void
	 */
	private static function clear_scheduled_hooks() {
		wp_clear_scheduled_hook( 'wpguardian_daily_backup_event' );
		wp_clear_scheduled_hook( 'wpguardian_cleanup_event' );
		wp_clear_scheduled_hook( 'wpguardian_license_health_event' );
		wp_clear_scheduled_hook( 'wpguardian_license_retry_event' );
	}
}
