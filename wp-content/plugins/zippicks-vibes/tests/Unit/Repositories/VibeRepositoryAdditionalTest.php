<?php
/**
 * Additional Unit tests for VibeRepository
 * 
 * Tests pagination, complex queries, transactions, cache, and error scenarios
 *
 * @package ZipPicksVibes\Tests\Unit\Repositories
 */

namespace ZipPicksVibes\Tests\Unit\Repositories;

use ZipPicksVibes\Tests\TestCase;
use ZipPicksVibes\Repositories\VibeRepository;
use ZipPicksVibes\Models\PaginatedResult;
use PHPUnit\Framework\MockObject\MockObject;

class VibeRepositoryAdditionalTest extends TestCase {
    
    /**
     * @var VibeRepository
     */
    private $repository;
    
    /**
     * @var MockObject
     */
    private $mockCache;
    
    /**
     * @var MockObject
     */
    private $mockLogger;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create mocks
        $this->mockCache = $this->createMock(\stdClass::class);
        $this->mockLogger = $this->createMock(\stdClass::class);
        
        // Add cache methods to mock
        $this->mockCache->method('get')->willReturn(false);
        $this->mockCache->method('set')->willReturn(true);
        $this->mockCache->method('delete')->willReturn(true);
        
        // Create repository with mocked dependencies
        $this->repository = new VibeRepository(null, $this->mockCache, $this->mockLogger);
        
