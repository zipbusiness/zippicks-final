<?php
/**
 * Unit tests for Vibe model
 *
 * @package ZipPicks_Vibes\Tests\Unit\Models
 */

namespace ZipPicks\Vibes\Tests\Unit\Models;

use ZipPicks\Vibes\Tests\TestCase;
use ZipPicks\Vibes\Models\Vibe;

/**
 * @group unit
 * @group models
 */
class VibeTest extends TestCase {
    
    /**
     * Test creating a vibe model with all properties
     */
    public function test_create_vibe_model_with_all_properties() {
        $data = [
            'id' => 123,
            'name' => 'Natural Wine',
            'slug' => 'natural-wine',
            'description' => 'Organic and biodynamic wines',
            'category' => 'social',
            'icon' => '🍷',
            'color' => '#8B0000',
            'priority' => 10,
            'status' => 'active',
            'meta' => ['featured' => true],
            'count' => 25,
            'created_at' => '2024-01-01 10:00:00',
            'updated_at' => '2024-01-02 15:30:00'
        ];
        
        $vibe = new Vibe($data);
        
        $this->assertEquals(123, $vibe->getId());
        $this->assertEquals('Natural Wine', $vibe->getName());
        $this->assertEquals('natural-wine', $vibe->getSlug());
        $this->assertEquals('Organic and biodynamic wines', $vibe->getDescription());
        $this->assertEquals('social', $vibe->getCategory());
        $this->assertEquals('🍷', $vibe->getIcon());
        $this->assertEquals('#8B0000', $vibe->getColor());
        $this->assertEquals(10, $vibe->getPriority());
        $this->assertEquals('active', $vibe->getStatus());
        $this->assertEquals(['featured' => true], $vibe->getMeta());
        $this->assertEquals(25, $vibe->getCount());
        $this->assertTrue($vibe->isActive());
    }
    
    /**
     * Test creating vibe with minimal data
     */
    public function test_create_vibe_with_minimal_data() {
        $data = [
            'name' => 'Test Vibe',
            'slug' => 'test-vibe'
        ];
        
        $vibe = new Vibe($data);
        
        $this->assertNull($vibe->getId());
        $this->assertEquals('Test Vibe', $vibe->getName());
        $this->assertEquals('test-vibe', $vibe->getSlug());
        $this->assertEquals('', $vibe->getDescription());
        $this->assertEquals('lifestyle', $vibe->getCategory()); // Default
        $this->assertEquals('📍', $vibe->getIcon()); // Default
        $this->assertEquals('#000000', $vibe->getColor()); // Default
        $this->assertEquals(0, $vibe->getPriority()); // Default
        $this->assertEquals('active', $vibe->getStatus()); // Default
        $this->assertEquals([], $vibe->getMeta());
        $this->assertEquals(0, $vibe->getCount());
    }
    
    /**
     * Test vibe validation
     */
    public function test_vibe_validation() {
        // Valid vibe
        $valid_vibe = new Vibe([
            'name' => 'Valid Vibe',
            'slug' => 'valid-vibe',
            'category' => 'social'
        ]);
        
        $this->assertTrue($valid_vibe->isValid());
        $this->assertEmpty($valid_vibe->getValidationErrors());
        
        // Invalid vibe - empty name
        $invalid_vibe = new Vibe([
            'name' => '',
            'slug' => 'test'
        ]);
        
        $this->assertFalse($invalid_vibe->isValid());
        $this->assertContains('Name is required', $invalid_vibe->getValidationErrors());
        
        // Invalid vibe - invalid category
        $invalid_category = new Vibe([
            'name' => 'Test',
            'slug' => 'test',
            'category' => 'invalid_category'
        ]);
        
        $this->assertFalse($invalid_category->isValid());
        $this->assertContains('Invalid category', $invalid_category->getValidationErrors());
    }
    
