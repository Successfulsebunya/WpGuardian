<?php
/**
 * Backup engine.
 *
 * @package WPGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGuardian_Backup {
	/**
	 * Get current site backup directory.
	 *
	 * @return string
	 */
	public static function get_backup_dir() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'wp-guard-backups/';
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'save_post', array( __CLASS__, 'trigger_partial_backup_on_post_save' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'trigger_full_backup_on_upgrade' ), 5, 2 );
	}

	/**
	 * Trigger partial backup on content change.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post Post object.
	 * @param bool    $update Is update.
	 * @return void
	 */
	public static function trigger_partial_backup_on_post_save( $post_id, $post, $update ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! $update ) {
			return;
		}

		self::create_partial_backup( $post_id );
	}

	/**
	 * Trigger full backup before updates.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $hook_extra Hook context.
	 * @return void
	 */
	public static function trigger_full_backup_on_upgrade( $upgrader, $hook_extra ) {
		self::create_full_backup( false );
	}

	/**
	 * Create full backup (DB + optional files).
	 *
	 * @param bool $manual Is manual trigger.
	 * @return int|WP_Error
	 */
	public static function create_full_backup( $manual = true ) {
		$timestamp = gmdate( 'Ymd-His' );
		$base_dir  = self::get_backup_dir();
		$temp_dir  = $base_dir . 'tmp-' . wp_generate_password( 8, false, false ) . '/';

		wp_mkdir_p( $temp_dir );

		$db_file = $temp_dir . 'database.sql';
		$db_dump = self::export_database_to_file( $db_file );
		if ( is_wp_error( $db_dump ) ) {
			self::notify_failure( $db_dump->get_error_message(), 'full' );
			return $db_dump;
		}

		$settings = get_option( 'wpguardian_settings', array() );
		if ( ! empty( $settings['allow_file_backup'] ) ) {
			self::export_selected_files( $temp_dir . 'files.zip' );
		}

		$zip_name = 'full-backup-' . $timestamp . '.zip';
		$zip_path = $base_dir . $zip_name;
		$result   = self::zip_directory( $temp_dir, $zip_path );

		self::delete_directory( $temp_dir );

		if ( is_wp_error( $result ) ) {
			self::notify_failure( $result->get_error_message(), 'full' );
			return $result;
		}

		$backup_id = self::store_backup_record( 'full', $zip_name );
		update_option( 'wpguardian_settings', array_merge( $settings, array( 'last_backup_status' => 'ok' ) ) );
		WPGuardian_Activity_Log::write( $manual ? 'backup_full_manual' : 'backup_full_auto', 'backup', $backup_id, get_current_user_id() );

		return $backup_id;
	}

	/**
	 * Create post-level backup.
	 *
	 * @param int $post_id Post ID.
	 * @return int|WP_Error
	 */
	public static function create_partial_backup( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			$error = new WP_Error( 'wpguardian_post_missing', __( 'Post not found.', 'wp-guard' ) );
			self::notify_failure( $error->get_error_message(), 'partial' );
			return $error;
		}

		$timestamp = gmdate( 'Ymd-His' );
		$json_name = "partial-post-{$post_id}-{$timestamp}.json";
		$file_path = self::get_backup_dir() . $json_name;
		$payload   = array(
			'type'       => 'post',
			'post_id'    => (int) $post_id,
			'post_type'  => $post->post_type,
			'post_title' => $post->post_title,
			'post_data'  => array(
				'post_content' => $post->post_content,
				'post_excerpt' => $post->post_excerpt,
				'post_status'  => $post->post_status,
				'post_name'    => $post->post_name,
			),
			'captured_at' => current_time( 'mysql', 1 ),
		);

		$written = file_put_contents( $file_path, wp_json_encode( $payload ) );
		if ( false === $written ) {
			$error = new WP_Error( 'wpguardian_partial_write_failed', __( 'Could not write partial backup file.', 'wp-guard' ) );
			self::notify_failure( $error->get_error_message(), 'partial' );
			return $error;
		}

		$backup_id = self::store_backup_record( 'partial', $json_name );
		WPGuardian_Activity_Log::write( 'backup_partial_created', 'post', $post_id, get_current_user_id() );

		return $backup_id;
	}

	/**
	 * Export all DB tables into SQL.
	 *
	 * @param string $target_file SQL file path.
	 * @return true|WP_Error
	 */
	private static function export_database_to_file( $target_file ) {
		global $wpdb;

		$tables = $wpdb->get_col( 'SHOW TABLES' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( empty( $tables ) ) {
			return new WP_Error( 'wpguardian_no_tables', __( 'No tables found to export.', 'wp-guard' ) );
		}

		$handle = fopen( $target_file, 'wb' );
		if ( ! $handle ) {
			return new WP_Error( 'wpguardian_sql_write_failed', __( 'Could not open database backup file.', 'wp-guard' ) );
		}

		$header = "-- WP Guard SQL backup\n-- Generated: " . gmdate( 'c' ) . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";
		fwrite( $handle, $header );

		$chunk_size = 500;

		foreach ( $tables as $table ) {
			$create = $wpdb->get_row( "SHOW CREATE TABLE `{$table}`", ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! empty( $create[1] ) ) {
				fwrite( $handle, "DROP TABLE IF EXISTS `{$table}`;\n{$create[1]};\n\n" );
			}

			$total_rows = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			for ( $offset = 0; $offset < $total_rows; $offset += $chunk_size ) {
				$rows = $wpdb->get_results( "SELECT * FROM `{$table}` LIMIT {$chunk_size} OFFSET {$offset}", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				foreach ( $rows as $row ) {
					$values = array();
					foreach ( $row as $value ) {
						$values[] = is_null( $value ) ? 'NULL' : "'" . esc_sql( $value ) . "'";
					}
					fwrite( $handle, "INSERT INTO `{$table}` VALUES (" . implode( ',', $values ) . ");\n" );
				}
			}
			fwrite( $handle, "\n" );
		}

		fwrite( $handle, "SET FOREIGN_KEY_CHECKS=1;\n" );
		fclose( $handle );

		return true;
	}

	/**
	 * Zip a directory.
	 *
	 * @param string $source_dir Source.
	 * @param string $zip_path Destination.
	 * @return true|WP_Error
	 */
	private static function zip_directory( $source_dir, $zip_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'wpguardian_zip_missing', __( 'ZipArchive extension is missing.', 'wp-guard' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return new WP_Error( 'wpguardian_zip_open_failed', __( 'Unable to create backup archive.', 'wp-guard' ) );
		}

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			$file_path = $file->getRealPath();
			if ( ! $file_path ) {
				continue;
			}
			$relative_path = ltrim( str_replace( $source_dir, '', $file_path ), '\\/' );
			if ( $file->isDir() ) {
				$zip->addEmptyDir( $relative_path );
			} else {
				$zip->addFile( $file_path, $relative_path );
			}
		}

		$zip->close();
		return true;
	}

	/**
	 * Save backup metadata in DB.
	 *
	 * @param string $type Backup type.
	 * @param string $file_name Relative filename.
	 * @return int
	 */
	public static function store_backup_record( $type, $file_name ) {
		global $wpdb;
		$table = $wpdb->prefix . 'guardian_backups';

		$wpdb->insert(
			$table,
			array(
				'backup_type' => sanitize_text_field( $type ),
				'file_path'   => sanitize_text_field( $file_name ),
				'created_at'  => current_time( 'mysql', 1 ),
			),
			array( '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch backups list.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public static function list_backups( $limit = 100 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'guardian_backups';
		$sql   = $wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
			max( 1, min( 500, absint( $limit ) ) )
		);
		return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Export optional files into zip (theme + plugins list only).
	 *
	 * @param string $zip_path Destination zip.
	 * @return void
	 */
	private static function export_selected_files( $zip_path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			return;
		}

		$active_theme = wp_get_theme();
		$plugins      = get_option( 'active_plugins', array() );

		$zip->addFromString( 'active-theme.txt', $active_theme->get_stylesheet() );
		$zip->addFromString( 'active-plugins.json', wp_json_encode( $plugins ) );
		$zip->close();
	}

	/**
	 * Remove temporary directory recursively.
	 *
	 * @param string $dir Directory.
	 * @return void
	 */
	private static function delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
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

	/**
	 * Send backup failure alerts.
	 *
	 * @param string $message Error message.
	 * @param string $type Backup type.
	 * @return void
	 */
	private static function notify_failure( $message, $type ) {
		$settings = get_option( 'wpguardian_settings', array() );
		if ( empty( $settings['alerts_enabled'] ) ) {
			return;
		}

		$email = ! empty( $settings['alert_email'] ) ? sanitize_email( $settings['alert_email'] ) : get_option( 'admin_email' );
		if ( ! is_email( $email ) ) {
			return;
		}

		$subject = sprintf( '[WP Guard] %s backup failed', ucfirst( sanitize_text_field( $type ) ) );
		$body    = sprintf( "Site: %s\nType: %s\nError: %s\nTime: %s", home_url(), sanitize_text_field( $type ), sanitize_text_field( $message ), gmdate( 'c' ) );
		wp_mail( $email, $subject, $body );
		WPGuardian_Activity_Log::write( 'backup_failed', 'backup', 0, get_current_user_id() );
	}
}
