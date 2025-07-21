<?php
/**
 * Integration tests for Security features
 *
 * @package ZipPicks_Vibes\Tests\Integration\Security
 */

namespace ZipPicks\Vibes\Tests\Integration\Security;

use ZipPicks\Vibes\Tests\IntegrationTestCase;
use WP_REST_Request;

class SecurityIntegrationTest extends IntegrationTestCase {
    
    /**
     * @var string
     */
    private $namespace = 'zippicks/v2';
    
    /**
     * @var string
     */
    private $base = 'vibes';
    
    public function setUp(): void {
        parent::setUp();
        
        // Create test vibes
        $this->create_test_vibe(['name' => 'Security Test Vibe']);
        
        // Clear any existing rate limits
        delete_transient('zippicks_rate_limit_' . $this->get_client_ip());
    }
    
    /**
     * Test rate limiting integration
     */
    public function test_rate_limiting_blocks_excessive_requests() {
        $ip = '192.168.1.100';
        $_SERVER['REMOTE_ADDR'] = $ip;
        
        // Make requests up to the limit
        $limit = 60; // Default limit per hour
        $responses = [];
        
        for ($i = 0; $i < $limit + 5; $i++) {
            $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}");
            $responses[] = $response;
            
            // After limit, should get 429
            if ($i >= $limit) {
                $this->assertEquals(429, $response->get_status());
                
                $data = $this->get_json_data($response);
                $this->assertEquals('rate_limit_exceeded', $data['code']);
                $this->assertArrayHasKey('retry_after', $data['data']);
            } else {
                $this->assertEquals(200, $response->get_status());
            }
        }
        
