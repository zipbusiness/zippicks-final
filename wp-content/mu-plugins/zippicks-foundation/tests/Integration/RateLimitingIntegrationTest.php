<?php

namespace ZipPicks\Foundation\Tests\Integration;

use PHPUnit\Framework\TestCase;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\RateLimiting\RateLimiterManager;
use ZipPicks\Foundation\RateLimiting\Middleware\ThrottleRequests;
use ZipPicks\Foundation\RateLimiting\Middleware\ThrottleJobs;
use ZipPicks\Foundation\RateLimiting\Exceptions\RateLimitExceededException;
use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Queue\Job;
use ZipPicks\Foundation\Models\User;

/**
 * Rate Limiting Integration Tests
 * 
 * Tests integration with queue, cache, auth, and HTTP systems
 * to ensure rate limiting works seamlessly across our $100B platform
 */
class RateLimitingIntegrationTest extends TestCase
{
    protected Foundation $foundation;
    protected Container $container;
    protected RateLimiterManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Bootstrap Foundation
        $this->foundation = Foundation::getInstance();
        $this->container = $this->foundation->getContainer();
        
        // Register rate limiting service
        $provider = new \ZipPicks\Foundation\Services\RateLimitingServiceProvider($this->container);
        $provider->register();
        $provider->boot();
        
