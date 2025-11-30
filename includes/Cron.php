<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IM_Cron {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_filter( 'cron_schedules', [ $this, 'add_cron_schedules' ] );
		add_action( 'im_process_queue', [ $this, 'process_queue' ] );
		add_action( 'im_cleanup_old_emails', [ $this, 'cleanup_old_emails' ] );
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
		$settings = get_option( 'im_settings', [] );

		if ( 'wp_cron' !== ( $settings['queue_runner'] ?? 'wp_cron' ) ) {
			return;
		}

		IM_Logger::debug( 'WP Cron processing queue' );

		IM_Queue::instance()->process();
	}

	public function cleanup_old_emails() {
		$settings = get_option( 'im_settings', [] );

		$auto_delete_days = $settings['auto_delete_days'] ?? 14;

		if ( $auto_delete_days <= 0 ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'im_emails';

		$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$auto_delete_days} days" ) );

		$email_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM $table_name
				WHERE status IN ('sent', 'failed', 'bounced', 'complained')
				AND created_at < %s",
				$cutoff_date
			)
		);

		if ( empty( $email_ids ) ) {
			IM_Logger::debug( 'No old emails to clean up' );
			return;
		}

		foreach ( $email_ids as $email_id ) {
			$this->delete_email_attachments( $email_id );
		}

		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name
				WHERE status IN ('sent', 'failed', 'bounced', 'complained')
				AND created_at < %s",
				$cutoff_date
			)
		);

		IM_Logger::info( 'Cleaned up old emails', [
			'deleted_count' => $deleted,
			'cutoff_date'   => $cutoff_date,
		] );

		$this->cleanup_orphan_attachments();
	}

	private function delete_email_attachments( $email_id ) {
		$upload_dir = wp_upload_dir();
		$im_dir    = $upload_dir['basedir'] . '/im-attachments/' . $email_id;

		if ( ! file_exists( $im_dir ) ) {
			return;
		}

		$files = glob( $im_dir . '/*' );
		foreach ( $files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}

		rmdir( $im_dir );
	}

	private function cleanup_orphan_attachments() {
		global $wpdb;

		$upload_dir = wp_upload_dir();
		$im_dir    = $upload_dir['basedir'] . '/im-attachments';

		if ( ! file_exists( $im_dir ) ) {
			return;
		}

		$table_name = $wpdb->prefix . 'im_emails';

		$dirs = glob( $im_dir . '/*', GLOB_ONLYDIR );
		foreach ( $dirs as $dir ) {
			$email_id = basename( $dir );

			if ( ! is_numeric( $email_id ) ) {
				continue;
			}

			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM $table_name WHERE id = %d",
					$email_id
				)
			);

			if ( ! $exists ) {
				$files = glob( $dir . '/*' );
				foreach ( $files as $file ) {
					if ( is_file( $file ) ) {
						unlink( $file );
					}
				}
				rmdir( $dir );

				IM_Logger::debug( 'Removed orphan attachment directory', [ 'email_id' => $email_id ] );
			}
		}
	}
}
