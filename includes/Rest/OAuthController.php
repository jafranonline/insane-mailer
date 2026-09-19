<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Rest/Controller.php';

/**
 * OAuth authorization for the Gmail and Outlook providers.
 *
 * An administrator asks for an authorization URL, is sent to Google or
 * Microsoft, and is redirected back to /oauth/callback with a code. The
 * callback is tied to the admin who started it through a single-use, short
 * lived state token, exchanges the code for tokens and stores them in the
 * provider credentials.
 */
class INSANEMAILER_Rest_OAuth_Controller extends INSANEMAILER_Rest_Controller {

	const PROVIDERS = [
		'gmail'   => 'INSANEMAILER_Provider_Gmail',
		'outlook' => 'INSANEMAILER_Provider_Outlook',
	];

	const STATE_TTL = 10 * MINUTE_IN_SECONDS;

	const CONSTANTS = [
		'gmail'   => [
			'client_id'     => 'INSANEMAILER_GMAIL_CLIENT_ID',
			'client_secret' => 'INSANEMAILER_GMAIL_CLIENT_SECRET',
		],
		'outlook' => [
			'client_id'     => 'INSANEMAILER_OUTLOOK_CLIENT_ID',
			'client_secret' => 'INSANEMAILER_OUTLOOK_CLIENT_SECRET',
		],
	];

	public static function redirect_uri() {
		return rest_url( 'insane-mailer/v1/oauth/callback' );
	}

	public static function has_access_token( $provider, array $credentials ) {
		return ! empty( $credentials[ $provider . '_access_token' ] );
	}

	public function register_routes() {
		$provider_arg = [
			'provider' => [
				'type'              => 'string',
				'enum'              => array_keys( self::PROVIDERS ),
				'sanitize_callback' => 'sanitize_key',
			],
		];

		register_rest_route(
			$this->namespace,
			'/oauth/(?P<provider>gmail|outlook)/url',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_authorization_url' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => $provider_arg,
			]
		);

