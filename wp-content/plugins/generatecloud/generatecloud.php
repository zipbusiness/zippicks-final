<?php
/**
 * Plugin Name: GenerateCloud
 * Plugin URI: https://generatepress.com
 * Description: Create your own remote Pattern Library collections.
 * Author: Tom Usborne
 * Author URI: https://generatepress.com
 * Version: 1.1.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: generatecloud
 * Update URI: https://generatepress.com/
 *
 * @package GenerateCloud
 */

use GenerateCloud\Utils\EDD_Updater;

const GENERATECLOUD_VERSION = '1.1.0';
const GENERATECLOUD_SLUG    = 'generatecloud';
define( 'GENERATECLOUD_DIR', plugin_dir_path( __FILE__ ) );
define( 'GENERATECLOUD_URL', plugin_dir_url( __FILE__ ) );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

add_action(
	'plugins_loaded',
	function () {
		GenerateCloud\Plugin::get_instance()->plugins_loaded();
	}
);

add_action( 'init', 'generatecloud_updater' );
/**
 * Check for and receive updates.
 *
 * @since 1.0.0
 */
function generatecloud_updater() {
	/**
	 * From EDD example plugin.
	 *
	 * To support auto-updates, this needs to run during the wp_version_check cron job for privileged users.
	 */
	$doing_cron = defined( 'DOING_CRON' ) && DOING_CRON;

	if ( ! current_user_can( 'manage_options' ) && ! $doing_cron ) {
		return;
	}

	$license_settings = get_option( 'generatecloud_licensing', [] );
	$license_key      = trim( $license_settings['key'] ?? '' );

	$edd_updater = new EDD_Updater(
		'https://generatepress.com',
		__FILE__,
		array(
			'version' => GENERATECLOUD_VERSION,
			'license' => esc_attr( $license_key ),
			'item_id' => 2883156,
			'author'  => 'GeneratePress',
			'beta'    => $license_settings['beta'] ?? false,
		)
	);
}

add_action( 'init', 'generatecloud_text_domain' );
/**
 * Load the text domain.
 *
 * @since 1.0.0
 */
function generatecloud_text_domain() {
	load_plugin_textdomain( GENERATECLOUD_SLUG );
}