        // Verify rate limit was logged
        $this->assertDatabaseHas('zippicks_audit_log', [
            'event_type' => 'SECURITY',
            'event_category' => 'security',
            'message' => 'Rate limit exceeded'
        ]);
    }
    
    /**
     * Test CSRF protection
     */
    public function test_csrf_protection() {
        // Create admin user
        $admin_id = $this->create_test_user(['manage_zippicks_vibes']);
        wp_set_current_user($admin_id);
        
        // Test with valid nonce
        $_POST = [
            'action' => 'create_vibe',
            'vibe_name' => 'CSRF Test',
            'zippicks_nonce' => wp_create_nonce('create_vibe')
        ];
        
        ob_start();
        do_action('admin_post_create_vibe');
        $output = ob_get_clean();
        
        // Should succeed with valid nonce
        $vibe = get_term_by('name', 'CSRF Test', 'zippicks_vibe');
        $this->assertNotFalse($vibe);
        
        // Test with invalid nonce
        $_POST['zippicks_nonce'] = 'invalid_nonce';
        $_POST['vibe_name'] = 'CSRF Attack';
        
        ob_start();
        do_action('admin_post_create_vibe');
        $output = ob_get_clean();
        
        // Should fail with invalid nonce
        $vibe = get_term_by('name', 'CSRF Attack', 'zippicks_vibe');
        $this->assertFalse($vibe);
        
        // Security event should be logged
        $this->assertDatabaseHas('zippicks_audit_log', [
            'event_type' => 'SECURITY',
            'message' => 'Invalid nonce'
        ]);
    }
    
    /**
     * Test request validation integration
     */
    public function test_request_validation_prevents_replay_attacks() {
        $timestamp = time();
        $method = 'POST';
        $uri = '/wp-json/zippicks/v2/vibes';
        $body = json_encode(['name' => 'Replay Test']);
        
        // Create properly signed request
        $secret = get_option('zippicks_api_secret', 'default_secret');
        $message = $method . "\n" . $uri . "\n" . $timestamp . "\n" . $body;
        $signature = hash_hmac('sha256', $message, $secret);
        
        $request = new WP_REST_Request($method, $uri);
        $request->set_header('X-ZipPicks-Timestamp', $timestamp);
        $request->set_header('X-ZipPicks-Signature', $signature);
        $request->set_body($body);
        
        // First request should succeed
        $response1 = $this->server->dispatch($request);
        $this->assertEquals(200, $response1->get_status());
        
        // Same request again should fail (replay attack)
        $response2 = $this->server->dispatch($request);
        $this->assertEquals(401, $response2->get_status());
        
        $data = $this->get_json_data($response2);
        $this->assertEquals('replay_attack', $data['code']);
        
        // Verify security log
        $this->assertDatabaseHas('zippicks_audit_log', [
            'event_type' => 'SECURITY',
            'message' => 'Replay attack detected'
        ]);
    }
    
    /**
     * Test IP whitelisting
     */
    public function test_ip_whitelisting() {
        // Enable IP whitelisting
        update_option('zippicks_enable_ip_whitelist', true);
        update_option('zippicks_api_ip_whitelist', ['192.168.1.0/24', '10.0.0.100']);
        
        // Test allowed IP
        $_SERVER['REMOTE_ADDR'] = '192.168.1.50';
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}");
        $this->assertEquals(200, $response->get_status());
        
        // Test blocked IP
        $_SERVER['REMOTE_ADDR'] = '192.168.2.50';
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}");
        $this->assertEquals(403, $response->get_status());
        
        $data = $this->get_json_data($response);
        $this->assertEquals('ip_not_whitelisted', $data['code']);
        
        // Verify security log
        $this->assertDatabaseHas('zippicks_audit_log', [
            'event_type' => 'SECURITY',
            'ip_address' => '192.168.2.50',
            'message' => 'IP not whitelisted'
        ]);
    }
    
    /**
     * Test user agent blocking
     */
    public function test_user_agent_blocking() {
        // Test blocked user agents
        $blocked_agents = [
            'curl/7.68.0',
            'Wget/1.20.3',
            'python-requests/2.25.1',
            'scrapy/2.5.0'
        ];
        
        foreach ($blocked_agents as $agent) {
            $_SERVER['HTTP_USER_AGENT'] = $agent;
            
            $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}");
            $this->assertEquals(403, $response->get_status());
            
            $data = $this->get_json_data($response);
            $this->assertEquals('blocked_user_agent', $data['code']);
        }
        
        // Test allowed user agent
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}");
        $this->assertEquals(200, $response->get_status());
    }
    
    /**
     * Test anti-scraping headers
     */
    public function test_anti_scraping_headers_present() {
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}");
        
        $headers = $response->get_headers();
        
        // Verify anti-scraping headers
        $this->assertEquals('noindex', $headers['X-Robots-Tag']);
        $this->assertEquals('private, max-age=0', $headers['Cache-Control']);
        $this->assertEquals('frontend-only', $headers['X-ZipPicks-Source']);
        $this->assertEquals('nosniff', $headers['X-Content-Type-Options']);
        $this->assertEquals('SAMEORIGIN', $headers['X-Frame-Options']);
    }
    
    /**
     * Test content watermarking
     */
    public function test_content_watermarking() {
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}");
        $data = $this->get_json_data($response);
        
        // Each vibe should have a fingerprint
        foreach ($data as $vibe) {
            $this->assertArrayHasKey('_fingerprint', $vibe);
            $this->assertMatchesRegularExpression('/^ZP[a-f0-9]{8}$/', $vibe['_fingerprint']);
        }
        
        // Fingerprints should be tracked
        $this->assertDatabaseHas('zippicks_audit_log', [
            'event_type' => 'API',
            'event_category' => 'api'
        ]);
    }
    
    /**
     * Test session validation
     */
    public function test_session_validation() {
        // Create valid session
        $session_token = wp_generate_password(32, false);
        set_transient('zippicks_session_' . $session_token, [
            'user_id' => 1,
            'ip' => '192.168.1.100',
            'created' => time()
        ], HOUR_IN_SECONDS);
        
        // Request with valid session
        $request = new WP_REST_Request('GET', "/{$this->namespace}/{$this->base}");
        $request->set_header('X-ZipPicks-Session', $session_token);
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        
        $response = $this->server->dispatch($request);
        $this->assertEquals(200, $response->get_status());
        
        // Request with invalid session
        $request->set_header('X-ZipPicks-Session', 'invalid_token');
        $response = $this->server->dispatch($request);
        $this->assertEquals(401, $response->get_status());
        
        // Request from different IP with same session
        $_SERVER['REMOTE_ADDR'] = '192.168.1.101';
        $request->set_header('X-ZipPicks-Session', $session_token);
        $response = $this->server->dispatch($request);
        $this->assertEquals(401, $response->get_status());
    }
    
    /**
     * Test security event aggregation
     */
    public function test_security_event_aggregation() {
        $ip = '192.168.1.100';
        $_SERVER['REMOTE_ADDR'] = $ip;
        
        // Generate multiple security events
        for ($i = 0; $i < 10; $i++) {
            $_POST = [
                'action' => 'create_vibe',
                'vibe_name' => 'Attack ' . $i,
                'zippicks_nonce' => 'invalid'
            ];
            
            ob_start();
            do_action('admin_post_create_vibe');
            ob_get_clean();
        }
        
        // Check if IP was auto-blocked
        $blocked_ips = get_option('zippicks_blocked_ips', []);
        $this->assertContains($ip, $blocked_ips);
        
        // Verify security alert was logged
        $this->assertDatabaseHas('zippicks_audit_log', [
            'event_type' => 'SECURITY',
            'severity' => 'critical',
            'message' => 'Multiple security violations detected'
        ]);
    }
    
    /**
     * Test health check security
     */
    public function test_health_check_requires_authentication() {
        // Anonymous request should get limited info
        wp_set_current_user(0);
        $response = $this->make_rest_request('GET', "/{$this->namespace}/health");
        
        $data = $this->get_json_data($response);
        $this->assertEquals('ok', $data['status']);
        $this->assertArrayNotHasKey('detailed_checks', $data);
        
        // Admin request should get full info
        $admin_id = $this->create_test_user(['manage_options']);
        wp_set_current_user($admin_id);
        
        $response = $this->make_rest_request('GET', "/{$this->namespace}/health");
        $data = $this->get_json_data($response);
        
        $this->assertArrayHasKey('checks', $data);
        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('cache', $data['checks']);
    }
    
    /**
     * Helper to get client IP
     */
    private function get_client_ip() {
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}