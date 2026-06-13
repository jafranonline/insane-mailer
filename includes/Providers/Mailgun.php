<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_Mailgun extends INSANEMAILER_Abstract_Provider {

	public function get_name() {
		return 'Mailgun';
	}

	public function send_raw( $mail_data ) {
		$api_key = $this->get_credential( 'api_key' );
		$domain  = $this->get_credential( 'domain' );
		$region  = $this->get_credential( 'region', 'us' );

		if ( empty( $api_key ) || empty( $domain ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Mailgun API key or domain',
			];
		}

		$base_url = 'us' === $region ? 'https://api.mailgun.net/v3' : 'https://api.eu.mailgun.net/v3';
		$url      = "{$base_url}/{$domain}/messages";

		$from_name  = ! empty( $mail_data['from']['name'] ) ? $mail_data['from']['name'] . ' <' . $mail_data['from']['email'] . '>' : $mail_data['from']['email'];
		$to_address = ! empty( $mail_data['to']['name'] ) ? $mail_data['to']['name'] . ' <' . $mail_data['to']['email'] . '>' : $mail_data['to']['email'];

		$body = [
			'from'    => $from_name,
			'to'      => $to_address,
			'subject' => $mail_data['subject'],
			'html'    => $mail_data['body_html'],
		];

		if ( ! empty( $mail_data['body_plain'] ) ) {
			$body['text'] = $mail_data['body_plain'];
		}

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$body['h:Reply-To'] = $mail_data['reply_to'];
		}

		$attachments = [];
		if ( ! empty( $mail_data['attachments'] ) ) {
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$attachments[] = [
						'name'     => basename( $attachment ),
						'contents' => file_get_contents( $attachment ),
					];
				}
			}
		}

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( 'api:' . $api_key ),
				],
				'body'    => $body,
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		$message_id = $result['response']['id'] ?? null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	public function test_connection() {
		$api_key = $this->get_credential( 'api_key' );
		$domain  = $this->get_credential( 'domain' );
		$region  = $this->get_credential( 'region', 'us' );

		if ( empty( $api_key ) || empty( $domain ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Mailgun API key or domain',
			];
		}

		$base_url = 'us' === $region ? 'https://api.mailgun.net/v3' : 'https://api.eu.mailgun.net/v3';
		$url      = "{$base_url}/{$domain}";

		$result = $this->make_request(
			$url,
			[
				'method'  => 'GET',
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode( 'api:' . $api_key ),
				],
			]
		);

		if ( ! $result['success'] ) {
			return $result;
		}

		return [
			'success' => true,
			'message' => 'Mailgun connection successful',
		];
	}
}
