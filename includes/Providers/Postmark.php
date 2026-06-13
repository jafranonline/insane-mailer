<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_Postmark extends INSANEMAILER_Abstract_Provider {

	public function get_name() {
		return 'Postmark';
	}

	public function send_raw( $mail_data ) {
		$server_token = $this->get_credential( 'server_token' );

		if ( empty( $server_token ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Postmark server token',
			];
		}

		$url = 'https://api.postmarkapp.com/email';

		$from = ! empty( $mail_data['from']['name'] )
			? "{$mail_data['from']['name']} <{$mail_data['from']['email']}>"
			: $mail_data['from']['email'];

		$to = ! empty( $mail_data['to']['name'] )
			? "{$mail_data['to']['name']} <{$mail_data['to']['email']}>"
			: $mail_data['to']['email'];

		$payload = [
			'From'     => $from,
			'To'       => $to,
			'Subject'  => $mail_data['subject'],
			'HtmlBody' => $mail_data['body_html'],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$payload['TextBody'] = $mail_data['body_plain'];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['ReplyTo'] = $mail_data['reply_to'];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['Attachments'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['Attachments'][] = [
						'Name'        => basename( $attachment ),
						'Content'     => base64_encode( file_get_contents( $attachment ) ),
						'ContentType' => mime_content_type( $attachment ),
					];
				}
			}
		}

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'X-Postmark-Server-Token' => $server_token,
					'Content-Type'            => 'application/json',
					'Accept'                  => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = $result['response']['MessageID'] ?? null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	public function test_connection() {
		$server_token = $this->get_credential( 'server_token' );

		if ( empty( $server_token ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Postmark server token',
			];
		}

		$url = 'https://api.postmarkapp.com/server';

		$result = $this->make_request(
			$url,
			[
				'method'  => 'GET',
				'headers' => [
					'X-Postmark-Server-Token' => $server_token,
					'Accept'                  => 'application/json',
				],
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'Postmark connection successful',
		];
	}
}
