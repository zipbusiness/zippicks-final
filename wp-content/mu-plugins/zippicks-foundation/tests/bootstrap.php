<?php
/**
 * PHPUnit Bootstrap File
 * 
 * @package ZipPicks\Foundation\Tests
 */

declare(strict_types=1);

// Define test constants
define('ZIPPICKS_FOUNDATION_TESTS', true);
define('ABSPATH', '/tmp/wordpress/');
define('WP_DEBUG', true);

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Load WordPress mock functions for testing
require_once __DIR__ . '/wordpress-mocks.php';

// Define foundation constants
define('ZIPPICKS_FOUNDATION_VERSION', '1.0.0');
define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__));
define('ZIPPICKS_FOUNDATION_URL', 'https://example.com/wp-content/mu-plugins/zippicks-foundation');