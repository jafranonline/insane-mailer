<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class IM_Queue {

	private static $instance = null;
	private $settings;
	private $is_processing = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings = get_option( 'im_settings', [] );
	}

	public function process() {
		if ( $this->is_processing ) {
			return;
		}

		if ( ! $this->acquire_lock() ) {
			return;
		}

		$this->is_processing = true;

		$bulk_limit = apply_filters( 'im_bulk_limit', $this->settings['bulk_limit'] ?? 50 );
		$emails     = $this->get_pending_emails( $bulk_limit );

		foreach ( $emails as $email ) {
			$this->process_email( $email );
		}

		$this->release_lock();
		$this->is_processing = false;

		do_action( 'im_queue_processed', count( $emails ) );
	}

	private function acquire_lock() {
		$lock = get_transient( 'im_queue_lock' );

		if ( $lock ) {
			return false;
		}

		set_transient( 'im_queue_lock', time(), 5 * MINUTE_IN_SECONDS );
		return true;
	}

	private function release_lock() {
		delete_transient( 'im_queue_lock' );
	}

	private function get_pending_emails( $limit ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'im_emails';
		$now        = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, queue processing needs fresh data.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i
				WHERE status = 'pending'
				AND scheduled_at <= %s
				AND attempts < max_attempts
				ORDER BY priority ASC, created_at ASC
				LIMIT %d",
				$table_name,
				$now,
				$limit
			)
		);
	}

	private function process_email( $email ) {
		$this->update_status( $email->id, 'processing' );

		$this->apply_rate_limit();

		do_action( 'im_before_send', $email );

		$result = $this->send_email( $email );

		if ( $result['success'] ) {
			$this->mark_sent( $email->id, $result );
		} else {
			$this->mark_failed( $email, $result );
		}
	}

	private function send_email( $email ) {
		if ( ! empty( $this->settings['pause_sending'] ) ) {
			return [
				'success'    => true,
				'message_id' => 'debug-' . uniqid(),
				'debug_mode' => true,
			];
		}

		$provider_name = $this->settings['provider'] ?? 'default';

		// Default provider uses WordPress's native wp_mail
		if ( 'default' === $provider_name ) {
			return $this->send_via_wp_mail( $email );
		}

		$provider_class = $this->get_provider_class( $provider_name );

		if ( ! $provider_class ) {
			return [
				'success' => false,
				'error'   => 'Provider not found',
			];
		}

		try {
			$provider = new $provider_class( $this->settings['credentials'] ?? [] );

			$mail_data = [
				'to'          => [
					'email' => $email->to_email,
					'name' => $email->to_name,
				],
				'from'        => [
					'email' => $email->from_email,
					'name' => $email->from_name,
				],
				'reply_to'    => $email->reply_to,
				'subject'     => $email->subject,
				'body_html'   => $email->body_html,
				'body_plain'  => $email->body_plain,
				'headers'     => json_decode( $email->headers, true ),
				'attachments' => json_decode( $email->attachments, true ),
			];

			$result = $provider->send_raw( $mail_data );

			return $result;
		} catch ( Exception $e ) {
			return [
				'success' => false,
				'error'   => $e->getMessage(),
			];
		}
	}

	private function send_via_wp_mail( $email ) {
		$to = $email->to_email;

		if ( ! empty( $email->to_name ) ) {
			$to = sprintf( '%s <%s>', $email->to_name, $email->to_email );
		}

		$headers = [];

		if ( ! empty( $email->from_email ) ) {
			$from = $email->from_email;
			if ( ! empty( $email->from_name ) ) {
				$from = sprintf( '%s <%s>', $email->from_name, $email->from_email );
			}
			$headers[] = 'From: ' . $from;
		}

		if ( ! empty( $email->reply_to ) ) {
			$headers[] = 'Reply-To: ' . $email->reply_to;
		}

		if ( ! empty( $email->body_html ) ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		}

		$custom_headers = json_decode( $email->headers, true );
		if ( ! empty( $custom_headers ) && is_array( $custom_headers ) ) {
			foreach ( $custom_headers as $key => $value ) {
				$headers[] = $key . ': ' . $value;
			}
		}

		$body            = ! empty( $email->body_html ) ? $email->body_html : $email->body_plain;
		$attachment_data = json_decode( $email->attachments, true );
		$attachments     = $attachment_data ? $attachment_data : [];

		// Temporarily remove our hooks to prevent infinite loop
		remove_filter( 'pre_wp_mail', [ IM_Mailer::instance(), 'maybe_queue_mail' ], 10 );
		remove_action( 'phpmailer_init', [ IM_Mailer::instance(), 'intercept_phpmailer' ], 999 );

		$sent = wp_mail( $to, $email->subject, $body, $headers, $attachments );

		// Re-add our hooks
		add_filter( 'pre_wp_mail', [ IM_Mailer::instance(), 'maybe_queue_mail' ], 10, 2 );
		add_action( 'phpmailer_init', [ IM_Mailer::instance(), 'intercept_phpmailer' ], 999 );

		if ( $sent ) {
			return [
				'success'    => true,
				'message_id' => 'wp-mail-' . uniqid(),
			];
		}

		global $phpmailer;
		$error = 'Unknown error';
		if ( isset( $phpmailer ) && $phpmailer instanceof PHPMailer\PHPMailer\PHPMailer ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$error = $phpmailer->ErrorInfo;
		}

		return [
			'success' => false,
			'error'   => $error,
		];
	}

	private function get_provider_class( $provider_name ) {
		$providers = [
			'ses'          => 'IM_Provider_SES',
			'mailgun'      => 'IM_Provider_Mailgun',
			'sendgrid'     => 'IM_Provider_SendGrid',
			'brevo'        => 'IM_Provider_Brevo',
			'sparkpost'    => 'IM_Provider_SparkPost',
			'netcore'      => 'IM_Provider_Netcore',
			'postmark'     => 'IM_Provider_Postmark',
			'elasticemail' => 'IM_Provider_ElasticEmail',
			'smtpcom'      => 'IM_Provider_SmtpCom',
			'smtp'         => 'IM_Provider_SMTP',
			'gmail'        => 'IM_Provider_Gmail',
			'outlook'      => 'IM_Provider_Outlook',
			'socketlabs'   => 'IM_Provider_SocketLabs',
			'mandrill'     => 'IM_Provider_Mandrill',
			'smtp2go'      => 'IM_Provider_Smtp2go',
			'mailtrap'     => 'IM_Provider_Mailtrap',
			'mailjet'      => 'IM_Provider_Mailjet',
			'zeptomail'    => 'IM_Provider_ZeptoMail',
			'mailersend'   => 'IM_Provider_MailerSend',
			'loops'        => 'IM_Provider_Loops',
			'resend'       => 'IM_Provider_Resend',
		];

		if ( ! isset( $providers[ $provider_name ] ) ) {
			return null;
		}

		$class_name = $providers[ $provider_name ];
		$file_path  = IM_PLUGIN_DIR . 'includes/Providers/' . str_replace( 'IM_Provider_', '', $class_name ) . '.php';

		if ( file_exists( $file_path ) ) {
			require_once $file_path;
		}

		return class_exists( $class_name ) ? $class_name : null;
	}

	private function update_status( $email_id, $status ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'im_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$wpdb->update(
			$table_name,
			[
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'id' => $email_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		wp_cache_delete( 'im_queue_stats', 'insane_mailer' );
	}

	private function mark_sent( $email_id, $result ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'im_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$wpdb->update(
			$table_name,
			[
				'status'            => 'sent',
				'sent_at'           => current_time( 'mysql' ),
				'message_id'        => $result['message_id'] ?? null,
				'provider_response' => wp_json_encode( $result ),
				'updated_at'        => current_time( 'mysql' ),
			],
			[ 'id' => $email_id ],
			[ '%s', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		wp_cache_delete( 'im_queue_stats', 'insane_mailer' );

		do_action( 'im_email_sent', $email_id, $result );
	}

	private function mark_failed( $email, $result ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'im_emails';

		$attempts = $email->attempts + 1;
		$status   = $attempts >= $email->max_attempts ? 'failed' : 'pending';

		$retry_delay  = $this->settings['retry_delay'] ?? 300;
		$scheduled_at = 'pending' === $status
			? gmdate( 'Y-m-d H:i:s', time() + $retry_delay )
			: $email->scheduled_at;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$wpdb->update(
			$table_name,
			[
				'status'            => $status,
				'attempts'          => $attempts,
				'scheduled_at'      => $scheduled_at,
				'error_message'     => $result['error'] ?? 'Unknown error',
				'provider_response' => wp_json_encode( $result ),
				'updated_at'        => current_time( 'mysql' ),
			],
			[ 'id' => $email->id ],
			[ '%s', '%d', '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		wp_cache_delete( 'im_queue_stats', 'insane_mailer' );

		if ( 'failed' === $status ) {
			do_action( 'im_email_failed', $email->id, $result['error'] ?? 'Unknown error' );
		}
	}

	private function apply_rate_limit() {
		$rate_limit = apply_filters( 'im_rate_limit', $this->settings['rate_limit'] ?? 14 );

		if ( $rate_limit > 0 ) {
			$delay = (int) ( 1000000 / $rate_limit );
			usleep( $delay );
		}
	}

	public function retry_email( $email_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'im_emails';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, single row lookup.
		$email = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table_name, $email_id ) );

		if ( ! $email ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$wpdb->update(
			$table_name,
			[
				'status'       => 'pending',
				'attempts'     => 0,
				'scheduled_at' => current_time( 'mysql' ),
				'updated_at'   => current_time( 'mysql' ),
			],
			[ 'id' => $email_id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);

		wp_cache_delete( 'im_queue_stats', 'insane_mailer' );

		return true;
	}

	public function get_stats() {
		$cache_key = 'im_queue_stats';
		$stats_result = wp_cache_get( $cache_key, 'insane_mailer' );

		if ( false === $stats_result ) {
			global $wpdb;
			$table_name = $wpdb->prefix . 'im_emails';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
			$stats_result = $wpdb->get_results(
				$wpdb->prepare( 'SELECT status, COUNT(*) as count FROM %i GROUP BY status', $table_name ),
				OBJECT_K
			);

			wp_cache_set( $cache_key, $stats_result, 'insane_mailer', 60 );
		}

		$pending    = isset( $stats_result['pending'] ) ? (int) $stats_result['pending']->count : 0;
		$processing = isset( $stats_result['processing'] ) ? (int) $stats_result['processing']->count : 0;
		$sent       = isset( $stats_result['sent'] ) ? (int) $stats_result['sent']->count : 0;
		$failed     = isset( $stats_result['failed'] ) ? (int) $stats_result['failed']->count : 0;
		$paused     = isset( $stats_result['paused'] ) ? (int) $stats_result['paused']->count : 0;
		$bounced    = isset( $stats_result['bounced'] ) ? (int) $stats_result['bounced']->count : 0;
		$complained = isset( $stats_result['complained'] ) ? (int) $stats_result['complained']->count : 0;

		return [
			'pending'    => $pending,
			'processing' => $processing,
			'sent'       => $sent,
			'failed'     => $failed,
			'paused'     => $paused,
			'bounced'    => $bounced,
			'complained' => $complained,
			'total'      => $pending + $processing + $sent + $failed + $paused + $bounced + $complained,
		];
	}
}
