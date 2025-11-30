<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/AbstractMigrator.php';

class IM_WpSmtpMigrator extends IM_Abstract_Migrator {

	protected string $option_name = 'solid_smtp_providers';

	protected array $provider_map = [
		'other'      => 'smtp',
		'mailgun'    => 'mailgun',
		'brevo'      => 'brevo',
		'sendgrid'   => 'sendgrid',
		'amazon_ses' => 'ses',
		'postmark'   => 'postmark',
	];

	public function get_name(): string {
		return 'Solid Mail';
	}

	public function get_slug(): string {
		return 'wp-smtp';
	}

	public function get_settings(): array {
		$source = $this->get_source_settings();

		if ( empty( $source ) ) {
			return [];
		}

		$connection = $this->get_default_connection( $source );

		if ( empty( $connection ) ) {
			return [];
		}

		$provider_type = $connection['name'] ?? 'other';
		$provider = $this->map_provider( $provider_type );

		$settings = [
			'provider'   => $provider,
			'from_email' => $connection['from_email'] ?? '',
			'from_name'  => $connection['from_name'] ?? '',
			'force_from' => $this->normalize_bool( $connection['force_from'] ?? false ),
		];

		$settings['credentials'] = $this->map_credentials( $connection, $provider );

		return $settings;
	}

	private function get_default_connection( array $source ): array {
		foreach ( $source as $connection ) {
			if ( ! empty( $connection['is_default'] ) ) {
				return $connection;
			}
		}

		return reset( $source ) ?: [];
	}

	private function map_credentials( array $connection, string $provider ): array {
		$credentials = [];

		switch ( $provider ) {
			case 'smtp':
				$credentials = [
					'host'       => $connection['host'] ?? '',
					'port'       => $this->normalize_port( $connection['port'] ?? 587 ),
					'encryption' => $this->normalize_encryption( $connection['encryption'] ?? 'tls' ),
					'username'   => $connection['user'] ?? '',
					'password'   => $connection['pass'] ?? '',
					'auth'       => ! empty( $connection['user'] ),
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
					'domain'  => $connection['domain'] ?? '',
					'region'  => ( $connection['region'] ?? 'us' ) === 'eu' ? 'eu' : 'us',
				];
				break;

			case 'brevo':
				$credentials = [
					'api_key' => $connection['api_key'] ?? '',
				];
				break;

			case 'ses':
				$credentials = [
					'access_key' => $connection['access_key'] ?? '',
					'secret_key' => $connection['secret_access_key'] ?? '',
					'region'     => $connection['region'] ?? 'us-east-1',
				];
				break;

			case 'postmark':
				$credentials = [
					'server_token' => $connection['api_key'] ?? '',
				];
				break;
		}

		return $credentials;
	}
}