        // Create test vibes table structure
        $this->createTestTables();
    }
    
    public function tearDown(): void {
        $this->dropTestTables();
        parent::tearDown();
    }
    
    /**
     * Test pagination with various scenarios
     * 
     * @group pagination
     */
    public function test_pagination_functionality() {
        // Create 25 test vibes
        for ($i = 1; $i <= 25; $i++) {
            $this->createTestVibe([
                'name' => "Paginated Vibe $i",
                'order_position' => $i,
                'is_active' => 1
            ]);
        }
        
        // Test first page
        $page1 = $this->repository->findPaginated(1, 10);
        $this->assertInstanceOf(PaginatedResult::class, $page1);
        $this->assertEquals(10, count($page1->items));
        $this->assertEquals(25, $page1->total);
        $this->assertEquals(1, $page1->currentPage);
        $this->assertEquals(3, $page1->totalPages);
        $this->assertTrue($page1->hasMorePages());
        
        // Test second page
        $page2 = $this->repository->findPaginated(2, 10);
        $this->assertEquals(10, count($page2->items));
        $this->assertEquals(2, $page2->currentPage);
        
        // Test last page
        $page3 = $this->repository->findPaginated(3, 10);
        $this->assertEquals(5, count($page3->items));
        $this->assertEquals(3, $page3->currentPage);
        $this->assertFalse($page3->hasMorePages());
        
        // Test beyond last page
        $page4 = $this->repository->findPaginated(4, 10);
        $this->assertEquals(0, count($page4->items));
        $this->assertEquals(4, $page4->currentPage);
    }
    
    /**
     * Test pagination with different page sizes
     * 
     * @group pagination
     */
    public function test_pagination_with_different_page_sizes() {
        // Create 50 test vibes
        for ($i = 1; $i <= 50; $i++) {
            $this->createTestVibe(['name' => "Vibe $i"]);
        }
        
        // Test with small page size
        $smallPage = $this->repository->findPaginated(1, 5);
        $this->assertEquals(5, count($smallPage->items));
        $this->assertEquals(10, $smallPage->totalPages);
        
        // Test with large page size
        $largePage = $this->repository->findPaginated(1, 25);
        $this->assertEquals(25, count($largePage->items));
        $this->assertEquals(2, $largePage->totalPages);
        
        // Test with max page size limit (100)
        $maxPage = $this->repository->findPaginated(1, 150);
        $this->assertEquals(50, count($maxPage->items)); // Should get all 50, not 150
    }
    
    /**
     * Test complex search queries
     * 
     * @group search
     */
    public function test_complex_search_queries() {
        // Create vibes with various attributes
        $this->createTestVibe([
            'name' => 'Natural Wine Bar',
            'description' => 'Cozy spot for organic wines'
        ]);
        $this->createTestVibe([
            'name' => 'Coffee & Wine',
            'description' => 'Day cafe, night wine bar'
        ]);
        $this->createTestVibe([
            'name' => 'Sunset Rooftop',
            'description' => 'Beautiful views with cocktails'
        ]);
        
        // Test exact name match
        $results = $this->repository->search('Natural Wine Bar');
        $this->assertEquals(1, count($results));
        $this->assertEquals('Natural Wine Bar', $results[0]->name);
        
        // Test partial match
        $wineResults = $this->repository->search('wine');
        $this->assertEquals(2, count($wineResults));
        
        // Test description search
        $cozyResults = $this->repository->search('cozy');
        $this->assertEquals(1, count($cozyResults));
        
        // Test no results
        $noResults = $this->repository->search('nonexistent');
        $this->assertEquals(0, count($noResults));
    }
    
    /**
     * Test search with pagination
     * 
     * @group search
     * @group pagination
     */
    public function test_search_paginated() {
        // Create 30 vibes with "test" in name
        for ($i = 1; $i <= 30; $i++) {
            $this->createTestVibe(['name' => "Test Vibe $i"]);
        }
        
        // Search with pagination
        $page1 = $this->repository->searchPaginated('test', 1, 10);
        $this->assertInstanceOf(PaginatedResult::class, $page1);
        $this->assertEquals(10, count($page1->items));
        $this->assertEquals(30, $page1->total);
        
        // Test second page
        $page2 = $this->repository->searchPaginated('test', 2, 10);
        $this->assertEquals(10, count($page2->items));
        
        // Test with different search term
        $noResults = $this->repository->searchPaginated('xyz', 1, 10);
        $this->assertEquals(0, count($noResults->items));
        $this->assertEquals(0, $noResults->total);
    }
    
    /**
     * Test transaction handling
     * 
     * @group transactions
     */
    public function test_transaction_handling() {
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        // Create vibe within transaction
        $vibeId = $this->repository->create([
            'name' => 'Transaction Test',
            'slug' => 'transaction-test',
            'is_active' => 1
        ]);
        
        $this->assertIsInt($vibeId);
        
        // Verify it exists within transaction
        $vibe = $this->repository->find($vibeId);
        $this->assertNotNull($vibe);
        
        // Rollback
        $wpdb->query('ROLLBACK');
        
        // Verify it doesn't exist after rollback
        $vibeAfterRollback = $this->repository->find($vibeId);
        $this->assertNull($vibeAfterRollback);
    }
    
    /**
     * Test cache integration
     * 
     * @group cache
     */
    public function test_cache_integration() {
        // Create mock cache that tracks calls
        $cacheData = [];
        $mockCache = $this->createMock(\stdClass::class);
        
        $mockCache->method('get')
            ->willReturnCallback(function($key) use (&$cacheData) {
                return $cacheData[$key] ?? false;
            });
            
        $mockCache->method('set')
            ->willReturnCallback(function($key, $value, $ttl) use (&$cacheData) {
                $cacheData[$key] = $value;
                return true;
            });
            
        $mockCache->method('delete')
            ->willReturnCallback(function($key) use (&$cacheData) {
                unset($cacheData[$key]);
                return true;
            });
        
        // Create repository with tracking cache
        $repository = new VibeRepository(null, $mockCache, $this->mockLogger);
        
        // Create a vibe
        $vibeId = $repository->create([
            'name' => 'Cached Vibe',
            'slug' => 'cached-vibe'
        ]);
        
        // First find should miss cache
        $vibe1 = $repository->find($vibeId);
        $this->assertNotNull($vibe1);
        
        // Second find should hit cache
        $vibe2 = $repository->find($vibeId);
        $this->assertEquals($vibe1->name, $vibe2->name);
        
        // Update should clear cache
        $repository->update($vibeId, ['name' => 'Updated Cached Vibe']);
        
        // Next find should get updated data
        $vibe3 = $repository->find($vibeId);
        $this->assertEquals('Updated Cached Vibe', $vibe3->name);
    }
    
    /**
     * Test findBySlug method
     * 
     * @group slug
     */
    public function test_find_by_slug() {
        // Create test vibes
        $activeId = $this->repository->create([
            'name' => 'Active Vibe',
            'slug' => 'active-vibe',
            'is_active' => 1
        ]);
        
        $inactiveId = $this->repository->create([
            'name' => 'Inactive Vibe',
            'slug' => 'inactive-vibe',
            'is_active' => 0
        ]);
        
        // Test finding active vibe
        $activeVibe = $this->repository->findBySlug('active-vibe');
        $this->assertNotNull($activeVibe);
        $this->assertEquals('Active Vibe', $activeVibe->name);
        
        // Test finding inactive vibe (should return null)
        $inactiveVibe = $this->repository->findBySlug('inactive-vibe');
        $this->assertNull($inactiveVibe);
        
        // Test non-existent slug
        $notFound = $this->repository->findBySlug('non-existent');
        $this->assertNull($notFound);
    }
    
    /**
     * Test error scenarios
     * 
     * @group errors
     */
    public function test_error_scenarios() {
        // Test creating vibe with empty name
        $result = $this->repository->create([
            'name' => '',
            'slug' => 'empty-name'
        ]);
        $this->assertFalse($result);
        
        // Test updating non-existent vibe
        $updateResult = $this->repository->update(999999, ['name' => 'Updated']);
        $this->assertTrue($updateResult); // WordPress update returns true even if no rows affected
        
        // Test deleting non-existent vibe
        $deleteResult = $this->repository->delete(999999);
        $this->assertTrue($deleteResult); // WordPress delete returns true even if no rows affected
    }
    
    /**
     * Test findByCategory method
     * 
     * @group categories
     */
    public function test_find_by_category() {
        // Create categories
        $categoryId1 = $this->createTestCategory('Social');
        $categoryId2 = $this->createTestCategory('Adventure');
        
        // Create vibes
        $vibe1 = $this->repository->create(['name' => 'Social Vibe 1', 'is_active' => 1]);
        $vibe2 = $this->repository->create(['name' => 'Social Vibe 2', 'is_active' => 1]);
        $vibe3 = $this->repository->create(['name' => 'Adventure Vibe', 'is_active' => 1]);
        
        // Assign categories
        $this->repository->assignCategories($vibe1, [$categoryId1]);
        $this->repository->assignCategories($vibe2, [$categoryId1]);
        $this->repository->assignCategories($vibe3, [$categoryId2]);
        
        // Test finding by category
        $socialVibes = $this->repository->findByCategory($categoryId1);
        $this->assertEquals(2, count($socialVibes));
        
        $adventureVibes = $this->repository->findByCategory($categoryId2);
        $this->assertEquals(1, count($adventureVibes));
        
        // Test empty category
        $emptyCategory = $this->createTestCategory('Empty');
        $emptyVibes = $this->repository->findByCategory($emptyCategory);
        $this->assertEquals(0, count($emptyVibes));
    }
    
    /**
     * Test getPopular method with ZIP code filtering
     * 
     * @group popular
     * @group waitlist
     */
    public function test_get_popular_with_zip_filtering() {
        // Create vibes
        $vibe1 = $this->repository->create(['name' => 'Popular in 90210', 'is_active' => 1]);
        $vibe2 = $this->repository->create(['name' => 'Popular in 10001', 'is_active' => 1]);
        $vibe3 = $this->repository->create(['name' => 'Not Popular', 'is_active' => 1]);
        
        // Add waitlist entries
        for ($i = 0; $i < 10; $i++) {
            $this->repository->logWaitlist([
                'vibe_id' => $vibe1,
                'zip_code' => '90210',
                'email' => "user$i@example.com"
            ]);
        }
        
        for ($i = 0; $i < 5; $i++) {
            $this->repository->logWaitlist([
                'vibe_id' => $vibe2,
                'zip_code' => '10001',
                'email' => "user$i@example.com"
            ]);
        }
        
        // Test popular vibes for specific ZIP
        $popular90210 = $this->repository->getPopular(10, '90210');
        $this->assertGreaterThan(0, count($popular90210));
        $this->assertEquals('Popular in 90210', $popular90210[0]->name);
        
        // Test global popular vibes
        $popularGlobal = $this->repository->getPopular(10);
        $this->assertGreaterThan(0, count($popularGlobal));
    }
    
    /**
     * Test updateOrder method
     * 
     * @group order
     */
    public function test_update_order() {
        // Create vibes with initial order
        $vibe1 = $this->repository->create(['name' => 'First', 'order_position' => 1]);
        $vibe2 = $this->repository->create(['name' => 'Second', 'order_position' => 2]);
        $vibe3 = $this->repository->create(['name' => 'Third', 'order_position' => 3]);
        
        // Update order (reverse it)
        $newOrder = [
            0 => $vibe3,
            1 => $vibe2,
            2 => $vibe1
        ];
        
        $result = $this->repository->updateOrder($newOrder);
        $this->assertTrue($result);
        
        // Verify new order
        $vibes = $this->repository->findAll(['orderby' => 'order_position', 'order' => 'ASC']);
        $this->assertEquals('Third', $vibes[0]->name);
        $this->assertEquals('Second', $vibes[1]->name);
        $this->assertEquals('First', $vibes[2]->name);
    }
    
    /**
     * Test concurrent access scenarios
     * 
     * @group concurrency
     */
    public function test_concurrent_access() {
        // Create a vibe
        $vibeId = $this->repository->create(['name' => 'Concurrent Test', 'is_active' => 1]);
        
        // Simulate concurrent updates
        $update1 = $this->repository->update($vibeId, ['description' => 'Update 1']);
        $update2 = $this->repository->update($vibeId, ['description' => 'Update 2']);
        
        $this->assertTrue($update1);
        $this->assertTrue($update2);
        
        // Verify last update wins
        $vibe = $this->repository->find($vibeId);
        $this->assertEquals('Update 2', $vibe->description);
    }
    
    /**
     * Test findAll with various filters
     * 
     * @group filters
     */
    public function test_find_all_with_filters() {
        // Create mixed vibes
        $this->repository->create(['name' => 'Active A', 'is_active' => 1, 'order_position' => 2]);
        $this->repository->create(['name' => 'Active B', 'is_active' => 1, 'order_position' => 1]);
        $this->repository->create(['name' => 'Inactive', 'is_active' => 0, 'order_position' => 0]);
        
        // Test active only (default)
        $activeVibes = $this->repository->findAll();
        $this->assertEquals(2, count($activeVibes));
        
        // Test with different order
        $descVibes = $this->repository->findAll(['order' => 'DESC']);
        $this->assertEquals('Active A', $descVibes[0]->name);
        
        // Test with limit
        $limitedVibes = $this->repository->findAll(['limit' => 1]);
        $this->assertEquals(1, count($limitedVibes));
        
        // Test ordering by name
        $nameOrdered = $this->repository->findAll(['orderby' => 'name']);
        $this->assertEquals('Active A', $nameOrdered[0]->name);
    }
    
    /**
     * Test database error handling
     * 
     * @group errors
     * @group database
     */
    public function test_database_error_handling() {
        global $wpdb;
        
        // Mock logger to verify error logging
        $mockLogger = $this->createMock(\stdClass::class);
        $mockLogger->expects($this->atLeastOnce())
            ->method('error')
            ->with($this->stringContains('Failed to create vibe'));
        
        $repository = new VibeRepository(null, $this->mockCache, $mockLogger);
        
        // Try to create vibe with invalid data that would cause DB error
        // Using very long name that exceeds column limit
        $result = $repository->create([
            'name' => str_repeat('a', 1000), // Exceeds typical varchar limit
            'slug' => 'test'
        ]);
        
        // Should handle error gracefully
        $this->assertFalse($result);
    }
    
    /**
     * Helper method to create test tables
     */
    private function createTestTables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create vibes table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_vibes (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            description text,
            icon varchar(255) DEFAULT 'default',
            color varchar(7) DEFAULT '#000000',
            order_position int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY is_active (is_active),
            KEY order_position (order_position)
        ) $charset_collate;";
        
        $wpdb->query($sql);
        
        // Create categories table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_vibe_categories (
            id int(11) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(255) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        
        $wpdb->query($sql);
        
        // Create assignments table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_vibe_category_assignments (
            vibe_id int(11) NOT NULL,
            category_id int(11) NOT NULL,
            PRIMARY KEY (vibe_id, category_id)
        ) $charset_collate;";
        
        $wpdb->query($sql);
        
        // Create waitlist table
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}zippicks_waitlist (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11),
            ip_address varchar(45),
            email varchar(255),
            zip_code varchar(10),
            city varchar(255),
            state varchar(2),
            vibe_id int(11),
            vibe_slug varchar(255),
            vibe_name varchar(255),
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY zip_code (zip_code),
            KEY vibe_id (vibe_id)
        ) $charset_collate;";
        
        $wpdb->query($sql);
    }
    
    /**
     * Helper method to drop test tables
     */
    private function dropTestTables() {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}zippicks_vibes");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}zippicks_vibe_categories");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}zippicks_vibe_category_assignments");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}zippicks_waitlist");
    }
    
    /**
     * Helper method to create test vibe
     */
    private function createTestVibe($data = []) {
        $defaults = [
            'name' => 'Test Vibe',
            'slug' => 'test-vibe-' . rand(1000, 9999),
            'is_active' => 1
        ];
        
        return $this->repository->create(array_merge($defaults, $data));
    }
    
    /**
     * Helper method to create test category
     */
    private function createTestCategory($name) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'zippicks_vibe_categories',
            [
                'name' => $name,
                'slug' => sanitize_title($name)
            ]
        );
        
        return $wpdb->insert_id;
    }
}