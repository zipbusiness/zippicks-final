<?php
/**
 * Cache Service Unit Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use DateInterval;
use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Contracts\Cache\CacheInterface;
use ZipPicks\Foundation\Cache\WPObjectCacheAdapter;
use ZipPicks\Foundation\Cache\CacheInvalidArgumentException;
use ZipPicks\Foundation\Services\CacheServiceProvider;

class CacheTest extends TestCase
{
    private WPObjectCacheAdapter $cache;
    private array $wpCacheStorage = [];

    protected function setUp(): void
    {
        parent::setUp();
        
        // Reset mock storage
        $this->wpCacheStorage = [];
        
        // Create cache instance
        $this->cache = new WPObjectCacheAdapter('test_', 'test_group', 3600);
    }

    public function testGet(): void
    {
        // Test non-existent key returns default
        $this->assertNull($this->cache->get('non_existent'));
        $this->assertEquals('default', $this->cache->get('non_existent', 'default'));
        
        // Test existing key returns value
        $this->wpCacheStorage['test_group']['test_existing'] = 'stored_value';
        $this->assertEquals('stored_value', $this->cache->get('existing'));
    }

    public function testSet(): void
    {
        $this->assertTrue($this->cache->set('key1', 'value1'));
        $this->assertEquals('value1', $this->wpCacheStorage['test_group']['test_key1']);
        
        // Test with TTL
        $this->assertTrue($this->cache->set('key2', 'value2', 300));
        $this->assertEquals('value2', $this->wpCacheStorage['test_group']['test_key2']);
        
        // Test with DateInterval
        $interval = new DateInterval('PT1H'); // 1 hour
        $this->assertTrue($this->cache->set('key3', 'value3', $interval));
        $this->assertEquals('value3', $this->wpCacheStorage['test_group']['test_key3']);
    }

    public function testDelete(): void
    {
        $this->wpCacheStorage['test_group']['test_key'] = 'value';
        
        $this->assertTrue($this->cache->delete('key'));
        $this->assertArrayNotHasKey('test_key', $this->wpCacheStorage['test_group'] ?? []);
        
        // Test deleting non-existent key
        $this->assertTrue($this->cache->delete('non_existent'));
    }

    public function testClear(): void
    {
        $this->wpCacheStorage['test_group'] = [
            'test_key1' => 'value1',
            'test_key2' => 'value2',
        ];
        $this->wpCacheStorage['other_group'] = [
            'key' => 'value',
        ];
        
        $this->assertTrue($this->cache->clear());
        
        // In our mock, clear() flushes everything
        $this->assertEmpty($this->wpCacheStorage);
    }

    public function testHas(): void
    {
        $this->assertFalse($this->cache->has('non_existent'));
        
        $this->wpCacheStorage['test_group']['test_key'] = 'value';
        $this->assertTrue($this->cache->has('key'));
    }

    public function testGetMultiple(): void
    {
        $this->wpCacheStorage['test_group'] = [
            'test_key1' => 'value1',
            'test_key2' => 'value2',
        ];
        
        $result = $this->cache->getMultiple(['key1', 'key2', 'key3']);
        
        $expected = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => null,
        ];
        
        $this->assertEquals($expected, $result);
        
        // Test with default
        $result = $this->cache->getMultiple(['key1', 'key3'], 'default');
        $expected = [
            'key1' => 'value1',
            'key3' => 'default',
        ];
        
        $this->assertEquals($expected, $result);
    }

    public function testSetMultiple(): void
    {
        $values = [
            'key1' => 'value1',
            'key2' => 'value2',
            'key3' => 'value3',
        ];
        
        $this->assertTrue($this->cache->setMultiple($values));
        
        $this->assertEquals('value1', $this->wpCacheStorage['test_group']['test_key1']);
        $this->assertEquals('value2', $this->wpCacheStorage['test_group']['test_key2']);
        $this->assertEquals('value3', $this->wpCacheStorage['test_group']['test_key3']);
    }

    public function testDeleteMultiple(): void
    {
        $this->wpCacheStorage['test_group'] = [
            'test_key1' => 'value1',
            'test_key2' => 'value2',
            'test_key3' => 'value3',
        ];
        
        $this->assertTrue($this->cache->deleteMultiple(['key1', 'key3']));
        
        $this->assertArrayNotHasKey('test_key1', $this->wpCacheStorage['test_group']);
        $this->assertArrayHasKey('test_key2', $this->wpCacheStorage['test_group']);
        $this->assertArrayNotHasKey('test_key3', $this->wpCacheStorage['test_group']);
    }

    public function testForever(): void
    {
        $this->assertTrue($this->cache->forever('eternal', 'forever_value'));
        $this->assertEquals('forever_value', $this->wpCacheStorage['test_group']['test_eternal']);
    }

    public function testPull(): void
    {
        $this->wpCacheStorage['test_group']['test_key'] = 'pulled_value';
        
        $value = $this->cache->pull('key');
        $this->assertEquals('pulled_value', $value);
        $this->assertArrayNotHasKey('test_key', $this->wpCacheStorage['test_group'] ?? []);
        
        // Test pull non-existent with default
        $value = $this->cache->pull('non_existent', 'default');
        $this->assertEquals('default', $value);
    }

    public function testAdd(): void
    {
        // Test adding new key
        $this->assertTrue($this->cache->add('new_key', 'new_value'));
        $this->assertEquals('new_value', $this->wpCacheStorage['test_group']['test_new_key']);
        
        // Test adding existing key fails
        $this->assertFalse($this->cache->add('new_key', 'another_value'));
        $this->assertEquals('new_value', $this->wpCacheStorage['test_group']['test_new_key']);
    }

    public function testGetPrefix(): void
    {
        $this->assertEquals('test_', $this->cache->getPrefix());
    }

    public function testSetWithTags(): void
    {
        // Stub implementation should just set the value
        $this->assertTrue($this->cache->setWithTags('tagged_key', 'tagged_value', null, ['tag1', 'tag2']));
        $this->assertEquals('tagged_value', $this->wpCacheStorage['test_group']['test_tagged_key']);
    }

    public function testInvalidateTags(): void
    {
        // Stub implementation should always return true
        $this->assertTrue($this->cache->invalidateTags(['tag1', 'tag2']));
    }

    public function testInvalidKeys(): void
    {
        $this->expectException(CacheInvalidArgumentException::class);
        $this->cache->get('');
    }

    public function testInvalidKeysWithSpecialCharacters(): void
    {
        $invalidKeys = ['{key}', '(key)', 'ke/y', 'ke@y', 'ke:y', 'ke\\y'];
        
        foreach ($invalidKeys as $key) {
            try {
                $this->cache->get($key);
                $this->fail("Expected exception for key: $key");
            } catch (CacheInvalidArgumentException $e) {
                $this->assertStringContainsString('invalid characters', $e->getMessage());
            }
        }
    }

    public function testDataTypePreservation(): void
    {
        $testData = [
            'string' => 'test string',
            'int' => 42,
            'float' => 3.14,
            'bool_true' => true,
            'bool_false' => false,
            'array' => ['a', 'b', 'c'],
            'assoc_array' => ['key' => 'value'],
            'null' => null,
        ];
        
        foreach ($testData as $key => $value) {
            $this->cache->set($key, $value);
            $retrieved = $this->cache->get($key);
            
            $this->assertSame($value, $retrieved, "Failed to preserve type for $key");
        }
    }

    public function testServiceProviderRegistration(): void
    {
        // Define constants if not already defined
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }
        
        $container = new Container();
        
        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);
        
        // Create and register the service provider
        $provider = new CacheServiceProvider($foundation);
        $provider->register();
        
        // Test that cache is registered
        $this->assertTrue($container->has(CacheInterface::class));
        $this->assertTrue($container->has('cache'));
        
        // Test that we can resolve the cache
        $cache = $container->get('cache');
        $this->assertInstanceOf(CacheInterface::class, $cache);
        $this->assertInstanceOf(WPObjectCacheAdapter::class, $cache);
    }

    public function testServiceProviderDoesNotOverwriteExistingAlias(): void
    {
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }
        
        $container = new Container();
        
        // Pre-register a custom cache alias
        $customCache = new WPObjectCacheAdapter('custom_', 'custom', 7200);
        $container->instance('cache', $customCache);
        
        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);
        
        // Create and register the service provider
        $provider = new CacheServiceProvider($foundation);
        $provider->register();
        
        // Test that the original cache alias was not overwritten
        $resolvedCache = $container->get('cache');
        $this->assertSame($customCache, $resolvedCache);
        $this->assertEquals('custom_', $resolvedCache->getPrefix());
    }

    public function testNegativeTtlDeletesKey(): void
    {
        // First set a value
        $this->cache->set('temp_key', 'temp_value');
        $this->assertTrue($this->cache->has('temp_key'));
        
        // Set with negative TTL should delete
        $this->assertTrue($this->cache->set('temp_key', 'new_value', -1));
        $this->assertFalse($this->cache->has('temp_key'));
    }
}

// Mock WordPress cache functions
if (!function_exists('wp_cache_get')) {
    function wp_cache_get($key, $group = 'default', $force = false, &$found = null) {
        global $wpCacheStorage;
        $testCase = $GLOBALS['currentTestCase'] ?? null;
        
        if ($testCase && property_exists($testCase, 'wpCacheStorage')) {
            $storage = &$testCase->wpCacheStorage;
        } else {
            $storage = &$wpCacheStorage;
        }
        
        if (isset($storage[$group][$key])) {
            $found = true;
            return $storage[$group][$key];
        }
        
        $found = false;
        return false;
    }
}

if (!function_exists('wp_cache_set')) {
    function wp_cache_set($key, $data, $group = 'default', $expire = 0) {
        global $wpCacheStorage;
        $testCase = $GLOBALS['currentTestCase'] ?? null;
        
        if ($testCase && property_exists($testCase, 'wpCacheStorage')) {
            $storage = &$testCase->wpCacheStorage;
        } else {
            $storage = &$wpCacheStorage;
        }
        
        if (!isset($storage[$group])) {
            $storage[$group] = [];
        }
        
        $storage[$group][$key] = $data;
        return true;
    }
}

if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete($key, $group = 'default') {
        global $wpCacheStorage;
        $testCase = $GLOBALS['currentTestCase'] ?? null;
        
        if ($testCase && property_exists($testCase, 'wpCacheStorage')) {
            $storage = &$testCase->wpCacheStorage;
        } else {
            $storage = &$wpCacheStorage;
        }
        
        if (isset($storage[$group][$key])) {
            unset($storage[$group][$key]);
        }
        
        return true;
    }
}

if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush() {
        global $wpCacheStorage;
        $testCase = $GLOBALS['currentTestCase'] ?? null;
        
        if ($testCase && property_exists($testCase, 'wpCacheStorage')) {
            $testCase->wpCacheStorage = [];
        } else {
            $wpCacheStorage = [];
        }
        
        return true;
    }
}

if (!function_exists('wp_cache_flush_group')) {
    function wp_cache_flush_group($group) {
        global $wpCacheStorage;
        $testCase = $GLOBALS['currentTestCase'] ?? null;
        
        if ($testCase && property_exists($testCase, 'wpCacheStorage')) {
            $storage = &$testCase->wpCacheStorage;
        } else {
            $storage = &$wpCacheStorage;
        }
        
        if (isset($storage[$group])) {
            unset($storage[$group]);
        }
        
        return true;
    }
}

// Set current test case for mock functions
$GLOBALS['currentTestCase'] = null;

// Override setUp to set current test case
namespace ZipPicks\Foundation\Tests\Unit\Services {
    class CacheTest extends \PHPUnit\Framework\TestCase
    {
        private WPObjectCacheAdapter $cache;
        public array $wpCacheStorage = [];
        
        protected function setUp(): void
        {
            parent::setUp();
            $GLOBALS['currentTestCase'] = $this;
            $this->wpCacheStorage = [];
            $this->cache = new WPObjectCacheAdapter('test_', 'test_group', 3600);
        }
        
        protected function tearDown(): void
        {
            $GLOBALS['currentTestCase'] = null;
            parent::tearDown();
        }
    }
}