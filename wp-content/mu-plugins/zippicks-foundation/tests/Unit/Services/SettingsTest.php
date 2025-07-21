<?php
/**
 * Settings Manager Unit Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Services
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Settings\SettingsManager;
use ZipPicks\Foundation\Services\SettingsServiceProvider;

class SettingsTest extends TestCase
{
    private SettingsManager $settings;
    private array $defaults;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaults = [
            'enable_logging' => true,
            'api_timeout' => 30,
            'features' => [
                'taste_graph' => true,
                'ai_recommendations' => false,
            ],
            'cache' => [
                'enabled' => true,
                'ttl' => 3600,
            ],
        ];

        $this->settings = new SettingsManager($this->defaults);
    }

    public function testGetReturnsDefaultValues(): void
    {
        $this->assertTrue($this->settings->get('enable_logging'));
        $this->assertEquals(30, $this->settings->get('api_timeout'));
        $this->assertTrue($this->settings->get('features.taste_graph'));
        $this->assertFalse($this->settings->get('features.ai_recommendations'));
    }

    public function testGetReturnsDefaultWhenKeyNotFound(): void
    {
        $this->assertNull($this->settings->get('non_existent_key'));
        $this->assertEquals('default', $this->settings->get('non_existent_key', 'default'));
    }

    public function testSetOverridesValue(): void
    {
        $this->settings->set('enable_logging', false);
        $this->assertFalse($this->settings->get('enable_logging'));

        $this->settings->set('api_timeout', 60);
        $this->assertEquals(60, $this->settings->get('api_timeout'));
    }

    public function testSetCreatesNewKeys(): void
    {
        $this->settings->set('new_setting', 'new_value');
        $this->assertEquals('new_value', $this->settings->get('new_setting'));

        $this->settings->set('nested.new.setting', 'nested_value');
        $this->assertEquals('nested_value', $this->settings->get('nested.new.setting'));
    }

    public function testGetPreservesDataTypes(): void
    {
        $this->settings->set('string_value', 'hello');
        $this->assertIsString($this->settings->get('string_value'));
        $this->assertEquals('hello', $this->settings->get('string_value'));

        $this->settings->set('int_value', 42);
        $this->assertIsInt($this->settings->get('int_value'));
        $this->assertEquals(42, $this->settings->get('int_value'));

        $this->settings->set('float_value', 3.14);
        $this->assertIsFloat($this->settings->get('float_value'));
        $this->assertEquals(3.14, $this->settings->get('float_value'));

        $this->settings->set('bool_value', true);
        $this->assertIsBool($this->settings->get('bool_value'));
        $this->assertTrue($this->settings->get('bool_value'));

        $this->settings->set('array_value', ['a', 'b', 'c']);
        $this->assertIsArray($this->settings->get('array_value'));
        $this->assertEquals(['a', 'b', 'c'], $this->settings->get('array_value'));

        $this->settings->set('null_value', null);
        $this->assertNull($this->settings->get('null_value'));
    }

    public function testHasReturnsCorrectBoolean(): void
    {
        $this->assertTrue($this->settings->has('enable_logging'));
        $this->assertTrue($this->settings->has('features.taste_graph'));
        $this->assertFalse($this->settings->has('non_existent_key'));
        $this->assertFalse($this->settings->has('features.non_existent'));

        // Test with overrides
        $this->settings->set('new_key', 'value');
        $this->assertTrue($this->settings->has('new_key'));
    }

    public function testNestedGetAndSet(): void
    {
        $this->assertEquals(3600, $this->settings->get('cache.ttl'));
        
        $this->settings->set('cache.ttl', 7200);
        $this->assertEquals(7200, $this->settings->get('cache.ttl'));

        $this->settings->set('deeply.nested.value', 'deep');
        $this->assertEquals('deep', $this->settings->get('deeply.nested.value'));
    }

    public function testAllReturnsAllSettings(): void
    {
        $all = $this->settings->all();
        
        $this->assertArrayHasKey('enable_logging', $all);
        $this->assertArrayHasKey('api_timeout', $all);
        $this->assertArrayHasKey('features', $all);
        $this->assertArrayHasKey('cache', $all);

        // Test with overrides
        $this->settings->set('enable_logging', false);
        $this->settings->set('new_setting', 'new');
        
        $allWithOverrides = $this->settings->all();
        $this->assertFalse($allWithOverrides['enable_logging']);
        $this->assertEquals('new', $allWithOverrides['new_setting']);
    }

    public function testReset(): void
    {
        $this->settings->set('enable_logging', false);
        $this->settings->set('api_timeout', 60);
        $this->settings->set('new_setting', 'value');

        $this->settings->reset();

        $this->assertTrue($this->settings->get('enable_logging'));
        $this->assertEquals(30, $this->settings->get('api_timeout'));
        $this->assertNull($this->settings->get('new_setting'));
    }

    public function testResetKey(): void
    {
        $this->settings->set('enable_logging', false);
        $this->settings->set('api_timeout', 60);

        $this->settings->resetKey('enable_logging');

        $this->assertTrue($this->settings->get('enable_logging'));
        $this->assertEquals(60, $this->settings->get('api_timeout'));
    }

    public function testDefaults(): void
    {
        $defaults = $this->settings->defaults();
        
        $this->assertEquals($this->defaults, $defaults);
        
        // Defaults should not change after overrides
        $this->settings->set('enable_logging', false);
        $defaults = $this->settings->defaults();
        $this->assertTrue($defaults['enable_logging']);
    }

    public function testOverrides(): void
    {
        $this->assertEmpty($this->settings->overrides());

        $this->settings->set('enable_logging', false);
        $this->settings->set('new_setting', 'value');

        $overrides = $this->settings->overrides();
        $this->assertArrayHasKey('enable_logging', $overrides);
        $this->assertArrayHasKey('new_setting', $overrides);
        $this->assertFalse($overrides['enable_logging']);
        $this->assertEquals('value', $overrides['new_setting']);
    }

    public function testMerge(): void
    {
        $toMerge = [
            'enable_logging' => false,
            'api_timeout' => 45,
            'new_feature' => 'enabled',
            'features' => [
                'ai_recommendations' => true,
            ],
        ];

        $this->settings->merge($toMerge);

        $this->assertFalse($this->settings->get('enable_logging'));
        $this->assertEquals(45, $this->settings->get('api_timeout'));
        $this->assertEquals('enabled', $this->settings->get('new_feature'));
        $this->assertTrue($this->settings->get('features.ai_recommendations'));
        $this->assertTrue($this->settings->get('features.taste_graph')); // Original value preserved
    }

    public function testMultipleInstancesBehavior(): void
    {
        $settings1 = new SettingsManager($this->defaults);
        $settings2 = new SettingsManager($this->defaults);

        // Each instance should be independent
        $settings1->set('enable_logging', false);
        $this->assertFalse($settings1->get('enable_logging'));
        $this->assertTrue($settings2->get('enable_logging'));

        // Each instance maintains its own overrides
        $settings2->set('api_timeout', 60);
        $this->assertEquals(30, $settings1->get('api_timeout'));
        $this->assertEquals(60, $settings2->get('api_timeout'));
    }

    public function testServiceProviderRegistration(): void
    {
        // Define constant if not already defined
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }

        $container = new Container();
        
        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);

        // Create and register the service provider
        $provider = new SettingsServiceProvider($foundation);
        $provider->register();

        // Test that settings manager is registered
        $this->assertTrue($container->has(SettingsManager::class));
        $this->assertTrue($container->has('settings'));

        // Test that we can resolve the settings manager
        $settings = $container->get('settings');
        $this->assertInstanceOf(SettingsManager::class, $settings);
    }

    public function testServiceProviderDoesNotOverwriteExistingAlias(): void
    {
        if (!defined('ZIPPICKS_FOUNDATION_PATH')) {
            define('ZIPPICKS_FOUNDATION_PATH', dirname(__DIR__, 2));
        }

        $container = new Container();
        
        // Pre-register a custom settings alias
        $customSettings = new SettingsManager(['custom' => true]);
        $container->instance('settings', $customSettings);

        // Mock the foundation instance
        $foundation = $this->createMock(Foundation::class);
        $foundation->method('getContainer')->willReturn($container);

        // Create and register the service provider
        $provider = new SettingsServiceProvider($foundation);
        $provider->register();

        // Test that the original settings alias was not overwritten
        $resolvedSettings = $container->get('settings');
        $this->assertSame($customSettings, $resolvedSettings);
        $this->assertTrue($resolvedSettings->get('custom'));
    }

    public function testComplexNestedOperations(): void
    {
        $this->settings->set('a.b.c.d', 'deep_value');
        $this->assertEquals('deep_value', $this->settings->get('a.b.c.d'));
        $this->assertTrue($this->settings->has('a.b.c.d'));

        $this->settings->set('a.b.e', 'sibling');
        $this->assertEquals('sibling', $this->settings->get('a.b.e'));
        $this->assertEquals('deep_value', $this->settings->get('a.b.c.d'));

        $this->settings->resetKey('a.b.c.d');
        $this->assertNull($this->settings->get('a.b.c.d'));
        $this->assertEquals('sibling', $this->settings->get('a.b.e'));
    }

    public function testUnsetNestedKey(): void
    {
        $this->settings->set('parent.child.grandchild', 'value');
        $this->assertTrue($this->settings->has('parent.child.grandchild'));

        $this->settings->resetKey('parent.child.grandchild');
        $this->assertFalse($this->settings->has('parent.child.grandchild'));
        
        // Parent keys should still exist
        $this->assertTrue($this->settings->has('parent'));
        $this->assertTrue($this->settings->has('parent.child'));
    }
}