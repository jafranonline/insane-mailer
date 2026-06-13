<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_SparkPost extends INSANEMAILER_Abstract_Provider {

	public function get_name() {
		return 'SparkPost';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential( 'api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing SparkPost API key',
			];
		}

		$url = 'https://api.sparkpost.com/api/v1/transmissions';

		$payload = [
			'content' => [
				'from'    => [
					'email' => $mail_data['from']['email'],
					'name'  => $mail_data['from']['name'] ?? '',
				],
				'subject' => $mail_data['subject'],
				'html'    => $mail_data['body_html'],
			],
			'recipients' => [
				[
					'address' => [
						'email' => $mail_data['to']['email'],
						'name'  => $mail_data['to']['name'] ?? '',
					],
				],
			],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['content']['text'] = $mail_data['body_plain'];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['content']['reply_to'] = $mail_data['reply_to'];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['content']['attachments'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['content']['attachments'][] = [
						'type' => mime_content_type( $attachment ),
						'name' => basename( $attachment ),
						'data' => base64_encode( file_get_contents( $attachment ) ),
					];
				}
			}
		}

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Authorization' => $api_key,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = $result['response']['results']['id'] ?? null;

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
				'error'   => 'Missing SparkPost API key',
			];
		}

		$url = 'https://api.sparkpost.com/api/v1/account';

		$result = $this->make_request(
			$url,
			[
				'method'  => 'GET',
				'headers' => [
					'Authorization' => $api_key,
				],
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'SparkPost connection successful',
		];
	}
}
