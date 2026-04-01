<?php
/**
 * WP-CLI commands.
 *
 * @package WPGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGuardian_CLI {
	/**
	 * Register CLI commands.
	 *
	 * @return void
	 */
	public static function init() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'guardian backup', array( __CLASS__, 'backup_command' ) );
			WP_CLI::add_command( 'guardian restore', array( __CLASS__, 'restore_command' ) );
			WP_CLI::add_command( 'guardian logs', array( __CLASS__, 'logs_command' ) );
		}
	}

	/**
	 * Create backup via CLI.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : full|partial
	 *
	 * [--post_id=<post_id>]
	 * : Required if type=partial
	 *
	 * @param array $args Positional args.
	 * @param array $assoc_args Assoc args.
	 * @return void
	 */
	public static function backup_command( $args, $assoc_args ) {
		$type = isset( $assoc_args['type'] ) ? sanitize_key( $assoc_args['type'] ) : 'full';

		if ( 'partial' === $type ) {
			$post_id = isset( $assoc_args['post_id'] ) ? absint( $assoc_args['post_id'] ) : 0;
			if ( ! $post_id ) {
				WP_CLI::error( 'When type=partial, --post_id is required.' );
				return;
			}
			$result = WPGuardian_Backup::create_partial_backup( $post_id );
		} else {
			$result = WPGuardian_Backup::create_full_backup( true );
		}

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}

		WP_CLI::success( sprintf( 'Backup created. ID: %d', (int) $result ) );
	}

	/**
	 * Restore backup by ID.
	 *
	 * ## OPTIONS
	 *
	 * --id=<id>
	 * : Backup ID
	 *
	 * [--resume]
	 * : Resume full restore from checkpoint (optional --id).
	 *
	 * @param array $args Positional args.
	 * @param array $assoc_args Assoc args.
	 * @return void
	 */
	public static function restore_command( $args, $assoc_args ) {
		$resume = ! empty( $assoc_args['resume'] );

		if ( $resume ) {
			$backup_id = isset( $assoc_args['id'] ) ? absint( $assoc_args['id'] ) : 0;
			$result    = WPGuardian_Restore::resume_restore( $backup_id );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
				return;
			}
			WP_CLI::success( 'Restore resumed from checkpoint and completed.' );
			return;
		}

		$backup_id = isset( $assoc_args['id'] ) ? absint( $assoc_args['id'] ) : 0;
		if ( ! $backup_id ) {
			WP_CLI::error( '--id is required.' );
			return;
		}

		$result = WPGuardian_Restore::restore_backup( $backup_id );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
			return;
		}

		WP_CLI::success( sprintf( 'Restore completed for backup #%d.', $backup_id ) );
	}

	/**
	 * List latest logs.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<limit>]
	 * : Number of rows
	 *
	 * @param array $args Positional args.
	 * @param array $assoc_args Assoc args.
	 * @return void
	 */
	public static function logs_command( $args, $assoc_args ) {
		$limit = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : 20;
		$logs  = WPGuardian_Activity_Log::fetch_logs( array( 'limit' => $limit ) );

		if ( empty( $logs ) ) {
			WP_CLI::warning( 'No logs found.' );
			return;
		}

		$rows = array();
		foreach ( $logs as $log ) {
			$rows[] = array(
				'id'         => $log['id'],
				'created_at' => $log['created_at'],
				'action'     => $log['action'],
				'user_id'    => $log['user_id'],
				'object'     => $log['object_type'] . ':' . $log['object_id'],
			);
		}

		WP_CLI\Utils\format_items( 'table', $rows, array( 'id', 'created_at', 'action', 'user_id', 'object' ) );
	}
}
