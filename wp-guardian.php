<?php
/**
 * Plugin Name: WP Guard - Client Protection & Recovery System
 * Plugin URI: https://wordpress.org/plugins/wp-guard/
 * Description: Client-safe protection toolkit with activity logs, backup and restore workflows, and recovery tooling.
 * Version: 1.9.2
 * Author: WP Guard
 * Author URI: https://wordpress.org/
 * Requires at least: 6.1
 * Requires PHP: 7.4
 * Text Domain: wp-guard
 * Domain Path: /languages
 *
 * @package WPGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPGUARDIAN_VERSION', '1.9.2' );
define( 'WPGUARDIAN_PLUGIN_FILE', __FILE__ );
define( 'WPGUARDIAN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPGUARDIAN_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPGUARDIAN_BACKUP_DIR', trailingslashit( wp_upload_dir()['basedir'] ) . 'wp-guard-backups/' );
define( 'WPGUARDIAN_BACKUP_URL', trailingslashit( wp_upload_dir()['baseurl'] ) . 'wp-guard-backups/' );
define( 'WPGUARDIAN_IS_PRO', false );

require_once WPGUARDIAN_PLUGIN_DIR . 'includes/class-loader.php';

register_activation_hook( __FILE__, array( 'WPGuardian_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPGuardian_Deactivator', 'deactivate' ) );

WPGuardian_Loader::init();
