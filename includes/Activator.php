<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class INSANEMAILER_Activator {

	public static function activate() {
		self::migrate_legacy_prefix();
		self::create_tables();
		self::maybe_upgrade_tables();
		self::set_default_settings();
		self::create_upload_directory();
		self::schedule_cron();

		flush_rewrite_rules();
	}

	/**
	 * One-time migration from the legacy "im_" prefix to "insanemailer_".
	 *
	 * Renames the emails table, copies options, and clears orphaned cron
	 * events left behind by versions that used the shorter prefix. Runs
	 * before create_tables() so the rename happens before any empty table
	 * would be created.
	 */
	private static function migrate_legacy_prefix() {
		global $wpdb;

		$old_table = $wpdb->prefix . 'im_emails';
		$new_table = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check, not cacheable.
		$old_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old_table ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check, not cacheable.
		$new_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new_table ) );

		if ( $old_exists && ! $new_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-time table rename.
			$wpdb->query( $wpdb->prepare( 'RENAME TABLE %i TO %i', $old_table, $new_table ) );
		}

		// Migrate options.
		foreach ( [ 'im_settings' => 'insanemailer_settings', 'im_db_version' => 'insanemailer_db_version' ] as $old_option => $new_option ) {
			$old_value = get_option( $old_option, null );
			if ( null !== $old_value && false === get_option( $new_option, false ) ) {
				add_option( $new_option, $old_value );
			}
			if ( null !== $old_value ) {
				delete_option( $old_option );
			}
		}

		// Clear orphaned cron events scheduled under the old hook names.
		wp_clear_scheduled_hook( 'im_process_queue' );
		wp_clear_scheduled_hook( 'im_cleanup_old_emails' );

		// Drop stale transients from the old prefix.
		delete_transient( 'im_connection_status' );
		delete_transient( 'im_queue_lock' );
	}

	private static function maybe_upgrade_tables() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check, not cacheable.
		$column_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'send_mode'",
				DB_NAME,
				$table_name
			)
		);

		if ( ! $column_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration.
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN send_mode VARCHAR(10) NOT NULL DEFAULT %s AFTER status', $table_name, 'queue' ) );
		}

		// Convert ENUM to VARCHAR if needed.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check, not cacheable.
		$status_type = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'status'",
				DB_NAME,
				$table_name
			)
		);

		if ( 'enum' === strtolower( $status_type ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration.
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i MODIFY COLUMN status VARCHAR(20) NOT NULL DEFAULT %s', $table_name, 'pending' ) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema migration.
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i MODIFY COLUMN send_mode VARCHAR(10) NOT NULL DEFAULT %s', $table_name, 'queue' ) );
		}
	}

	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'insanemailer_emails';

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			send_mode VARCHAR(10) NOT NULL DEFAULT 'queue',
			priority INT NOT NULL DEFAULT 2,
			to_email VARCHAR(255) NOT NULL,
			to_name VARCHAR(255) DEFAULT NULL,
			from_email VARCHAR(255) NOT NULL,
			from_name VARCHAR(255) DEFAULT NULL,
			reply_to VARCHAR(255) DEFAULT NULL,
			subject VARCHAR(255) DEFAULT NULL,
			body_html LONGTEXT DEFAULT NULL,
			body_plain TEXT DEFAULT NULL,
			headers TEXT DEFAULT NULL,
			attachments TEXT DEFAULT NULL,
			attempts INT NOT NULL DEFAULT 0,
			max_attempts INT NOT NULL DEFAULT 3,
			scheduled_at DATETIME DEFAULT NULL,
			sent_at DATETIME DEFAULT NULL,
			provider VARCHAR(50) DEFAULT NULL,
			provider_response TEXT DEFAULT NULL,
			message_id VARCHAR(255) DEFAULT NULL,
			error_message TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY priority (priority),
			KEY scheduled_at (scheduled_at),
			KEY created_at (created_at),
			KEY to_email (to_email),
			KEY message_id (message_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'insanemailer_db_version', INSANEMAILER_VERSION );
	}

	private static function set_default_settings() {
		$default_settings = [
			'provider'           => 'default',
			'credentials'        => [],
			'from_email'         => get_option( 'admin_email' ),
			'from_name'          => get_option( 'blogname' ),
			'force_from'         => true,
			'send_mode'          => 'direct',
			'auto_plain_text'    => true,
			'queue_runner'       => 'wp_cron',
			'external_token'     => wp_generate_password( 32, false ),
			'bulk_limit'         => 50,
			'rate_limit'         => 14,
			'max_retries'        => 3,
			'retry_delay'        => 300,
			'priority_bypass'    => [
				'retrieve_password',
				'woocommerce_order_status_completed',
			],
			'auto_delete_days'   => 14,
			'network_mode'       => 'per_site',
			'allow_site_override' => false,
		];

		if ( ! get_option( 'insanemailer_settings' ) ) {
			add_option( 'insanemailer_settings', $default_settings );
		}
	}

	private static function create_upload_directory() {
		$upload_dir = wp_upload_dir();
		$insanemailer_dir     = $upload_dir['basedir'] . '/insanemailer-attachments';

		if ( ! file_exists( $insanemailer_dir ) ) {
			wp_mkdir_p( $insanemailer_dir );

			global $wp_filesystem;
			if ( empty( $wp_filesystem ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				WP_Filesystem();
			}

			$htaccess_file = $insanemailer_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				$wp_filesystem->put_contents( $htaccess_file, 'Deny from all', FS_CHMOD_FILE );
			}

			$index_file = $insanemailer_dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				$wp_filesystem->put_contents( $index_file, '<?php // Silence is golden', FS_CHMOD_FILE );
			}
		}
	}

	private static function schedule_cron() {
		if ( ! wp_next_scheduled( 'insanemailer_process_queue' ) ) {
			wp_schedule_event( time(), 'every_minute', 'insanemailer_process_queue' );
		}

		if ( ! wp_next_scheduled( 'insanemailer_cleanup_old_emails' ) ) {
			wp_schedule_event( time(), 'daily', 'insanemailer_cleanup_old_emails' );
		}
	}
}
