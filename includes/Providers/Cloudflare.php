<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_Cloudflare extends INSANEMAILER_Abstract_Provider {

	const API_BASE = 'https://api.cloudflare.com/client/v4/accounts/';

	// Cloudflare rejects any message whose total size exceeds 5 MiB.
	const MAX_MESSAGE_SIZE = 5242880;

	/**
	 * Headers Cloudflare accepts in the `headers` object. Anything else (other
	 * than an X- prefixed header) makes Cloudflare reject the whole request,
	 * so unknown headers are stripped instead of failing the email.
	 */
	private $allowed_headers = [
		'in-reply-to',
		'references',
		'thread-index',
		'thread-topic',
		'list-unsubscribe',
		'list-unsubscribe-post',
		'list-id',
		'list-archive',
		'list-help',
		'list-owner',
		'list-post',
		'list-subscribe',
		'precedence',
		'auto-submitted',
		'content-language',
		'keywords',
		'comments',
		'importance',
		'priority',
		'sensitivity',
		'organization',
		'require-recipient-valid-since',
		'expires',
		'reply-by',
		'archived-at',
	];

	private $error_messages = [
		10000 => 'Account not found. Check the Cloudflare Account ID.',
		10001 => 'Cloudflare rejected the message format.',
		10002 => 'Cloudflare internal server error. Try again shortly.',
		10003 => 'Operation not implemented by Cloudflare Email Sending.',
		10004 => 'Cloudflare rate limit exceeded. Lower the send rate and retry.',
		10100 => 'Cloudflare authentication service is temporarily unavailable.',
		10101 => 'Invalid or missing Cloudflare API token.',
		10102 => 'This Cloudflare API token does not have permission to send email. Grant it "Email Sending: Edit".',
		10103 => 'Wrong Cloudflare token type for this endpoint. Use an API token, not a Global API key.',
		10105 => 'This Cloudflare account is not entitled to use Email Sending.',
		10200 => 'Message exceeds the Cloudflare 5 MiB size limit.',
		10201 => 'Message is missing a content length.',
		10202 => 'Cloudflare rejected the message. Check that the From address uses a domain onboarded for Email Sending.',
		10203 => 'Email Sending is disabled for this Cloudflare zone or account.',
	];

	public function get_name() {
		return 'Cloudflare Email Service';
	}

	public function send_raw( $mail_data ) {
		$api_token  = $this->get_api_token();
		$account_id = $this->get_account_id();

		$missing = $this->check_credentials( $api_token, $account_id );
		if ( $missing ) {
			return $missing;
		}

		$from_email = $mail_data['from']['email'] ?? '';

		if ( ! $this->validate_email( $from_email ) ) {
			return [
				'success' => false,
				'error'   => 'Invalid sender address for Cloudflare Email Service',
			];
		}

		$body_html  = $mail_data['body_html'] ?? '';
		$body_plain = $mail_data['body_plain'] ?? '';

		if ( '' === trim( (string) $body_html ) && '' === trim( (string) $body_plain ) ) {
			return [
				'success' => false,
				'error'   => 'Cloudflare requires either an HTML or a plain text body',
			];
		}

		$payload = [
			'from'    => $this->build_address( $from_email, $mail_data['from']['name'] ?? '' ),
			'to'      => [ $this->build_address( $mail_data['to']['email'] ?? '', $mail_data['to']['name'] ?? '' ) ],
			'subject' => $mail_data['subject'] ?? '',
		];

		if ( '' !== trim( (string) $body_html ) ) {
			$payload['html'] = $body_html;
		}

		if ( '' !== trim( (string) $body_plain ) ) {
			$payload['text'] = $body_plain;
		}

		if ( ! empty( $mail_data['reply_to'] ) && $this->validate_email( $mail_data['reply_to'] ) ) {
			$payload['reply_to'] = $mail_data['reply_to'];
		}

		$dropped_headers  = [];
		$filtered_headers = $this->filter_headers( $mail_data['headers'] ?? [], $dropped_headers );

		if ( ! empty( $filtered_headers ) ) {
			$payload['headers'] = $filtered_headers;
		}

		$attachments = $this->build_attachments( $mail_data['attachments'] ?? [] );

		if ( ! empty( $attachments ) ) {
			$payload['attachments'] = $attachments;
		}

		$encoded = wp_json_encode( $payload );

		if ( strlen( $encoded ) > self::MAX_MESSAGE_SIZE ) {
			return [
				'success' => false,
				'error'   => sprintf(
					'Message is %s, which exceeds the Cloudflare limit of %s.',
					size_format( strlen( $encoded ) ),
					size_format( self::MAX_MESSAGE_SIZE )
				),
			];
		}

		$result = $this->make_request(
			self::API_BASE . rawurlencode( $account_id ) . '/email/sending/send',
			[
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Bearer ' . $api_token,
					'Content-Type'  => 'application/json',
				],
				'body'    => $encoded,
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		// Cloudflare can answer HTTP 200 with success:false, which make_request() treats as a pass.
		$response = $result['response'];

		if ( ! is_array( $response ) || empty( $response['success'] ) ) {
			return [
				'success'     => false,
				'error'       => $this->extract_error_message( $response, '' ),
				'status_code' => $result['status_code'],
				'response'    => $response,
			];
		}

		$delivery = $response['result'] ?? [];
		$accepted = array_merge( (array) ( $delivery['delivered'] ?? [] ), (array) ( $delivery['queued'] ?? [] ) );
		$bounced  = (array) ( $delivery['permanent_bounces'] ?? [] );
		$dropped  = (array) ( $delivery['suppressed_recipients'] ?? [] );

		// A 200 with nothing accepted is a delivery failure, not a success.
		if ( empty( $accepted ) && ( ! empty( $bounced ) || ! empty( $dropped ) ) ) {
			return [
				'success'     => false,
				'error'       => ! empty( $bounced )
					? 'Cloudflare permanently bounced: ' . implode( ', ', $bounced )
					: 'Recipient is on the Cloudflare suppression list: ' . implode( ', ', $dropped ),
				'status_code' => $result['status_code'],
				'response'    => $response,
			];
		}

		$return = [
			'success'    => true,
			'message_id' => $delivery['message_id'] ?? null,
			'response'   => $response,
		];

		if ( ! empty( $dropped_headers ) ) {
			$count = count( $dropped_headers );

			$return['warnings'] = [
				sprintf(
					'%d %s stripped because Cloudflare does not allow %s: %s',
					$count,
					1 === $count ? 'header was' : 'headers were',
					1 === $count ? 'it' : 'them',
					implode( ', ', $dropped_headers )
				),
			];
		}

		return $return;
	}

	public function test_connection() {
		$api_token  = $this->get_api_token();
		$account_id = $this->get_account_id();

		$missing = $this->check_credentials( $api_token, $account_id );
		if ( $missing ) {
			return $missing;
		}

		// Account scoped and covered by the same Email Sending permission, so this
		// validates the token and the account ID in a single call.
		$result = $this->make_request(
			self::API_BASE . rawurlencode( $account_id ) . '/email/sending/suppressions?per_page=1',
			[
				'method'  => 'GET',
				'headers' => [
					'Authorization' => 'Bearer ' . $api_token,
				],
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$response = $result['response'];

		if ( ! is_array( $response ) || empty( $response['success'] ) ) {
			return [
				'success' => false,
				'error'   => $this->extract_error_message( $response, '' ),
			];
		}

		return [
			'success' => true,
			'message' => 'Cloudflare Email Service connection successful',
		];
	}

	/**
	 * Cloudflare returns errors as a numeric code plus a machine-readable slug,
	 * so the base implementation would surface raw JSON.
	 */
	protected function extract_error_message( $data, $body ) {
		if ( is_array( $data ) && ! empty( $data['errors'] ) && is_array( $data['errors'] ) ) {
			$messages = [];

			foreach ( $data['errors'] as $error ) {
				if ( ! is_array( $error ) ) {
					continue;
				}

				$code = isset( $error['code'] ) ? (int) $error['code'] : 0;

				if ( isset( $this->error_messages[ $code ] ) ) {
					$messages[] = $this->error_messages[ $code ];
				} elseif ( ! empty( $error['message'] ) ) {
					$messages[] = $error['message'];
				}
			}

			if ( ! empty( $messages ) ) {
				return implode( ' ', array_unique( $messages ) );
			}
		}

		return parent::extract_error_message( $data, $body );
	}

	private function get_api_token() {
		return $this->get_credential_any( [ 'api_key', 'cloudflare_api_token' ] );
	}

	private function get_account_id() {
		return $this->get_credential_any( [ 'account_id', 'cloudflare_account_id' ] );
	}

	private function check_credentials( $api_token, $account_id ) {
		if ( empty( $api_token ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Cloudflare API token',
			];
		}

		if ( empty( $account_id ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Cloudflare Account ID',
			];
		}

		return null;
	}

	private function build_address( $email, $name = '' ) {
		$address = [ 'address' => $email ];

		if ( ! empty( $name ) ) {
			$address['name'] = $name;
		}

		return $address;
	}

	/**
	 * Keep only headers Cloudflare allows, recording the rest so the log can
	 * explain what went missing.
	 */
	private function filter_headers( $headers, &$dropped ) {
		$dropped = [];

		if ( ! is_array( $headers ) || empty( $headers ) ) {
			return [];
		}

		$filtered = [];

		foreach ( $headers as $key => $value ) {
			if ( ! is_string( $key ) || '' === trim( $key ) ) {
				continue;
			}

			$key        = trim( $key );
			$normalized = strtolower( $key );

			$is_custom  = (bool) preg_match( '/^x-[a-z0-9\-_]{1,98}$/', $normalized );
			$is_allowed = $is_custom || in_array( $normalized, $this->allowed_headers, true );

			if ( $is_allowed ) {
				$filtered[ $key ] = is_scalar( $value ) ? (string) $value : wp_json_encode( $value );
			} else {
				$dropped[] = $key;
			}
		}

		return $filtered;
	}

	/**
	 * Normalizes both attachment shapes the plugin produces: a flat list of
	 * file paths (queue mode) and PHPMailer's getAttachments() rows (direct mode).
	 */
	private function build_attachments( $attachments ) {
		if ( ! is_array( $attachments ) || empty( $attachments ) ) {
			return [];
		}

		$built = [];

		foreach ( $attachments as $attachment ) {
			$content = '';
			$name    = '';
			$type    = '';

			if ( is_array( $attachment ) ) {
				// PHPMailer rows: 0 path/content, 2 name, 4 type, 5 is-string-attachment.
				$source    = $attachment[0] ?? '';
				$name      = $attachment[2] ?? '';
				$type      = $attachment[4] ?? '';
				$is_string = ! empty( $attachment[5] );

				if ( $is_string ) {
					$content = $source;
				} elseif ( is_string( $source ) && file_exists( $source ) ) {
					$content = file_get_contents( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local attachment file.
					$name    = $name ? $name : basename( $source );
					$type    = $type ? $type : mime_content_type( $source );
				} else {
					continue;
				}
			} else {
				if ( ! is_string( $attachment ) || ! file_exists( $attachment ) ) {
					continue;
				}

				$content = file_get_contents( $attachment ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local attachment file.
				$name    = basename( $attachment );
				$type    = mime_content_type( $attachment );
			}

			if ( false === $content || '' === $content ) {
				continue;
			}

			$built[] = [
				'content'     => base64_encode( $content ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Cloudflare requires base64 attachments.
				'filename'    => $name ? $name : 'attachment',
				'type'        => $type ? $type : 'application/octet-stream',
				'disposition' => 'attachment',
			];
		}

		return $built;
	}
}
