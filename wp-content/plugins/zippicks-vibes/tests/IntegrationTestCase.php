<?php
/**
 * Base test case for ZipPicks Vibes integration tests
 *
 * @package ZipPicks_Vibes\Tests
 */

namespace ZipPicks\Vibes\Tests;

use WP_REST_Request;
use WP_REST_Server;

/**
 * Integration test case class
 */
abstract class IntegrationTestCase extends TestCase {
    
    /**
     * @var WP_REST_Server
     */
    protected $server;
    
    /**
     * Set up before each test
     */
    public function setUp(): void {
        parent::setUp();
        
        // Set up REST API server
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server();
        do_action('rest_api_init');
        
        // Create database tables if needed
        $this->ensure_tables_exist();
    }
    
    /**
     * Tear down after each test
     */
    public function tearDown(): void {
        // Reset REST server
        global $wp_rest_server;
        $wp_rest_server = null;
        
        parent::tearDown();
    }
    
    /**
     * Ensure database tables exist
     */
    protected function ensure_tables_exist() {
        global $wpdb;
        
        // Check if tables exist
        $audit_table = $wpdb->prefix . 'zippicks_audit_log';
        $performance_table = $wpdb->prefix . 'zippicks_performance_metrics';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$audit_table'") !== $audit_table) {
            // Create audit log table
            $sql = "CREATE TABLE IF NOT EXISTS $audit_table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                event_type varchar(50) NOT NULL,
                event_category varchar(50) NOT NULL,
                severity varchar(20) NOT NULL DEFAULT 'info',
                user_id bigint(20) unsigned DEFAULT NULL,
                message text NOT NULL,
                details longtext,
                ip_address varchar(45) DEFAULT NULL,
                user_agent varchar(255) DEFAULT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_event_type (event_type),
                KEY idx_category (event_category),
                KEY idx_severity (severity),
                KEY idx_user_id (user_id),
                KEY idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $wpdb->query($sql);
        }
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$performance_table'") !== $performance_table) {
            // Create performance metrics table
            $sql = "CREATE TABLE IF NOT EXISTS $performance_table (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                metric_type varchar(50) NOT NULL,
                endpoint varchar(255) NOT NULL,
                duration decimal(10,3) NOT NULL,
                memory_usage bigint(20) unsigned DEFAULT NULL,
                details longtext,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_metric_type (metric_type),
                KEY idx_endpoint (endpoint),
                KEY idx_duration (duration),
                KEY idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $wpdb->query($sql);
        }
    }
    
    /**
     * Make REST API request
     *
     * @param string $method HTTP method
     * @param string $route API route
     * @param array $params Request parameters
     * @return WP_REST_Response
     */
    protected function make_rest_request($method, $route, $params = []) {
        $request = new WP_REST_Request($method, $route);
        
        if ($method === 'GET') {
            foreach ($params as $key => $value) {
                $request->set_query_params([$key => $value]);
            }
        } else {
            $request->set_body_params($params);
        }
        
        // Add nonce for authentication
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        
        return $this->server->dispatch($request);
    }
    
    /**
     * Assert REST response is successful
     *
     * @param WP_REST_Response $response
     * @param int $expected_status Expected status code
     */
    protected function assertRestSuccess($response, $expected_status = 200) {
        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertEquals($expected_status, $response->get_status());
    }
    
    /**
     * Assert REST response is error
     *
     * @param WP_REST_Response $response
     * @param int $expected_status Expected status code
     * @param string $expected_code Expected error code
     */
    protected function assertRestError($response, $expected_status, $expected_code = null) {
        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertEquals($expected_status, $response->get_status());
        
        $data = $response->get_data();
        $this->assertArrayHasKey('code', $data);
        
        if ($expected_code) {
            $this->assertEquals($expected_code, $data['code']);
        }
    }
    
    /**
     * Create authenticated request
     *
     * @param int $user_id User ID to authenticate as
     * @return WP_REST_Request
     */
    protected function create_authenticated_request($user_id) {
        wp_set_current_user($user_id);
        
        $request = new WP_REST_Request();
        $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
        
        return $request;
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
        $where_parts = [];
        $values = [];
        
        foreach ($criteria as $column => $value) {
            $where_parts[] = "$column = %s";
            $values[] = $value;
        }
        
        $where = implode(' AND ', $where_parts);
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE $where",
            $values
        );
        
        $count = $wpdb->get_var($query);
        
        $this->assertGreaterThan(0, $count, $message ?: "Failed asserting that table [$table] has matching record.");
    }
    
    /**
     * Assert database missing record
     *
     * @param string $table Table name (without prefix)
     * @param array $criteria Search criteria
     * @param string $message Optional message
     */
    protected function assertDatabaseMissing($table, $criteria, $message = '') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . $table;
        $where_parts = [];
        $values = [];
        
        foreach ($criteria as $column => $value) {
            $where_parts[] = "$column = %s";
            $values[] = $value;
        }
        
        $where = implode(' AND ', $where_parts);
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE $where",
            $values
        );
        
        $count = $wpdb->get_var($query);
        
        $this->assertEquals(0, $count, $message ?: "Failed asserting that table [$table] is missing matching record.");
    }
    
    /**
     * Get JSON response data
     *
     * @param WP_REST_Response $response
     * @return array
     */
    protected function get_json_data($response) {
        return json_decode(wp_json_encode($response->get_data()), true);
    }
    
    /**
     * Test AJAX request
     *
     * @param string $action AJAX action
     * @param array $data Request data
     * @param int $user_id User ID to authenticate as
     * @return mixed Response
     */
    protected function make_ajax_request($action, $data = [], $user_id = null) {
        // Set up AJAX environment
        if (!defined('DOING_AJAX')) {
            define('DOING_AJAX', true);
        }
        
        // Set current user if provided
        if ($user_id) {
            wp_set_current_user($user_id);
        }
        
        // Set up request data
        $_POST = array_merge($data, [
            'action' => $action,
            '_ajax_nonce' => wp_create_nonce($action),
        ]);
        
        // Capture output
        ob_start();
        
        try {
            do_action('wp_ajax_' . $action);
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }
        
        $output = ob_get_clean();
        
        // Try to decode JSON response
        $json = json_decode($output, true);
        return json_last_error() === JSON_ERROR_NONE ? $json : $output;
    }
    
    /**
     * Assert AJAX response success
     *
     * @param mixed $response AJAX response
     * @param string $message Optional message
     */
    protected function assertAjaxSuccess($response, $message = '') {
        $this->assertIsArray($response, $message ?: 'AJAX response should be array');
        $this->assertTrue(
            isset($response['success']) && $response['success'],
            $message ?: 'AJAX request should be successful'
        );
    }
    
    /**
     * Assert AJAX response error
     *
     * @param mixed $response AJAX response
     * @param string $expected_code Expected error code
     * @param string $message Optional message
     */
    protected function assertAjaxError($response, $expected_code = null, $message = '') {
        $this->assertIsArray($response, $message ?: 'AJAX response should be array');
        $this->assertFalse(
            isset($response['success']) && $response['success'],
            $message ?: 'AJAX request should fail'
        );
        
        if ($expected_code) {
            $this->assertEquals(
                $expected_code,
                $response['data']['code'] ?? null,
                $message ?: 'AJAX error code mismatch'
            );
        }
    }
    
    /**
     * Test admin page rendering
     *
     * @param string $page_hook Admin page hook
     * @param array $args Query arguments
     * @return string Rendered output
     */
    protected function render_admin_page($page_hook, $args = []) {
        // Set up admin environment
        set_current_screen($page_hook);
        
        // Set query vars
        foreach ($args as $key => $value) {
            $_GET[$key] = $value;
            $_REQUEST[$key] = $value;
        }
        
        // Capture output
        ob_start();
        do_action('load-' . $page_hook);
        do_action($page_hook);
        $output = ob_get_clean();
        
        return $output;
    }
    
    /**
     * Assert admin notice exists
     *
     * @param string $notice_text Expected notice text
     * @param string $type Notice type (error, warning, success, info)
     * @param string $message Optional message
     */
    protected function assertAdminNotice($notice_text, $type = 'info', $message = '') {
        $notices = get_transient('zippicks_admin_notices_' . get_current_user_id());
        
        $this->assertIsArray($notices, $message ?: 'Admin notices should exist');
        
        $found = false;
        foreach ($notices as $notice) {
            if (strpos($notice['message'], $notice_text) !== false && $notice['type'] === $type) {
                $found = true;
                break;
            }
        }
        
        $this->assertTrue($found, $message ?: "Admin notice with text '$notice_text' not found");
    }
    
    /**
     * Test shortcode output
     *
     * @param string $shortcode Shortcode tag
     * @param array $atts Shortcode attributes
     * @return string Rendered output
     */
    protected function render_shortcode($shortcode, $atts = []) {
        $atts_string = '';
        foreach ($atts as $key => $value) {
            $atts_string .= sprintf(' %s="%s"', $key, esc_attr($value));
        }
        
        $content = sprintf('[%s%s]', $shortcode, $atts_string);
        return do_shortcode($content);
    }
    
    /**
     * Assert HTML contains element
     *
     * @param string $html HTML content
     * @param string $selector CSS selector
     * @param string $message Optional message
     */
    protected function assertHtmlContainsSelector($html, $selector, $message = '') {
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        
        // Convert CSS selector to XPath
        $xpath_query = $this->css_to_xpath($selector);
        $elements = $xpath->query($xpath_query);
        
        $this->assertGreaterThan(
            0,
            $elements->length,
            $message ?: "HTML should contain element matching '$selector'"
        );
    }
    
    /**
     * Convert CSS selector to XPath
     *
     * @param string $selector CSS selector
     * @return string XPath query
     */
    private function css_to_xpath($selector) {
        // Simple CSS to XPath conversion (basic selectors only)
        $selector = trim($selector);
        
        // ID selector
        if (strpos($selector, '#') === 0) {
            return "//*[@id='" . substr($selector, 1) . "']";
        }
        
        // Class selector
        if (strpos($selector, '.') === 0) {
            return "//*[contains(@class, '" . substr($selector, 1) . "')]";
        }
        
        // Element selector
        return "//" . $selector;
    }
    
    /**
     * Simulate different user roles
     *
     * @param string $role User role
     * @param callable $callback Callback to run as user
     * @return mixed Callback result
     */
    protected function actingAs($role, callable $callback) {
        $original_user = wp_get_current_user();
        
        // Create user with role
        $user_id = $this->create_test_user([], ['role' => $role]);
        wp_set_current_user($user_id);
        
        try {
            $result = $callback();
        } finally {
            // Restore original user
            wp_set_current_user($original_user->ID);
        }
        
        return $result;
    }
    
    /**
     * Test scheduled event
     *
     * @param string $hook Action hook
     * @param array $args Hook arguments
     */
    protected function run_scheduled_event($hook, $args = []) {
        // Get the next scheduled event
        $timestamp = wp_next_scheduled($hook, $args);
        
        if ($timestamp) {
            // Run the event
            do_action_ref_array($hook, $args);
        }
    }
    
    /**
     * Assert event is scheduled
     *
     * @param string $hook Action hook
     * @param array $args Hook arguments
     * @param string $message Optional message
     */
    protected function assertEventScheduled($hook, $args = [], $message = '') {
        $timestamp = wp_next_scheduled($hook, $args);
        
        $this->assertNotFalse(
            $timestamp,
            $message ?: "Event '$hook' should be scheduled"
        );
    }
    
    /**
     * Mock external API response
     *
     * @param string $url API URL
     * @param array $response Response data
     * @param int $status HTTP status code
     */
    protected function mockApiResponse($url, $response, $status = 200) {
        add_filter('pre_http_request', function($preempt, $args, $request_url) use ($url, $response, $status) {
            if (strpos($request_url, $url) !== false) {
                return [
                    'response' => ['code' => $status],
                    'body' => is_array($response) ? json_encode($response) : $response,
                    'headers' => ['content-type' => 'application/json'],
                ];
            }
            return $preempt;
        }, 10, 3);
    }
    
    /**
     * Test with different ZIP codes
     *
     * @param array $zip_codes ZIP codes to test
     * @param callable $callback Test callback
     */
    protected function withZipCodes(array $zip_codes, callable $callback) {
        foreach ($zip_codes as $zip) {
            // Set ZIP in session/cookie
            $_COOKIE['zippicks_user_zip'] = $zip;
            $_SESSION['zippicks_user_zip'] = $zip;
            
            // Run test
            $callback($zip);
        }
    }
    
    /**
     * Assert cache was hit
     *
     * @param string $key Cache key
     * @param callable $callback Callback that should hit cache
     * @param string $message Optional message
     */
    protected function assertCacheHit($key, callable $callback, $message = '') {
        $cache = $this->get_service('cache');
        
        // Warm cache
        $callback();
        
        // Track cache hits
        $hit = false;
        add_filter('zippicks_cache_get', function($value, $cache_key) use ($key, &$hit) {
            if ($cache_key === $key && $value !== false) {
                $hit = true;
            }
            return $value;
        }, 10, 2);
        
        // Run again
        $callback();
        
        $this->assertTrue($hit, $message ?: "Cache key '$key' should have been hit");
    }
}