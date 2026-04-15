<?php
/**
 * Main loader.
 *
 * @package wpguard
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once wpguard_PLUGIN_DIR . 'includes/class-activator.php';
require_once wpguard_PLUGIN_DIR . 'includes/class-deactivator.php';
require_once wpguard_PLUGIN_DIR . 'includes/class-activity-log.php';
require_once wpguard_PLUGIN_DIR . 'includes/class-backup.php';
require_once wpguard_PLUGIN_DIR . 'includes/class-restore.php';
require_once wpguard_PLUGIN_DIR . 'includes/class-security.php';
require_once wpguard_PLUGIN_DIR . 'includes/class-cron.php';
require_once wpguard_PLUGIN_DIR . 'includes/class-api.php';
require_once wpguard_PLUGIN_DIR . 'includes/class-cli.php';
require_once wpguard_PLUGIN_DIR . 'includes/class-license.php';
require_once wpguard_PLUGIN_DIR . 'admin/class-admin-menu.php';
require_once wpguard_PLUGIN_DIR . 'admin/class-admin-pages.php';

class wpguard_Loader {
	/**
	 * Boot plugin.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'init', array( __CLASS__, 'boot_services' ) );
	}

	/**
	 * Load language pack.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain( 'wp-guard', false, dirname( plugin_basename( wpguard_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * Boot all services.
	 *
	 * @return void
	 */
	public static function boot_services() {
		wpguard_Activity_Log::init();
		wpguard_Backup::init();
		wpguard_Restore::init();
		wpguard_Security::init();
		wpguard_Cron::init();
		wpguard_API::init();
		wpguard_CLI::init();
		wpguard_License::init();

		if ( is_admin() ) {
			wpguard_Admin_Menu::init();
			wpguard_Admin_Pages::init();
		}
	}
}
