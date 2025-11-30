<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/AbstractMigrator.php';

class IM_FluentSmtpMigrator extends IM_Abstract_Migrator {

	protected string $option_name = 'fluentmail-settings';

	protected array $provider_map = [
		'smtp'         => 'smtp',
		'ses'          => 'ses',
		'sendgrid'     => 'sendgrid',
		'mailgun'      => 'mailgun',
		'sparkpost'    => 'sparkpost',
		'postmark'     => 'postmark',
		'elasticemail' => 'elasticemail',
		'sendinblue'   => 'brevo',
		'pepipost'     => 'pepipost',
		'outlook'      => 'outlook',
		'gmail'        => 'gmail',
	];

	public function get_name(): string {
		return 'FluentSMTP';
	}

	public function get_slug(): string {
		return 'fluentsmtp';
	}

	public function get_settings(): array {
		$source = $this->get_source_settings();

		if ( empty( $source['connections'] ) ) {
			return [];
		}

		// Get primary connection or first available
		$connection = $source['connections']['primary'] ?? reset( $source['connections'] );

		if ( empty( $connection ) ) {
			return [];
		}

		$provider = $this->map_provider( $connection['provider'] ?? 'smtp' );

		$settings = [
			'provider'   => $provider,
			'from_email' => $connection['sender_email'] ?? '',
			'from_name'  => $connection['sender_name'] ?? '',
			'force_from' => $this->normalize_bool( $connection['force_from_email'] ?? false ),
		];

		$settings['credentials'] = $this->map_credentials( $connection, $provider );

		return $settings;
	}

	/**
	 * Map FluentSMTP credentials to Insane Mailer format.
	 *
	 * @param array  $connection FluentSMTP connection data.
	 * @param string $provider   Insane Mailer provider key.
	 * @return array
	 */
	private function map_credentials( array $connection, string $provider ): array {
		$credentials = [];

		switch ( $provider ) {
			case 'smtp':
				$credentials = [
					'host'       => $connection['host'] ?? '',
					'port'       => $this->normalize_port( $connection['port'] ?? 587 ),
					'encryption' => $this->normalize_encryption( $connection['encryption'] ?? 'tls' ),
					'username'   => $connection['username'] ?? '',
					'password'   => $connection['password'] ?? '',
					'auth'       => ! empty( $connection['username'] ),
				];
				break;

			case 'ses':
				$credentials = [
					'access_key' => $connection['access_key'] ?? '',
					'secret_key' => $connection['secret_key'] ?? '',
					'region'     => $connection['region'] ?? 'us-east-1',
				];
				break;

			case 'sendgrid':
				$credentials = [
					'api_key' => $connection['api_key'] ?? '',
				];
				break;

			case 'mailgun':
				$credentials = [
					'api_key' => $connection['api_key'] ?? '',
					'domain'  => $connection['domain_name'] ?? '',
					'region'  => ( $connection['region'] ?? 'us' ) === 'eu' ? 'eu' : 'us',
				];
				break;

			case 'sparkpost':
				$credentials = [
					'api_key' => $connection['api_key'] ?? '',
				];
				break;

			case 'postmark':
				$credentials = [
					'server_token' => $connection['api_key'] ?? '',
				];
				break;

			case 'elasticemail':
				$credentials = [
					'api_key' => $connection['api_key'] ?? '',
				];
				break;

			case 'brevo':
				$credentials = [
					'api_key' => $connection['api_key'] ?? '',
				];
				break;

			case 'pepipost':
				$credentials = [
					'api_key' => $connection['api_key'] ?? '',
				];
				break;

			case 'gmail':
			case 'outlook':
				$credentials = [
					'client_id'     => $connection['client_id'] ?? '',
					'client_secret' => $connection['client_secret'] ?? '',
				];
				break;
		}

		return $credentials;
	}
}
