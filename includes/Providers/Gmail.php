<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class INSANEMAILER_Provider_Gmail extends INSANEMAILER_Abstract_Provider {

	public function get_name() {
		return 'Gmail';
	}

	public function send_raw( $mail_data ) {
		$access_token = $this->get_credential( 'gmail_access_token' );

		if ( empty( $access_token ) ) {
			return [
				'success' => false,
				'error'   => 'Gmail not authenticated. Please authorize the application first.',
			];
		}

		$url = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

		// Build RFC 2822 message
		$message = $this->build_raw_message( $mail_data );

		$payload = [
			'raw' => rtrim( strtr( base64_encode( $message ), '+/', '-_' ), '=' ),
		];

		$result = $this->make_request(
			$url,
			[
				'method'  => 'POST',
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( $payload ),
			]
		);

		if ( ! $result['success'] ) {
			// Check if token expired
			if ( isset( $result['status_code'] ) && 401 === $result['status_code'] ) {
				$refresh_result = $this->refresh_access_token();
				if ( $refresh_result['success'] ) {
					// Retry with new token
					return $this->send_raw( $mail_data );
				}
			}
			return $result;
		}

		$message_id = $result['response']['id'] ?? null;

		return [
			'success'    => true,
			'message_id' => $message_id,
			'response'   => $result['response'],
		];
	}

	private function build_raw_message( $mail_data ) {
		$boundary = md5( time() );
		$headers  = [];

		$headers[] = 'From: ' . ( $mail_data['from']['name'] ? $mail_data['from']['name'] . ' <' . $mail_data['from']['email'] . '>' : $mail_data['from']['email'] );
		$headers[] = 'To: ' . ( isset( $mail_data['to']['name'] ) ? $mail_data['to']['name'] . ' <' . $mail_data['to']['email'] . '>' : $mail_data['to']['email'] );
		$headers[] = 'Subject: =?UTF-8?B?' . base64_encode( $mail_data['subject'] ) . '?=';
		$headers[] = 'MIME-Version: 1.0';

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$headers[] = 'Reply-To: ' . $mail_data['reply_to'];
		}

		$has_attachments = ! empty( $mail_data['attachments'] );
		$has_html        = ! empty( $mail_data['body_html'] );
		$has_plain       = ! empty( $mail_data['body_plain'] );

		if ( $has_attachments ) {
			$headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
		} elseif ( $has_html && $has_plain ) {
			$headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
		} elseif ( $has_html ) {
			$headers[] = 'Content-Type: text/html; charset=UTF-8';
		} else {
			$headers[] = 'Content-Type: text/plain; charset=UTF-8';
		}

		$message = implode( "\r\n", $headers ) . "\r\n\r\n";

		if ( $has_attachments || ( $has_html && $has_plain ) ) {
			if ( $has_plain ) {
				$message .= "--{$boundary}\r\n";
				$message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
				$message .= $mail_data['body_plain'] . "\r\n";
			}

			if ( $has_html ) {
				$message .= "--{$boundary}\r\n";
				$message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
				$message .= $mail_data['body_html'] . "\r\n";
			}

			if ( $has_attachments ) {
				foreach ( $mail_data['attachments'] as $attachment ) {
					if ( file_exists( $attachment ) ) {
						$message .= "--{$boundary}\r\n";
						$message .= 'Content-Type: ' . mime_content_type( $attachment ) . '; name="' . basename( $attachment ) . "\"\r\n";
						$message .= "Content-Transfer-Encoding: base64\r\n";
						$message .= 'Content-Disposition: attachment; filename="' . basename( $attachment ) . "\"\r\n\r\n";
						$message .= chunk_split( base64_encode( file_get_contents( $attachment ) ) ) . "\r\n";
					}
				}
			}

			$message .= "--{$boundary}--";
		} else {
			$message .= $has_html ? $mail_data['body_html'] : $mail_data['body_plain'];
		}

		return $message;
	}

	private function refresh_access_token() {
		$client_id     = $this->get_credential_any( [ 'client_id', 'gmail_client_id' ] );
		$client_secret = $this->get_credential_any( [ 'client_secret', 'gmail_client_secret' ] );
		$refresh_token = $this->get_credential( 'gmail_refresh_token' );

		if ( empty( $refresh_token ) ) {
			return [
				'success' => false,
				'error'   => 'No refresh token available',
			];
		}

		$result = $this->make_request(
			'https://oauth2.googleapis.com/token',
			[
				'method' => 'POST',
				'body'   => [
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
				],
			]
		);

		if ( $result['success'] && isset( $result['response']['access_token'] ) ) {
			// Update the stored access token
			$settings                                      = get_option( 'insanemailer_settings', [] );
			$settings['credentials']['gmail_access_token'] = $result['response']['access_token'];
			update_option( 'insanemailer_settings', $settings );

			$this->credentials['gmail_access_token'] = $result['response']['access_token'];

			return [ 'success' => true ];
		}

		return $result;
	}

	public function test_connection() {
		$client_id     = $this->get_credential_any( [ 'client_id', 'gmail_client_id' ] );
		$client_secret = $this->get_credential_any( [ 'client_secret', 'gmail_client_secret' ] );
		$access_token  = $this->get_credential( 'gmail_access_token' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Gmail Client ID or Client Secret',
			];
		}

		if ( empty( $access_token ) ) {
			return [
				'success' => false,
				'error'   => 'Gmail not authenticated. Please authorize the application.',
			];
		}

		$url = 'https://gmail.googleapis.com/gmail/v1/users/me/profile';

		$result = $this->make_request(
			$url,
			[
				'method'  => 'GET',
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
				],
			]
		);

		if ( ! $result['success'] ) {
			if ( isset( $result['status_code'] ) && 401 === $result['status_code'] ) {
				$refresh_result = $this->refresh_access_token();
				if ( $refresh_result['success'] ) {
					return $this->test_connection();
				}
				return [
					'success' => false,
					'error'   => 'Gmail token expired. Please re-authorize.',
				];
			}
			return $result;
		}

		return [
			'success' => true,
			'message' => 'Gmail connection successful (' . $result['response']['emailAddress'] . ')',
		];
	}

	public function get_auth_url( $redirect_uri, $state ) {
		$client_id = $this->get_credential_any( [ 'client_id', 'gmail_client_id' ] );

		$params = [
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => 'https://www.googleapis.com/auth/gmail.send',
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'state'         => $state,
		];

		return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query( $params );
	}

	public function handle_oauth_callback( $code, $redirect_uri ) {
		$client_id     = $this->get_credential_any( [ 'client_id', 'gmail_client_id' ] );
		$client_secret = $this->get_credential_any( [ 'client_secret', 'gmail_client_secret' ] );

		$result = $this->make_request(
			'https://oauth2.googleapis.com/token',
			[
				'method' => 'POST',
				'body'   => [
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
				],
			]
		);

		if ( $result['success'] ) {
			return [
				'access_token'  => $result['response']['access_token'],
				'refresh_token' => $result['response']['refresh_token'] ?? null,
			];
		}

		return $result;
	}
}
