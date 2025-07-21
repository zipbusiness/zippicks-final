<?php
/**
 * Unit tests for VibeService
 *
 * @package ZipPicks_Vibes\Tests\Unit\Services
 */

namespace ZipPicks\Vibes\Tests\Unit\Services;

use ZipPicks\Vibes\Tests\TestCase;
use ZipPicks\Vibes\Services\VibeService;
use ZipPicks\Vibes\Repositories\VibeRepositoryInterface;
use ZipPicks\Vibes\Cache\CacheManager;
use Psr\Log\LoggerInterface;
use WP_Error;

class VibeServiceTest extends TestCase {
    
    /**
     * @var VibeService
     */
    private $service;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|VibeRepositoryInterface
     */
    private $mockRepository;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|CacheManager
     */
    private $mockCache;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    private $mockLogger;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create mocks
        $this->mockRepository = $this->createMock(VibeRepositoryInterface::class);
        $this->mockCache = $this->createMock(CacheManager::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        // Create service with mocked dependencies
        $this->service = new VibeService(
            $this->mockRepository,
            $this->mockCache,
            $this->mockLogger
        );
    }
    
    /**
     * Test creating a vibe successfully
     */
    public function test_create_vibe_success() {
        $vibe_data = [
            'name' => 'Natural Wine',
            'slug' => 'natural-wine',
            'description' => 'Organic and biodynamic wines',
            'category' => 'social',
            'icon' => '🍷',
            'color' => '#8B0000',
            'priority' => 10,
            'status' => 'active'
        ];
        
        // Mock repository to return a vibe ID
        $this->mockRepository->expects($this->once())
            ->method('create')
            ->with($this->equalTo($vibe_data))
            ->willReturn(123);
        
        // Mock cache clearing
        $this->mockCache->expects($this->once())
            ->method('delete')
            ->with('vibes_all');
        
        // Execute
        $result = $this->service->create($vibe_data);
        
        // Assert
        $this->assertEquals(123, $result);
    }
    
    /**
     * Test creating a vibe with validation error
     */
    public function test_create_vibe_validation_error() {
        $invalid_data = [
            'name' => '', // Empty name should fail validation
            'slug' => 'test-vibe'
        ];
        
        // Repository should not be called if validation fails
        $this->mockRepository->expects($this->never())
            ->method('create');
        
        // Execute
        $result = $this->service->create($invalid_data);
        
        // Assert
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertEquals('invalid_vibe_data', $result->get_error_code());
    }
    
    /**
     * Test getting a vibe by ID with cache hit
     */
    public function test_get_vibe_cache_hit() {
        $vibe_id = 123;
        $cached_vibe = [
            'id' => 123,
            'name' => 'Cozy Corners',
            'slug' => 'cozy-corners'
        ];
        
        // Mock cache hit
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with("vibe_{$vibe_id}")
            ->willReturn($cached_vibe);
        
        // Repository should not be called on cache hit
        $this->mockRepository->expects($this->never())
            ->method('find');
        
        // Execute
        $result = $this->service->get($vibe_id);
        
        // Assert
        $this->assertEquals($cached_vibe, $result);
    }
    
    /**
     * Test getting a vibe by ID with cache miss
     */
    public function test_get_vibe_cache_miss() {
        $vibe_id = 123;
        $vibe_data = [
            'id' => 123,
            'name' => 'Work From Here',
            'slug' => 'work-from-here'
        ];
        
        // Mock cache miss
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with("vibe_{$vibe_id}")
            ->willReturn(null);
        
        // Mock repository returning vibe
        $this->mockRepository->expects($this->once())
            ->method('find')
            ->with($vibe_id)
            ->willReturn($vibe_data);
        
        // Mock cache set
        $this->mockCache->expects($this->once())
            ->method('set')
            ->with("vibe_{$vibe_id}", $vibe_data, 3600);
        
        // Execute
        $result = $this->service->get($vibe_id);
        
        // Assert
        $this->assertEquals($vibe_data, $result);
    }
    
