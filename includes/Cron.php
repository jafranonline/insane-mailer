<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class INSANEMAILER_Cron {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );
		add_action( 'insanemailer_process_queue', [ $this, 'process_queue' ] );
		add_action( 'insanemailer_cleanup_old_emails', [ $this, 'cleanup_old_emails' ] );
	}

	public function add_cron_schedules( $schedules ) {
		$schedules['every_minute'] = [
			'interval' => 60,
			'display'  => __( 'Every Minute', 'insane-mailer' ),
		];

		$schedules['every_5_minutes'] = [
			'interval' => 5 * 60,
			'display'  => __( 'Every 5 Minutes', 'insane-mailer' ),
		];

		$schedules['every_15_minutes'] = [
			'interval' => 15 * 60,
			'display'  => __( 'Every 15 Minutes', 'insane-mailer' ),
		];

		$schedules['every_30_minutes'] = [
			'interval' => 30 * 60,
			'display'  => __( 'Every 30 Minutes', 'insane-mailer' ),
		];

		return $schedules;
	}

	public function process_queue() {
		$settings = get_option( 'insanemailer_settings', [] );

		if ( 'wp_cron' !== ( $settings['queue_runner'] ?? 'wp_cron' ) ) {
			return;
		}

		INSANEMAILER_Queue::instance()->process();
	}

	public function cleanup_old_emails() {
		$settings = get_option( 'insanemailer_settings', [] );

		$auto_delete_days = $settings['auto_delete_days'] ?? 14;

		if ( $auto_delete_days <= 0 ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$auto_delete_days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table cleanup.
		$email_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM %i
				WHERE status IN ('sent', 'failed', 'bounced', 'complained')
				AND created_at < %s",
				$table_name,
				$cutoff_date
			)
		);

		if ( empty( $email_ids ) ) {
			return;
		}

		foreach ( $email_ids as $email_id ) {
			$this->delete_email_attachments( $email_id );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table cleanup.
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i
				WHERE status IN ('sent', 'failed', 'bounced', 'complained')
				AND created_at < %s",
				$table_name,
				$cutoff_date
			)
		);

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );

		$this->cleanup_orphan_attachments();
	}

	private function delete_email_attachments( $email_id ) {
		$upload_dir = wp_upload_dir();
		$insanemailer_dir    = $upload_dir['basedir'] . '/insanemailer-attachments/' . $email_id;

		if ( ! file_exists( $insanemailer_dir ) ) {
			return;
		}

		$files = glob( $insanemailer_dir . '/*' );
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
		$wp_filesystem->rmdir( $insanemailer_dir );
	}

	private function cleanup_orphan_attachments() {
		global $wpdb;

		$upload_dir = wp_upload_dir();
		$insanemailer_dir    = $upload_dir['basedir'] . '/insanemailer-attachments';

		if ( ! file_exists( $insanemailer_dir ) ) {
			return;
		}

		$table_name = $wpdb->prefix . 'insanemailer_emails';

		$dirs = glob( $insanemailer_dir . '/*', GLOB_ONLYDIR );
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
				$wp_filesystem->rmdir( $dir );
			}
		}
	}
}
