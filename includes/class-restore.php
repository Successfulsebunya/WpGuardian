<?php
/**
 * Restore engine.
 *
 * @package WPGuardian
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGuardian_Restore {
	/**
	 * Init restore hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Reserved for future event-driven restores.
	}

	/**
	 * Restore backup by ID.
	 *
	 * @param int $backup_id Backup ID.
	 * @return true|WP_Error
	 */
	public static function restore_backup( $backup_id ) {
		global $wpdb;

		$table  = $wpdb->prefix . 'guardian_backups';
		$backup = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $backup_id ) ),
			ARRAY_A
		);

		if ( empty( $backup ) ) {
			return new WP_Error( 'wpguardian_backup_missing', __( 'Backup not found.', 'wp-guardian' ) );
		}

		$file_path = WPGuardian_Backup::get_backup_dir() . $backup['file_path'];
		if ( ! self::is_valid_backup_file( $file_path ) ) {
			return new WP_Error( 'wpguardian_invalid_backup_file', __( 'Invalid backup file.', 'wp-guardian' ) );
		}

		// Rollback safety: create a full backup snapshot before restore.
		WPGuardian_Backup::create_full_backup( false );

		if ( 'partial' === $backup['backup_type'] ) {
			$result = self::restore_partial_file( $file_path );
		} else {
			$result = self::restore_full_archive( $file_path, 1, 0, $backup_id );
		}

		if ( is_wp_error( $result ) ) {
			self::notify_failure( $result->get_error_message() );
			return $result;
		}

		WPGuardian_Activity_Log::write( 'backup_restore_completed', 'backup', $backup_id, get_current_user_id() );
		return true;
	}

	/**
	 * Resume a full restore from saved checkpoint.
	 *
	 * @param int $backup_id Optional backup ID.
	 * @return true|WP_Error
	 */
	public static function resume_restore( $backup_id = 0 ) {
		global $wpdb;

		$checkpoint = self::get_restore_checkpoint();
		if ( empty( $checkpoint['line_number'] ) ) {
			return new WP_Error( 'wpguardian_no_checkpoint', __( 'No restore checkpoint exists.', 'wp-guardian' ) );
		}

		$resolved_backup_id = absint( $backup_id );
		if ( ! $resolved_backup_id && ! empty( $checkpoint['backup_id'] ) ) {
			$resolved_backup_id = absint( $checkpoint['backup_id'] );
		}
		if ( ! $resolved_backup_id ) {
			return new WP_Error( 'wpguardian_checkpoint_missing_backup', __( 'Checkpoint exists but backup ID is missing.', 'wp-guardian' ) );
		}

		$table  = $wpdb->prefix . 'guardian_backups';
		$backup = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $resolved_backup_id ),
			ARRAY_A
		);
		if ( empty( $backup ) || 'full' !== $backup['backup_type'] ) {
			return new WP_Error( 'wpguardian_resume_invalid_backup', __( 'Checkpoint backup is invalid or not a full backup.', 'wp-guardian' ) );
		}

		$file_path = WPGuardian_Backup::get_backup_dir() . $backup['file_path'];
		if ( ! self::is_valid_backup_file( $file_path ) ) {
			return new WP_Error( 'wpguardian_invalid_backup_file', __( 'Invalid backup file.', 'wp-guardian' ) );
		}

		return self::restore_full_archive( $file_path, (int) $checkpoint['line_number'], (int) $checkpoint['executed_count'], $resolved_backup_id );
	}

	/**
	 * Validate backup file path and extension.
	 *
	 * @param string $path File path.
	 * @return bool
	 */
	public static function is_valid_backup_file( $path ) {
		$real = realpath( $path );
		if ( ! $real ) {
			return false;
		}
		$base_backup_dir = realpath( WPGuardian_Backup::get_backup_dir() );
		if ( ! $base_backup_dir ) {
			return false;
		}
		if ( 0 !== strpos( wp_normalize_path( $real ), wp_normalize_path( $base_backup_dir ) ) ) {
			return false;
		}
		$ext = strtolower( pathinfo( $real, PATHINFO_EXTENSION ) );
		return in_array( $ext, array( 'zip', 'json' ), true );
	}

	/**
	 * Restore partial (post/page) backup.
	 *
	 * @param string $json_path File path.
	 * @return true|WP_Error
	 */
	private static function restore_partial_file( $json_path ) {
		$content = file_get_contents( $json_path );
		$data    = json_decode( $content, true );

		if ( empty( $data['post_id'] ) || empty( $data['post_data'] ) ) {
			return new WP_Error( 'wpguardian_invalid_partial_data', __( 'Partial backup payload is invalid.', 'wp-guardian' ) );
		}

		$post_id = absint( $data['post_id'] );
		$post    = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'wpguardian_restore_post_missing', __( 'Target post is missing.', 'wp-guardian' ) );
		}

		$updated = wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $data['post_data']['post_content'],
				'post_excerpt' => $data['post_data']['post_excerpt'],
				'post_status'  => $data['post_data']['post_status'],
				'post_name'    => $data['post_data']['post_name'],
			),
			true
		);

		return is_wp_error( $updated ) ? $updated : true;
	}

	/**
	 * Restore full backup from zip.
	 *
	 * @param string $zip_path Zip path.
	 * @return true|WP_Error
	 */
	private static function restore_full_archive( $zip_path, $start_line = 1, $executed_count = 0, $backup_id = 0 ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'wpguardian_zip_missing', __( 'ZipArchive extension is unavailable.', 'wp-guardian' ) );
		}

		$temp_dir = WPGuardian_Backup::get_backup_dir() . 'restore-' . wp_generate_password( 8, false, false ) . '/';
		wp_mkdir_p( $temp_dir );

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return new WP_Error( 'wpguardian_restore_zip_open_failed', __( 'Could not open backup archive.', 'wp-guardian' ) );
		}
		$zip->extractTo( $temp_dir );
		$zip->close();

		$sql_file = $temp_dir . 'database.sql';
		if ( ! file_exists( $sql_file ) ) {
			return new WP_Error( 'wpguardian_restore_sql_missing', __( 'No SQL dump found in backup.', 'wp-guardian' ) );
		}

		$result = self::import_sql_file( $sql_file, (int) $start_line, (int) $executed_count, (int) $backup_id );
		self::cleanup_restore_directory( $temp_dir );

		return $result;
	}

	/**
	 * Import SQL file.
	 *
	 * @param string $sql_file SQL file path.
	 * @return true|WP_Error
	 */
	private static function import_sql_file( $sql_file, $start_line = 1, $executed_count = 0, $backup_id = 0 ) {
		global $wpdb;

		$handle = fopen( $sql_file, 'rb' );
		if ( ! $handle ) {
			return new WP_Error( 'wpguardian_restore_sql_invalid', __( 'SQL file is empty or unreadable.', 'wp-guardian' ) );
		}

		$statement   = '';
		$in_string   = false;
		$string_char = '';
		$line_number = 0;
		$start_line  = max( 1, absint( $start_line ) );
		$executed    = max( 0, absint( $executed_count ) );

		while ( false !== ( $line = fgets( $handle ) ) ) {
			++$line_number;
			if ( $line_number < $start_line ) {
				continue;
			}
			$trimmed_line = ltrim( $line );

			// Skip SQL comments outside strings.
			if ( ! $in_string && ( 0 === strpos( $trimmed_line, '-- ' ) || 0 === strpos( $trimmed_line, '#' ) ) ) {
				continue;
			}

			$line_length = strlen( $line );
			for ( $i = 0; $i < $line_length; $i++ ) {
				$char      = $line[ $i ];
				$next_char = ( $i + 1 < $line_length ) ? $line[ $i + 1 ] : '';

				if ( ! $in_string && '/' === $char && '*' === $next_char ) {
					$end_pos = strpos( $line, '*/', $i + 2 );
					if ( false !== $end_pos ) {
						$i = $end_pos + 1;
						continue;
					}
				}

				if ( ( "'" === $char || '"' === $char ) ) {
					$previous_char = ( $i > 0 ) ? $line[ $i - 1 ] : '';
					if ( ! $in_string ) {
						$in_string   = true;
						$string_char = $char;
					} elseif ( $string_char === $char && '\\' !== $previous_char ) {
						$in_string   = false;
						$string_char = '';
					}
				}

				$statement .= $char;

				if ( ! $in_string && ';' === $char ) {
					$result = self::execute_sql_statement( $wpdb, $statement, $line_number );
					if ( is_wp_error( $result ) ) {
						fclose( $handle );
						return $result;
					}
					$statement = '';
					++$executed;
					if ( 0 === $executed % 200 ) {
						self::save_restore_checkpoint( $line_number, $executed, $backup_id );
					}
				}
			}
		}

		fclose( $handle );

		if ( '' !== trim( $statement ) ) {
			$result = self::execute_sql_statement( $wpdb, $statement, $line_number );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			++$executed;
		}

		if ( 0 === $executed ) {
			return new WP_Error( 'wpguardian_restore_sql_empty', __( 'No executable SQL statements were found.', 'wp-guardian' ) );
		}

		delete_option( 'wpguardian_restore_checkpoint' );
		return true;
	}

	/**
	 * Save restore progress checkpoint.
	 *
	 * @param int $line_number Processed line.
	 * @param int $executed_count Statement count.
	 * @return void
	 */
	private static function save_restore_checkpoint( $line_number, $executed_count, $backup_id = 0 ) {
		update_option(
			'wpguardian_restore_checkpoint',
			array(
				'line_number'    => (int) $line_number,
				'executed_count' => (int) $executed_count,
				'backup_id'      => (int) $backup_id,
				'updated_at'     => time(),
			),
			false
		);
	}

	/**
	 * Get restore checkpoint details.
	 *
	 * @return array
	 */
	public static function get_restore_checkpoint() {
		$checkpoint = get_option( 'wpguardian_restore_checkpoint', array() );
		return is_array( $checkpoint ) ? $checkpoint : array();
	}

	/**
	 * Execute single SQL statement.
	 *
	 * @param wpdb  $wpdb DB object.
	 * @param string $statement SQL statement.
	 * @param int    $line_number Approx line number.
	 * @return true|WP_Error
	 */
	private static function execute_sql_statement( $wpdb, $statement, $line_number ) {
		$sql = trim( $statement );
		if ( '' === $sql || ';' === $sql ) {
			return true;
		}

		$result = $wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( false === $result ) {
			return new WP_Error(
				'wpguardian_restore_sql_exec_failed',
				sprintf(
					/* translators: 1: line number, 2: database error */
					__( 'SQL restore failed near line %1$d: %2$s', 'wp-guardian' ),
					(int) $line_number,
					(string) $wpdb->last_error
				)
			);
		}

		return true;
	}

	/**
	 * Remove restore temp directory.
	 *
	 * @param string $dir Directory path.
	 * @return void
	 */
	private static function cleanup_restore_directory( $dir ) {
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
	 * Send restore failure alerts.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private static function notify_failure( $message ) {
		$settings = get_option( 'wpguardian_settings', array() );
		if ( empty( $settings['alerts_enabled'] ) ) {
			return;
		}

		$email = ! empty( $settings['alert_email'] ) ? sanitize_email( $settings['alert_email'] ) : get_option( 'admin_email' );
		if ( ! is_email( $email ) ) {
			return;
		}

		$subject = '[WP Guardian] Restore failed';
		$body    = sprintf( "Site: %s\nError: %s\nTime: %s", home_url(), sanitize_text_field( $message ), gmdate( 'c' ) );
		wp_mail( $email, $subject, $body );
		WPGuardian_Activity_Log::write( 'restore_failed', 'backup', 0, get_current_user_id() );
	}
}
