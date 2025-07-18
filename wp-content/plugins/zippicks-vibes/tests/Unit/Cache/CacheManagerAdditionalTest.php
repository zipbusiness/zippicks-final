<?php
/**
 * Additional unit tests for CacheManager
 *
 * @package ZipPicks_Vibes\Tests\Unit\Cache
 */

namespace ZipPicks\Vibes\Tests\Unit\Cache;

use ZipPicks\Vibes\Tests\TestCase;
use ZipPicks\Vibes\Cache\CacheManager;
use ZipPicks\Vibes\Cache\CacheInterface;
use ZipPicks\Vibes\Cache\Adapters\TransientAdapter;
use ZipPicks\Vibes\Cache\Adapters\ObjectCacheAdapter;
use ZipPicks\Vibes\Cache\Adapters\RedisAdapter;
use Psr\Log\LoggerInterface;

/**
 * @group unit
 * @group cache
 */
class CacheManagerAdditionalTest extends TestCase {
    
    /**
     * @var CacheManager
     */
    private $cacheManager;
    
    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|LoggerInterface
     */
    private $mockLogger;
    
    public function setUp(): void {
        parent::setUp();
        
        $this->mockLogger = $this->createMock(LoggerInterface::class);
    }
    
    /**
     * Test cache manager with different adapters
     */
    public function test_cache_manager_adapter_selection() {
        // Test with transient adapter (default)
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        $adapter = $this->getPrivateProperty($cacheManager, 'adapter');
        $this->assertInstanceOf(TransientAdapter::class, $adapter);
        
        // Test with object cache adapter
        if (function_exists('wp_cache_get')) {
            $cacheManager = new CacheManager('object', $this->mockLogger);
            $adapter = $this->getPrivateProperty($cacheManager, 'adapter');
            $this->assertInstanceOf(ObjectCacheAdapter::class, $adapter);
        }
    }
    
    /**
     * Test cache key prefixing
     */
    public function test_cache_key_prefixing() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        // Set a value
        $cacheManager->set('test_key', 'test_value');
        
