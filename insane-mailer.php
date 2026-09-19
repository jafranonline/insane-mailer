<?php
/**
 * Plugin Name: Insane Mailer - SMTP, Email Logs & Delivery
 * Plugin URI: https://arraystory.com/insane-mailer
 * Description: Fast, reliable SMTP and email delivery. Send through 20+ providers with complete email logs and bounce handling.
 * Version: 1.1.0
 * Author: Jafran Hasan
 * Author URI: https://jaf.run
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: insane-mailer
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Tested up to: 7.0
 *
 * @package InsaneMailer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INSANEMAILER_VERSION', '1.1.0' );
define( 'INSANEMAILER_PLUGIN_FILE', __FILE__ );
define( 'INSANEMAILER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INSANEMAILER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'INSANEMAILER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once INSANEMAILER_PLUGIN_DIR . 'includes/Activator.php';
require_once INSANEMAILER_PLUGIN_DIR . 'includes/Deactivator.php';

register_activation_hook( __FILE__, [ 'INSANEMAILER_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'INSANEMAILER_Deactivator', 'deactivate' ] );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'insanemailer_plugin_action_links' );

/**
 * Add settings link to plugin action links.
 *
 * @param array $links Existing action links.
 * @return array Modified action links.
 */
function insanemailer_plugin_action_links( array $links ): array {
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		admin_url( 'options-general.php?page=insane-mailer' ),
		__( 'Settings', 'insane-mailer' )
	);
	array_unshift( $links, $settings_link );
	return $links;
}

/**
 * Initialize plugin core functionality.
 */
function insanemailer_init() {
	require_once INSANEMAILER_PLUGIN_DIR . 'includes/Mailer.php';
	require_once INSANEMAILER_PLUGIN_DIR . 'includes/Cleanup.php';
	require_once INSANEMAILER_PLUGIN_DIR . 'includes/Admin.php';

	insanemailer_maybe_upgrade();

	INSANEMAILER_Mailer::instance();
	INSANEMAILER_Cleanup::instance();
	INSANEMAILER_Admin::instance();

	// Only loaded while emails left over from queue mode are still being drained.
	if ( get_option( 'insanemailer_queue_drain_pending' ) ) {
		require_once INSANEMAILER_PLUGIN_DIR . 'includes/QueueDrain.php';
		INSANEMAILER_Queue_Drain::instance();
	}
}
add_action( 'plugins_loaded', 'insanemailer_init' );

/**
 * Run database upgrades if needed.
 */
function insanemailer_maybe_upgrade() {
	$db_version = get_option( 'insanemailer_db_version', '0' );

	if ( version_compare( $db_version, INSANEMAILER_VERSION, '<' ) ) {
		INSANEMAILER_Activator::activate();
	}
}
