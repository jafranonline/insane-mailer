<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class INSANEMAILER_Activator {

	public static function activate() {
		self::migrate_legacy_prefix();
		self::create_tables();
		self::upgrade_schema();
		self::set_default_settings();
		self::upgrade_settings();
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
		foreach ( [
			'im_settings' => 'insanemailer_settings',
			'im_db_version' => 'insanemailer_db_version',
		] as $old_option => $new_option ) {
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

	/**
	 * Bring a table created by a queue-era version up to the current schema.
	 *
	 * Records any undelivered queue rows for the one-time drain, settles the
	 * legacy "completed" status, then drops the columns only the queue used.
	 * Every step is guarded so re-running the upgrade is harmless.
	 */
	private static function upgrade_schema() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'insanemailer_emails';

		self::flag_queue_drain( $table_name );

		// Direct sends used to be logged as "completed", a status no filter,
		// stat or cleanup query has ever matched.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, one-time migration.
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE %i SET status = 'sent', sent_at = COALESCE( sent_at, updated_at ) WHERE status = 'completed'",
				$table_name
			)
		);

		foreach ( [ 'send_mode', 'priority', 'attempts', 'max_attempts', 'scheduled_at' ] as $column ) {
			if ( self::column_exists( $table_name, $column ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Column name from a fixed list.
				$wpdb->query( "ALTER TABLE `{$table_name}` DROP COLUMN `{$column}`" );
			}
		}
	}

	/**
	 * Note the highest undelivered queue row so INSANEMAILER_Queue_Drain can work
	 * through the backlog after the upgrade. Rows created later are left alone.
	 */
	private static function flag_queue_drain( $table_name ) {
		global $wpdb;

		if ( ! self::column_exists( $table_name, 'send_mode' ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, one-time migration.
		$max_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT MAX(id) FROM %i WHERE status IN ('pending', 'processing', 'paused')",
				$table_name
			)
		);

		if ( $max_id > 0 ) {
			update_option( 'insanemailer_queue_drain_pending', $max_id, false );
		}
	}

	private static function column_exists( $table_name, $column ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check, not cacheable.
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = %s',
				DB_NAME,
				$table_name,
				$column
			)
		);
	}

	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'insanemailer_emails';

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			status VARCHAR(20) NOT NULL DEFAULT 'sending',
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
			sent_at DATETIME DEFAULT NULL,
			provider VARCHAR(50) DEFAULT NULL,
			provider_response TEXT DEFAULT NULL,
			message_id VARCHAR(255) DEFAULT NULL,
			error_message TEXT DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY created_at (created_at),
			KEY to_email (to_email),
			KEY message_id (message_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'insanemailer_db_version', INSANEMAILER_VERSION );
	}

	private static function set_default_settings() {
		$default_settings = self::get_default_settings();

		if ( ! get_option( 'insanemailer_settings' ) ) {
			add_option( 'insanemailer_settings', $default_settings );
		}
	}

	/**
	 * The full set of settings the plugin recognises.
	 */
	public static function get_default_settings() {
		return [
			'provider'         => 'default',
			'credentials'      => [],
			'from_email'       => get_option( 'admin_email' ),
			'from_name'        => get_option( 'blogname' ),
			'force_from'       => true,
			'auto_plain_text'  => true,
			'auto_delete_days' => 14,
			'pause_sending'    => false,
		];
	}

	/**
	 * Drop settings that only queue mode used, so they stop travelling through
	 * export / import and the settings screen.
	 */
	private static function upgrade_settings() {
		$settings = get_option( 'insanemailer_settings' );

		if ( ! is_array( $settings ) ) {
			return;
		}

		$removed = [
			'send_mode',
			'queue_runner',
			'cron_token',
			'cron_interval',
			'bulk_limit',
			'rate_limit',
			'max_retries',
			'retry_delay',
			'priority_bypass',
			'priority_express',
			'network_mode',
			'allow_site_override',
		];

		$cleaned = array_diff_key( $settings, array_flip( $removed ) );

		if ( count( $cleaned ) !== count( $settings ) ) {
			update_option( 'insanemailer_settings', $cleaned );
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
		// Left behind by queue mode.
		wp_clear_scheduled_hook( 'insanemailer_process_queue' );
		delete_transient( 'insanemailer_queue_lock' );

		if ( ! wp_next_scheduled( 'insanemailer_cleanup_old_emails' ) ) {
			wp_schedule_event( time(), 'daily', 'insanemailer_cleanup_old_emails' );
		}
	}
}
