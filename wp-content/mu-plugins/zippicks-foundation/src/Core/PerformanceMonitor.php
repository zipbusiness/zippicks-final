<?php
/**
 * Performance Monitor
 * 
 * @package ZipPicks\Foundation\Core
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Core;

/**
 * Tracks performance metrics for the Foundation
 */
class PerformanceMonitor
{
    private static ?self $instance = null;
    private array $timers = [];
    private array $metrics = [];
    private array $thresholds = [];
    private float $startTime;
    private float $startMemory;

    private function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        
        // Default thresholds
        $this->thresholds = [
            'page_load' => 1.0, // 1 second
            'database_query' => 0.1, // 100ms
            'api_call' => 0.5, // 500ms
            'cache_operation' => 0.01, // 10ms
        ];
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function startTimer(string $name, array $context = []): void
    {
        $this->timers[$name] = [
            'start' => microtime(true),
            'memory_start' => memory_get_usage(true),
            'context' => $context,
        ];
    }

    public function endTimer(string $name): ?array
    {
        if (!isset($this->timers[$name])) {
            return null;
        }

        $timer = $this->timers[$name];
        $duration = microtime(true) - $timer['start'];
        $memoryUsed = memory_get_usage(true) - $timer['memory_start'];

        $metric = [
            'name' => $name,
            'duration' => $duration,
            'memory_used' => $memoryUsed,
            'context' => $timer['context'],
            'timestamp' => time(),
        ];

        $this->recordMetric($name, $metric);
        unset($this->timers[$name]);

        return $metric;
    }

    public function measure(string $name, callable $callback, array $context = []): mixed
    {
        $this->startTimer($name, $context);
        
        try {
            $result = $callback();
        } finally {
            $this->endTimer($name);
        }
        
        return $result;
    }

    public function recordMetric(string $name, array $metric): void
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = [
                'count' => 0,
                'total_duration' => 0,
                'min_duration' => PHP_FLOAT_MAX,
                'max_duration' => 0,
                'avg_duration' => 0,
                'total_memory' => 0,
                'violations' => 0,
                'samples' => [],
            ];
        }

        $stats = &$this->metrics[$name];
        $duration = $metric['duration'];

        $stats['count']++;
        $stats['total_duration'] += $duration;
        $stats['min_duration'] = min($stats['min_duration'], $duration);
        $stats['max_duration'] = max($stats['max_duration'], $duration);
        $stats['avg_duration'] = $stats['total_duration'] / $stats['count'];
        $stats['total_memory'] += $metric['memory_used'];

        // Check threshold violations
        foreach ($this->thresholds as $pattern => $threshold) {
            if (str_contains($name, $pattern) && $duration > $threshold) {
                $stats['violations']++;
                $this->logSlowOperation($name, $metric, $threshold);
            }
        }

        // Keep last 10 samples for debugging
        $stats['samples'][] = $metric;
        if (count($stats['samples']) > 10) {
            array_shift($stats['samples']);
        }
    }

    public function getMetrics(): array
    {
        return [
            'uptime' => microtime(true) - $this->startTime,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_start' => $this->startMemory,
            'operations' => $this->metrics,
            'active_timers' => array_keys($this->timers),
        ];
    }

    public function getMetric(string $name): ?array
    {
        return $this->metrics[$name] ?? null;
    }

    public function setThreshold(string $pattern, float $threshold): void
    {
        $this->thresholds[$pattern] = $threshold;
    }

    public function reset(): void
    {
        $this->timers = [];
        $this->metrics = [];
    }

    private function logSlowOperation(string $name, array $metric, float $threshold): void
    {
        if (function_exists('zippicks_foundation')) {
            $foundation = zippicks_foundation();
            if ($foundation && $foundation->getContainer()->has('logger')) {
                $logger = $foundation->getContainer()->get('logger');
                $logger->warning('Slow operation detected', [
                    'operation' => $name,
                    'duration' => $metric['duration'],
                    'threshold' => $threshold,
                    'memory_used' => $metric['memory_used'],
                    'context' => $metric['context'],
                ]);
            }
        }
    }

    public function registerWordPressHooks(): void
    {
        // Track total page generation time
        add_action('init', function() {
            $this->startTimer('wordpress_page_load');
        }, 1);

        add_action('shutdown', function() {
            $this->endTimer('wordpress_page_load');
        }, 999);

        // Track database queries
        if (defined('SAVEQUERIES') && SAVEQUERIES) {
            add_filter('query', function($query) {
                $this->startTimer('db_query_' . md5($query), ['query' => $query]);
                return $query;
            });

            add_action('query_end', function($query) {
                $this->endTimer('db_query_' . md5($query));
            });
        }

        // Track plugin loads
        add_action('activated_plugin', function($plugin) {
            $this->recordMetric('plugin_activation', [
                'name' => 'plugin_activation',
                'duration' => 0,
                'memory_used' => 0,
                'context' => ['plugin' => $plugin],
                'timestamp' => time(),
            ]);
        });
    }
}