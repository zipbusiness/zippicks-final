<?php

namespace ZipPicks\Foundation\Tests\Unit\RateLimiting;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\RateLimiting\RateLimiter;
use ZipPicks\Foundation\RateLimiting\Stores\InMemoryStore;
use ZipPicks\Foundation\RateLimiting\Exceptions\RateLimitExceededException;
use ZipPicks\Foundation\Core\CircuitBreaker;

/**
 * Unit tests for RateLimiter
 */
class RateLimiterTest extends TestCase
{
    protected InMemoryStore $store;
    protected RateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new InMemoryStore();
        $this->limiter = new RateLimiter($this->store);
    }

    public function testBasicRateLimiting()
    {
        $key = 'test:basic';
        $maxAttempts = 5;
        
        // Should allow first 5 attempts
        for ($i = 0; $i < $maxAttempts; $i++) {
            $this->assertFalse($this->limiter->tooManyAttempts($key, $maxAttempts));
            $this->limiter->hit($key);
        }
        
        // 6th attempt should be blocked
        $this->assertTrue($this->limiter->tooManyAttempts($key, $maxAttempts));
    }

    public function testAttemptMethod()
    {
        $key = 'test:attempt';
        $executed = false;
        
        $result = $this->limiter->attempt($key, 1, function () use (&$executed) {
            $executed = true;
            return 'success';
        });
        
        $this->assertTrue($executed);
        $this->assertEquals('success', $result);
        
        // Second attempt should throw exception
        $this->expectException(RateLimitExceededException::class);
        $this->limiter->attempt($key, 1, function () {
            return 'should not execute';
        });
    }

    public function testCostBasedLimiting()
    {
        $key = 'test:cost';
        $maxUnits = 10;
        
        // Use 5 units
        $this->assertFalse($this->limiter->tooManyAttempts($key, $maxUnits, 5));
        $this->limiter->hit($key, 1, 5);
        
        // Should still have 5 units left
        $this->assertFalse($this->limiter->tooManyAttempts($key, $maxUnits, 5));
        
        // But not enough for 6 units
        $this->assertTrue($this->limiter->tooManyAttempts($key, $maxUnits, 6));
    }

    public function testUsageStatistics()
    {
        $key = 'test:usage';
        $this->limiter->hit($key, 60);
        $this->limiter->hit($key, 60);
        
        $usage = $this->limiter->usage($key);
        
        $this->assertEquals(2, $usage['current']);
        $this->assertArrayHasKey('limit', $usage);
        $this->assertArrayHasKey('remaining', $usage);
        $this->assertArrayHasKey('reset_at', $usage);
        $this->assertArrayHasKey('tier', $usage);
    }

    public function testClearLimit()
    {
        $key = 'test:clear';
        $this->limiter->hit($key);
        
        $this->assertEquals(1, $this->limiter->usage($key)['current']);
        
        $this->limiter->clear($key);
        
        $this->assertEquals(0, $this->limiter->usage($key)['current']);
    }

    public function testAvailableIn()
    {
        $key = 'test:available';
        
        // Before any hits, should be available
        $this->assertEquals(-1, $this->limiter->availableIn($key));
        
        // After hitting limit
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->hit($key, 1); // 1 minute TTL
        }
        
        $availableIn = $this->limiter->availableIn($key);
        $this->assertGreaterThan(0, $availableIn);
        $this->assertLessThanOrEqual(60, $availableIn);
    }

    public function testBatchChecks()
    {
        $keys = ['test:batch:1', 'test:batch:2', 'test:batch:3'];
        
        // Hit limits on first two keys
        $this->limiter->hit($keys[0], 1, 5);
        $this->limiter->hit($keys[1], 1, 5);
        
        $results = $this->limiter->tooManyAttemptsBatch($keys, 5);
        
        $this->assertTrue($results[$keys[0]]);  // Exceeded
        $this->assertTrue($results[$keys[1]]);  // Exceeded
        $this->assertFalse($results[$keys[2]]); // Not exceeded
    }

    public function testCustomLimits()
    {
        $key = 'test:custom';
        
        // Set custom limit
        $this->limiter->setLimit($key, 20, 60);
        
        $usage = $this->limiter->usage($key);
        $this->assertEquals(20, $usage['limit']);
    }

    public function testCircuitBreakerIntegration()
    {
        $failingStore = $this->createMock(InMemoryStore::class);
        $failingStore->method('get')->willThrowException(new \Exception('Store failure'));
        $failingStore->method('increment')->willThrowException(new \Exception('Store failure'));
        
        $circuitBreaker = new CircuitBreaker('test', 3, 60);
        $limiter = new RateLimiter($failingStore, 'fixed_window', $circuitBreaker);
        
        // Should fail open (allow requests) when store fails
        $this->assertFalse($limiter->tooManyAttempts('test:circuit', 100));
        
        // Hit should return default value on failure
        $count = $limiter->hit('test:circuit');
        $this->assertEquals(1, $count);
    }

    public function testOperationCosts()
    {
        $this->limiter->setOperationCosts([
            'expensive_op' => 50,
            'cheap_op' => 1,
        ]);
        
        $this->assertEquals(50, $this->limiter->getOperationCost('expensive_op'));
        $this->assertEquals(1, $this->limiter->getOperationCost('cheap_op'));
        $this->assertEquals(1, $this->limiter->getOperationCost('unknown_op')); // Default
    }

    public function testRateLimitExceptionContext()
    {
        $key = 'test:exception';
        
        // Hit limit
        $this->limiter->hit($key, 60);
        
        try {
            $this->limiter->attempt($key, 1, function () {});
            $this->fail('Expected exception');
        } catch (RateLimitExceededException $e) {
            $this->assertEquals($key, $e->getKey());
            $this->assertGreaterThan(0, $e->getRetryAfter());
            $this->assertNotEmpty($e->getUsage());
            $this->assertNotEmpty($e->getHeaders());
            $this->assertArrayHasKey('X-RateLimit-Limit', $e->getHeaders());
            $this->assertArrayHasKey('X-RateLimit-Remaining', $e->getHeaders());
        }
    }
}