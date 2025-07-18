<?php
/**
 * Unit tests for CacheManager
 *
 * @package ZipPicks_Vibes\Tests\Unit\Cache
 */

namespace ZipPicks\Vibes\Tests\Unit\Cache;

use ZipPicks\Vibes\Tests\TestCase;
use ZipPicks\Vibes\Cache\CacheManager;
use ZipPicks\Vibes\Cache\Adapters\CacheAdapterInterface;
use ZipPicks\Vibes\Container;
use Psr\Log\LoggerInterface;

class CacheManagerTest extends TestCase {
    
    /**
     * @var CacheManager
     */
    private $cacheManager;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|CacheAdapterInterface
     */
    private $mockAdapter;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    private $mockLogger;
    
    public function setUp(): void {
        parent::setUp();
        
        // Create mocks
        $this->mockAdapter = $this->createMock(CacheAdapterInterface::class);
        $this->mockLogger = $this->createMock(LoggerInterface::class);
        
        // Mock container to return our mock adapter
        $mockContainer = $this->createMock(Container::class);
        $mockContainer->method('make')
            ->willReturn($this->mockAdapter);
        
        // Create cache manager
        $this->cacheManager = new CacheManager($mockContainer, $this->mockLogger);
    }
    
    /**
     * Test getting a cached value
     */
    public function test_get_cached_value() {
        $key = 'test_key';
        $expected_value = ['data' => 'test'];
        
        // Mock adapter behavior
        $this->mockAdapter->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn($expected_value);
        
        // Execute
        $result = $this->cacheManager->get($key);
        
        // Assert
        $this->assertEquals($expected_value, $result);
    }
    
    /**
     * Test getting with default value when cache miss
     */
    public function test_get_with_default_on_cache_miss() {
        $key = 'missing_key';
        $default = 'default_value';
        
        // Mock cache miss
        $this->mockAdapter->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn(null);
        
        // Execute
        $result = $this->cacheManager->get($key, $default);
        
        // Assert
        $this->assertEquals($default, $result);
    }
    
