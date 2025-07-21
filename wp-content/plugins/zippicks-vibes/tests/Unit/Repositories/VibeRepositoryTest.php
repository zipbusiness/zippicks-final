<?php
/**
 * Unit tests for VibeRepository
 *
 * @package ZipPicks_Vibes\Tests\Unit\Repositories
 */

namespace ZipPicks\Vibes\Tests\Unit\Repositories;

use ZipPicks\Vibes\Tests\TestCase;
use ZipPicks\Vibes\Repositories\VibeRepository;
use ZipPicks\Vibes\Cache\CacheManager;
use ZipPicks\Vibes\Audit\AuditLogger;
use WP_Error;

class VibeRepositoryTest extends TestCase {
    
    /**
     * @var VibeRepository
     */
    private $repository;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|CacheManager
     */
    private $mockCache;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|AuditLogger
     */
    private $mockAuditLogger;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create mocks
        $this->mockCache = $this->createMock(CacheManager::class);
        $this->mockAuditLogger = $this->createMock(AuditLogger::class);
        
        // Create repository with mocked dependencies
        $this->repository = new VibeRepository($this->mockCache, $this->mockAuditLogger);
    }
    
    /**
     * Test creating a vibe term
     */
    public function test_create_vibe() {
        $vibe_data = [
            'name' => 'Test Vibe',
            'slug' => 'test-vibe',
            'description' => 'Test description',
            'parent' => 0,
            'category' => 'social',
            'icon' => '🎉',
            'color' => '#FF0000',
            'priority' => 50,
            'status' => 'active'
        ];
        
        // Mock audit logging
        $this->mockAuditLogger->expects($this->once())
            ->method('logCreate')
            ->with(
                $this->equalTo('vibes'),
                $this->anything(),
                $this->equalTo($vibe_data)
            );
        
        // Create vibe
        $result = $this->repository->create($vibe_data);
        
        // Assert
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
        
        // Verify term was created
        $term = get_term($result, 'zippicks_vibe');
        $this->assertNotNull($term);
        $this->assertEquals('Test Vibe', $term->name);
        $this->assertEquals('test-vibe', $term->slug);
        
        // Verify meta data
        $this->assertEquals('social', get_term_meta($result, 'category', true));
        $this->assertEquals('🎉', get_term_meta($result, 'icon', true));
        $this->assertEquals('#FF0000', get_term_meta($result, 'color', true));
        $this->assertEquals(50, get_term_meta($result, 'priority', true));
        $this->assertEquals('active', get_term_meta($result, 'status', true));
    }
    
    /**
     * Test creating a vibe with invalid data
     */
    public function test_create_vibe_with_invalid_data() {
        $invalid_data = [
            'name' => '', // Empty name
            'slug' => 'test'
        ];
        
        $result = $this->repository->create($invalid_data);
        
        $this->assertInstanceOf(WP_Error::class, $result);
    }
    
    /**
     * Test finding a vibe by ID
     */
    public function test_find_vibe_by_id() {
        // Create a test vibe first
        $vibe_id = $this->create_test_vibe([
            'name' => 'Find Me Vibe',
            'slug' => 'find-me-vibe',
            'meta' => [
                'category' => 'adventure',
                'icon' => '🔍',
                'status' => 'active'
            ]
        ]);
        
        // Find the vibe
        $result = $this->repository->find($vibe_id);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertEquals($vibe_id, $result['id']);
        $this->assertEquals('Find Me Vibe', $result['name']);
        $this->assertEquals('find-me-vibe', $result['slug']);
        $this->assertEquals('adventure', $result['category']);
        $this->assertEquals('🔍', $result['icon']);
        $this->assertEquals('active', $result['status']);
    }
    
    /**
     * Test finding non-existent vibe
     */
    public function test_find_non_existent_vibe() {
        $result = $this->repository->find(999999);
        
        $this->assertNull($result);
    }
    
    /**
     * Test updating a vibe
     */
    public function test_update_vibe() {
        // Create a test vibe
        $vibe_id = $this->create_test_vibe([
            'name' => 'Original Name',
            'meta' => ['status' => 'active']
        ]);
        
        // Update data
        $update_data = [
            'name' => 'Updated Name',
            'description' => 'Updated description',
            'status' => 'inactive',
            'priority' => 75
        ];
        
        // Mock audit logging
        $this->mockAuditLogger->expects($this->once())
            ->method('logUpdate')
            ->with(
                $this->equalTo('vibes'),
                $this->equalTo($vibe_id),
                $this->anything(),
                $this->equalTo($update_data)
            );
        
        // Update vibe
        $result = $this->repository->update($vibe_id, $update_data);
        
        // Assert
        $this->assertTrue($result);
        
        // Verify updates
        $term = get_term($vibe_id, 'zippicks_vibe');
        $this->assertEquals('Updated Name', $term->name);
        $this->assertEquals('Updated description', $term->description);
        $this->assertEquals('inactive', get_term_meta($vibe_id, 'status', true));
        $this->assertEquals(75, get_term_meta($vibe_id, 'priority', true));
    }
    
    /**
     * Test deleting a vibe
     */
    public function test_delete_vibe() {
        // Create a test vibe
        $vibe_id = $this->create_test_vibe(['name' => 'Delete Me']);
        
        // Mock audit logging
        $this->mockAuditLogger->expects($this->once())
            ->method('logDelete')
            ->with(
                $this->equalTo('vibes'),
                $this->equalTo($vibe_id),
                $this->anything()
            );
        
        // Delete vibe
        $result = $this->repository->delete($vibe_id);
        
        // Assert
        $this->assertTrue($result);
        
        // Verify deletion
        $term = get_term($vibe_id, 'zippicks_vibe');
        $this->assertTrue(is_wp_error($term) || $term === null);
    }
    
    /**
     * Test finding all vibes
     */
    public function test_find_all_vibes() {
        // Create test vibes
        $vibe1 = $this->create_test_vibe(['name' => 'Vibe 1', 'meta' => ['priority' => 10]]);
        $vibe2 = $this->create_test_vibe(['name' => 'Vibe 2', 'meta' => ['priority' => 20]]);
        $vibe3 = $this->create_test_vibe(['name' => 'Vibe 3', 'meta' => ['priority' => 30]]);
        
        // Find all
        $result = $this->repository->findAll();
        
        // Assert
        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(3, count($result));
        
        // Check if our vibes are in the result
        $vibe_ids = array_column($result, 'id');
        $this->assertContains($vibe1, $vibe_ids);
        $this->assertContains($vibe2, $vibe_ids);
        $this->assertContains($vibe3, $vibe_ids);
    }
    
    /**
     * Test finding vibes by category
     */
    public function test_find_by_category() {
        // Create test vibes with different categories
        $social1 = $this->create_test_vibe([
            'name' => 'Social 1',
            'meta' => ['category' => 'social']
        ]);
        $social2 = $this->create_test_vibe([
            'name' => 'Social 2',
            'meta' => ['category' => 'social']
        ]);
        $adventure = $this->create_test_vibe([
            'name' => 'Adventure 1',
            'meta' => ['category' => 'adventure']
        ]);
        
        // Find by category
        $result = $this->repository->findByCategory('social');
        
        // Assert
        $this->assertIsArray($result);
        $vibe_ids = array_column($result, 'id');
        $this->assertContains($social1, $vibe_ids);
        $this->assertContains($social2, $vibe_ids);
        $this->assertNotContains($adventure, $vibe_ids);
    }
    
    /**
     * Test searching vibes
     */
    public function test_search_vibes() {
        // Create test vibes
        $wine1 = $this->create_test_vibe(['name' => 'Natural Wine']);
        $wine2 = $this->create_test_vibe(['name' => 'Wine Bar Vibes']);
        $coffee = $this->create_test_vibe(['name' => 'Coffee Shop']);
        
        // Search for "wine"
        $result = $this->repository->search(['query' => 'wine']);
        
        // Assert
        $this->assertIsArray($result);
        $vibe_ids = array_column($result, 'id');
        $this->assertContains($wine1, $vibe_ids);
        $this->assertContains($wine2, $vibe_ids);
        $this->assertNotContains($coffee, $vibe_ids);
    }
    
    /**
     * Test getting popular vibes
     */
    public function test_get_popular_vibes() {
        // Create test vibes with different counts
        $popular1 = $this->create_test_vibe(['name' => 'Popular 1']);
        $popular2 = $this->create_test_vibe(['name' => 'Popular 2']);
        $unpopular = $this->create_test_vibe(['name' => 'Unpopular']);
        
        // Simulate usage counts by adding test posts
        for ($i = 0; $i < 10; $i++) {
            wp_set_object_terms($this->factory->post->create(), $popular1, 'zippicks_vibe');
        }
        for ($i = 0; $i < 5; $i++) {
            wp_set_object_terms($this->factory->post->create(), $popular2, 'zippicks_vibe');
        }
        
        // Get popular vibes
        $result = $this->repository->getPopular(2);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        
        // Check order (most popular first)
        if (count($result) >= 2) {
            $this->assertGreaterThanOrEqual($result[1]['count'], $result[0]['count']);
        }
    }
    
    /**
     * Test bulk update
     */
    public function test_bulk_update() {
        // Create test vibes
        $vibe1 = $this->create_test_vibe(['name' => 'Bulk 1', 'meta' => ['status' => 'active']]);
        $vibe2 = $this->create_test_vibe(['name' => 'Bulk 2', 'meta' => ['status' => 'active']]);
        $vibe3 = $this->create_test_vibe(['name' => 'Bulk 3', 'meta' => ['status' => 'active']]);
        
        // Bulk update
        $result = $this->repository->bulkUpdate(
            [$vibe1, $vibe2, $vibe3],
            ['status' => 'inactive']
        );
        
        // Assert
        $this->assertEquals(3, $result);
        
        // Verify updates
        $this->assertEquals('inactive', get_term_meta($vibe1, 'status', true));
        $this->assertEquals('inactive', get_term_meta($vibe2, 'status', true));
        $this->assertEquals('inactive', get_term_meta($vibe3, 'status', true));
    }
    
    /**
     * Test paginated results
     */
    public function test_paginated_results() {
        // Create test vibes
        for ($i = 1; $i <= 15; $i++) {
            $this->create_test_vibe(['name' => "Paginated Vibe $i"]);
        }
        
        // Get first page
        $page1 = $this->repository->paginate(1, 10);
        
        // Assert first page
        $this->assertArrayHasKey('data', $page1);
        $this->assertArrayHasKey('total', $page1);
        $this->assertArrayHasKey('page', $page1);
        $this->assertArrayHasKey('per_page', $page1);
        $this->assertCount(10, $page1['data']);
        $this->assertEquals(1, $page1['page']);
        
        // Get second page
        $page2 = $this->repository->paginate(2, 10);
        
        // Assert second page
        $this->assertGreaterThanOrEqual(5, count($page2['data']));
        $this->assertEquals(2, $page2['page']);
    }
}