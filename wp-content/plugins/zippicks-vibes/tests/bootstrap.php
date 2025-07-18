<?php
/**
 * PHPUnit bootstrap file for ZipPicks Vibes plugin
 *
 * @package ZipPicks_Vibes
 */

// Define test environment constants
define('ZIPPICKS_VIBES_TESTS', true);
define('ZIPPICKS_VIBES_TEST_MODE', true);
define('ZIPPICKS_VIBES_REDIS_DISABLED', true);
define('ZIPPICKS_VIBES_RATE_LIMIT_DISABLED', true);

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

// Get plugin root directory
$plugin_root = dirname(dirname(__FILE__));

// Load Composer autoloader
$composer_autoload = $plugin_root . '/vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

// Get WordPress tests directory from environment or use default
$_tests_dir = getenv('WP_TESTS_DIR');
if (! $_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Alternative paths for WordPress test suite
$alt_tests_dir = [
    dirname(dirname(dirname(dirname(dirname($plugin_root))))) . '/wordpress-tests-lib',
    dirname(dirname(dirname(dirname(dirname($plugin_root))))) . '/tests/phpunit',
    '/tmp/wordpress-tests-lib',
];

foreach ($alt_tests_dir as $alt_dir) {
    if (!$_tests_dir && file_exists($alt_dir . '/includes/functions.php')) {
        $_tests_dir = $alt_dir;
        break;
    }
}

// Check if WordPress test suite is available
if (! file_exists($_tests_dir . '/includes/functions.php')) {
    echo "Could not find $_tests_dir/includes/functions.php\n";
    echo "Please run: composer run install-wp-tests\n";
    exit(1);
}

// Give access to tests_add_filter() function
require_once $_tests_dir . '/includes/functions.php';

/**
 * Set up test environment variables
 */
function _set_test_environment() {
    // Set test database name
    if (!defined('DB_NAME')) {
        define('DB_NAME', 'wordpress_test');
    }
    
    // Enable debug mode for tests
    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
    
    // Disable external HTTP requests during tests
    if (!defined('WP_HTTP_BLOCK_EXTERNAL')) {
        define('WP_HTTP_BLOCK_EXTERNAL', true);
    }
    
    // Allow localhost HTTP requests
    if (!defined('WP_ACCESSIBLE_HOSTS')) {
        define('WP_ACCESSIBLE_HOSTS', 'localhost,127.0.0.1');
    }
    
    // Set memory limit for tests
    if (function_exists('ini_set')) {
        ini_set('memory_limit', '256M');
    }
}

/**
 * Manually load the plugin being tested
 */
function _manually_load_plugin() {
    // Set up test environment
    _set_test_environment();
    
    // Load ZipPicks Foundation first (required dependency)
    $foundation_file = dirname(dirname(dirname(dirname(__FILE__)))) . '/mu-plugins/00-zippicks-foundation.php';
    if (file_exists($foundation_file)) {
        require_once $foundation_file;
    } else {
        // Create mock foundation function if not available
        if (!function_exists('zippicks')) {
            function zippicks() {
                static $container = null;
                if ($container === null) {
                    require_once dirname(__FILE__) . '/mocks/MockFoundation.php';
                    $container = new \ZipPicks\Vibes\Tests\Mocks\MockFoundation();
                }
                return $container;
            }
        }
    }

    // Load the main plugin file
    $plugin_file = dirname(dirname(__FILE__)) . '/zippicks-vibes.php';
    if (file_exists($plugin_file)) {
        require_once $plugin_file;
    } else {
        // Try alternative plugin file
        $plugin_file = dirname(dirname(__FILE__)) . '/zippicks-vibes.php';
        if (file_exists($plugin_file)) {
            require_once $plugin_file;
        }
    }
}

// Hook plugin loading
tests_add_filter('muplugins_loaded', '_manually_load_plugin');

// Set up WordPress test filters
tests_add_filter('wp_die_handler', function() {
    return function($message, $title = '', $args = []) {
        throw new WPDieException($message, $title, $args);
    };
});

// Mock AJAX requests for testing
tests_add_filter('wp_doing_ajax', '__return_false');

// Start up the WP testing environment
require $_tests_dir . '/includes/bootstrap.php';

// Load test case classes
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/PerformanceTestCase.php';

// Define custom exception for wp_die
class WPDieException extends Exception {
    protected $title;
    protected $args;
    
    public function __construct($message = '', $title = '', $args = []) {
        parent::__construct($message);
        $this->title = $title;
        $this->args = $args;
    }
    
    public function getTitle() {
        return $this->title;
    }
    
    public function getArgs() {
        return $this->args;
    }
}

// Set up test database tables
if (function_exists('activate_plugin')) {
    activate_plugin('zippicks-vibes/zippicks-vibes.php');
}

// Create test tables if needed
global $wpdb;
$tables = [
    'zippicks_vibes_health_checks',
    'zippicks_audit_log',
    'zippicks_performance_metrics',
    'zippicks_vibes_cache',
];

foreach ($tables as $table) {
    $table_name = $wpdb->prefix . $table;
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
        // Table doesn't exist, try to create it
        require_once dirname(dirname(__FILE__)) . '/includes/class-database.php';
        if (class_exists('\\ZipPicks\\Vibes\\Database\\Installer')) {
            $installer = new \ZipPicks\Vibes\Database\Installer();
            $installer->create_tables();
        }
    }
}

// Output test environment info
echo "\n";
echo "========================================\n";
echo "ZipPicks Vibes Test Suite Initialized\n";
echo "========================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "WordPress Version: " . $GLOBALS['wp_version'] . "\n";
echo "Plugin Version: " . (defined('ZIPPICKS_VIBES_VERSION') ? ZIPPICKS_VIBES_VERSION : 'Unknown') . "\n";
echo "Test Mode: " . (ZIPPICKS_VIBES_TEST_MODE ? 'Enabled' : 'Disabled') . "\n";
echo "Redis: " . (ZIPPICKS_VIBES_REDIS_DISABLED ? 'Disabled' : 'Enabled') . "\n";
echo "Rate Limiting: " . (ZIPPICKS_VIBES_RATE_LIMIT_DISABLED ? 'Disabled' : 'Enabled') . "\n";
echo "========================================\n\n";