        // Check that the key is prefixed internally
        $prefixed_key = 'zippicks_vibes_test_key';
        $this->assertEquals('test_value', get_transient($prefixed_key));
    }
    
    /**
     * Test cache tags functionality
     */
    public function test_cache_tags() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        // Set multiple values with tags
        $cacheManager->setWithTags('key1', 'value1', ['tag1', 'tag2']);
        $cacheManager->setWithTags('key2', 'value2', ['tag1', 'tag3']);
        $cacheManager->setWithTags('key3', 'value3', ['tag2', 'tag3']);
        
        // Clear by tag
        $cacheManager->clearByTag('tag1');
        
        // Keys with tag1 should be cleared
        $this->assertNull($cacheManager->get('key1'));
        $this->assertNull($cacheManager->get('key2'));
        
        // Key without tag1 should remain
        $this->assertEquals('value3', $cacheManager->get('key3'));
    }
    
    /**
     * Test cache statistics
     */
    public function test_cache_statistics() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        // Perform some cache operations
        $cacheManager->set('key1', 'value1');
        $cacheManager->get('key1'); // Hit
        $cacheManager->get('key2'); // Miss
        $cacheManager->get('key1'); // Hit
        
        $stats = $cacheManager->getStatistics();
        
        $this->assertArrayHasKey('hits', $stats);
        $this->assertArrayHasKey('misses', $stats);
        $this->assertArrayHasKey('hit_rate', $stats);
        $this->assertEquals(2, $stats['hits']);
        $this->assertEquals(1, $stats['misses']);
        $this->assertEquals(0.67, round($stats['hit_rate'], 2));
    }
    
    /**
     * Test cache warming
     */
    public function test_cache_warming() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        $data_to_warm = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];
        
        $cacheManager->warm($data_to_warm);
        
        // All keys should be cached
        foreach ($data_to_warm as $key => $value) {
            $this->assertEquals($value, $cacheManager->get($key));
        }
    }
    
    /**
     * Test atomic operations
     */
    public function test_atomic_increment() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        // Initialize counter
        $cacheManager->set('counter', 0);
        
        // Increment
        $result1 = $cacheManager->increment('counter');
        $result2 = $cacheManager->increment('counter', 5);
        
        $this->assertEquals(1, $result1);
        $this->assertEquals(6, $result2);
        $this->assertEquals(6, $cacheManager->get('counter'));
    }
    
    /**
     * Test atomic decrement
     */
    public function test_atomic_decrement() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        // Initialize counter
        $cacheManager->set('counter', 10);
        
        // Decrement
        $result1 = $cacheManager->decrement('counter');
        $result2 = $cacheManager->decrement('counter', 3);
        
        $this->assertEquals(9, $result1);
        $this->assertEquals(6, $result2);
        $this->assertEquals(6, $cacheManager->get('counter'));
    }
    
    /**
     * Test cache locking
     */
    public function test_cache_locking() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        $key = 'locked_resource';
        
        // Acquire lock
        $lock_acquired = $cacheManager->lock($key, 5);
        $this->assertTrue($lock_acquired);
        
        // Try to acquire same lock (should fail)
        $lock_acquired_again = $cacheManager->lock($key, 5);
        $this->assertFalse($lock_acquired_again);
        
        // Release lock
        $cacheManager->unlock($key);
        
        // Now lock can be acquired again
        $lock_acquired_after_release = $cacheManager->lock($key, 5);
        $this->assertTrue($lock_acquired_after_release);
    }
    
    /**
     * Test cache serialization
     */
    public function test_cache_serialization() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        $complex_data = [
            'string' => 'test',
            'int' => 123,
            'float' => 123.45,
            'bool' => true,
            'array' => [1, 2, 3],
            'object' => (object)['prop' => 'value'],
            'null' => null
        ];
        
        $cacheManager->set('complex', $complex_data);
        $retrieved = $cacheManager->get('complex');
        
        $this->assertEquals($complex_data['string'], $retrieved['string']);
        $this->assertEquals($complex_data['int'], $retrieved['int']);
        $this->assertEquals($complex_data['float'], $retrieved['float']);
        $this->assertEquals($complex_data['bool'], $retrieved['bool']);
        $this->assertEquals($complex_data['array'], $retrieved['array']);
        $this->assertEquals($complex_data['object']->prop, $retrieved['object']->prop);
        $this->assertNull($retrieved['null']);
    }
    
    /**
     * Test cache memory limit protection
     */
    public function test_memory_limit_protection() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        // Try to cache very large data
        $large_data = str_repeat('a', 10 * 1024 * 1024); // 10MB string
        
        // Should log warning about large data
        $this->mockLogger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Large cache entry'));
        
        $cacheManager->set('large_key', $large_data);
    }
    
    /**
     * Test cache pattern deletion
     */
    public function test_delete_by_pattern() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        // Set multiple keys
        $cacheManager->set('user_123_profile', 'data1');
        $cacheManager->set('user_123_settings', 'data2');
        $cacheManager->set('user_456_profile', 'data3');
        $cacheManager->set('post_789_data', 'data4');
        
        // Delete by pattern
        $deleted = $cacheManager->deleteByPattern('user_123_*');
        
        $this->assertEquals(2, $deleted);
        $this->assertNull($cacheManager->get('user_123_profile'));
        $this->assertNull($cacheManager->get('user_123_settings'));
        $this->assertEquals('data3', $cacheManager->get('user_456_profile'));
        $this->assertEquals('data4', $cacheManager->get('post_789_data'));
    }
    
    /**
     * Test cache callback functionality
     */
    public function test_remember_with_callback() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        $callback_count = 0;
        $expensive_operation = function() use (&$callback_count) {
            $callback_count++;
            return 'expensive_result_' . $callback_count;
        };
        
        // First call - callback executed
        $result1 = $cacheManager->remember('callback_key', $expensive_operation, 3600);
        $this->assertEquals('expensive_result_1', $result1);
        $this->assertEquals(1, $callback_count);
        
        // Second call - cached value returned
        $result2 = $cacheManager->remember('callback_key', $expensive_operation, 3600);
        $this->assertEquals('expensive_result_1', $result2);
        $this->assertEquals(1, $callback_count); // Callback not executed again
    }
    
    /**
     * Test cache many operations
     */
    public function test_get_many() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        // Set multiple values
        $data = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3'
        ];
        
        foreach ($data as $key => $value) {
            $cacheManager->set($key, $value);
        }
        
        // Get many
        $keys = ['key1', 'key2', 'key3', 'key4']; // key4 doesn't exist
        $results = $cacheManager->getMany($keys);
        
        $this->assertEquals('value1', $results['key1']);
        $this->assertEquals('value2', $results['key2']);
        $this->assertEquals('value3', $results['key3']);
        $this->assertNull($results['key4']);
    }
    
    /**
     * Test cache many set operations
     */
    public function test_set_many() {
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        
        $data = [
            'bulk1' => 'value1',
            'bulk2' => 'value2',
            'bulk3' => 'value3'
        ];
        
        $result = $cacheManager->setMany($data, 3600);
        $this->assertTrue($result);
        
        // Verify all values are set
        foreach ($data as $key => $value) {
            $this->assertEquals($value, $cacheManager->get($key));
        }
    }
    
    /**
     * Test cache error handling
     */
    public function test_cache_error_handling() {
        // Create a mock adapter that throws exceptions
        $mockAdapter = $this->createMock(CacheInterface::class);
        $mockAdapter->method('get')
            ->willThrowException(new \Exception('Cache error'));
        
        $cacheManager = new CacheManager('transient', $this->mockLogger);
        $this->setPrivateProperty($cacheManager, 'adapter', $mockAdapter);
        
        // Should log error and return default
        $this->mockLogger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Cache error'));
        
        $result = $cacheManager->get('error_key', 'default_value');
        $this->assertEquals('default_value', $result);
    }
}