<?php
/**
 * Uninstall plugin.
 *
 * @package WPGuardian
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Remove tables/options/files for current blog.
 *
 * @return void
 */
function wpguardian_uninstall_for_blog() {
	global $wpdb;

	$logs_table    = $wpdb->prefix . 'guardian_logs';
	$backups_table = $wpdb->prefix . 'guardian_backups';

	$wpdb->query( "DROP TABLE IF EXISTS {$logs_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$backups_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

	delete_option( 'wpguardian_settings' );
	delete_option( 'wpguardian_restore_checkpoint' );

	$upload_dir = wp_upload_dir();
	$dir        = trailingslashit( $upload_dir['basedir'] ) . 'wp-guardian-backups/';

	if ( is_dir( $dir ) ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $iterator as $path ) {
			if ( $path->isDir() ) {
				rmdir( $path->getPathname() );
			} else {
				wp_delete_file( $path->getPathname() );
			}
		}
		rmdir( $dir );
	}
}

if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		wpguardian_uninstall_for_blog();
		restore_current_blog();
	}
} else {
	wpguardian_uninstall_for_blog();
}

if ( is_multisite() ) {
	delete_site_option( 'wpguardian_network_settings' );
}
