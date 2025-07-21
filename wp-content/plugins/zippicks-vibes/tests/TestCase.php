<?php
/**
 * Base test case for ZipPicks Vibes unit tests
 *
 * @package ZipPicks_Vibes\Tests
 */

namespace ZipPicks\Vibes\Tests;

use WP_UnitTestCase;
use ZipPicks\Vibes\Container;
use ZipPicks\Vibes\Core\VibeManager;
use Mockery;

/**
 * Base test case class
 */
abstract class TestCase extends WP_UnitTestCase {
    
    /**
     * @var Container
     */
    protected $container;
    
    /**
     * @var array Test vibes created during tests
     */
    protected $test_vibes = [];
    
    /**
     * @var array Test users created during tests
     */
    protected $test_users = [];
    
    /**
     * @var array Test businesses created during tests
     */
    protected $test_businesses = [];
    
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Reset container for clean state
        $this->container = Container::getInstance();
        
        // Clear any existing test data
        $this->clean_test_data();
        
        // Set up test environment
        $this->setup_test_environment();
        
        // Initialize mocks
        $this->initializeMocks();
    }
    
    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        // Clean up test vibes
        foreach ($this->test_vibes as $vibe_id) {
            wp_delete_term($vibe_id, 'zippicks_vibe');
        }
        
        // Clean up test users
        foreach ($this->test_users as $user_id) {
            wp_delete_user($user_id);
        }
        
        // Clean up test businesses
        foreach ($this->test_businesses as $business_id) {
            wp_delete_post($business_id, true);
        }
        
        // Clear caches
        wp_cache_flush();
        
        // Reset globals
        unset($GLOBALS['current_user']);
        
        // Close Mockery
        Mockery::close();
        
        parent::tearDown();
    }
    
    /**
     * Set up test environment
     */
    protected function setup_test_environment() {
        // Set test mode constants
        if (!defined('ZIPPICKS_VIBES_TEST_MODE')) {
            define('ZIPPICKS_VIBES_TEST_MODE', true);
        }
        
        // Disable rate limiting in tests
        add_filter('zippicks_vibes_skip_rate_limit', '__return_true');
        
        // Use mock cache in tests
        add_filter('zippicks_vibes_cache_driver', function() {
            return 'array';
        });
        
        // Disable Redis in tests
        add_filter('zippicks_vibes_redis_enabled', '__return_false');
        
        // Set test nonce
        add_filter('wp_create_nonce', function($nonce) {
            return 'test_nonce_12345';
        });
    }
    
    /**
     * Initialize common mocks
     */
    protected function initializeMocks() {
        // Mock WordPress functions if needed
        if (!function_exists('wp_doing_ajax')) {
            function wp_doing_ajax() {
                return false;
            }
        }
    }
    
    /**
     * Clean test data
     */
    protected function clean_test_data() {
        global $wpdb;
        
        // Clean test vibes
        $wpdb->query("DELETE FROM {$wpdb->terms} WHERE slug LIKE 'test-%'");
        
        // Clean test posts
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_title LIKE 'Test %'");
        
        // Clean test users
        $wpdb->query("DELETE FROM {$wpdb->users} WHERE user_login LIKE 'test_user_%'");
        
        // Clean test audit logs
        $table = $wpdb->prefix . 'zippicks_audit_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->query("DELETE FROM $table WHERE event_category = 'test'");
        }
        
        // Clean test performance metrics
        $table = $wpdb->prefix . 'zippicks_performance_metrics';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->query("DELETE FROM $table WHERE endpoint LIKE 'test/%'");
        }
        
        // Clean test cache entries
        $table = $wpdb->prefix . 'zippicks_vibes_cache';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $wpdb->query("DELETE FROM $table WHERE cache_key LIKE 'test_%'");
        }
    }
    
    /**
     * Create a test vibe
     *
     * @param array $args Vibe arguments
     * @return int|WP_Error Term ID or error
     */
    protected function create_test_vibe($args = []) {
        $defaults = [
            'name' => 'Test Vibe ' . wp_rand(1000, 9999),
            'slug' => 'test-vibe-' . wp_rand(1000, 9999),
            'description' => 'Test vibe description',
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $result = wp_insert_term($args['name'], 'zippicks_vibe', $args);
        
        if (!is_wp_error($result)) {
            $this->test_vibes[] = $result['term_id'];
            
            // Add test meta
            if (isset($args['meta'])) {
                foreach ($args['meta'] as $key => $value) {
                    update_term_meta($result['term_id'], $key, $value);
                }
            }
        }
        
        return is_wp_error($result) ? $result : $result['term_id'];
    }
    
    /**
     * Create a test user with specific capabilities
     *
     * @param array $caps Capabilities to assign
     * @param array $args Additional user arguments
     * @return int User ID
     */
    protected function create_test_user($caps = [], $args = []) {
        $defaults = [
            'user_login' => 'test_user_' . wp_rand(1000, 9999),
            'user_email' => 'test' . wp_rand(1000, 9999) . '@example.com',
            'user_pass' => 'test_pass_123',
            'role' => 'subscriber'
        ];
        
        $args = wp_parse_args($args, $defaults);
        $user_id = wp_insert_user($args);
        
        if (!is_wp_error($user_id)) {
            $this->test_users[] = $user_id;
            
            if (!empty($caps)) {
                $user = get_user_by('id', $user_id);
                foreach ($caps as $cap) {
                    $user->add_cap($cap);
                }
            }
        }
        
        return $user_id;
    }
    
    /**
     * Create a test business
     *
     * @param array $args Business arguments
     * @return int Post ID
     */
    protected function create_test_business($args = []) {
        $defaults = [
            'post_title' => 'Test Business ' . wp_rand(1000, 9999),
            'post_type' => 'zippicks_business',
            'post_status' => 'publish',
            'meta_input' => [
                'business_zip' => '10001',
                'business_city' => 'New York',
                'business_state' => 'NY',
                'business_verified' => true
            ]
        ];
        
        $args = wp_parse_args($args, $defaults);
        $business_id = wp_insert_post($args);
        
        if ($business_id && !is_wp_error($business_id)) {
            $this->test_businesses[] = $business_id;
        }
        
        return $business_id;
    }
    
    /**
     * Simulate HTTP request
     *
     * @param string $method Request method
     * @param array $params Request parameters
     * @param array $headers Request headers
     * @return array
     */
    protected function simulateRequest($method = 'GET', $params = [], $headers = []) {
        $_SERVER['REQUEST_METHOD'] = $method;
        
        if ($method === 'GET') {
            $_GET = $params;
        } elseif ($method === 'POST') {
            $_POST = $params;
        }
        
        foreach ($headers as $key => $value) {
            $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }
        
        return [
            'method' => $method,
            'params' => $params,
            'headers' => $headers
        ];
    }
    
    /**
     * Create mock user with specific role
     *
     * @param string $role User role
     * @return \WP_User
     */
    protected function createMockUser($role = 'administrator') {
        $user_id = $this->create_test_user([], ['role' => $role]);
        wp_set_current_user($user_id);
        return wp_get_current_user();
    }
    
    /**
     * Assert performance metrics
     *
     * @param callable $callback Function to measure
     * @param float $maxTime Maximum execution time in seconds
     * @param string $message Optional message
     */
    protected function assertPerformance($callback, $maxTime, $message = '') {
        $start = microtime(true);
        $callback();
        $duration = microtime(true) - $start;
        
        $this->assertLessThan(
            $maxTime,
            $duration,
            $message ?: "Execution time {$duration}s exceeded maximum {$maxTime}s"
        );
    }
    
    /**
     * Assert that a WP_Error contains specific error code
     *
     * @param string $code Expected error code
     * @param mixed $actual Actual value
     * @param string $message Optional message
     */
    protected function assertWPErrorCode($code, $actual, $message = '') {
        $this->assertWPError($actual, $message);
        $this->assertEquals($code, $actual->get_error_code(), $message);
    }
    
    /**
     * Assert that an array has specific structure
     *
     * @param array $structure Expected structure
     * @param array $actual Actual array
     * @param string $message Optional message
     */
    protected function assertArrayStructure($structure, $actual, $message = '') {
        foreach ($structure as $key) {
            $this->assertArrayHasKey($key, $actual, $message);
        }
    }
    
    /**
     * Assert JSON structure
     *
     * @param array $structure Expected structure
     * @param string $json JSON string
     * @param string $message Optional message
     */
    protected function assertJsonStructure($structure, $json, $message = '') {
        $data = json_decode($json, true);
        $this->assertIsArray($data, 'Invalid JSON provided');
        $this->assertArrayStructure($structure, $data, $message);
    }
    
    /**
     * Assert database has record
     *
     * @param string $table Table name (without prefix)
     * @param array $criteria Search criteria
     * @param string $message Optional message
     */
    protected function assertDatabaseHas($table, $criteria, $message = '') {
        global $wpdb;
        $table_name = $wpdb->prefix . $table;
        
        $where = [];
        foreach ($criteria as $field => $value) {
            $where[] = $wpdb->prepare("{$field} = %s", $value);
        }
        
        $sql = "SELECT COUNT(*) FROM {$table_name} WHERE " . implode(' AND ', $where);
        $count = $wpdb->get_var($sql);
        
        $this->assertGreaterThan(0, $count, $message ?: "Record not found in {$table}");
    }
    
    /**
     * Assert cache has key
     *
     * @param string $key Cache key
     * @param string $message Optional message
     */
    protected function assertCacheHas($key, $message = '') {
        $cache = $this->get_service('cache');
        $this->assertTrue(
            $cache && $cache->has($key),
            $message ?: "Cache key '{$key}' not found"
        );
    }
    
    /**
     * Get a service from container
     *
     * @param string $service Service name
     * @return mixed
     */
    protected function get_service($service) {
        return $this->container->get($service);
    }
    
    /**
     * Mock a service in container
     *
     * @param string $service Service name
     * @param object $mock Mock object
     */
    protected function mock_service($service, $mock) {
        $this->container->bind($service, $mock);
    }
    
    /**
     * Create mock for a class
     *
     * @param string $class Class name
     * @param array $methods Methods to mock
     * @return \PHPUnit\Framework\MockObject\MockObject
     */
    protected function create_mock($class, $methods = []) {
        $builder = $this->getMockBuilder($class);
        
        if (!empty($methods)) {
            $builder->onlyMethods($methods);
        }
        
        return $builder->getMock();
    }
    
    /**
     * Create Mockery mock
     *
     * @param string $class Class name
     * @return \Mockery\MockInterface
     */
    protected function mock($class) {
        return Mockery::mock($class);
    }
    
    /**
     * Create partial mock
     *
     * @param string $class Class name
     * @param array $methods Methods to mock
     * @return \Mockery\MockInterface
     */
    protected function partialMock($class, array $methods = []) {
        return Mockery::mock($class)->makePartial()->shouldAllowMockingProtectedMethods();
    }
    
    /**
     * Set up fake AJAX request
     */
    protected function setUpAjax() {
        add_filter('wp_doing_ajax', '__return_true');
        
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
    }
    
    /**
     * Get private/protected property value
     *
     * @param object $object Object instance
     * @param string $property Property name
     * @return mixed
     */
    protected function getPrivateProperty($object, $property) {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
    
    /**
     * Set private/protected property value
     *
     * @param object $object Object instance
     * @param string $property Property name
     * @param mixed $value Value to set
     */
    protected function setPrivateProperty($object, $property, $value) {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($property);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
    
    /**
     * Call private/protected method
     *
     * @param object $object Object instance
     * @param string $method Method name
     * @param array $args Method arguments
     * @return mixed
     */
    protected function callPrivateMethod($object, $method, array $args = []) {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }
}