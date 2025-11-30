<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IM_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class IM_Provider_Mailjet extends IM_Abstract_Provider {

	public function get_name() {
		return 'Mailjet';
	}

	public function send_raw( $mail_data ) {
		$api_key    = $this->get_credential( 'mailjet_api_key' );
		$secret_key = $this->get_credential( 'mailjet_secret_key' );

		if ( empty( $api_key ) || empty( $secret_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Mailjet API key or Secret key',
			];
		}

		$url = 'https://api.mailjet.com/v3.1/send';

		$payload = [
			'Messages' => [
				[
					'From' => [
						'Email' => $mail_data['from']['email'],
						'Name'  => $mail_data['from']['name'] ?? '',
					],
					'To' => [
						[
							'Email' => $mail_data['to']['email'],
							'Name'  => $mail_data['to']['name'] ?? '',
						],
					],
					'Subject' => $mail_data['subject'],
				],
			],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['Messages'][0]['TextPart'] = $mail_data['body_plain'];
		}

		if ( ! empty( $mail_data['body_html'] ) ) {
			$payload['Messages'][0]['HTMLPart'] = $mail_data['body_html'];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['Messages'][0]['ReplyTo'] = [
				'Email' => $mail_data['reply_to'],
			];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['Messages'][0]['Attachments'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['Messages'][0]['Attachments'][] = [
						'ContentType'   => mime_content_type( $attachment ),
						'Filename'      => basename( $attachment ),
						'Base64Content' => base64_encode( file_get_contents( $attachment ) ),
					];
				}
			}
		}

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . $secret_key ),
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = $result['response']['Messages'][0]['To'][0]['MessageID'] ?? null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	public function test_connection() {
		$api_key    = $this->get_credential( 'mailjet_api_key' );
		$secret_key = $this->get_credential( 'mailjet_secret_key' );

		if ( empty( $api_key ) || empty( $secret_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Mailjet API key or Secret key',
			];
		}

		$url = 'https://api.mailjet.com/v3/REST/sender';

		$result = $this->make_request(
			$url,
			[
				'method'  => 'GET',
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( $api_key . ':' . $secret_key ),
				],
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'Mailjet connection successful',
		];
	}
}
