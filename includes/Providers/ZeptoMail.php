<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_ZeptoMail extends INSANEMAILER_Abstract_Provider {

	public function get_name() {
		return 'ZeptoMail';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential( 'zeptomail_api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing ZeptoMail Send Mail Token',
			];
		}

		$url = 'https://api.zeptomail.com/v1.1/email';

		$payload = [
			'from' => [
				'address' => $mail_data['from']['email'],
				'name'    => $mail_data['from']['name'] ?? '',
			],
			'to' => [
				[
					'email_address' => [
						'address' => $mail_data['to']['email'],
						'name'    => $mail_data['to']['name'] ?? '',
					],
				],
			],
			'subject' => $mail_data['subject'],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['textbody'] = $mail_data['body_plain'];
		}

		if ( ! empty( $mail_data['body_html'] ) ) {
			$payload['htmlbody'] = $mail_data['body_html'];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['reply_to'] = [
				'address' => $mail_data['reply_to'],
			];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['attachments'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['attachments'][] = [
						'name'    => basename( $attachment ),
						'content' => base64_encode( file_get_contents( $attachment ) ),
						'mime_type' => mime_content_type( $attachment ),
					];
				}
			}
		}

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Zoho-enczapikey ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = $result['response']['data'][0]['message_id'] ?? null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	public function test_connection() {
		$api_key = $this->get_credential( 'zeptomail_api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing ZeptoMail Send Mail Token',
			];
		}

		if ( strlen( $api_key ) < 20 ) {
			return [
				'success' => false,
				'error'   => 'Invalid ZeptoMail Send Mail Token format',
			];
		}

		// Make a test request to verify the token.
		$url = 'https://api.zeptomail.com/v1.1/email/bounce';

		$result = $this->make_request(
			$url,
			[
				'method'  => 'GET',
				'headers' => [
					'Authorization' => 'Zoho-enczapikey ' . $api_key,
				],
			]
		);

		// A 401/403 means invalid token, anything else (including 400/404) means token is valid.
		if ( ! $result['success'] && isset( $result['status_code'] ) && in_array( $result['status_code'], [ 401, 403 ], true ) ) {
			return [
				'success' => false,
				'error'   => 'Invalid ZeptoMail Send Mail Token',
			];
		}

		return [
			'success' => true,
			'message' => 'Connected to ZeptoMail successfully',
		];
	}
}
