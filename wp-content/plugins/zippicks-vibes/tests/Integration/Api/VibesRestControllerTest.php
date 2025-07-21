<?php
/**
 * Integration tests for Vibes REST API Controller
 *
 * @package ZipPicks_Vibes\Tests\Integration\Api
 */

namespace ZipPicks\Vibes\Tests\Integration\Api;

use ZipPicks\Vibes\Tests\IntegrationTestCase;
use WP_REST_Request;

class VibesRestControllerTest extends IntegrationTestCase {
    
    /**
     * @var string
     */
    private $namespace = 'zippicks/v2';
    
    /**
     * @var string
     */
    private $base = 'vibes';
    
    /**
     * @var int
     */
    private $admin_user_id;
    
    /**
     * @var int
     */
    private $subscriber_user_id;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create test users
        $this->admin_user_id = $this->create_test_user(['manage_zippicks_vibes']);
        $this->subscriber_user_id = $this->create_test_user([]);
        
        // Create test vibes
        $this->setupTestVibes();
    }
    
    /**
     * Set up test vibes
     */
    private function setupTestVibes() {
        $this->create_test_vibe([
            'name' => 'Natural Wine',
            'slug' => 'natural-wine',
            'description' => 'Organic and biodynamic wines',
            'meta' => [
                'category' => 'social',
                'icon' => '🍷',
                'color' => '#8B0000',
                'priority' => 10,
                'status' => 'active'
            ]
        ]);
        
        $this->create_test_vibe([
            'name' => 'Work From Here',
            'slug' => 'work-from-here',
            'description' => 'Great spots for remote work',
            'meta' => [
                'category' => 'professional',
                'icon' => '💻',
                'color' => '#0066CC',
                'priority' => 20,
                'status' => 'active'
            ]
        ]);
        
        $this->create_test_vibe([
            'name' => 'Dog Friendly',
            'slug' => 'dog-friendly',
            'description' => 'Welcoming to four-legged friends',
            'meta' => [
                'category' => 'family',
                'icon' => '🐕',
                'color' => '#8B4513',
                'priority' => 30,
                'status' => 'active'
            ]
        ]);
        
        $this->create_test_vibe([
            'name' => 'Inactive Vibe',
            'slug' => 'inactive-vibe',
            'meta' => [
                'status' => 'inactive'
            ]
        ]);
    }
    
    /**
     * Test getting collection of vibes
     */
    public function test_get_vibes_collection() {
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}");
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(3, count($data));
        
        // Check vibe structure
        foreach ($data as $vibe) {
            $this->assertArrayStructure([
                'id', 'name', 'slug', 'description', 'category',
                'icon', 'color', 'priority', 'status', 'count'
            ], $vibe);
        }
        
        // Should not include inactive vibes by default
        $slugs = array_column($data, 'slug');
        $this->assertNotContains('inactive-vibe', $slugs);
    }
    
    /**
     * Test getting single vibe
     */
    public function test_get_single_vibe() {
        $vibe = get_term_by('slug', 'natural-wine', 'zippicks_vibe');
        
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}/{$vibe->term_id}");
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        $this->assertEquals($vibe->term_id, $data['id']);
        $this->assertEquals('Natural Wine', $data['name']);
        $this->assertEquals('natural-wine', $data['slug']);
        $this->assertEquals('🍷', $data['icon']);
        $this->assertEquals('social', $data['category']);
    }
    
    /**
     * Test getting non-existent vibe
     */
    public function test_get_non_existent_vibe() {
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}/999999");
        
        $this->assertRestError($response, 404, 'vibe_not_found');
    }
    
    /**
     * Test search endpoint
     */
    public function test_search_vibes() {
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}/search", [
            'q' => 'wine'
        ]);
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals('natural-wine', $data[0]['slug']);
    }
    
    /**
     * Test search with ZIP code
     */
    public function test_search_with_zip() {
        // Add ZIP-specific meta to a vibe
        $vibe = get_term_by('slug', 'work-from-here', 'zippicks_vibe');
        update_term_meta($vibe->term_id, 'zip_relevance', ['90210' => 100, '10001' => 50]);
        
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}/search", [
            'q' => 'work',
            'zip' => '90210'
        ]);
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        $this->assertCount(1, $data);
        $this->assertEquals('work-from-here', $data[0]['slug']);
        
        // Audit log should record ZIP search
        $this->assertDatabaseHas('zippicks_audit_log', [
            'event_type' => 'API',
            'event_category' => 'api'
        ]);
    }
    
    /**
     * Test popular vibes endpoint
     */
    public function test_get_popular_vibes() {
        // Add some posts to vibes to make them popular
        $wine_vibe = get_term_by('slug', 'natural-wine', 'zippicks_vibe');
        for ($i = 0; $i < 5; $i++) {
            wp_set_object_terms($this->factory->post->create(), $wine_vibe->term_id, 'zippicks_vibe');
        }
        
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}/popular", [
            'limit' => 2
        ]);
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        $this->assertIsArray($data);
        $this->assertLessThanOrEqual(2, count($data));
        
        // Should be ordered by count descending
        if (count($data) > 1) {
            $this->assertGreaterThanOrEqual($data[1]['count'], $data[0]['count']);
        }
    }
    
    /**
     * Test categories endpoint
     */
    public function test_get_categories() {
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}/categories");
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        $this->assertIsArray($data);
        
        // Should include master vibe categories
        $this->assertContains('social', $data);
        $this->assertContains('professional', $data);
        $this->assertContains('family', $data);
    }
    
    /**
     * Test autocomplete endpoint
     */
    public function test_autocomplete() {
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}/autocomplete", [
            'q' => 'nat'
        ]);
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        $this->assertIsArray($data);
        $this->assertCount(1, $data);
        $this->assertEquals('Natural Wine', $data[0]['name']);
        $this->assertEquals('natural-wine', $data[0]['slug']);
    }
    
    /**
     * Test rate limiting
     */
    public function test_rate_limiting() {
        // Make multiple rapid requests
        $responses = [];
        for ($i = 0; $i < 65; $i++) {
            $responses[] = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}");
        }
        
        // After rate limit, should get 429 error
        $last_response = end($responses);
        $this->assertEquals(429, $last_response->get_status());
        
        $data = $this->get_json_data($last_response);
        $this->assertEquals('rate_limit_exceeded', $data['code']);
    }
    
    /**
     * Test collection filtering
     */
    public function test_collection_filtering() {
        // Filter by category
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}", [
            'category' => 'social'
        ]);
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        foreach ($data as $vibe) {
            $this->assertEquals('social', $vibe['category']);
        }
        
        // Filter by status (as admin)
        wp_set_current_user($this->admin_user_id);
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}", [
            'status' => 'inactive'
        ]);
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        foreach ($data as $vibe) {
            $this->assertEquals('inactive', $vibe['status']);
        }
    }
    
    /**
     * Test pagination
     */
    public function test_pagination() {
        // Create more vibes for pagination
        for ($i = 1; $i <= 15; $i++) {
            $this->create_test_vibe(['name' => "Paginated Vibe $i"]);
        }
        
        // Get first page
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}", [
            'page' => 1,
            'per_page' => 10
        ]);
        
        $this->assertRestSuccess($response);
        
        // Check pagination headers
        $headers = $response->get_headers();
        $this->assertArrayHasKey('X-WP-Total', $headers);
        $this->assertArrayHasKey('X-WP-TotalPages', $headers);
        $this->assertGreaterThan(1, $headers['X-WP-TotalPages']);
        
        $data = $this->get_json_data($response);
        $this->assertCount(10, $data);
    }
    
    /**
     * Test sorting
     */
    public function test_sorting() {
        // Sort by name ascending
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}", [
            'orderby' => 'name',
            'order' => 'asc'
        ]);
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        $names = array_column($data, 'name');
        $sorted_names = $names;
        sort($sorted_names);
        $this->assertEquals($sorted_names, $names);
        
        // Sort by priority descending
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}", [
            'orderby' => 'priority',
            'order' => 'desc'
        ]);
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        $priorities = array_column($data, 'priority');
        $sorted_priorities = $priorities;
        rsort($sorted_priorities);
        $this->assertEquals($sorted_priorities, $priorities);
    }
    
    /**
     * Test health check endpoint
     */
    public function test_health_check() {
        $response = $this->make_rest_request('GET', "/{$this->namespace}/health");
        
        $this->assertRestSuccess($response);
        
        $data = $this->get_json_data($response);
        $this->assertEquals('ok', $data['status']);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('checks', $data);
        
        // Verify health checks
        $this->assertArrayHasKey('database', $data['checks']);
        $this->assertArrayHasKey('cache', $data['checks']);
        $this->assertArrayHasKey('api', $data['checks']);
    }
    
    /**
     * Test anti-scraping headers
     */
    public function test_anti_scraping_headers() {
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}");
        
        $headers = $response->get_headers();
        $this->assertEquals('noindex', $headers['X-Robots-Tag']);
        $this->assertEquals('private, max-age=0', $headers['Cache-Control']);
        $this->assertEquals('frontend-only', $headers['X-ZipPicks-Source']);
    }
    
    /**
     * Test request validation
     */
    public function test_request_validation() {
        // Test invalid per_page
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}", [
            'per_page' => 1000
        ]);
        
        $this->assertRestError($response, 400);
        
        // Test invalid orderby
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}", [
            'orderby' => 'invalid_field'
        ]);
        
        $this->assertRestError($response, 400);
    }
    
    /**
     * Test performance tracking
     */
    public function test_performance_tracking() {
        $response = $this->make_rest_request('GET', "/{$this->namespace}/{$this->base}");
        
        $this->assertRestSuccess($response);
        
        // Check that performance was tracked
        $this->assertDatabaseHas('zippicks_performance_metrics', [
            'metric_type' => 'api',
            'endpoint' => '/zippicks/v2/vibes'
        ]);
    }
}