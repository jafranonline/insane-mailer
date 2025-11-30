<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/AbstractMigrator.php';

class IM_SmtpMailerMigrator extends IM_Abstract_Migrator {

	protected string $option_name = 'smtp_mailer_options';

	public function get_name(): string {
		return 'SMTP Mailer';
	}

	public function get_slug(): string {
		return 'smtp-mailer';
	}

	public function get_settings(): array {
		$source = $this->get_source_settings();

		if ( empty( $source ) ) {
			return [];
		}

		$settings = [
			'provider'   => 'smtp',
			'from_email' => $source['from_email'] ?? '',
			'from_name'  => $source['from_name'] ?? '',
			'force_from' => $this->normalize_bool( $source['force_from_email'] ?? false ),
		];

		$password = $source['smtp_password'] ?? '';
		if ( ! empty( $password ) ) {
			$password = base64_decode( $password );
		}

		$settings['credentials'] = [
			'host'       => $source['smtp_host'] ?? '',
			'port'       => $this->normalize_port( $source['smtp_port'] ?? 587 ),
			'encryption' => $this->normalize_encryption( $source['type_of_encryption'] ?? 'tls' ),
			'username'   => $source['smtp_username'] ?? '',
			'password'   => $password,
			'auth'       => ( $source['smtp_auth'] ?? 'true' ) === 'true',
		];

		return $settings;
	}
}
