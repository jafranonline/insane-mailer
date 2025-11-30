<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once IM_PLUGIN_DIR . 'includes/Providers/AbstractProvider.php';

class IM_Provider_Outlook extends IM_Abstract_Provider {

	public function get_name() {
		return 'Outlook';
	}

	public function send_raw( $mail_data ) {
		$access_token = $this->get_credential( 'outlook_access_token' );

		if ( empty( $access_token ) ) {
			return [
				'success' => false,
				'error'   => 'Outlook not authenticated. Please authorize the application first.',
			];
		}

		$url = 'https://graph.microsoft.com/v1.0/me/sendMail';

		$payload = [
			'message' => [
				'subject' => $mail_data['subject'],
				'body'    => [
					'contentType' => ! empty( $mail_data['body_html'] ) ? 'HTML' : 'Text',
					'content'     => ! empty( $mail_data['body_html'] ) ? $mail_data['body_html'] : $mail_data['body_plain'],
				],
				'toRecipients' => [
					[
						'emailAddress' => [
							'address' => $mail_data['to']['email'],
							'name'    => $mail_data['to']['name'] ?? '',
						],
					],
				],
				'from' => [
					'emailAddress' => [
						'address' => $mail_data['from']['email'],
						'name'    => $mail_data['from']['name'] ?? '',
					],
				],
			],
			'saveToSentItems' => true,
		];

		if ( ! empty( $mail_data['reply_to'] ) ) {
			$payload['message']['replyTo'] = [
				[
					'emailAddress' => [
						'address' => $mail_data['reply_to'],
					],
				],
			];
		}

		if ( ! empty( $mail_data['attachments'] ) ) {
			$payload['message']['attachments'] = [];
			foreach ( $mail_data['attachments'] as $attachment ) {
				if ( file_exists( $attachment ) ) {
					$payload['message']['attachments'][] = [
						'@odata.type'  => '#microsoft.graph.fileAttachment',
						'name'         => basename( $attachment ),
						'contentType'  => mime_content_type( $attachment ),
						'contentBytes' => base64_encode( file_get_contents( $attachment ) ),
					];
				}
			}
		}

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
			if ( isset( $result['status_code'] ) && $result['status_code'] === 401 ) {
				$refresh_result = $this->refresh_access_token();
				if ( $refresh_result['success'] ) {
					// Retry with new token
					return $this->send_raw( $mail_data );
				}
			}
			return $result;
		}

		return [
			'success'    => true,
			'message_id' => null, // Microsoft Graph sendMail doesn't return message ID
			'response'   => $result['response'],
		];
	}

	private function refresh_access_token() {
		$client_id     = $this->get_credential( 'outlook_client_id' );
		$client_secret = $this->get_credential( 'outlook_client_secret' );
		$refresh_token = $this->get_credential( 'outlook_refresh_token' );

		if ( empty( $refresh_token ) ) {
			return [
				'success' => false,
				'error'   => 'No refresh token available',
			];
		}

		$result = $this->make_request(
			'https://login.microsoftonline.com/common/oauth2/v2.0/token',
			[
				'method' => 'POST',
				'body'   => [
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
					'grant_type'    => 'refresh_token',
					'scope'         => 'https://graph.microsoft.com/Mail.Send offline_access',
				],
			]
		);

		if ( $result['success'] && isset( $result['response']['access_token'] ) ) {
			// Update the stored access token
			$settings                                        = get_option( 'im_settings', [] );
			$settings['credentials']['outlook_access_token'] = $result['response']['access_token'];
			if ( isset( $result['response']['refresh_token'] ) ) {
				$settings['credentials']['outlook_refresh_token'] = $result['response']['refresh_token'];
			}
			update_option( 'im_settings', $settings );

			$this->credentials['outlook_access_token'] = $result['response']['access_token'];

			return [ 'success' => true ];
		}

		return $result;
	}

	public function test_connection() {
		$client_id     = $this->get_credential( 'outlook_client_id' );
		$client_secret = $this->get_credential( 'outlook_client_secret' );
		$access_token  = $this->get_credential( 'outlook_access_token' );

		if ( empty( $client_id ) || empty( $client_secret ) ) {
			return [
				'success' => false,
				'error'   => 'Missing Outlook Client ID or Client Secret',
			];
		}

		if ( empty( $access_token ) ) {
			return [
				'success' => false,
				'error'   => 'Outlook not authenticated. Please authorize the application.',
			];
		}

		$url = 'https://graph.microsoft.com/v1.0/me';

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
			if ( isset( $result['status_code'] ) && $result['status_code'] === 401 ) {
				$refresh_result = $this->refresh_access_token();
				if ( $refresh_result['success'] ) {
					return $this->test_connection();
				}
				return [
					'success' => false,
					'error'   => 'Outlook token expired. Please re-authorize.',
				];
			}
			return $result;
		}

		$email = $result['response']['mail'] ?? $result['response']['userPrincipalName'] ?? 'Unknown';

		return [
			'success' => true,
			'message' => 'Outlook connection successful (' . $email . ')',
		];
	}

	public function get_auth_url() {
		$client_id    = $this->get_credential( 'outlook_client_id' );
		$redirect_uri = admin_url( 'admin.php?page=insane-mailer&oauth=outlook' );

		$params = [
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => 'https://graph.microsoft.com/Mail.Send offline_access',
			'response_mode' => 'query',
		];

		return 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query( $params );
	}

	public function handle_oauth_callback( $code ) {
		$client_id     = $this->get_credential( 'outlook_client_id' );
		$client_secret = $this->get_credential( 'outlook_client_secret' );
		$redirect_uri  = admin_url( 'admin.php?page=insane-mailer&oauth=outlook' );

		$result = $this->make_request(
			'https://login.microsoftonline.com/common/oauth2/v2.0/token',
			[
				'method' => 'POST',
				'body'   => [
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'redirect_uri'  => $redirect_uri,
					'grant_type'    => 'authorization_code',
					'scope'         => 'https://graph.microsoft.com/Mail.Send offline_access',
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