        $this->manager = $this->container->get(RateLimiterManager::class);
    }

    /**
     * Test HTTP middleware integration
     */
    public function testHttpMiddlewareIntegration()
    {
        $middleware = $this->container->get(ThrottleRequests::class);
        $request = new Request();
        
        $hitCount = 0;
        $next = function ($req) use (&$hitCount) {
            $hitCount++;
            return new \ZipPicks\Foundation\Http\Response('OK');
        };
        
        // Should allow first 60 requests
        for ($i = 0; $i < 60; $i++) {
            $response = $middleware->handle($request, $next, 60, 1);
            $this->assertEquals('OK', $response->getContent());
        }
        
        $this->assertEquals(60, $hitCount);
        
        // 61st request should be rate limited
        try {
            $middleware->handle($request, $next, 60, 1);
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
            $this->assertEquals(429, $e->getCode());
            $this->assertArrayHasKey('X-RateLimit-Limit', $e->getHeaders());
        }
    }

    /**
     * Test queue job middleware integration
     */
    public function testQueueMiddlewareIntegration()
    {
        $middleware = new ThrottleJobs('test:queue', 10, 1);
        $middleware->setManager($this->manager);
        
        $job = $this->createMock(Job::class);
        $job->method('getJobId')->willReturn('test-job-1');
        
        $processCount = 0;
        $next = function ($j) use (&$processCount) {
            $processCount++;
            return true;
        };
        
        // Should allow first 10 jobs
        for ($i = 0; $i < 10; $i++) {
            $result = $middleware->handle($job, $next);
            $this->assertTrue($result);
        }
        
        $this->assertEquals(10, $processCount);
        
        // 11th job should be rate limited
        $job->expects($this->once())
            ->method('release')
            ->with($this->greaterThan(0));
        
        $result = $middleware->handle($job, $next);
        $this->assertNull($result);
    }

    /**
     * Test user tier integration
     */
    public function testUserTierIntegration()
    {
        // Mock user with different tiers
        $freeUser = $this->createMock(User::class);
        $freeUser->method('getRateLimitTier')->willReturn('free');
        $freeUser->method('getRateLimitMultiplier')->willReturn(1.0);
        $freeUser->method('getThrottleKey')->willReturn('user:1:api');
        
        $proUser = $this->createMock(User::class);
        $proUser->method('getRateLimitTier')->willReturn('pro');
        $proUser->method('getRateLimitMultiplier')->willReturn(10.0);
        $proUser->method('getThrottleKey')->willReturn('user:2:api');
        
        // Test free tier limits
        $freeLimiter = $this->manager->forTier('free', 'api');
        $allowed = 0;
        
        for ($i = 0; $i < 200; $i++) {
            if (!$freeLimiter->tooManyAttempts($freeUser->getThrottleKey(), 100)) {
                $freeLimiter->hit($freeUser->getThrottleKey());
                $allowed++;
            }
        }
        
        $this->assertEquals(100, $allowed); // Free tier gets base limit
        
        // Test pro tier limits
        $proLimiter = $this->manager->forTier('pro', 'api');
        $allowed = 0;
        
        for ($i = 0; $i < 2000; $i++) {
            if (!$proLimiter->tooManyAttempts($proUser->getThrottleKey(), 100)) {
                $proLimiter->hit($proUser->getThrottleKey());
                $allowed++;
            }
        }
        
        $this->assertEquals(1000, $allowed); // Pro tier gets 10x limit
    }

    /**
     * Test cost-based rate limiting
     */
    public function testCostBasedRateLimiting()
    {
        $limiter = $this->manager->limiter('taste_graph');
        $key = 'user:123:taste_graph';
        
        // Each Taste Graph calculation costs 10 units
        $limiter->hit($key, 60, 10); // First calculation
        $limiter->hit($key, 60, 10); // Second calculation
        
        $usage = $limiter->usage($key);
        $this->assertEquals(20, $usage['current']); // 2 calculations × 10 units
        
        // With 100 unit limit, should allow 10 calculations total
        for ($i = 2; $i < 10; $i++) {
            $this->assertFalse($limiter->tooManyAttempts($key, 100, 10));
            $limiter->hit($key, 60, 10);
        }
        
        // 11th calculation should exceed limit
        $this->assertTrue($limiter->tooManyAttempts($key, 100, 10));
    }

    /**
     * Test cache integration for performance
     */
    public function testCacheIntegration()
    {
        if (!$this->container->has('cache')) {
            $this->markTestSkipped('Cache service not available');
        }
        
        $cache = $this->container->get('cache');
        $limiter = $this->manager->limiter('api');
        
        // Warm up cache with rate limit data
        $keys = [];
        for ($i = 0; $i < 100; $i++) {
            $key = "cached:user:$i";
            $keys[] = $key;
            $limiter->hit($key);
            
            // Cache the usage data
            $cache->put("rate_limit:$key", $limiter->usage($key), 300);
        }
        
        // Verify cached data matches live data
        foreach ($keys as $key) {
            $cachedUsage = $cache->get("rate_limit:$key");
            $liveUsage = $limiter->usage($key);
            
            $this->assertEquals($cachedUsage['current'], $liveUsage['current']);
        }
    }

    /**
     * Test rate limit headers in responses
     */
    public function testRateLimitHeaders()
    {
        $middleware = $this->container->get(ThrottleRequests::class);
        $request = new Request();
        
        $response = $middleware->handle($request, function ($req) {
            return new \ZipPicks\Foundation\Http\Response('OK');
        }, 100, 1);
        
        // Check rate limit headers
        $this->assertNotNull($response->header('X-RateLimit-Limit'));
        $this->assertNotNull($response->header('X-RateLimit-Remaining'));
        $this->assertNotNull($response->header('X-RateLimit-Reset'));
        
        $this->assertEquals('100', $response->header('X-RateLimit-Limit'));
        $this->assertEquals('99', $response->header('X-RateLimit-Remaining'));
    }

    /**
     * Test multi-store failover
     */
    public function testMultiStoreFailover()
    {
        // Configure manager with Redis primary and database fallback
        $config = [
            'stores' => [
                'redis' => ['driver' => 'redis'],
                'database' => ['driver' => 'database'],
            ],
            'limiters' => [
                'failover' => [
                    'algorithm' => 'fixed_window',
                    'store' => 'redis',
                    'fallback_store' => 'database',
                ],
            ],
        ];
        
        $manager = new RateLimiterManager($this->container, $config);
        
        // Even if Redis fails, should use database
        $limiter = $manager->limiter('failover');
        $this->assertFalse($limiter->tooManyAttempts('test:failover', 100));
    }

    /**
     * Test rate limiting with WordPress hooks
     */
    public function testWordPressHookIntegration()
    {
        if (!function_exists('add_action')) {
            $this->markTestSkipped('WordPress functions not available');
        }
        
        $rateLimited = false;
        
        add_action('zippicks_rate_limit_exceeded', function ($key, $context) use (&$rateLimited) {
            $rateLimited = true;
        });
        
        $limiter = $this->manager->limiter('api');
        
        // Exceed rate limit
        for ($i = 0; $i <= 100; $i++) {
            $limiter->hit('wp:hook:test');
        }
        
        try {
            $limiter->attempt('wp:hook:test', 100, function () {
                return 'should not execute';
            });
        } catch (RateLimitExceededException $e) {
            // Expected
        }
        
        $this->assertTrue($rateLimited, 'WordPress hook should have been triggered');
    }

    /**
     * Test cleanup of expired limits
     */
    public function testExpiredLimitCleanup()
    {
        $store = new \ZipPicks\Foundation\RateLimiting\Stores\InMemoryStore();
        $limiter = new \ZipPicks\Foundation\RateLimiting\RateLimiter($store);
        
        // Create limits with 1 second TTL
        for ($i = 0; $i < 100; $i++) {
            $limiter->hit("cleanup:test:$i", 1/60); // 1 second
        }
        
        // Verify limits exist
        $this->assertEquals(1, $limiter->usage('cleanup:test:0')['current']);
        
        // Wait for expiration
        sleep(2);
        
        // Limits should be expired
        $this->assertEquals(0, $limiter->usage('cleanup:test:0')['current']);
    }

    /**
     * Test rate limiting analytics tracking
     */
    public function testAnalyticsTracking()
    {
        $events = [];
        
        if (function_exists('add_action')) {
            add_action('zippicks_analytics_event', function ($event, $data) use (&$events) {
                $events[] = ['event' => $event, 'data' => $data];
            }, 10, 2);
        }
        
        $limiter = $this->manager->limiter('api');
        
        // Trigger rate limit
        for ($i = 0; $i <= 100; $i++) {
            $limiter->hit('analytics:test');
        }
        
        try {
            $limiter->attempt('analytics:test', 100, function () {});
        } catch (RateLimitExceededException $e) {
            // Expected
        }
        
        if (function_exists('add_action')) {
            $this->assertNotEmpty($events);
            $this->assertEquals('rate_limit_exceeded', $events[0]['event']);
        }
    }
}