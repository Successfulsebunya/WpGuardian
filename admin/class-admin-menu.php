<?php
/**
 * Admin menu registration.
 *
 * @package WPGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGuardian_Admin_Menu {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'register_network_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'add_theme_body_class' ) );
	}

	/**
	 * Register admin pages.
	 *
	 * @return void
	 */
	public static function register_menu() {
		$capability = 'manage_options';

		add_menu_page(
			__( 'WP Guard', 'wp-guard' ),
			__( 'WP Guard', 'wp-guard' ),
			$capability,
			'wpguardian-dashboard',
			array( 'WPGuardian_Admin_Pages', 'render_dashboard' ),
			'dashicons-shield-alt',
			58
		);

		add_submenu_page( 'wpguardian-dashboard', __( 'Dashboard', 'wp-guard' ), __( 'Dashboard', 'wp-guard' ), $capability, 'wpguardian-dashboard', array( 'WPGuardian_Admin_Pages', 'render_dashboard' ) );
		add_submenu_page( 'wpguardian-dashboard', __( 'Backups', 'wp-guard' ), __( 'Backups', 'wp-guard' ), $capability, 'wpguardian-backups', array( 'WPGuardian_Admin_Pages', 'render_backups' ) );
		add_submenu_page( 'wpguardian-dashboard', __( 'Activity Logs', 'wp-guard' ), __( 'Activity Logs', 'wp-guard' ), $capability, 'wpguardian-logs', array( 'WPGuardian_Admin_Pages', 'render_logs' ) );
		add_submenu_page( 'wpguardian-dashboard', __( 'Settings', 'wp-guard' ), __( 'Settings', 'wp-guard' ), $capability, 'wpguardian-settings', array( 'WPGuardian_Admin_Pages', 'render_settings' ) );
	}

	/**
	 * Register multisite network admin page.
	 *
	 * @return void
	 */
	public static function register_network_menu() {
		if ( ! is_multisite() ) {
			return;
		}

		add_menu_page(
			__( 'WP Guard Network', 'wp-guard' ),
			__( 'WP Guard', 'wp-guard' ),
			'manage_network_options',
			'wpguardian-network',
			array( 'WPGuardian_Admin_Pages', 'render_network_dashboard' ),
			'dashicons-shield-alt',
			58
		);

		add_submenu_page(
			'wpguardian-network',
			__( 'Network Overview', 'wp-guard' ),
			__( 'Network Overview', 'wp-guard' ),
			'manage_network_options',
			'wpguardian-network',
			array( 'WPGuardian_Admin_Pages', 'render_network_dashboard' )
		);

		add_submenu_page(
			'wpguardian-network',
			__( 'Network Settings', 'wp-guard' ),
			__( 'Network Settings', 'wp-guard' ),
			'manage_network_options',
			'wpguardian-network-settings',
			array( 'WPGuardian_Admin_Pages', 'render_network_settings' )
		);
	}

	/**
	 * Enqueue UI assets.
	 *
	 * @param string $hook_suffix Admin hook.
	 * @return void
	 */
	public static function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'wpguardian' ) ) {
			return;
		}

		wp_enqueue_style( 'wpguardian-admin', WPGUARDIAN_PLUGIN_URL . 'assets/css/admin.css', array(), WPGUARDIAN_VERSION );
		wp_enqueue_script( 'wpguardian-admin', WPGUARDIAN_PLUGIN_URL . 'assets/js/admin.js', array(), WPGUARDIAN_VERSION, true );
	}

	/**
	 * Add UI theme class for plugin screens.
	 *
	 * @param string $classes Existing classes.
	 * @return string
	 */
	public static function add_theme_body_class( $classes ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || false === strpos( (string) $screen->id, 'wpguardian' ) ) {
			return $classes;
		}

		$settings = get_option( 'wpguardian_settings', array() );
		$theme    = isset( $settings['ui_theme'] ) ? sanitize_key( $settings['ui_theme'] ) : 'auto';
		if ( ! in_array( $theme, array( 'auto', 'light', 'dark' ), true ) ) {
			$theme = 'auto';
		}

		return trim( $classes . ' wpguardian-theme-' . $theme );
	}
}
