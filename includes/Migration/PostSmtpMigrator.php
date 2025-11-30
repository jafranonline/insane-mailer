<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/AbstractMigrator.php';

class IM_PostSmtpMigrator extends IM_Abstract_Migrator {

	protected string $option_name = 'postman_options';

	protected array $provider_map = [
		'default'         => 'default',
		'smtp'            => 'smtp',
		'gmail_api'       => 'gmail',
		'sendgrid_api'    => 'sendgrid',
		'mailgun_api'     => 'mailgun',
		'mandrill_api'    => 'mandrill',
		'sendinblue_api'  => 'brevo',
		'postmark_api'    => 'postmark',
		'sparkpost_api'   => 'sparkpost',
		'office365'       => 'outlook',
		'zoho_api'        => 'zeptomail',
		'amazonses'       => 'ses',
		'smtp2go_api'     => 'smtp2go',
	];

	public function get_name(): string {
		return 'Post SMTP';
	}

	public function get_slug(): string {
		return 'post-smtp';
	}

	public function get_settings(): array {
		$source = $this->get_source_settings();

		if ( empty( $source ) ) {
			return [];
		}

		$transport_type = $source['transport_type'] ?? 'default';
		$provider = $this->map_provider( $transport_type );

		$settings = [
			'provider'   => $provider,
			'from_email' => $source['sender_email'] ?? '',
			'from_name'  => $source['sender_name'] ?? '',
			'force_from' => $this->normalize_bool( $source['prevent_sender_email_override'] ?? false ),
		];

		$settings['credentials'] = $this->map_credentials( $source, $transport_type, $provider );

		return $settings;
	}

	/**
	 * Map Post SMTP credentials to Insane Mailer format.
	 *
	 * @param array  $source         Full Post SMTP settings.
	 * @param string $transport_type Post SMTP transport type.
	 * @param string $provider       Insane Mailer provider key.
	 * @return array
	 */
	private function map_credentials( array $source, string $transport_type, string $provider ): array {
		$credentials = [];

		switch ( $provider ) {
			case 'smtp':
				$credentials = [
					'host'       => $source['hostname'] ?? '',
					'port'       => $this->normalize_port( $source['port'] ?? 587 ),
					'encryption' => $this->map_encryption( $source['enc_type'] ?? 'tls' ),
					'username'   => $source['basic_auth_username'] ?? '',
					'password'   => $source['basic_auth_password'] ?? '',
					'auth'       => ( $source['auth_type'] ?? 'none' ) !== 'none',
				];
				break;

			case 'sendgrid':
				$credentials = [
					'api_key' => $source['sendgrid_api_key'] ?? '',
				];
				break;

			case 'mailgun':
				$credentials = [
					'api_key' => $source['mailgun_api_key'] ?? '',
					'domain'  => $source['mailgun_domain_name'] ?? '',
					'region'  => ( $source['mailgun_region'] ?? 'us' ) === 'eu' ? 'eu' : 'us',
				];
				break;

			case 'mandrill':
				$credentials = [
					'api_key' => $source['mandrill_api_key'] ?? '',
				];
				break;

			case 'brevo':
				$credentials = [
					'api_key' => $source['sendinblue_api_key'] ?? '',
				];
				break;

			case 'postmark':
				$credentials = [
					'server_token' => $source['postmark_api_key'] ?? '',
				];
				break;

			case 'sparkpost':
				$credentials = [
					'api_key' => $source['sparkpost_api_key'] ?? '',
				];
				break;

			case 'ses':
				$credentials = [
					'access_key' => $source['amazonses_access_key'] ?? '',
					'secret_key' => $source['amazonses_secret_key'] ?? '',
					'region'     => $source['amazonses_region'] ?? 'us-east-1',
				];
				break;

			case 'smtp2go':
				$credentials = [
					'api_key' => $source['smtp2go_api_key'] ?? '',
				];
				break;

			case 'zeptomail':
				$credentials = [
					'api_key' => $source['zoho_api_key'] ?? '',
				];
				break;

			case 'gmail':
				$credentials = [
					'client_id'     => $source['oauth_client_id'] ?? '',
					'client_secret' => $source['oauth_client_secret'] ?? '',
				];
				break;

			case 'outlook':
				$credentials = [
					'client_id'     => $source['oauth_client_id'] ?? '',
					'client_secret' => $source['oauth_client_secret'] ?? '',
				];
				break;
		}

		return $credentials;
	}

	/**
	 * Map Post SMTP encryption type to Insane Mailer format.
	 *
	 * @param string $enc_type Post SMTP encryption type.
	 * @return string
	 */
	private function map_encryption( string $enc_type ): string {
		$map = [
			'none'     => 'none',
			'ssl'      => 'ssl',
			'tls'      => 'tls',
			'starttls' => 'tls',
		];

		return $map[ strtolower( $enc_type ) ] ?? 'tls';
	}
}