		register_rest_route(
			$this->namespace,
			'/oauth/(?P<provider>gmail|outlook)/disconnect',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'disconnect' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => $provider_arg,
			]
		);

		register_rest_route(
			$this->namespace,
			'/oauth/callback',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'handle_callback' ],
				/*
				 * Intentionally public: Google and Microsoft redirect the
				 * administrator's browser here after sign-in, and that request
				 * carries no REST nonce. The request is useless without the
				 * `state` value, a random single-use token that was issued to a
				 * logged-in administrator by get_authorization_url() minutes
				 * earlier and is verified in handle_callback() before the
				 * code is exchanged. Nothing is read or written otherwise.
				 */
				'permission_callback' => '__return_true',
				'args'                => [
					'state' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'code'  => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'error' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
	}

	public function get_authorization_url( $request ) {
		$provider    = $request->get_param( 'provider' );
		$credentials = $this->get_credentials( $provider );

		if ( empty( $credentials['client_id'] ) || empty( $credentials['client_secret'] ) ) {
			return $this->error_response( 'Save the Client ID and Client Secret before connecting.' );
		}

		$state = wp_generate_password( 32, false );

		set_transient(
			'insanemailer_oauth_state_' . $state,
			[
				'provider' => $provider,
				'user_id'  => get_current_user_id(),
			],
			self::STATE_TTL
		);

		$instance = $this->get_provider_instance( $provider, $credentials );

		return $this->success_response(
			[
				'url'          => $instance->get_auth_url( self::redirect_uri(), $state ),
				'redirect_uri' => self::redirect_uri(),
			]
		);
	}

	public function handle_callback( $request ) {
		$state = (string) $request->get_param( 'state' );
		$code  = (string) $request->get_param( 'code' );
		$error = (string) $request->get_param( 'error' );

		$pending = '' === $state ? false : get_transient( 'insanemailer_oauth_state_' . $state );

		if ( ! is_array( $pending ) || empty( $pending['provider'] ) || ! isset( self::PROVIDERS[ $pending['provider'] ] ) ) {
			return $this->redirect_back( 'error', 'The sign-in link has expired. Please try connecting again.' );
		}

		// Single use.
		delete_transient( 'insanemailer_oauth_state_' . $state );

		$provider = $pending['provider'];

		if ( '' !== $error ) {
			return $this->redirect_back( 'error', 'Authorization was cancelled: ' . $error, $provider );
		}

		if ( '' === $code ) {
			return $this->redirect_back( 'error', 'No authorization code was returned.', $provider );
		}

		$credentials = $this->get_credentials( $provider );
		$instance    = $this->get_provider_instance( $provider, $credentials );
		$result      = $instance->handle_oauth_callback( $code, self::redirect_uri() );

		if ( empty( $result['access_token'] ) ) {
			$message = $result['error'] ?? 'The provider did not return an access token.';
			return $this->redirect_back( 'error', $message, $provider );
		}

		$settings = get_option( 'insanemailer_settings', [] );

		if ( ! isset( $settings['credentials'] ) || ! is_array( $settings['credentials'] ) ) {
			$settings['credentials'] = [];
		}

		$settings['credentials'][ $provider . '_access_token' ] = sanitize_text_field( $result['access_token'] );

		if ( ! empty( $result['refresh_token'] ) ) {
			$settings['credentials'][ $provider . '_refresh_token' ] = sanitize_text_field( $result['refresh_token'] );
		}

		update_option( 'insanemailer_settings', $settings );

		$test = $this->get_provider_instance( $provider, $this->get_credentials( $provider ) )->test_connection();

		set_transient(
			'insanemailer_connection_status',
			[
				'status'    => ! empty( $test['success'] ) ? 'success' : 'error',
				'message'   => $test['message'] ?? $test['error'] ?? '',
				'timestamp' => time(),
			],
			DAY_IN_SECONDS
		);

		return $this->redirect_back( 'success', 'Account connected.', $provider );
	}

	public function disconnect( $request ) {
		$provider = $request->get_param( 'provider' );
		$settings = get_option( 'insanemailer_settings', [] );

		unset(
			$settings['credentials'][ $provider . '_access_token' ],
			$settings['credentials'][ $provider . '_refresh_token' ]
		);

		update_option( 'insanemailer_settings', $settings );
		delete_transient( 'insanemailer_connection_status' );

		return $this->success_response(
			[
				'message'  => 'Account disconnected.',
				'settings' => INSANEMAILER_Settings::mask( $settings ),
			]
		);
	}

	/**
	 * Send the browser back to the Sender tab with the outcome in the query
	 * string, where the admin app turns it into a toast.
	 */
	private function redirect_back( $status, $message, $provider = '' ) {
		$url = add_query_arg(
			[
				'page'                     => 'insane-mailer',
				'insanemailer_oauth'       => $status,
				'insanemailer_oauth_msg'   => rawurlencode( $message ),
				'insanemailer_oauth_prov'  => $provider,
			],
			admin_url( 'options-general.php' )
		) . '#settings/sender';

		wp_safe_redirect( $url );
		exit;
	}

	private function get_credentials( $provider ) {
		$settings    = get_option( 'insanemailer_settings', [] );
		$credentials = isset( $settings['credentials'] ) && is_array( $settings['credentials'] ) ? $settings['credentials'] : [];

		// wp-config.php constants win over stored values, as elsewhere.
		foreach ( self::CONSTANTS[ $provider ] as $key => $constant ) {
			if ( defined( $constant ) && '' !== constant( $constant ) ) {
				$credentials[ $key ] = constant( $constant );
			}
		}

		return $credentials;
	}

	private function get_provider_instance( $provider, array $credentials ) {
		$class = self::PROVIDERS[ $provider ];

		require_once INSANEMAILER_PLUGIN_DIR . 'includes/Providers/' . str_replace( 'INSANEMAILER_Provider_', '', $class ) . '.php';

		return new $class( $credentials );
	}
}
