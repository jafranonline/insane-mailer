<?php
/**
 * Plugin Name: Insane Mailer
 * Plugin URI: https://arraystory.com/insane-mailer
 * Description: The SMTP plugin you'll never replace. Blazing fast, queue-powered, zero bloat, less than 1MB.
 * Version: 1.0.0
 * Author: ArrayStory
 * Author URI: https://arraystory.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: insane-mailer
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Tested up to: 6.9
 *
 * @package InsaneMailer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IM_VERSION', '1.0.0' );
define( 'IM_PLUGIN_FILE', __FILE__ );
define( 'IM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'IM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'IM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once IM_PLUGIN_DIR . 'includes/Activator.php';
require_once IM_PLUGIN_DIR . 'includes/Deactivator.php';

register_activation_hook( __FILE__, [ 'IM_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'IM_Deactivator', 'deactivate' ] );

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'im_plugin_action_links' );

/**
 * Add settings link to plugin action links.
 *
 * @param array $links Existing action links.
 * @return array Modified action links.
 */
function im_plugin_action_links( array $links ): array {
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
function im_init() {
	require_once IM_PLUGIN_DIR . 'includes/Mailer.php';
	require_once IM_PLUGIN_DIR . 'includes/Queue.php';
	require_once IM_PLUGIN_DIR . 'includes/Cron.php';
	require_once IM_PLUGIN_DIR . 'includes/Admin.php';

	im_maybe_upgrade();

	IM_Mailer::instance();
	IM_Queue::instance();
	IM_Cron::instance();
	IM_Admin::instance();
}
add_action( 'plugins_loaded', 'im_init' );

/**
 * Run database upgrades if needed.
 */
function im_maybe_upgrade() {
	$db_version = get_option( 'im_db_version', '0' );

	if ( version_compare( $db_version, IM_VERSION, '<' ) ) {
		IM_Activator::activate();
	}
}
