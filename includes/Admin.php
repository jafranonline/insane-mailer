<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class INSANEMAILER_Admin {

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		if ( is_admin() ) {
			add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
			add_action( 'admin_notices', [ $this, 'show_pause_notice' ] );
		}
	}

	public function show_pause_notice() {
		$current_screen = get_current_screen();

		if ( $current_screen && 'settings_page_insane-mailer' === $current_screen->id ) {
			return;
		}

		$settings = get_option( 'insanemailer_settings', [] );

		if ( empty( $settings['pause_sending'] ) ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=insane-mailer#settings/preferences' );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<strong>Insane Mailer:</strong>
				<?php esc_html_e( 'Email sending is paused. Emails are being logged but not delivered.', 'insane-mailer' ); ?>
				<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Go to settings', 'insane-mailer' ); ?></a>
			</p>
		</div>
		<?php
	}

	public function add_admin_menu() {
		add_options_page(
			__( 'Insane Mailer', 'insane-mailer' ),
			__( 'Insane Mailer', 'insane-mailer' ),
			'manage_options',
			'insane-mailer',
			[ $this, 'render_admin_page' ]
		);
	}

	public function render_admin_page() {
		echo '<div id="insane-mailer-app"></div>';
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_insane-mailer' !== $hook ) {
			return;
		}

		$js_file  = INSANEMAILER_PLUGIN_DIR . 'assets/script.js';
		$css_file = INSANEMAILER_PLUGIN_DIR . 'assets/style.css';

		if ( file_exists( $js_file ) ) {
			wp_enqueue_script(
				'insanemailer-admin',
				INSANEMAILER_PLUGIN_URL . 'assets/script.js',
				[],
				filemtime( $js_file ),
				true
			);

			wp_localize_script(
				'insanemailer-admin',
				'insaneMailerAdmin',
				[
					'restUrl'          => rest_url( 'insane-mailer/v1' ),
					'nonce'            => wp_create_nonce( 'wp_rest' ),
					'adminUrl'         => admin_url(),
					'siteUrl'          => site_url(),
					'pluginUrl'        => INSANEMAILER_PLUGIN_URL,
					'oauthRedirectUri' => rest_url( 'insane-mailer/v1/oauth/callback' ),
					'settings'         => $this->get_settings_for_frontend(),
					'constants'        => $this->get_defined_constants(),
				]
			);
		}

		if ( file_exists( $css_file ) ) {
			wp_enqueue_style(
				'insanemailer-admin',
				INSANEMAILER_PLUGIN_URL . 'assets/style.css',
				[],
				filemtime( $css_file )
			);
		}
	}

	private function get_defined_constants() {
		$constant_map = [
			// SES
			'ses_access_key'         => 'INSANEMAILER_SES_ACCESS_KEY',
			'ses_secret_key'         => 'INSANEMAILER_SES_SECRET_KEY',
			// SendGrid
			'sendgrid_api_key'       => 'INSANEMAILER_SENDGRID_API_KEY',
			// Mailgun
			'mailgun_api_key'        => 'INSANEMAILER_MAILGUN_API_KEY',
			// Postmark
			'postmark_server_token'  => 'INSANEMAILER_POSTMARK_TOKEN',
			// Brevo
			'brevo_api_key'          => 'INSANEMAILER_BREVO_API_KEY',
			// SparkPost
			'sparkpost_api_key'      => 'INSANEMAILER_SPARKPOST_API_KEY',
			// Mailjet
			'mailjet_api_key'        => 'INSANEMAILER_MAILJET_API_KEY',
			'mailjet_secret_key'     => 'INSANEMAILER_MAILJET_SECRET_KEY',
			// Elastic Email
			'elasticemail_api_key'   => 'INSANEMAILER_ELASTICEMAIL_API_KEY',
			// SMTP.com
			'smtpcom_api_key'        => 'INSANEMAILER_SMTPCOM_API_KEY',
			// Netcore/Pepipost
			'pepipost_api_key'       => 'INSANEMAILER_PEPIPOST_API_KEY',
			// Cloudflare
			'cloudflare_api_token'   => 'INSANEMAILER_CLOUDFLARE_API_TOKEN',
			'cloudflare_account_id'  => 'INSANEMAILER_CLOUDFLARE_ACCOUNT_ID',
			// Resend
			'resend_api_key'         => 'INSANEMAILER_RESEND_API_KEY',
			// MailerSend
			'mailersend_api_key'     => 'INSANEMAILER_MAILERSEND_API_KEY',
			// Mailtrap
			'mailtrap_api_key'       => 'INSANEMAILER_MAILTRAP_API_KEY',
			// Loops
			'loops_api_key'          => 'INSANEMAILER_LOOPS_API_KEY',
			// Mandrill
			'mandrill_api_key'       => 'INSANEMAILER_MANDRILL_API_KEY',
			// SMTP2GO
			'smtp2go_api_key'        => 'INSANEMAILER_SMTP2GO_API_KEY',
			// SocketLabs
			'socketlabs_server_id'   => 'INSANEMAILER_SOCKETLABS_SERVER_ID',
			'socketlabs_api_key'     => 'INSANEMAILER_SOCKETLABS_API_KEY',
			// ZeptoMail
			'zeptomail_api_key'      => 'INSANEMAILER_ZEPTOMAIL_TOKEN',
			// Gmail
			'gmail_client_id'        => 'INSANEMAILER_GMAIL_CLIENT_ID',
			'gmail_client_secret'    => 'INSANEMAILER_GMAIL_CLIENT_SECRET',
			// Outlook
			'outlook_client_id'      => 'INSANEMAILER_OUTLOOK_CLIENT_ID',
			'outlook_client_secret'  => 'INSANEMAILER_OUTLOOK_CLIENT_SECRET',
			// Custom SMTP
			'smtp_username'          => 'INSANEMAILER_SMTP_USERNAME',
			'smtp_password'          => 'INSANEMAILER_SMTP_PASSWORD',
		];

		$defined = [];
		foreach ( $constant_map as $field => $constant ) {
			$defined[ $field ] = defined( $constant ) && ! empty( constant( $constant ) );
		}

		return $defined;
	}

	private function get_settings_for_frontend() {
		$default_settings = [
			'provider'         => 'default',
			'credentials'      => [],
			'from_email'       => get_option( 'admin_email' ),
			'from_name'        => get_option( 'blogname' ),
			'reply_to'         => '',
			'force_from'       => true,
			'auto_plain_text'  => true,
			'auto_delete_days' => 14,
			'pause_sending'    => false,
			'setup_completed'  => false,
		];

		$settings = get_option( 'insanemailer_settings', $default_settings );
		$settings = array_merge( $default_settings, $settings );

		return INSANEMAILER_Settings::mask( $settings );
	}

	public function register_rest_routes() {
		$controllers = [
			'Settings',
			'Emails',
			'Webhook',
			'Migration',
			'Stats',
			'OAuth',
		];

		foreach ( $controllers as $controller ) {
			$class_name = 'INSANEMAILER_Rest_' . $controller . '_Controller';
			$file_path  = INSANEMAILER_PLUGIN_DIR . 'includes/Rest/' . $controller . 'Controller.php';

			if ( file_exists( $file_path ) ) {
				require_once $file_path;

				if ( class_exists( $class_name ) ) {
					$controller_instance = new $class_name();
					$controller_instance->register_routes();
				}
			}
		}
	}
}
