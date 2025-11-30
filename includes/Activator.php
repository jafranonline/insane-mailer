<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IM_Activator {

	public static function activate() {
		self::create_tables();
		self::maybe_upgrade_tables();
		self::set_default_settings();
		self::create_upload_directory();
		self::schedule_cron();

		flush_rewrite_rules();
	}

	private static function maybe_upgrade_tables() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'im_emails';

		$column_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'send_mode'",
				DB_NAME,
				$table_name
			)
		);

		if ( ! $column_exists ) {
			$wpdb->query( "ALTER TABLE $table_name ADD COLUMN send_mode ENUM('queue', 'direct') NOT NULL DEFAULT 'queue' AFTER status" );
		}
	}

	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'im_emails';

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			status ENUM('pending', 'processing', 'sent', 'failed', 'bounced', 'complained', 'paused') NOT NULL DEFAULT 'pending',
			send_mode ENUM('queue', 'direct') NOT NULL DEFAULT 'queue',
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

		update_option( 'im_db_version', IM_VERSION );
	}

	private static function set_default_settings() {
		$default_settings = [
			'provider'           => 'default',
			'credentials'        => [],
			'from_email'         => get_option( 'admin_email' ),
			'from_name'          => get_option( 'blogname' ),
			'force_from'         => true,
			'send_mode'          => 'queue',
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

		if ( ! get_option( 'im_settings' ) ) {
			add_option( 'im_settings', $default_settings );
		}
	}

	private static function create_upload_directory() {
		$upload_dir = wp_upload_dir();
		$im_dir  = $upload_dir['basedir'] . '/im-attachments';

		if ( ! file_exists( $im_dir ) ) {
			wp_mkdir_p( $im_dir );

			$htaccess_file = $im_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, 'Deny from all' );
			}

			$index_file = $im_dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, '<?php // Silence is golden' );
			}
		}
	}

	private static function schedule_cron() {
		if ( ! wp_next_scheduled( 'im_process_queue' ) ) {
			wp_schedule_event( time(), 'every_minute', 'im_process_queue' );
		}

		if ( ! wp_next_scheduled( 'im_cleanup_old_emails' ) ) {
			wp_schedule_event( time(), 'daily', 'im_cleanup_old_emails' );
		}
	}
}
