<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IM_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class IM_Provider_SocketLabs extends IM_Abstract_Provider {

	public function get_name() {
		return 'SocketLabs';
	}

	public function send_raw( $mail_data ) {
		$server_id = $this->get_credential( 'socketlabs_server_id' );
		$api_key   = $this->get_credential( 'socketlabs_api_key' );

		if ( empty( $server_id ) || empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing SocketLabs Server ID or API key',
			];
		}

		$url = 'https://inject.socketlabs.com/api/v1/email';

		$payload = [
			'serverId' => (int) $server_id,
			'apiKey'   => $api_key,
			'messages' => [
				[
					'to' => [
						[
							'emailAddress' => $mail_data['to']['email'],
							'friendlyName' => $mail_data['to']['name'] ?? '',
						],
					],
					'from' => [
						'emailAddress' => $mail_data['from']['email'],
						'friendlyName' => $mail_data['from']['name'] ?? '',
					],
					'subject' => $mail_data['subject'],
				],
			],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['messages'][0]['textBody'] = $mail_data['body_plain'];
		}

		if ( ! empty( $mail_data['body_html'] ) ) {
			$payload['messages'][0]['htmlBody'] = $mail_data['body_html'];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['messages'][0]['replyTo'] = [
				'emailAddress' => $mail_data['reply_to'],
			];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['messages'][0]['attachments'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['messages'][0]['attachments'][] = [
						'name'        => basename( $attachment ),
						'content'     => base64_encode( file_get_contents( $attachment ) ),
						'contentType' => mime_content_type( $attachment ),
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

		$message_id = $result['response']['messageResults'][0]['messageId'] ?? null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	public function test_connection() {
		$server_id = $this->get_credential( 'socketlabs_server_id' );
		$api_key   = $this->get_credential( 'socketlabs_api_key' );

		if ( empty( $server_id ) || empty( $api_key ) ) {
			return [
				'success' => false,
				'error'   => 'Missing SocketLabs Server ID or API key',
			];
		}

		// SocketLabs doesn't have a dedicated test endpoint, so we verify by checking the API
		$url = 'https://inject.socketlabs.com/api/v1/email';

		// Send a minimal validation request
		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'body'    => wp_json_encode(
					[
						'serverId' => (int) $server_id,
						'apiKey'   => $api_key,
						'messages' => [],
					]
				),
			]
		);

		// Even with empty messages, valid credentials will return a specific response
		if ( isset( $result['response']['errorCode'] ) && $result['response']['errorCode'] === 'NoMessages' ) {
			return [
				'success' => true,
				'message' => 'SocketLabs connection successful',
			];
		}

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'SocketLabs connection successful',
		];
	}
}