    /**
     * Test setting a cache value
     */
    public function test_set_cache_value() {
        $key = 'test_key';
        $value = ['data' => 'test'];
        $ttl = 3600;
        
        // Mock adapter behavior
        $this->mockAdapter->expects($this->once())
            ->method('set')
            ->with($key, $value, $ttl)
            ->willReturn(true);
        
        // Execute
        $result = $this->cacheManager->set($key, $value, $ttl);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test deleting a cache value
     */
    public function test_delete_cache_value() {
        $key = 'test_key';
        
        // Mock adapter behavior
        $this->mockAdapter->expects($this->once())
            ->method('delete')
            ->with($key)
            ->willReturn(true);
        
        // Mock logging
        $this->mockLogger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Cache key deleted'));
        
        // Execute
        $result = $this->cacheManager->delete($key);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test checking if key exists
     */
    public function test_has_key() {
        $key = 'test_key';
        
        // Mock adapter behavior
        $this->mockAdapter->expects($this->once())
            ->method('has')
            ->with($key)
            ->willReturn(true);
        
        // Execute
        $result = $this->cacheManager->has($key);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test flushing cache by prefix
     */
    public function test_flush_by_prefix() {
        $prefix = 'vibes_';
        
        // Mock adapter behavior
        $this->mockAdapter->expects($this->once())
            ->method('flush')
            ->with($prefix)
            ->willReturn(true);
        
        // Mock logging
        $this->mockLogger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Cache flushed'));
        
        // Execute
        $result = $this->cacheManager->flush($prefix);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test remember functionality (get or compute)
     */
    public function test_remember_with_cache_hit() {
        $key = 'remember_key';
        $cached_value = 'cached_data';
        
        // Mock cache hit
        $this->mockAdapter->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn($cached_value);
        
        // Callback should not be called on cache hit
        $callback = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['__invoke'])
            ->getMock();
        $callback->expects($this->never())
            ->method('__invoke');
        
        // Execute
        $result = $this->cacheManager->remember($key, $callback, 3600);
        
        // Assert
        $this->assertEquals($cached_value, $result);
    }
    
    /**
     * Test remember functionality with cache miss
     */
    public function test_remember_with_cache_miss() {
        $key = 'remember_key';
        $computed_value = 'computed_data';
        $ttl = 3600;
        
        // Mock cache miss
        $this->mockAdapter->expects($this->once())
            ->method('get')
            ->with($key)
            ->willReturn(null);
        
        // Mock cache set
        $this->mockAdapter->expects($this->once())
            ->method('set')
            ->with($key, $computed_value, $ttl)
            ->willReturn(true);
        
        // Callback should be called
        $callback = function() use ($computed_value) {
            return $computed_value;
        };
        
        // Execute
        $result = $this->cacheManager->remember($key, $callback, $ttl);
        
        // Assert
        $this->assertEquals($computed_value, $result);
    }
    
    /**
     * Test getting multiple values
     */
    public function test_get_multiple() {
        $keys = ['key1', 'key2', 'key3'];
        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => null
        ];
        
        // Mock adapter behavior
        $this->mockAdapter->expects($this->once())
            ->method('getMultiple')
            ->with($keys)
            ->willReturn($expected);
        
        // Execute
        $result = $this->cacheManager->getMultiple($keys);
        
        // Assert
        $this->assertEquals($expected, $result);
    }
    
    /**
     * Test setting multiple values
     */
    public function test_set_multiple() {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];
        $ttl = 3600;
        
        // Mock adapter behavior
        $this->mockAdapter->expects($this->once())
            ->method('setMultiple')
            ->with($values, $ttl)
            ->willReturn(true);
        
        // Execute
        $result = $this->cacheManager->setMultiple($values, $ttl);
        
        // Assert
        $this->assertTrue($result);
    }
    
    /**
     * Test getting cache statistics
     */
    public function test_get_stats() {
        $expected_stats = [
            'hits' => 150,
            'misses' => 50,
            'hit_rate' => 0.75,
            'memory_usage' => 1024000,
            'keys_count' => 200
        ];
        
        // Mock adapter behavior
        $this->mockAdapter->expects($this->once())
            ->method('getStats')
            ->willReturn($expected_stats);
        
        // Execute
        $result = $this->cacheManager->getStats();
        
        // Assert
        $this->assertEquals($expected_stats, $result);
    }
    
    /**
     * Test adapter switching
     */
    public function test_switch_adapter() {
        // Create a second mock adapter
        $newAdapter = $this->createMock(CacheAdapterInterface::class);
        
        // Mock container to return new adapter
        $mockContainer = $this->createMock(Container::class);
        $mockContainer->expects($this->once())
            ->method('make')
            ->with('cache.adapter.redis')
            ->willReturn($newAdapter);
        
        // Create new cache manager
        $cacheManager = new CacheManager($mockContainer, $this->mockLogger);
        
        // Switch adapter
        $cacheManager->useAdapter('redis');
        
        // Test that new adapter is used
        $newAdapter->expects($this->once())
            ->method('get')
            ->with('test')
            ->willReturn('from_redis');
        
        $result = $cacheManager->get('test');
        $this->assertEquals('from_redis', $result);
    }
    
    /**
     * Test error handling
     */
    public function test_error_handling() {
        $key = 'error_key';
        
        // Mock adapter throwing exception
        $this->mockAdapter->expects($this->once())
            ->method('get')
            ->with($key)
            ->willThrowException(new \Exception('Cache error'));
        
        // Mock error logging
        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Cache error'));
        
        // Execute - should return null on error
        $result = $this->cacheManager->get($key);
        
        // Assert
        $this->assertNull($result);
    }
    
    /**
     * Test cache key sanitization
     */
    public function test_key_sanitization() {
        $invalid_key = 'invalid key with spaces!@#$%';
        $value = 'test_value';
        
        // The cache manager should sanitize the key
        // Mock adapter should receive sanitized key
        $this->mockAdapter->expects($this->once())
            ->method('set')
            ->with(
                $this->matchesRegularExpression('/^[a-zA-Z0-9_-]+$/'),
                $value,
                $this->anything()
            )
            ->willReturn(true);
        
        // Execute
        $result = $this->cacheManager->set($invalid_key, $value);
        
        // Assert
        $this->assertTrue($result);
    }
}