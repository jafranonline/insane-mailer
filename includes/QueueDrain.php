<?php
/**
 * One-time drain of emails left behind by queue mode.
 *
 * Queue mode is gone, so rows still sitting at pending / processing / paused
 * would never be delivered by anything. The upgrade routine records the highest
 * such row id in the insanemailer_queue_drain_pending option, and this class
 * works through them in small batches, giving each message exactly one delivery
 * attempt before it settles as sent or failed.
 *
 * Only rows at or below the recorded id are touched, so messages paused after
 * the upgrade are left alone.
 *
 * @package InsaneMailer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class INSANEMAILER_Queue_Drain {

	const OPTION   = 'insanemailer_queue_drain_pending';
	const HOOK     = 'insanemailer_drain_legacy_queue';
	const CRON_BATCH  = 25;
	const ADMIN_BATCH = 5;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( self::HOOK, [ $this, 'run_cron_pass' ] );
		add_action( 'admin_init', [ $this, 'run_admin_pass' ] );

		$this->maybe_schedule();
	}

	private function maybe_schedule() {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK );
		}
	}

	public function run_cron_pass() {
		$this->drain( self::CRON_BATCH );
	}

	/**
	 * Small pass on admin page loads, so sites with WP-Cron disabled still finish.
	 */
	public function run_admin_pass() {
		$this->drain( self::ADMIN_BATCH );
	}

	private function drain( $limit ) {
		$max_id = (int) get_option( self::OPTION, 0 );

		if ( $max_id <= 0 ) {
			return;
		}

		$settings = get_option( 'insanemailer_settings', [] );

		// Sending is paused; hold the backlog until it is switched back on.
		if ( ! empty( $settings['pause_sending'] ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, one-time migration.
		$emails = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				WHERE id <= %d
				AND status IN ('pending', 'processing', 'paused')
				ORDER BY id ASC
				LIMIT %d",
				$table_name,
				$max_id,
				$limit
			)
		);

		if ( empty( $emails ) ) {
			$this->finish();
			return;
		}

		$mailer = INSANEMAILER_Mailer::instance();

		foreach ( $emails as $email ) {
			/*
			 * Claim the row before attempting delivery. A send that dies partway
			 * leaves the row out of the drain set rather than being retried, which
			 * is what "one attempt each" means here.
			 */
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, one-time migration.
			$wpdb->update(
				$table_name,
				[
					'status'     => 'sending',
					'updated_at' => current_time( 'mysql' ),
				],
				[ 'id' => $email->id ],
				[ '%s', '%s' ],
				[ '%d' ]
			);

			$mailer->send_logged_email( $email );
		}

		wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );

		$this->maybe_schedule();
	}

	private function finish() {
		delete_option( self::OPTION );
		wp_clear_scheduled_hook( self::HOOK );
		delete_transient( 'insanemailer_queue_lock' );
	}
}
