<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IM_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class IM_Provider_Brevo extends IM_Abstract_Provider {

	public function get_name() {
		return 'Brevo (Sendinblue)';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential( 'api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Brevo API key',
			];
		}

		$url = 'https://api.brevo.com/v3/smtp/email';

		$payload = [
			'sender' => [
				'email' => $mail_data['from']['email'],
				'name'  => $mail_data['from']['name'] ?? '',
			],
			'to' => [
				[
					'email' => $mail_data['to']['email'],
					'name'  => $mail_data['to']['name'] ?? '',
				],
			],
			'subject'     => $mail_data['subject'],
			'htmlContent' => $mail_data['body_html'],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['textContent'] = $mail_data['body_plain'];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['replyTo'] = [
				'email' => $mail_data['reply_to'],
			];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['attachment'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['attachment'][] = [
						'content' => base64_encode( file_get_contents( $attachment ) ),
						'name'    => basename( $attachment ),
					];
				}
			}
		}

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'api-key'      => $api_key,
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = $result['response']['messageId'] ?? null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	public function test_connection() {
		$api_key = $this->get_credential( 'api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Brevo API key',
			];
		}

		$url = 'https://api.brevo.com/v3/account';

		$result = $this->make_request(
			$url,
			[
				'method'  => 'GET',
				'headers' => [
					'api-key' => $api_key,
				],
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'Brevo connection successful',
		];
	}
}
