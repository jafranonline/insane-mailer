<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IM_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class IM_Provider_SendGrid extends IM_Abstract_Provider {

	public function get_name() {
		return 'SendGrid';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential( 'api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing SendGrid API key',
			];
		}

		$url = 'https://api.sendgrid.com/v3/mail/send';

		$payload = [
			'personalizations' => [
				[
					'to' => [
						[
							'email' => $mail_data['to']['email'],
							'name'  => $mail_data['to']['name'] ?? '',
						],
					],
					'subject' => $mail_data['subject'],
				],
			],
			'from' => [
				'email' => $mail_data['from']['email'],
				'name'  => $mail_data['from']['name'] ?? '',
			],
			'content' => [],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['content'][] = [
				'type'  => 'text/plain',
				'value' => $mail_data['body_plain'],
			];
		}

		if ( ! empty( $mail_data['body_html'] ) ) {
			$payload['content'][] = [
				'type'  => 'text/html',
				'value' => $mail_data['body_html'],
			];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['reply_to'] = [
				'email' => $mail_data['reply_to'],
			];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['attachments'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['attachments'][] = [
						'content'     => base64_encode( file_get_contents( $attachment ) ),
						'filename'    => basename( $attachment ),
						'type'        => mime_content_type( $attachment ),
						'disposition' => 'attachment',
					];
				}
			}
		}

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = isset( $result['response']['X-Message-Id'] ) ? $result['response']['X-Message-Id'] : null;

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
				'error'   => 'Missing SendGrid API key',
			];
		}

		$url = 'https://api.sendgrid.com/v3/scopes';

		$result = $this->make_request(
			$url,
			[
				'method'  => 'GET',
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
				],
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'SendGrid connection successful',
		];
	}
}
