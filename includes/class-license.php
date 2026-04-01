<?php
/**
 * License service.
 *
 * @package WPGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGuardian_License {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wpguardian_license_retry_event', array( __CLASS__, 'run_retry_verification' ) );
		add_action( 'wpguardian_license_health_event', array( __CLASS__, 'run_scheduled_health_check' ) );
	}

	/**
	 * Verify license against remote service.
	 *
	 * @param string $license_key License key.
	 * @param bool   $force Force remote call.
	 * @return array
	 */
	public static function verify_license( $license_key, $force = false ) {
		if ( is_multisite() ) {
			$network_settings = get_site_option( 'wpguardian_network_settings', array() );
			if ( ! empty( $network_settings['force_license_override'] ) && ! empty( $network_settings['override_license_key'] ) ) {
				$license_key = $network_settings['override_license_key'];
			}
		}

		$license_key = strtoupper( trim( (string) $license_key ) );
		if ( '' === $license_key ) {
			return array(
				'active'  => false,
				'status'  => 'empty',
				'message' => __( 'No license key provided.', 'wp-guardian' ),
			);
		}

		$cache_key = 'wpguardian_license_' . md5( $license_key );
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$endpoint = apply_filters( 'wpguardian_license_endpoint', 'https://license.wpguardian.example/verify' );
		$args     = array(
			'timeout' => 15,
			'body'    => array(
				'license_key' => $license_key,
				'site_url'    => home_url(),
				'product'     => 'wp-guardian',
				'version'     => WPGUARDIAN_VERSION,
			),
		);

		$settings = get_option( 'wpguardian_settings', array() );
		$response = wp_remote_post( esc_url_raw( $endpoint ), $args );
		if ( is_wp_error( $response ) ) {
			$result = self::build_failed_result( 'request_failed', $response->get_error_message() );
			$result = self::apply_grace_if_available( $result, $settings );
			self::register_retry( $settings );
			set_transient( $cache_key, $result, HOUR_IN_SECONDS );
			return $result;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== $code || ! is_array( $body ) ) {
			$result = self::build_failed_result( 'invalid_response', __( 'License server returned an invalid response.', 'wp-guardian' ) );
			$result = self::apply_grace_if_available( $result, $settings );
			self::register_retry( $settings );
			set_transient( $cache_key, $result, HOUR_IN_SECONDS );
			return $result;
		}

		$result = array(
			'active'     => ! empty( $body['active'] ),
			'status'     => isset( $body['status'] ) ? sanitize_key( $body['status'] ) : 'unknown',
			'message'    => isset( $body['message'] ) ? sanitize_text_field( $body['message'] ) : '',
			'expires_at' => isset( $body['expires_at'] ) ? sanitize_text_field( $body['expires_at'] ) : '',
			'plan'       => isset( $body['plan'] ) ? sanitize_text_field( $body['plan'] ) : '',
		);

		self::reset_retry_state( $settings, $result );
		set_transient( $cache_key, $result, 6 * HOUR_IN_SECONDS );
		return $result;
	}

	/**
	 * Execute backoff retry.
	 *
	 * @return void
	 */
	public static function run_retry_verification() {
		$settings = get_option( 'wpguardian_settings', array() );
		if ( empty( $settings['license_key'] ) ) {
			return;
		}
		self::verify_license( $settings['license_key'], true );
	}

	/**
	 * Recurring health verification.
	 *
	 * @return void
	 */
	public static function run_scheduled_health_check() {
		$settings = get_option( 'wpguardian_settings', array() );
		if ( empty( $settings['license_key'] ) ) {
			return;
		}
		self::verify_license( $settings['license_key'], true );
	}

	/**
	 * Build failed result payload.
	 *
	 * @param string $status Status code.
	 * @param string $message Message.
	 * @return array
	 */
	private static function build_failed_result( $status, $message ) {
		return array(
				'active'  => false,
				'status'  => sanitize_key( $status ),
				'message' => sanitize_text_field( $message ),
			);
	}

	/**
	 * Apply grace when remote check fails.
	 *
	 * @param array $result Result payload.
	 * @param array $settings Settings.
	 * @return array
	 */
	private static function apply_grace_if_available( $result, $settings ) {
		$last_success = isset( $settings['license_last_success_at'] ) ? absint( $settings['license_last_success_at'] ) : 0;
		$grace_window = 7 * DAY_IN_SECONDS;
		if ( ! empty( $settings['pro_features_enabled'] ) && $last_success > 0 && ( time() - $last_success ) <= $grace_window ) {
			$result['active']  = true;
			$result['status']  = 'degraded';
			$result['message'] = __( 'License server unreachable. Running in grace mode temporarily.', 'wp-guardian' );
		}
		return $result;
	}

	/**
	 * Register exponential retry event.
	 *
	 * @param array $settings Settings.
	 * @return void
	 */
	private static function register_retry( $settings ) {
		$retry_count = isset( $settings['license_retry_count'] ) ? absint( $settings['license_retry_count'] ) : 0;
		$retry_count++;
		$delay = min( 12 * HOUR_IN_SECONDS, (int) pow( 2, min( 12, $retry_count ) ) * MINUTE_IN_SECONDS );
		$next  = time() + $delay;

		$settings['license_retry_count']   = $retry_count;
		$settings['license_next_retry_at'] = $next;
		$settings['license_last_checked_at'] = time();
		update_option( 'wpguardian_settings', $settings );

		if ( ! wp_next_scheduled( 'wpguardian_license_retry_event' ) ) {
			wp_schedule_single_event( $next, 'wpguardian_license_retry_event' );
		}
	}

	/**
	 * Reset retry state after successful remote verification.
	 *
	 * @param array $settings Settings.
	 * @param array $result License result.
	 * @return void
	 */
	private static function reset_retry_state( $settings, $result ) {
		$settings['license_retry_count']     = 0;
		$settings['license_next_retry_at']   = 0;
		$settings['license_last_checked_at'] = time();
		if ( ! empty( $result['active'] ) ) {
			$settings['license_last_success_at'] = time();
		}
		update_option( 'wpguardian_settings', $settings );
	}

	/**
	 * Whether pro features are active.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		if ( WPGUARDIAN_IS_PRO ) {
			return true;
		}

		$settings = get_option( 'wpguardian_settings', array() );
		if ( empty( $settings['pro_features_enabled'] ) ) {
			return false;
		}

		$status = isset( $settings['license_status'] ) ? $settings['license_status'] : '';
		if ( ! in_array( $status, array( 'active', 'degraded' ), true ) ) {
			return false;
		}

		return true;
	}
}
