<?php
/**
 * Integration tests for Vibes Admin functionality
 *
 * @package ZipPicks_Vibes\Tests\Integration\Admin
 */

namespace ZipPicks\Vibes\Tests\Integration\Admin;

use ZipPicks\Vibes\Tests\IntegrationTestCase;
use ZipPicks\Vibes\Admin\VibesAdmin;

class VibesAdminTest extends IntegrationTestCase {
    
    /**
     * @var VibesAdmin
     */
    private $admin;
    
    /**
     * @var int
     */
    private $admin_user_id;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create admin user
        $this->admin_user_id = $this->create_test_user(['manage_zippicks_vibes']);
        wp_set_current_user($this->admin_user_id);
        
        // Set up admin context
        set_current_screen('edit-zippicks_vibe');
        
        // Initialize admin
        $this->admin = new VibesAdmin();
        $this->admin->init();
    }
    
    /**
     * Test admin menu registration
     */
    public function test_admin_menu_registration() {
        global $menu, $submenu;
        
        // Trigger menu registration
        do_action('admin_menu');
        
        // Check main menu exists
        $menu_exists = false;
        foreach ($menu as $menu_item) {
            if (isset($menu_item[2]) && $menu_item[2] === 'zippicks') {
                $menu_exists = true;
                break;
            }
        }
        $this->assertTrue($menu_exists, 'ZipPicks menu should exist');
        
        // Check submenu items
        $this->assertArrayHasKey('zippicks', $submenu);
        
        $submenu_slugs = array_column($submenu['zippicks'], 2);
        $this->assertContains('zippicks-vibes', $submenu_slugs);
        $this->assertContains('zippicks-vibes-add', $submenu_slugs);
        $this->assertContains('zippicks-monitoring', $submenu_slugs);
    }
    
    /**
     * Test creating a vibe through admin
     */
    public function test_create_vibe_admin() {
        $_POST = [
            'vibe_name' => 'Admin Test Vibe',
            'vibe_slug' => 'admin-test-vibe',
            'vibe_description' => 'Created through admin',
            'vibe_category' => 'social',
            'vibe_icon' => '🎉',
            'vibe_color' => '#FF0000',
            'vibe_priority' => '50',
            'vibe_status' => 'active',
            'zippicks_nonce' => wp_create_nonce('create_vibe')
        ];
        
        // Process form submission
        do_action('admin_post_create_vibe');
        
        // Verify vibe was created
        $vibe = get_term_by('slug', 'admin-test-vibe', 'zippicks_vibe');
        $this->assertNotFalse($vibe);
        $this->assertEquals('Admin Test Vibe', $vibe->name);
        
        // Verify meta data
        $this->assertEquals('social', get_term_meta($vibe->term_id, 'category', true));
        $this->assertEquals('🎉', get_term_meta($vibe->term_id, 'icon', true));
        $this->assertEquals('#FF0000', get_term_meta($vibe->term_id, 'color', true));
        
        // Verify audit log
        $this->assertDatabaseHas('zippicks_audit_log', [
            'event_type' => 'CREATE',
            'event_category' => 'vibes',
            'user_id' => $this->admin_user_id
        ]);
    }
    
    /**
     * Test bulk actions
     */
    public function test_bulk_actions() {
        // Create test vibes
        $vibe_ids = [];
        for ($i = 1; $i <= 3; $i++) {
            $vibe_ids[] = $this->create_test_vibe([
                'name' => "Bulk Test $i",
                'meta' => ['status' => 'active']
            ]);
        }
        
        // Test bulk deactivate
        $_REQUEST = [
            'action' => 'bulk_deactivate',
            'vibes' => $vibe_ids,
            '_wpnonce' => wp_create_nonce('bulk-vibes')
        ];
        
        do_action('admin_action_bulk_deactivate');
        
        // Verify all vibes were deactivated
        foreach ($vibe_ids as $vibe_id) {
            $this->assertEquals('inactive', get_term_meta($vibe_id, 'status', true));
        }
        
        // Verify audit logs
        $this->assertDatabaseHas('zippicks_audit_log', [
            'event_type' => 'UPDATE',
            'event_category' => 'vibes'
        ]);
    }
    
    /**
     * Test AJAX vibe search
     */
    public function test_ajax_vibe_search() {
        // Create test vibes
        $this->create_test_vibe(['name' => 'Searchable Vibe']);
        $this->create_test_vibe(['name' => 'Another Test']);
        
        // Set up AJAX request
        $_POST = [
            'action' => 'zippicks_search_vibes',
            'search' => 'search',
            'nonce' => wp_create_nonce('zippicks_ajax')
        ];
        
        // Capture AJAX output
        ob_start();
        do_action('wp_ajax_zippicks_search_vibes');
        $response = ob_get_clean();
        
        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['data']);
        $this->assertEquals('Searchable Vibe', $data['data'][0]['name']);
    }
    
    /**
     * Test CSV export
     */
    public function test_csv_export() {
        // Create test vibes
        for ($i = 1; $i <= 5; $i++) {
            $this->create_test_vibe([
                'name' => "Export Test $i",
                'meta' => [
                    'category' => 'social',
                    'status' => 'active'
                ]
            ]);
        }
        
        // Set up export request
        $_GET = [
            'action' => 'export_vibes',
            'format' => 'csv',
            '_wpnonce' => wp_create_nonce('export_vibes')
        ];
        
        // Capture export output
        ob_start();
        do_action('admin_action_export_vibes');
        $csv_output = ob_get_clean();
        
        // Verify CSV structure
        $lines = explode("\n", trim($csv_output));
        $this->assertGreaterThanOrEqual(6, count($lines)); // Header + 5 vibes
        
        // Check header
        $header = str_getcsv($lines[0]);
        $this->assertContains('ID', $header);
        $this->assertContains('Name', $header);
        $this->assertContains('Slug', $header);
        $this->assertContains('Category', $header);
        $this->assertContains('Status', $header);
        
        // Verify audit log
        $this->assertDatabaseHas('zippicks_audit_log', [
            'event_type' => 'EXPORT',
            'event_category' => 'admin'
        ]);
    }
    
    /**
     * Test admin notices
     */
    public function test_admin_notices() {
        // Simulate a successful action
        set_transient('zippicks_admin_notice', [
            'type' => 'success',
            'message' => 'Vibe created successfully!'
        ]);
        
        ob_start();
        do_action('admin_notices');
        $output = ob_get_clean();
        
        $this->assertStringContainsString('notice-success', $output);
        $this->assertStringContainsString('Vibe created successfully!', $output);
        
        // Transient should be deleted after display
        $this->assertFalse(get_transient('zippicks_admin_notice'));
    }
    
    /**
     * Test capability checks
     */
    public function test_capability_checks() {
        // Create non-admin user
        $subscriber_id = $this->create_test_user([]);
        wp_set_current_user($subscriber_id);
        
        // Try to access admin page
        $this->assertFalse(current_user_can('manage_zippicks_vibes'));
        
        // AJAX request should fail
        $_POST = [
            'action' => 'zippicks_search_vibes',
            'nonce' => wp_create_nonce('zippicks_ajax')
        ];
        
        ob_start();
        do_action('wp_ajax_zippicks_search_vibes');
        $response = ob_get_clean();
        
        $data = json_decode($response, true);
        $this->assertFalse($data['success']);
        $this->assertEquals('insufficient_permissions', $data['data']['code']);
    }
    
    /**
     * Test settings page
     */
    public function test_settings_page() {
        // Update settings
        $_POST = [
            'zippicks_cache_ttl' => '7200',
            'zippicks_rate_limit' => '100',
            'zippicks_enable_debug' => '1',
            'zippicks_settings_nonce' => wp_create_nonce('zippicks_settings')
        ];
        
        do_action('admin_post_update_zippicks_settings');
        
        // Verify settings were saved
        $this->assertEquals('7200', get_option('zippicks_cache_ttl'));
        $this->assertEquals('100', get_option('zippicks_rate_limit'));
        $this->assertEquals('1', get_option('zippicks_enable_debug'));
        
        // Verify audit log
        $this->assertDatabaseHas('zippicks_audit_log', [
            'event_type' => 'SETTINGS',
            'event_category' => 'admin'
        ]);
    }
    
    /**
     * Test monitoring dashboard data
     */
    public function test_monitoring_dashboard_data() {
        // Create some test data
        for ($i = 0; $i < 10; $i++) {
            $this->create_test_vibe(['name' => "Monitor Test $i"]);
        }
        
        // Make some API requests to generate metrics
        for ($i = 0; $i < 5; $i++) {
            $this->make_rest_request('GET', '/zippicks/v2/vibes');
        }
        
        // Get monitoring metrics via AJAX
        $_POST = [
            'action' => 'zippicks_get_monitoring_metrics',
            'nonce' => wp_create_nonce('zippicks_monitoring')
        ];
        
        ob_start();
        do_action('wp_ajax_zippicks_get_monitoring_metrics');
        $response = ob_get_clean();
        
        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        
        // Verify metrics structure
        $metrics = $data['data'];
        $this->assertArrayHasKey('metrics', $metrics);
        $this->assertArrayHasKey('health_checks', $metrics);
        $this->assertArrayHasKey('chart_data', $metrics);
        $this->assertArrayHasKey('recent_events', $metrics);
    }
    
    /**
     * Test quick edit functionality
     */
    public function test_quick_edit() {
        $vibe_id = $this->create_test_vibe([
            'name' => 'Quick Edit Test',
            'meta' => ['status' => 'active']
        ]);
        
        // Quick edit via AJAX
        $_POST = [
            'action' => 'zippicks_quick_edit',
            'vibe_id' => $vibe_id,
            'status' => 'inactive',
            'priority' => '75',
            'nonce' => wp_create_nonce('zippicks_quick_edit_' . $vibe_id)
        ];
        
        ob_start();
        do_action('wp_ajax_zippicks_quick_edit');
        $response = ob_get_clean();
        
        $data = json_decode($response, true);
        $this->assertTrue($data['success']);
        
        // Verify changes
        $this->assertEquals('inactive', get_term_meta($vibe_id, 'status', true));
        $this->assertEquals('75', get_term_meta($vibe_id, 'priority', true));
    }
    
    /**
     * Test admin scripts and styles enqueue
     */
    public function test_admin_assets_enqueue() {
        global $wp_scripts, $wp_styles;
        
        // Trigger enqueue
        do_action('admin_enqueue_scripts', 'toplevel_page_zippicks-vibes');
        
        // Check scripts
        $this->assertTrue(wp_script_is('zippicks-vibes-admin', 'enqueued'));
        $this->assertTrue(wp_script_is('wp-color-picker', 'enqueued'));
        
        // Check styles
        $this->assertTrue(wp_style_is('zippicks-vibes-admin', 'enqueued'));
        $this->assertTrue(wp_style_is('wp-color-picker', 'enqueued'));
        
        // Check localized data
        $data = $wp_scripts->get_data('zippicks-vibes-admin', 'data');
        $this->assertStringContainsString('zippicksAdmin', $data);
    }
}