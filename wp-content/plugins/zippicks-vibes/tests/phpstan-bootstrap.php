<?php
/**
 * PHPStan bootstrap file
 *
 * @package ZipPicks_Vibes
 */

// Define constants that PHPStan needs
define('ZIPPICKS_VIBES_VERSION', '2.0.0');
define('ZIPPICKS_VIBES_PLUGIN_FILE', dirname(__DIR__) . '/zippicks-vibes.php');
define('ZIPPICKS_VIBES_PLUGIN_DIR', dirname(__DIR__) . '/');
define('ZIPPICKS_VIBES_PLUGIN_URL', 'https://example.com/wp-content/plugins/zippicks-vibes/');

// WordPress constants
define('ABSPATH', '/tmp/wordpress/');
define('WP_CONTENT_DIR', '/tmp/wordpress/wp-content/');
define('WP_PLUGIN_DIR', '/tmp/wordpress/wp-content/plugins/');

// Load WordPress stubs
require_once dirname(__DIR__) . '/vendor/php-stubs/wordpress-stubs/wordpress-stubs.php';