<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IM_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class IM_Provider_Mandrill extends IM_Abstract_Provider {

	public function get_name() {
		return 'Mandrill';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential( 'mandrill_api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Mandrill API key',
			];
		}

		$url = 'https://mandrillapp.com/api/1.0/messages/send.json';

		$payload = [
			'key'     => $api_key,
			'message' => [
				'from_email' => $mail_data['from']['email'],
				'from_name'  => $mail_data['from']['name'] ?? '',
				'to'         => [
					[
						'email' => $mail_data['to']['email'],
						'name'  => $mail_data['to']['name'] ?? '',
						'type'  => 'to',
					],
				],
				'subject'    => $mail_data['subject'],
			],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['message']['text'] = $mail_data['body_plain'];
		}

		if ( ! empty( $mail_data['body_html'] ) ) {
			$payload['message']['html'] = $mail_data['body_html'];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['message']['headers'] = [
				'Reply-To' => $mail_data['reply_to'],
			];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['message']['attachments'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['message']['attachments'][] = [
						'type'    => mime_content_type( $attachment ),
						'name'    => basename( $attachment ),
						'content' => base64_encode( file_get_contents( $attachment ) ),
					];
				}
			}
		}

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$response   = $result['response'];
		$message_id = is_array( $response ) && isset( $response[0]['_id'] ) ? $response[0]['_id'] : null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	public function test_connection() {
		$api_key = $this->get_credential( 'mandrill_api_key' );

		if ( empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Mandrill API key',
			];
		}

		$url = 'https://mandrillapp.com/api/1.0/users/ping.json';

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode( [ 'key' => $api_key ] ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'Mandrill connection successful',
		];
	}
}
