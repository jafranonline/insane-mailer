<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Rest/Controller.php';

class INSANEMAILER_Rest_Webhook_Controller extends INSANEMAILER_Rest_Controller {

	/**
	 * Providers whose webhooks are authenticated with a secret entered in the
	 * plugin settings. Amazon SES is verified through the SNS message signature
	 * instead and needs no secret.
	 */
	const SECRET_PROVIDERS = [ 'mailgun', 'sendgrid', 'postmark', 'sparkpost' ];

	/**
	 * Mailgun signs a timestamp; anything older than this is treated as a replay.
	 */
	const MAX_SIGNATURE_AGE = 15 * MINUTE_IN_SECONDS;

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/webhook/(?P<provider>[a-zA-Z0-9_-]+)',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_webhook' ],
				/*
				 * Intentionally public: these requests are POSTed by the email
				 * provider, which cannot present a WordPress user or nonce.
				 * Every request is authenticated in handle_webhook() before
				 * anything is touched — Amazon SES by verifying the SNS
				 * message signature, the other providers against the signing
				 * key or secret stored in the plugin settings. On failure the
				 * request is rejected with 401 and no data is read or written.
				 */
				'permission_callback' => '__return_true',
				'args'                => [
					'provider' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);
	}

	public function handle_webhook( $request ) {
		$provider = sanitize_key( $request->get_param( 'provider' ) );
		$method   = "handle_{$provider}_webhook";

		if ( ! method_exists( $this, $method ) ) {
			return $this->error_response( 'Webhook handler not found for provider: ' . $provider, 404 );
		}

		$body   = $request->get_body();
		$params = $request->get_json_params();

		if ( ! is_array( $params ) ) {
			$params = [];
		}

		$verified = $this->verify_request( $provider, $request, $params, $body );

		if ( is_wp_error( $verified ) ) {
			do_action( 'insanemailer_webhook_rejected', $provider, $verified->get_error_message() );
			return $this->error_response( $verified->get_error_message(), 401 );
		}

		return $this->$method( $params, $body, $request );
	}

	/**
	 * Authenticate the request for the given provider.
	 *
	 * @return true|WP_Error
	 */
	private function verify_request( $provider, $request, array $params, $body ) {
		if ( 'ses' === $provider ) {
			return $this->verify_sns_signature( $params );
		}

		$secret = $this->get_webhook_secret( $provider );

		if ( '' === $secret ) {
			return new WP_Error( 'insanemailer_webhook_secret_missing', 'Webhook secret is not configured for this provider.' );
		}

		switch ( $provider ) {
			case 'mailgun':
				return $this->verify_mailgun( $params, $secret );
			case 'sendgrid':
				return $this->verify_sendgrid( $request, $body, $secret );
			case 'postmark':
				return $this->verify_basic_auth( $request, $secret );
			case 'sparkpost':
				return $this->verify_basic_or_bearer( $request, $secret );
		}

		return new WP_Error( 'insanemailer_webhook_unverifiable', 'Webhook verification is not supported for this provider.' );
	}

	private function get_webhook_secret( $provider ) {
		$settings = get_option( 'insanemailer_settings', [] );
		$secret   = $settings['webhook_secrets'][ $provider ] ?? '';

		return is_string( $secret ) ? trim( $secret ) : '';
	}

	/**
	 * Mailgun: HMAC-SHA256 of timestamp + token with the account's webhook
	 * signing key.
	 */
	private function verify_mailgun( array $params, $secret ) {
		$signature = $params['signature'] ?? [];
		$timestamp = (string) ( $signature['timestamp'] ?? '' );
		$token     = (string) ( $signature['token'] ?? '' );
		$provided  = (string) ( $signature['signature'] ?? '' );

		if ( '' === $timestamp || '' === $token || '' === $provided ) {
			return new WP_Error( 'insanemailer_webhook_unsigned', 'Mailgun signature is missing.' );
		}

		if ( abs( time() - (int) $timestamp ) > self::MAX_SIGNATURE_AGE ) {
			return new WP_Error( 'insanemailer_webhook_expired', 'Mailgun signature has expired.' );
		}

		$expected = hash_hmac( 'sha256', $timestamp . $token, $secret );

		if ( ! hash_equals( $expected, $provided ) ) {
			return new WP_Error( 'insanemailer_webhook_invalid', 'Mailgun signature does not match.' );
		}

		return true;
	}

	/**
	 * SendGrid Signed Event Webhook: ECDSA signature over timestamp + raw body,
	 * checked against the public verification key.
	 */
	private function verify_sendgrid( $request, $body, $public_key ) {
		$signature = (string) $request->get_header( 'x-twilio-email-event-webhook-signature' );
		$timestamp = (string) $request->get_header( 'x-twilio-email-event-webhook-timestamp' );

		if ( '' === $signature || '' === $timestamp ) {
			return new WP_Error( 'insanemailer_webhook_unsigned', 'SendGrid signature headers are missing.' );
		}

		if ( ! function_exists( 'openssl_verify' ) ) {
			return new WP_Error( 'insanemailer_webhook_unverifiable', 'The OpenSSL extension is required to verify SendGrid webhooks.' );
		}

		$decoded = base64_decode( $signature, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Signature transport encoding.

		if ( false === $decoded ) {
			return new WP_Error( 'insanemailer_webhook_invalid', 'SendGrid signature is malformed.' );
		}

		$pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split( preg_replace( '/\s+/', '', $public_key ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
		$key = openssl_pkey_get_public( $pem );

		if ( false === $key ) {
			return new WP_Error( 'insanemailer_webhook_invalid', 'The SendGrid verification key is not a valid public key.' );
		}

		if ( 1 !== openssl_verify( $timestamp . $body, $decoded, $key, OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'insanemailer_webhook_invalid', 'SendGrid signature does not match.' );
		}

		return true;
	}

	/**
	 * Postmark: HTTP Basic Auth; the password is compared to the stored secret.
	 */
	private function verify_basic_auth( $request, $secret ) {
		$password = $this->get_basic_auth_password( $request );

		if ( null === $password || ! hash_equals( $secret, $password ) ) {
			return new WP_Error( 'insanemailer_webhook_invalid', 'Webhook credentials are missing or do not match.' );
		}

		return true;
	}

	/**
	 * SparkPost: either Basic Auth or an OAuth 2.0 bearer token, both carrying
	 * the stored secret.
	 */
	private function verify_basic_or_bearer( $request, $secret ) {
		$authorization = (string) $request->get_header( 'authorization' );

		if ( 0 === stripos( $authorization, 'Bearer ' ) ) {
			$token = trim( substr( $authorization, 7 ) );

			return hash_equals( $secret, $token )
				? true
				: new WP_Error( 'insanemailer_webhook_invalid', 'Webhook token does not match.' );
		}

		return $this->verify_basic_auth( $request, $secret );
	}

	/**
	 * Read the password half of an HTTP Basic Authorization header.
	 *
	 * @return string|null Password, or null when no Basic credentials were sent.
	 */
	private function get_basic_auth_password( $request ) {
		$authorization = (string) $request->get_header( 'authorization' );

		if ( 0 === stripos( $authorization, 'Basic ' ) ) {
			$decoded = base64_decode( trim( substr( $authorization, 6 ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- HTTP Basic Auth transport encoding.

			if ( false !== $decoded && false !== strpos( $decoded, ':' ) ) {
				return substr( $decoded, strpos( $decoded, ':' ) + 1 );
			}
		}

		// Some servers strip the Authorization header and expose the parsed
		// credentials instead.
		if ( isset( $_SERVER['PHP_AUTH_PW'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
		}

		return null;
	}

	/**
	 * Amazon SNS signs every message with a certificate hosted on
	 * sns.<region>.amazonaws.com. Rebuild the canonical string and verify it.
	 *
	 * @see https://docs.aws.amazon.com/sns/latest/dg/sns-verify-signature-of-message.html
	 */
	private function verify_sns_signature( array $params ) {
		$type      = (string) ( $params['Type'] ?? '' );
		$signature = (string) ( $params['Signature'] ?? '' );
		$cert_url  = (string) ( $params['SigningCertURL'] ?? '' );
		$version   = (string) ( $params['SignatureVersion'] ?? '1' );

		if ( '' === $type || '' === $signature || '' === $cert_url ) {
			return new WP_Error( 'insanemailer_webhook_unsigned', 'SNS message is not signed.' );
		}

		if ( ! self::is_sns_url( $cert_url ) || '.pem' !== substr( wp_parse_url( $cert_url, PHP_URL_PATH ), -4 ) ) {
			return new WP_Error( 'insanemailer_webhook_invalid', 'SNS signing certificate URL is not an Amazon SNS endpoint.' );
		}

		if ( ! function_exists( 'openssl_verify' ) ) {
			return new WP_Error( 'insanemailer_webhook_unverifiable', 'The OpenSSL extension is required to verify SNS messages.' );
		}

		if ( 'SubscriptionConfirmation' === $type || 'UnsubscribeConfirmation' === $type ) {
			$fields = [ 'Message', 'MessageId', 'SubscribeURL', 'Timestamp', 'Token', 'TopicArn', 'Type' ];
		} elseif ( 'Notification' === $type ) {
			$fields = [ 'Message', 'MessageId', 'Subject', 'Timestamp', 'TopicArn', 'Type' ];
		} else {
			return new WP_Error( 'insanemailer_webhook_invalid', 'Unknown SNS message type.' );
		}

		$canonical = '';
		foreach ( $fields as $field ) {
			if ( 'Subject' === $field && ! isset( $params['Subject'] ) ) {
				continue;
			}
			if ( ! isset( $params[ $field ] ) || ! is_scalar( $params[ $field ] ) ) {
				return new WP_Error( 'insanemailer_webhook_invalid', 'SNS message is missing signed fields.' );
			}
			$canonical .= $field . "\n" . $params[ $field ] . "\n";
		}

		$certificate = $this->get_sns_certificate( $cert_url );

		if ( is_wp_error( $certificate ) ) {
			return $certificate;
		}

		$decoded = base64_decode( $signature, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Signature transport encoding.
		$key     = false === $decoded ? false : openssl_pkey_get_public( $certificate );

		if ( false === $key ) {
			return new WP_Error( 'insanemailer_webhook_invalid', 'SNS signature or certificate is malformed.' );
		}

		$algorithm = '2' === $version ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;

		if ( 1 !== openssl_verify( $canonical, $decoded, $key, $algorithm ) ) {
			return new WP_Error( 'insanemailer_webhook_invalid', 'SNS signature does not match.' );
		}

		return true;
	}

	/**
	 * Download (and cache for a day) the SNS signing certificate.
	 *
	 * @return string|WP_Error PEM certificate.
	 */
	private function get_sns_certificate( $cert_url ) {
		$cache_key   = 'insanemailer_sns_cert_' . md5( $cert_url );
		$certificate = get_transient( $cache_key );

		if ( is_string( $certificate ) && '' !== $certificate ) {
			return $certificate;
		}

		$response = wp_remote_get( $cert_url, [ 'timeout' => 10 ] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'insanemailer_webhook_unverifiable', 'Could not download the SNS signing certificate.' );
		}

		$certificate = wp_remote_retrieve_body( $response );

		if ( false === strpos( $certificate, 'BEGIN CERTIFICATE' ) ) {
			return new WP_Error( 'insanemailer_webhook_invalid', 'SNS signing certificate is not a PEM certificate.' );
		}

		set_transient( $cache_key, $certificate, DAY_IN_SECONDS );

		return $certificate;
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
			if ( ! is_array( $event ) ) {
				continue;
			}

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
			if ( ! is_array( $event ) ) {
				continue;
			}

			$msys          = $event['msys'] ?? [];
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
	 * Guards the SubscriptionConfirmation handler against SSRF and pins the
	 * signing certificate download by rejecting any host that is not
	 * sns.<region>.amazonaws.com.
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
		if ( empty( $message_id ) || ! is_string( $message_id ) ) {
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
