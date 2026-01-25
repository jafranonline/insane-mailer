<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IM_Attachment {

	public static function store_attachments( $email_id, $attachments ) {
		if ( empty( $attachments ) ) {
			return [];
		}

		$upload_dir = wp_upload_dir();
		$im_dir    = $upload_dir['basedir'] . '/im-attachments/' . $email_id;

		if ( ! file_exists( $im_dir ) ) {
			wp_mkdir_p( $im_dir );
		}

		$stored_paths = [];

		foreach ( $attachments as $attachment_path ) {
			if ( file_exists( $attachment_path ) ) {
				$filename = basename( $attachment_path );
				$new_path = $im_dir . '/' . $filename;

				if ( copy( $attachment_path, $new_path ) ) {
					$stored_paths[] = $new_path;
				}
			}
		}

		return $stored_paths;
	}

	public static function delete_attachments( $email_id ) {
		$upload_dir = wp_upload_dir();
		$im_dir    = $upload_dir['basedir'] . '/im-attachments/' . $email_id;

		if ( ! file_exists( $im_dir ) ) {
			return true;
		}

		$files = glob( $im_dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				wp_delete_file( $file );
			}
		}

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}
		return $wp_filesystem->rmdir( $im_dir );
	}

	public static function cleanup_orphaned_attachments() {
		global $wpdb;

		$upload_dir = wp_upload_dir();
		$im_dir    = $upload_dir['basedir'] . '/im-attachments';

		if ( ! file_exists( $im_dir ) ) {
			return 0;
		}

		$table_name = $wpdb->prefix . 'im_emails';
		$deleted    = 0;

		$dirs = glob( $im_dir . '/*', GLOB_ONLYDIR );
		foreach ( $dirs as $dir ) {
			$email_id = basename( $dir );

			if ( ! is_numeric( $email_id ) ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, orphan check.
			$exists = $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE id = %d', $table_name, $email_id )
			);

			if ( ! $exists ) {
				$files = glob( $dir . '/*' );
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						wp_delete_file( $file );
					}
				}

				global $wp_filesystem;
				if ( empty( $wp_filesystem ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
					WP_Filesystem();
				}
				if ( $wp_filesystem->rmdir( $dir ) ) {
					$deleted++;
				}
			}
		}

		return $deleted;
	}

	public static function get_attachment_info( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return null;
		}

		return [
			'name' => basename( $file_path ),
			'path' => $file_path,
			'size' => filesize( $file_path ),
			'type' => mime_content_type( $file_path ),
		];
	}

	public static function validate_attachment( $file_path, $max_size = 10485760 ) {
		if ( ! file_exists( $file_path ) ) {
			return [
				'valid' => false,
				'error' => 'File does not exist',
			];
		}

		if ( ! is_readable( $file_path ) ) {
			return [
				'valid' => false,
				'error' => 'File is not readable',
			];
		}

		$file_size = filesize( $file_path );
		if ( $file_size > $max_size ) {
			return [
				'valid' => false,
				'error' => sprintf(
					'File size (%s) exceeds maximum allowed size (%s)',
					size_format( $file_size ),
					size_format( $max_size )
				),
			];
		}

		return [
			'valid' => true,
			'size'  => $file_size,
			'type'  => mime_content_type( $file_path ),
		];
	}
}
