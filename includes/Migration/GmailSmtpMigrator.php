<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/AbstractMigrator.php';

class IM_GmailSmtpMigrator extends IM_Abstract_Migrator {

	protected string $option_name = 'gmail_smtp_options';

	public function get_name(): string {
		return 'Gmail SMTP';
	}

	public function get_slug(): string {
		return 'gmail-smtp';
	}

	public function get_settings(): array {
		$source = $this->get_source_settings();

		if ( empty( $source ) ) {
			return [];
		}

		$settings = [
			'provider'   => 'gmail',
			'from_email' => $source['from_email'] ?? $source['oauth_user_email'] ?? '',
			'from_name'  => $source['from_name'] ?? '',
			'force_from' => false,
		];

		$settings['credentials'] = [
			'client_id'     => $source['oauth_client_id'] ?? '',
			'client_secret' => $source['oauth_client_secret'] ?? '',
		];

		return $settings;
	}
}