    /**
     * Test updating a vibe
     */
    public function test_update_vibe() {
        $vibe_id = 123;
        $update_data = [
            'name' => 'Updated Vibe Name',
            'status' => 'inactive'
        ];
        
        // Mock repository update
        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with($vibe_id, $update_data)
            ->willReturn(true);
        
        // Mock cache operations
        $this->mockCache->expects($this->exactly(2))
            ->method('delete')
            ->withConsecutive(
                ["vibe_{$vibe_id}"],
                ['vibes_all']
            );
        
        // Execute
        $result = $this->service->update($vibe_id, $update_data);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test deleting a vibe
     */
    public function test_delete_vibe() {
        $vibe_id = 123;
        
        // Mock repository delete
        $this->mockRepository->expects($this->once())
            ->method('delete')
            ->with($vibe_id)
            ->willReturn(true);
        
        // Mock cache clearing
        $this->mockCache->expects($this->exactly(2))
            ->method('delete')
            ->withConsecutive(
                ["vibe_{$vibe_id}"],
                ['vibes_all']
            );
        
        // Mock logging
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Vibe deleted'));
        
        // Execute
        $result = $this->service->delete($vibe_id);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test searching vibes
     */
    public function test_search_vibes() {
        $search_params = [
            'query' => 'wine',
            'category' => 'social',
            'status' => 'active',
            'limit' => 10
        ];
        
        $search_results = [
            ['id' => 1, 'name' => 'Natural Wine'],
            ['id' => 2, 'name' => 'Wine Bar Vibes']
        ];
        
        // Create cache key
        $cache_key = 'vibes_search_' . md5(serialize($search_params));
        
        // Mock cache miss
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with($cache_key)
            ->willReturn(null);
        
        // Mock repository search
        $this->mockRepository->expects($this->once())
            ->method('search')
            ->with($search_params)
            ->willReturn($search_results);
        
        // Mock cache set
        $this->mockCache->expects($this->once())
            ->method('set')
            ->with($cache_key, $search_results, 1800);
        
        // Execute
        $result = $this->service->search($search_params);
        
        // Assert
        $this->assertEquals($search_results, $result);
    }
    
    /**
     * Test getting popular vibes
     */
    public function test_get_popular_vibes() {
        $limit = 5;
        $popular_vibes = [
            ['id' => 1, 'name' => 'Date Night', 'count' => 150],
            ['id' => 2, 'name' => 'Work From Here', 'count' => 120],
            ['id' => 3, 'name' => 'Natural Wine', 'count' => 100],
            ['id' => 4, 'name' => 'Dog Friendly', 'count' => 90],
            ['id' => 5, 'name' => 'Cozy Corners', 'count' => 85]
        ];
        
        // Mock cache miss
        $this->mockCache->expects($this->once())
            ->method('get')
            ->with("vibes_popular_{$limit}")
            ->willReturn(null);
        
        // Mock repository
        $this->mockRepository->expects($this->once())
            ->method('getPopular')
            ->with($limit)
            ->willReturn($popular_vibes);
        
        // Mock cache set
        $this->mockCache->expects($this->once())
            ->method('set')
            ->with("vibes_popular_{$limit}", $popular_vibes, 3600);
        
        // Execute
        $result = $this->service->getPopular($limit);
        
        // Assert
        $this->assertCount(5, $result);
        $this->assertEquals($popular_vibes, $result);
    }
    
    /**
     * Test getting vibes by category
     */
    public function test_get_vibes_by_category() {
        $category = 'social';
        $expected_vibes = [
            ['id' => 1, 'name' => 'Date Night', 'category' => 'social'],
            ['id' => 2, 'name' => 'Happy Hour', 'category' => 'social']
        ];
        
        // Mock repository
        $this->mockRepository->expects($this->once())
            ->method('findByCategory')
            ->with($category)
            ->willReturn($expected_vibes);
        
        // Execute
        $result = $this->service->getByCategory($category);
        
        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals($expected_vibes, $result);
    }
    
    /**
     * Test bulk update vibes
     */
    public function test_bulk_update_vibes() {
        $vibe_ids = [1, 2, 3];
        $update_data = ['status' => 'inactive'];
        
        // Mock repository bulk update
        $this->mockRepository->expects($this->once())
            ->method('bulkUpdate')
            ->with($vibe_ids, $update_data)
            ->willReturn(3);
        
        // Mock cache clearing
        $this->mockCache->expects($this->once())
            ->method('flush')
            ->with('vibes_');
        
        // Execute
        $result = $this->service->bulkUpdate($vibe_ids, $update_data);
        
        // Assert
        $this->assertEquals(3, $result);
    }
    
    /**
     * Test activating a vibe
     */
    public function test_activate_vibe() {
        $vibe_id = 123;
        
        // Mock update call with status active
        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with($vibe_id, ['status' => 'active'])
            ->willReturn(true);
        
        // Mock cache clearing
        $this->mockCache->expects($this->exactly(2))
            ->method('delete');
        
        // Execute
        $result = $this->service->activate($vibe_id);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test deactivating a vibe
     */
    public function test_deactivate_vibe() {
        $vibe_id = 123;
        
        // Mock update call with status inactive
        $this->mockRepository->expects($this->once())
            ->method('update')
            ->with($vibe_id, ['status' => 'inactive'])
            ->willReturn(true);
        
        // Mock cache clearing
        $this->mockCache->expects($this->exactly(2))
            ->method('delete');
        
        // Execute
        $result = $this->service->deactivate($vibe_id);
        
        // Assert
        $this->assertTrue($result);
    }
}