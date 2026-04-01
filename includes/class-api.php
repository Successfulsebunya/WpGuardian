<?php
/**
 * REST API layer.
 *
 * @package WPGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGuardian_API {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public static function register_routes() {
		register_rest_route(
			'wp-guardian/v1',
			'/backup',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_trigger_backup' ),
				'permission_callback' => array( __CLASS__, 'permissions_manage' ),
			)
		);

		register_rest_route(
			'wp-guardian/v1',
			'/restore/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'api_trigger_restore' ),
				'permission_callback' => array( __CLASS__, 'permissions_pro_manage' ),
			)
		);

		register_rest_route(
			'wp-guardian/v1',
			'/logs',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_fetch_logs' ),
				'permission_callback' => array( __CLASS__, 'permissions_pro_manage' ),
			)
		);

		register_rest_route(
			'wp-guardian/v1',
			'/license-status',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'api_license_status' ),
				'permission_callback' => array( __CLASS__, 'permissions_manage' ),
			)
		);
	}

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public static function permissions_manage() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Permission check for pro features.
	 *
	 * @return bool
	 */
	public static function permissions_pro_manage() {
		return current_user_can( 'manage_options' ) && WPGuardian_License::is_pro_active();
	}

	/**
	 * Trigger backup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function api_trigger_backup( WP_REST_Request $request ) {
		$type = $request->get_param( 'type' );
		if ( 'partial' === $type ) {
			$post_id = absint( $request->get_param( 'post_id' ) );
			$result  = WPGuardian_Backup::create_partial_backup( $post_id );
		} else {
			$result = WPGuardian_Backup::create_full_backup( true );
		}

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => $result->get_error_message() ), 400 );
		}

		return new WP_REST_Response( array( 'success' => true, 'backup_id' => $result ), 200 );
	}

	/**
	 * Trigger restore.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function api_trigger_restore( WP_REST_Request $request ) {
		$backup_id = absint( $request['id'] );
		$result    = WPGuardian_Restore::restore_backup( $backup_id );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( array( 'success' => false, 'error' => $result->get_error_message() ), 400 );
		}
		return new WP_REST_Response( array( 'success' => true ), 200 );
	}

	/**
	 * Return logs.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function api_fetch_logs( WP_REST_Request $request ) {
		$filters = array(
			'action'    => sanitize_text_field( (string) $request->get_param( 'action' ) ),
			'user_id'   => absint( $request->get_param( 'user_id' ) ),
			'date_from' => sanitize_text_field( (string) $request->get_param( 'date_from' ) ),
			'date_to'   => sanitize_text_field( (string) $request->get_param( 'date_to' ) ),
			'limit'     => absint( $request->get_param( 'limit' ) ),
			'offset'    => absint( $request->get_param( 'offset' ) ),
		);

		$cache_key = 'wpguardian_logs_' . md5( wp_json_encode( $filters ) );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return new WP_REST_Response( array( 'success' => true, 'logs' => $cached, 'cached' => true ), 200 );
		}

		$logs = WPGuardian_Activity_Log::fetch_logs( $filters );
		set_transient( $cache_key, $logs, MINUTE_IN_SECONDS );

		return new WP_REST_Response( array( 'success' => true, 'logs' => $logs ), 200 );
	}

	/**
	 * Return license verification status.
	 *
	 * @return WP_REST_Response
	 */
	public static function api_license_status() {
		$settings = get_option( 'wpguardian_settings', array() );
		$status   = WPGuardian_License::verify_license( isset( $settings['license_key'] ) ? $settings['license_key'] : '' );
		return new WP_REST_Response(
			array(
				'success' => true,
				'pro'     => WPGuardian_License::is_pro_active(),
				'license' => $status,
			),
			200
		);
	}
}
