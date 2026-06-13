<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Rest/Controller.php';

class INSANEMAILER_Rest_Webhook_Controller extends INSANEMAILER_Rest_Controller {

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/webhook/(?P<provider>[a-zA-Z0-9_-]+)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_webhook' ],
				/*
				 * Intentionally public. This endpoint receives unauthenticated
				 * bounce/complaint notifications POSTed by external email
				 * providers (Amazon SES, SendGrid, Mailgun, Postmark, SparkPost,
				 * etc.), which cannot present a WordPress user or nonce. It only
				 * updates the delivery status of an email already in the log by
				 * provider message ID and performs no other privileged action.
				 */
				'permission_callback' => '__return_true',
			]
		);
	}

	public function handle_webhook( $request ) {
		$provider = $request->get_param( 'provider' );
		$body     = $request->get_body();
		$params   = $request->get_json_params();

		$method = "handle_{$provider}_webhook";

		if ( method_exists( $this, $method ) ) {
			return $this->$method( $params, $body, $request );
		}

		return $this->error_response( 'Webhook handler not found for provider: ' . $provider, 404 );
	}

	private function handle_ses_webhook( $params, $body, $request ) {
		$message_type = $request->get_header( 'x-amz-sns-message-type' );

		if ( 'SubscriptionConfirmation' === $message_type ) {
			$subscribe_url = $params['SubscribeURL'] ?? '';
			// Only follow genuine Amazon SNS confirmation URLs. This endpoint is
			// public, so an arbitrary SubscribeURL would otherwise allow SSRF.
			if ( ! empty( $subscribe_url ) && self::is_sns_url( $subscribe_url ) ) {
				wp_remote_get( $subscribe_url );
			}
			return $this->success_response( [ 'message' => 'Subscription confirmed' ] );
		}

		if ( isset( $params['Message'] ) ) {
			$message = json_decode( $params['Message'], true );

			$event_type = $message['notificationType'] ?? '';
			$message_id = $message['mail']['messageId'] ?? '';

			if ( 'Bounce' === $event_type ) {
				$this->update_email_status( $message_id, 'bounced' );
				do_action( 'insanemailer_email_bounce', $message_id, $message );
			} elseif ( 'Complaint' === $event_type ) {
				$this->update_email_status( $message_id, 'complained' );
				do_action( 'insanemailer_email_complaint', $message_id, $message );
			}
		}

		return $this->success_response( [ 'message' => 'Webhook processed' ] );
	}

	private function handle_mailgun_webhook( $params, $body, $request ) {
		$event_data = $params['event-data'] ?? $params;

		$event      = $event_data['event'] ?? '';
		$message_id = $event_data['message']['headers']['message-id'] ?? '';

		if ( 'failed' === $event || 'bounced' === $event ) {
			$this->update_email_status( $message_id, 'bounced' );
			do_action( 'insanemailer_email_bounce', $message_id, $event_data );
		} elseif ( 'complained' === $event ) {
			$this->update_email_status( $message_id, 'complained' );
			do_action( 'insanemailer_email_complaint', $message_id, $event_data );
		}

		return $this->success_response( [ 'message' => 'Webhook processed' ] );
	}

	private function handle_sendgrid_webhook( $params, $body, $request ) {
		foreach ( $params as $event ) {
			$event_type = $event['event'] ?? '';
			$message_id = $event['smtp-id'] ?? '';

			if ( 'bounce' === $event_type || 'dropped' === $event_type ) {
				$this->update_email_status( $message_id, 'bounced' );
				do_action( 'insanemailer_email_bounce', $message_id, $event );
			} elseif ( 'spamreport' === $event_type ) {
				$this->update_email_status( $message_id, 'complained' );
				do_action( 'insanemailer_email_complaint', $message_id, $event );
			}
		}

		return $this->success_response( [ 'message' => 'Webhook processed' ] );
	}

	private function handle_postmark_webhook( $params, $body, $request ) {
		$record_type = $params['RecordType'] ?? '';
		$message_id  = $params['MessageID'] ?? '';

		if ( 'Bounce' === $record_type ) {
			$this->update_email_status( $message_id, 'bounced' );
			do_action( 'insanemailer_email_bounce', $message_id, $params );
		} elseif ( 'SpamComplaint' === $record_type ) {
			$this->update_email_status( $message_id, 'complained' );
			do_action( 'insanemailer_email_complaint', $message_id, $params );
		}

		return $this->success_response( [ 'message' => 'Webhook processed' ] );
	}

	private function handle_sparkpost_webhook( $params, $body, $request ) {
		foreach ( $params as $event ) {
			$msys = $event['msys'] ?? [];
			$message_event = $msys['message_event'] ?? [];

			$event_type = $message_event['type'] ?? '';
			$message_id = $message_event['transmission_id'] ?? '';

			if ( in_array( $event_type, [ 'bounce', 'out_of_band', 'policy_rejection' ], true ) ) {
				$this->update_email_status( $message_id, 'bounced' );
				do_action( 'insanemailer_email_bounce', $message_id, $message_event );
			} elseif ( 'spam_complaint' === $event_type ) {
				$this->update_email_status( $message_id, 'complained' );
				do_action( 'insanemailer_email_complaint', $message_id, $message_event );
			}
		}

		return $this->success_response( [ 'message' => 'Webhook processed' ] );
	}

	/**
	 * Validate that a URL is a genuine Amazon SNS endpoint over HTTPS.
	 *
	 * Guards the SubscriptionConfirmation handler against SSRF by rejecting
	 * any host that is not sns.<region>.amazonaws.com.
	 *
	 * @param string $url URL to validate.
	 * @return bool
	 */
	private static function is_sns_url( $url ) {
		$parts = wp_parse_url( $url );

		if ( empty( $parts['scheme'] ) || 'https' !== $parts['scheme'] || empty( $parts['host'] ) ) {
			return false;
		}

		return (bool) preg_match( '/^sns\.[a-z0-9-]+\.amazonaws\.com$/', $parts['host'] );
	}

	private function update_email_status( $message_id, $status ) {
		if ( empty( $message_id ) ) {
			return;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'insanemailer_emails';

		$message_id = trim( $message_id, '<>' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cache invalidated below.
		$updated = $wpdb->update(
			$table_name,
			[
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			],
			[ 'message_id' => $message_id ],
			[ '%s', '%s' ],
			[ '%s' ]
		);

		if ( $updated ) {
			wp_cache_delete( 'insanemailer_queue_stats', 'insane_mailer' );
		}
	}
}
