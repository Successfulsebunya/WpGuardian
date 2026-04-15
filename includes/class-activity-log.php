<?php
/**
 * Activity logging service.
 *
 * @package WPGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGuardian_Activity_Log {
	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_login', array( __CLASS__, 'log_login' ), 10, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'log_logout' ) );
		add_action( 'wp_login_failed', array( __CLASS__, 'log_failed_login' ) );
		add_action( 'post_updated', array( __CLASS__, 'log_post_updated' ), 10, 3 );
		add_action( 'before_delete_post', array( __CLASS__, 'log_post_deleted' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'log_upgrade_event' ), 10, 2 );
		add_action( 'switch_theme', array( __CLASS__, 'log_theme_switch' ), 10, 3 );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_personal_data_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_personal_data_eraser' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_privacy_policy_content' ) );
	}

	/**
	 * Write log entry.
	 *
	 * @param string $action Action slug.
	 * @param string $object_type Related object type.
	 * @param int    $object_id Related object ID.
	 * @param int    $user_id User ID.
	 * @return void
	 */
	public static function write( $action, $object_type = '', $object_id = 0, $user_id = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'guardian_logs';
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		$wpdb->insert(
			$table,
			array(
				'user_id'    => absint( $user_id ),
				'action'     => sanitize_text_field( $action ),
				'object_type'=> sanitize_text_field( $object_type ),
				'object_id'  => absint( $object_id ),
				'ip_address' => $ip,
				'created_at' => current_time( 'mysql', 1 ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	public static function log_login( $user_login, $user ) {
		self::write( 'user_login', 'user', $user->ID, $user->ID );
	}

	public static function log_logout() {
		$user_id = get_current_user_id();
		self::write( 'user_logout', 'user', $user_id, $user_id );
	}

	public static function log_failed_login( $username ) {
		self::write( 'user_login_failed', 'user', 0, 0 );
	}

	public static function log_post_updated( $post_id, $post_after, $post_before ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::write( 'post_updated', $post_after->post_type, $post_id, get_current_user_id() );
	}

	public static function log_post_deleted( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		self::write( 'post_deleted', $post->post_type, $post_id, get_current_user_id() );
	}

	public static function log_upgrade_event( $upgrader, $options ) {
		if ( empty( $options['type'] ) ) {
			return;
		}
		self::write( 'upgrade_' . sanitize_key( $options['type'] ), 'upgrade', 0, get_current_user_id() );
	}

	public static function log_theme_switch( $new_name, $new_theme, $old_theme ) {
		self::write( 'theme_switch', 'theme', 0, get_current_user_id() );
	}

	/**
	 * Fetch logs for admin.
	 *
	 * @param array $filters Filters.
	 * @return array
	 */
	public static function fetch_logs( $filters = array() ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'guardian_logs';
		$limit  = isset( $filters['limit'] ) ? max( 1, min( 200, absint( $filters['limit'] ) ) ) : 50;
		$offset = isset( $filters['offset'] ) ? max( 0, absint( $filters['offset'] ) ) : 0;
		$where  = array( '1=1' );
		$args   = array();

		if ( ! empty( $filters['action'] ) ) {
			$where[] = 'action = %s';
			$args[]  = sanitize_text_field( $filters['action'] );
		}

		if ( ! empty( $filters['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$args[]  = absint( $filters['user_id'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$args[]  = sanitize_text_field( $filters['date_from'] ) . ' 00:00:00';
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$args[]  = sanitize_text_field( $filters['date_to'] ) . ' 23:59:59';
		}

		$sql = "SELECT * FROM {$table} WHERE " . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d OFFSET %d';
		$args[] = $limit;
		$args[] = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Register exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_personal_data_exporter( $exporters ) {
		$exporters['wpguardian-activity-log'] = array(
			'exporter_friendly_name' => __( 'WP Guard Activity Logs', 'wp-guard' ),
			'callback'               => array( __CLASS__, 'privacy_data_exporter' ),
		);
		return $exporters;
	}

	/**
	 * Register eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_personal_data_eraser( $erasers ) {
		$erasers['wpguardian-activity-log'] = array(
			'eraser_friendly_name' => __( 'WP Guard Activity Logs', 'wp-guard' ),
			'callback'             => array( __CLASS__, 'privacy_data_eraser' ),
		);
		return $erasers;
	}

	/**
	 * Export user activity data.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array
	 */
	public static function privacy_data_exporter( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}

		$limit  = 100;
		$offset = ( max( 1, absint( $page ) ) - 1 ) * $limit;
		$table  = $wpdb->prefix . 'guardian_logs';
		$rows   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, action, object_type, object_id, ip_address, created_at FROM {$table} WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d",
				(int) $user->ID,
				$limit,
				$offset
			),
			ARRAY_A
		);

		$data_to_export = array();
		foreach ( $rows as $row ) {
			$data_to_export[] = array(
				'group_id'    => 'wpguardian-activity-log',
				'group_label' => __( 'WP Guard Activity Logs', 'wp-guard' ),
				'item_id'     => 'wpguardian-log-' . (int) $row['id'],
				'data'        => array(
					array( 'name' => __( 'Action', 'wp-guard' ), 'value' => $row['action'] ),
					array( 'name' => __( 'Object Type', 'wp-guard' ), 'value' => $row['object_type'] ),
					array( 'name' => __( 'Object ID', 'wp-guard' ), 'value' => (string) $row['object_id'] ),
					array( 'name' => __( 'IP Address', 'wp-guard' ), 'value' => $row['ip_address'] ),
					array( 'name' => __( 'Created At', 'wp-guard' ), 'value' => $row['created_at'] ),
				),
			);
		}

		return array(
			'data' => $data_to_export,
			'done' => count( $rows ) < $limit,
		);
	}

	/**
	 * Erase/anonymize user activity data.
	 *
	 * @param string $email_address Email.
	 * @param int    $page Page.
	 * @return array
	 */
	public static function privacy_data_eraser( $email_address, $page = 1 ) {
		global $wpdb;

		$user = get_user_by( 'email', sanitize_email( $email_address ) );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$table   = $wpdb->prefix . 'guardian_logs';
		$updated = $wpdb->update(
			$table,
			array(
				'user_id'    => 0,
				'ip_address' => '',
			),
			array( 'user_id' => (int) $user->ID ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		return array(
			'items_removed'  => ( false !== $updated && $updated > 0 ),
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Add privacy policy content.
	 *
	 * @return void
	 */
	public static function register_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			__( 'WP Guard', 'wp-guard' ),
			wp_kses_post(
				'<p>' . esc_html__( 'WP Guard stores activity logs (including user IDs, action metadata, timestamps, and IP addresses) to support site protection and recovery workflows.', 'wp-guard' ) . '</p>'
			)
		);
	}
}
