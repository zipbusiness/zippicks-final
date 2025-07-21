<?php
/**
 * Container Unit Tests
 * 
 * @package ZipPicks\Foundation\Tests\Unit\Core
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Tests\Unit\Core;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Contracts\Container\NotFoundException;
use ZipPicks\Foundation\Contracts\Container\ContainerException;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testBindAndResolve(): void
    {
        $this->container->bind('test', fn() => 'test value');
        
        $this->assertTrue($this->container->has('test'));
        $this->assertEquals('test value', $this->container->get('test'));
    }

    public function testSingleton(): void
    {
        $this->container->singleton('counter', fn() => new \stdClass());
        
        $instance1 = $this->container->get('counter');
        $instance2 = $this->container->get('counter');
        
        $this->assertSame($instance1, $instance2);
    }

    public function testInstance(): void
    {
        $object = new \stdClass();
        $object->test = 'value';
        
        $this->container->instance('test', $object);
        
        $this->assertTrue($this->container->has('test'));
        $this->assertSame($object, $this->container->get('test'));
    }

    public function testAlias(): void
    {
        $this->container->bind('original', fn() => 'test value');
        $this->container->alias('alias', 'original');
        
        $this->assertEquals('test value', $this->container->get('alias'));
    }

    public function testNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->get('non-existent');
    }

    public function testAutomaticResolution(): void
    {
        $instance = $this->container->get(TestClassWithoutDependencies::class);
        
        $this->assertInstanceOf(TestClassWithoutDependencies::class, $instance);
    }

    public function testDependencyInjection(): void
    {
        $this->container->bind(TestDependency::class);
        
        $instance = $this->container->get(TestClassWithDependencies::class);
        
        $this->assertInstanceOf(TestClassWithDependencies::class, $instance);
        $this->assertInstanceOf(TestDependency::class, $instance->dependency);
    }

    public function testCircularDependencyDetection(): void
    {
        $this->container->bind(CircularA::class);
        $this->container->bind(CircularB::class);
        
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected');
        
        $this->container->get(CircularA::class);
    }
}

// Test classes
class TestClassWithoutDependencies
{
}

class TestDependency
{
}

class TestClassWithDependencies
{
    public function __construct(public TestDependency $dependency)
    {
    }
}

class CircularA
{
    public function __construct(CircularB $b)
    {
    }
}

class CircularB
{
    public function __construct(CircularA $a)
    {
    }
}