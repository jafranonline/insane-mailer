<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_Netcore extends INSANEMAILER_Abstract_Provider {

	public function get_name() {
		return 'Netcore';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential( 'api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Netcore API key',
			];
		}

		$url = 'https://emailapi.netcoresmartech.com/v2/transactional';

		$payload = [
			'from' => [
				'email' => $mail_data['from']['email'],
				'name'  => $mail_data['from']['name'] ?? '',
			],
			'subject' => $mail_data['subject'],
			'content' => [
				'html' => $mail_data['body_html'],
			],
			'personalizations' => [
				[
					'to' => [
						[
							'email' => $mail_data['to']['email'],
							'name'  => $mail_data['to']['name'] ?? '',
						],
					],
				],
			],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['content']['text'] = $mail_data['body_plain'];
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
						'name'    => basename( $attachment ),
						'content' => base64_encode( file_get_contents( $attachment ) ),
						'type'    => mime_content_type( $attachment ),
					];
				}
			}
		}

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'api_key'      => $api_key,
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = $result['response']['message_id'] ?? null;

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
				'error'   => 'Missing Netcore API key',
			];
		}

		// Make a test request to verify the API key.
		$url = 'https://emailapi.netcoresmartech.com/v2/stats';

		$result = $this->make_request(
			$url,
			[
				'method'  => 'GET',
				'headers' => [
					'api_key' => $api_key,
				],
			]
		);

		if ( ! $result['success'] ) {
			return [
				'success' => false,
				'error'   => $result['error'] ?? 'Invalid API key',
			];
		}

		return [
			'success' => true,
			'message' => 'Connected to Netcore successfully',
		];
	}
}