    /**
     * Test vibe array conversion
     */
    public function test_to_array() {
        $vibe = new Vibe([
            'id' => 1,
            'name' => 'Test Vibe',
            'slug' => 'test-vibe',
            'category' => 'social',
            'status' => 'active'
        ]);
        
        $array = $vibe->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('slug', $array);
        $this->assertArrayHasKey('category', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals('Test Vibe', $array['name']);
    }
    
    /**
     * Test vibe JSON serialization
     */
    public function test_json_serialize() {
        $vibe = new Vibe([
            'id' => 1,
            'name' => 'JSON Test',
            'slug' => 'json-test',
            'meta' => ['key' => 'value']
        ]);
        
        $json = json_encode($vibe);
        $decoded = json_decode($json, true);
        
        $this->assertIsString($json);
        $this->assertEquals(1, $decoded['id']);
        $this->assertEquals('JSON Test', $decoded['name']);
        $this->assertEquals(['key' => 'value'], $decoded['meta']);
    }
    
    /**
     * Test setting individual properties
     */
    public function test_setters() {
        $vibe = new Vibe(['name' => 'Initial', 'slug' => 'initial']);
        
        $vibe->setName('Updated Name');
        $vibe->setDescription('New description');
        $vibe->setCategory('food');
        $vibe->setIcon('🍕');
        $vibe->setColor('#FF0000');
        $vibe->setPriority(5);
        $vibe->setStatus('inactive');
        $vibe->setMeta(['new' => 'data']);
        
        $this->assertEquals('Updated Name', $vibe->getName());
        $this->assertEquals('New description', $vibe->getDescription());
        $this->assertEquals('food', $vibe->getCategory());
        $this->assertEquals('🍕', $vibe->getIcon());
        $this->assertEquals('#FF0000', $vibe->getColor());
        $this->assertEquals(5, $vibe->getPriority());
        $this->assertEquals('inactive', $vibe->getStatus());
        $this->assertEquals(['new' => 'data'], $vibe->getMeta());
        $this->assertFalse($vibe->isActive());
    }
    
    /**
     * Test vibe categories
     */
    public function test_get_available_categories() {
        $categories = Vibe::getAvailableCategories();
        
        $this->assertIsArray($categories);
        $this->assertContains('social', $categories);
        $this->assertContains('food', $categories);
        $this->assertContains('lifestyle', $categories);
        $this->assertContains('atmosphere', $categories);
    }
    
    /**
     * Test vibe status values
     */
    public function test_get_available_statuses() {
        $statuses = Vibe::getAvailableStatuses();
        
        $this->assertIsArray($statuses);
        $this->assertContains('active', $statuses);
        $this->assertContains('inactive', $statuses);
        $this->assertContains('pending', $statuses);
    }
    
    /**
     * Test creating vibe from WP_Term
     */
    public function test_from_wp_term() {
        // Create mock WP_Term
        $wp_term = new \stdClass();
        $wp_term->term_id = 123;
        $wp_term->name = 'WP Term Vibe';
        $wp_term->slug = 'wp-term-vibe';
        $wp_term->description = 'From WordPress term';
        $wp_term->count = 10;
        
        // Mock term meta
        add_filter('get_term_meta', function($value, $term_id, $key, $single) {
            if ($term_id === 123) {
                switch ($key) {
                    case 'vibe_category':
                        return $single ? 'social' : ['social'];
                    case 'vibe_icon':
                        return $single ? '🎉' : ['🎉'];
                    case 'vibe_color':
                        return $single ? '#00FF00' : ['#00FF00'];
                    case 'vibe_priority':
                        return $single ? 8 : [8];
                    case 'vibe_status':
                        return $single ? 'active' : ['active'];
                }
            }
            return $value;
        }, 10, 4);
        
        $vibe = Vibe::fromWpTerm($wp_term);
        
        $this->assertInstanceOf(Vibe::class, $vibe);
        $this->assertEquals(123, $vibe->getId());
        $this->assertEquals('WP Term Vibe', $vibe->getName());
        $this->assertEquals('wp-term-vibe', $vibe->getSlug());
        $this->assertEquals('From WordPress term', $vibe->getDescription());
        $this->assertEquals('social', $vibe->getCategory());
        $this->assertEquals('🎉', $vibe->getIcon());
        $this->assertEquals('#00FF00', $vibe->getColor());
        $this->assertEquals(8, $vibe->getPriority());
        $this->assertEquals('active', $vibe->getStatus());
        $this->assertEquals(10, $vibe->getCount());
    }
    
    /**
     * Test vibe comparison
     */
    public function test_equals() {
        $vibe1 = new Vibe(['id' => 1, 'name' => 'Vibe 1', 'slug' => 'vibe-1']);
        $vibe2 = new Vibe(['id' => 1, 'name' => 'Vibe 1', 'slug' => 'vibe-1']);
        $vibe3 = new Vibe(['id' => 2, 'name' => 'Vibe 2', 'slug' => 'vibe-2']);
        
        $this->assertTrue($vibe1->equals($vibe2));
        $this->assertFalse($vibe1->equals($vibe3));
    }
    
    /**
     * Test vibe cloning
     */
    public function test_clone() {
        $original = new Vibe([
            'id' => 1,
            'name' => 'Original',
            'slug' => 'original',
            'meta' => ['key' => 'value']
        ]);
        
        $clone = clone $original;
        $clone->setName('Cloned');
        $clone->setMeta(['key' => 'changed']);
        
        $this->assertEquals('Original', $original->getName());
        $this->assertEquals('Cloned', $clone->getName());
        $this->assertEquals(['key' => 'value'], $original->getMeta());
        $this->assertEquals(['key' => 'changed'], $clone->getMeta());
    }
    
    /**
     * @dataProvider colorValidationProvider
     */
    public function test_color_validation($color, $expected_valid) {
        $vibe = new Vibe([
            'name' => 'Test',
            'slug' => 'test',
            'color' => $color
        ]);
        
        if ($expected_valid) {
            $this->assertTrue($vibe->isValid());
        } else {
            $this->assertFalse($vibe->isValid());
            $this->assertContains('Invalid color format', $vibe->getValidationErrors());
        }
    }
    
    public function colorValidationProvider() {
        return [
            ['#FF0000', true],
            ['#000000', true],
            ['#ABC123', true],
            ['#GGGGGG', false],
            ['FF0000', false],
            ['#FF00', false],
            ['red', false],
            ['', false],
        ];
    }
    
    /**
     * @dataProvider priorityValidationProvider
     */
    public function test_priority_validation($priority, $expected_valid) {
        $vibe = new Vibe([
            'name' => 'Test',
            'slug' => 'test',
            'priority' => $priority
        ]);
        
        if ($expected_valid) {
            $this->assertTrue($vibe->isValid());
        } else {
            $this->assertFalse($vibe->isValid());
            $this->assertContains('Priority must be between 0 and 100', $vibe->getValidationErrors());
        }
    }
    
    public function priorityValidationProvider() {
        return [
            [0, true],
            [50, true],
            [100, true],
            [-1, false],
            [101, false],
            [1000, false],
        ];
    }
}