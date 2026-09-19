<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Rest/Controller.php';

class INSANEMAILER_Rest_Migration_Controller extends INSANEMAILER_Rest_Controller {

	/**
	 * Available migrators.
	 *
	 * @var array
	 */
	private array $migrators = [];

	/**
	 * Plugin file mapping for deactivation.
	 *
	 * @var array
	 */
	private array $plugin_files = [
		'fluentsmtp'    => 'fluent-smtp/fluent-smtp.php',
		'wp-mail-smtp'  => 'wp-mail-smtp/wp_mail_smtp.php',
		'post-smtp'     => 'post-smtp/postman-smtp.php',
		'easy-wp-smtp'  => 'easy-wp-smtp/easy-wp-smtp.php',
		'gmail-smtp'    => 'gmail-smtp/main.php',
		'gosmtp'        => 'gosmtp/gosmtp.php',
		'smtp-mailer'   => 'smtp-mailer/main.php',
		'wp-smtp'       => 'wp-smtp/wp-smtp.php',
		'yaysmtp'       => 'yaysmtp/yay-smtp.php',
	];

	public function __construct() {
		$this->load_migrators();
	}

	/**
	 * Load all available migrators.
	 */
	private function load_migrators(): void {
		$migrator_files = [
			'FluentSmtpMigrator',
			'WpMailSmtpMigrator',
			'PostSmtpMigrator',
			'EasyWpSmtpMigrator',
			'GmailSmtpMigrator',
			'GoSmtpMigrator',
			'SmtpMailerMigrator',
			'WpSmtpMigrator',
			'YaySmtpMigrator',
		];

		foreach ( $migrator_files as $file ) {
			$path = INSANEMAILER_PLUGIN_DIR . 'includes/Migration/' . $file . '.php';
			if ( file_exists( $path ) ) {
				require_once $path;
				$class_name = 'INSANEMAILER_' . $file;
				if ( class_exists( $class_name ) ) {
					$this->migrators[] = new $class_name();
				}
			}
		}
	}

	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/migration/detect',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'detect_plugins' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/migration/preview/(?P<slug>[a-z0-9_-]+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'preview_import' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'slug' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/migration/import',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'import_settings' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/migration/deactivate',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'deactivate_plugin' ],
				'permission_callback' => [ $this, 'permission_check' ],
			]
		);
	}

	/**
	 * Detect installed SMTP plugins with importable settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function detect_plugins( $request ) {
		$detected = [];

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		foreach ( $this->migrators as $migrator ) {
			$slug = $migrator->get_slug();
			$plugin_file = $this->plugin_files[ $slug ] ?? '';
			$is_installed = ! empty( $plugin_file ) && is_plugin_active( $plugin_file );
			$has_settings = $migrator->detect();

			if ( $has_settings || $is_installed ) {
				$preview = $has_settings ? $migrator->get_preview() : [];
				$detected[] = [
					'slug'         => $slug,
					'name'         => $migrator->get_name(),
					'provider'     => $preview['provider'] ?? '',
					'from_email'   => $preview['from_email'] ?? '',
					'from_name'    => $preview['from_name'] ?? '',
					'is_oauth'     => in_array( $preview['provider'] ?? '', [ 'gmail', 'outlook' ], true ),
					'has_settings' => $has_settings,
					'is_active'    => $is_installed,
				];
			}
		}

		return $this->success_response( [
			'plugins' => $detected,
			'count'   => count( $detected ),
		] );
	}

	/**
	 * Preview what will be imported from a specific plugin.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function preview_import( $request ) {
		$slug = $request->get_param( 'slug' );

		$migrator = $this->get_migrator_by_slug( $slug );

		if ( ! $migrator ) {
			return $this->error_response( 'Plugin not found', 404 );
		}

		if ( ! $migrator->detect() ) {
			return $this->error_response( 'No settings found for this plugin', 404 );
		}

		$preview = $migrator->get_preview();
		$is_oauth = in_array( $preview['provider'] ?? '', [ 'gmail', 'outlook' ], true );

		return $this->success_response( [
			'slug'     => $slug,
			'name'     => $migrator->get_name(),
			'settings' => $preview,
			'is_oauth' => $is_oauth,
			'warnings' => $is_oauth ? [ 'OAuth tokens cannot be migrated. You will need to re-authorize after import.' ] : [],
		] );
	}

	/**
	 * Import settings from a specific plugin.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function import_settings( $request ) {
		$params = $request->get_json_params();
		$slug = sanitize_key( $params['slug'] ?? '' );

		if ( empty( $slug ) ) {
			return $this->error_response( 'Plugin slug is required' );
		}

		$migrator = $this->get_migrator_by_slug( $slug );

		if ( ! $migrator ) {
			return $this->error_response( 'Plugin not found', 404 );
		}

		if ( ! $migrator->detect() ) {
			return $this->error_response( 'No settings found for this plugin', 404 );
		}

		$new_settings = $migrator->get_settings();

		if ( empty( $new_settings ) ) {
			return $this->error_response( 'Failed to parse settings from source plugin' );
		}

		// Merge with existing settings to preserve non-migrated fields
		$current_settings = get_option( 'insanemailer_settings', [] );
		$merged_settings = array_merge( $current_settings, $new_settings );

		// Preserve local settings that an import shouldn't overwrite
		$preserve_keys = [
			'auto_delete_days',
			'pause_sending',
		];

		foreach ( $preserve_keys as $key ) {
			if ( isset( $current_settings[ $key ] ) ) {
				$merged_settings[ $key ] = $current_settings[ $key ];
			}
		}

		update_option( 'insanemailer_settings', $merged_settings );

		return $this->success_response( [
			'message'  => sprintf( 'Settings imported from %s', $migrator->get_name() ),
			'settings' => $this->mask_credentials( $merged_settings ),
		] );
	}

	/**
	 * Deactivate a plugin after migration.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function deactivate_plugin( $request ) {
		$params = $request->get_json_params();
		$slug = sanitize_key( $params['slug'] ?? '' );

		if ( empty( $slug ) ) {
			return $this->error_response( 'Plugin slug is required' );
		}

		if ( ! isset( $this->plugin_files[ $slug ] ) ) {
			return $this->error_response( 'Unknown plugin slug', 404 );
		}

		$plugin_file = $this->plugin_files[ $slug ];

		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active( $plugin_file ) ) {
			return $this->success_response( [
				'message' => 'Plugin is already inactive',
				'slug'    => $slug,
			] );
		}

		deactivate_plugins( $plugin_file );

		$migrator = $this->get_migrator_by_slug( $slug );
		$name = $migrator ? $migrator->get_name() : $slug;

		return $this->success_response( [
			'message' => sprintf( '%s has been deactivated', $name ),
			'slug'    => $slug,
		] );
	}

	/**
	 * Get migrator by slug.
	 *
	 * @param string $slug Migrator slug.
	 * @return INSANEMAILER_Migrator_Interface|null
	 */
	private function get_migrator_by_slug( string $slug ): ?INSANEMAILER_Migrator_Interface {
		foreach ( $this->migrators as $migrator ) {
			if ( $migrator->get_slug() === $slug ) {
				return $migrator;
			}
		}
		return null;
	}

	/**
	 * Mask sensitive credentials in settings array.
	 *
	 * @param array $settings Settings array.
	 * @return array
	 */
	private function mask_credentials( array $settings ): array {
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
}
