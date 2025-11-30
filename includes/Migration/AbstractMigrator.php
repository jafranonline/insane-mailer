<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/MigratorInterface.php';

abstract class IM_Abstract_Migrator implements IM_Migrator_Interface {

	/**
	 * WordPress option name for the source plugin.
	 *
	 * @var string
	 */
	protected string $option_name = '';

	/**
	 * Cached source settings.
	 *
	 * @var array|null
	 */
	protected ?array $source_settings = null;

	/**
	 * Provider mapping from source plugin to Insane Mailer.
	 *
	 * @var array
	 */
	protected array $provider_map = [];

	/**
	 * Get the source plugin settings from database.
	 *
	 * @return array
	 */
	protected function get_source_settings(): array {
		if ( null === $this->source_settings ) {
			$this->source_settings = get_option( $this->option_name, [] );
			if ( ! is_array( $this->source_settings ) ) {
				$this->source_settings = [];
			}
		}
		return $this->source_settings;
	}

	/**
	 * Check if source plugin settings exist.
	 *
	 * @return bool
	 */
	public function detect(): bool {
		$settings = $this->get_source_settings();
		return ! empty( $settings );
	}

	/**
	 * Map source provider name to Insane Mailer provider.
	 *
	 * @param string $source_provider Source plugin provider key.
	 * @return string Insane Mailer provider key.
	 */
	protected function map_provider( string $source_provider ): string {
		$source_provider = strtolower( $source_provider );
		return $this->provider_map[ $source_provider ] ?? 'smtp';
	}

	/**
	 * Mask sensitive credential values for preview.
	 *
	 * @param array $settings Settings array.
	 * @return array Settings with masked credentials.
	 */
	protected function mask_credentials( array $settings ): array {
		$sensitive_keys = [
			'password',
			'secret_key',
			'api_key',
			'server_token',
			'client_secret',
		];

		if ( isset( $settings['credentials'] ) && is_array( $settings['credentials'] ) ) {
			foreach ( $settings['credentials'] as $key => $value ) {
				if ( in_array( $key, $sensitive_keys, true ) && ! empty( $value ) ) {
					$settings['credentials'][ $key ] = str_repeat( '*', 8 );
				}
			}
		}

		return $settings;
	}

	/**
	 * Get preview of settings with masked credentials.
	 *
	 * @return array
	 */
	public function get_preview(): array {
		$settings = $this->get_settings();
		return $this->mask_credentials( $settings );
	}

	/**
	 * Normalize encryption value.
	 *
	 * @param string $encryption Source encryption value.
	 * @return string Normalized value (none, ssl, tls).
	 */
	protected function normalize_encryption( string $encryption ): string {
		$encryption = strtolower( trim( $encryption ) );

		$map = [
			'none'     => 'none',
			''         => 'none',
			'ssl'      => 'ssl',
			'tls'      => 'tls',
			'starttls' => 'tls',
		];

		return $map[ $encryption ] ?? 'tls';
	}

	/**
	 * Normalize boolean value from various formats.
	 *
	 * @param mixed $value Value to normalize.
	 * @return bool
	 */
	protected function normalize_bool( $value ): bool {
		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), [ 'yes', 'true', '1', 'on' ], true );
		}

		return (bool) $value;
	}

	/**
	 * Normalize port value.
	 *
	 * @param mixed $port Port value.
	 * @return int
	 */
	protected function normalize_port( $port ): int {
		$port = (int) $port;
		return $port > 0 ? $port : 587;
	}

	/**
	 * Check if provider uses OAuth (tokens cannot be migrated).
	 *
	 * @param string $provider Provider key.
	 * @return bool
	 */
	protected function is_oauth_provider( string $provider ): bool {
		return in_array( $provider, [ 'gmail', 'outlook' ], true );
	}
}
