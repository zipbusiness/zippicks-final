<?php
/**
 * Enterprise Logger Implementation
 * 
 * @package ZipPicks\Foundation\Logging
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Logging;

use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Logging\LogDriverInterface;
use ZipPicks\Foundation\Core\CircuitBreaker;

/**
 * Enterprise-grade logger with multiple drivers, circuit breakers, and metrics
 */
class EnterpriseLogger implements LoggerInterface
{
    private array $drivers = [];
    private array $context = [];
    private string $channel = 'default';
    private array $metrics = [
        'logs_written' => 0,
        'logs_failed' => 0,
        'performance' => [],
    ];
    private array $circuitBreakers = [];
    private bool $asyncEnabled;
    private int $batchSize;

    public function __construct(
        array $drivers = [],
        bool $asyncEnabled = true,
        int $batchSize = 100
    ) {
        $this->drivers = $drivers;
        $this->asyncEnabled = $asyncEnabled;
        $this->batchSize = $batchSize;
        
        // Initialize circuit breakers for each driver
        foreach ($drivers as $driver) {
            $this->circuitBreakers[$driver->getName()] = new CircuitBreaker(
                $driver->getName(),
                failureThreshold: 5,
                recoveryTime: 60,
                successThreshold: 2
            );
        }
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $startTime = microtime(true);
        
        // Merge contexts
        $context = array_merge($this->context, $context);
        
        // Create log entry
        $entry = new LogEntry(
            (string)$level,
            (string)$message,
            $context,
            $this->channel
        );
        
        // Track metrics
        $this->metrics['logs_written']++;
        
        // Write to each healthy driver
        $writeCount = 0;
        foreach ($this->drivers as $driver) {
            $breaker = $this->circuitBreakers[$driver->getName()];
            
            if (!$breaker->canAttempt()) {
                continue;
            }
            
            try {
                $driverStart = microtime(true);
                
                if ($this->asyncEnabled && $this->canDispatchAsync()) {
                    $this->dispatchAsync($driver, $entry);
                } else {
                    $driver->write($entry);
                }
                
                $breaker->recordSuccess();
                $writeCount++;
                
                // Track driver performance
                $this->trackDriverPerformance($driver->getName(), microtime(true) - $driverStart);
                
            } catch (\Throwable $e) {
                $breaker->recordFailure();
                $this->metrics['logs_failed']++;
                
                // Log driver failure to error_log as fallback
                error_log(sprintf(
                    "Logger driver '%s' failed: %s",
                    $driver->getName(),
                    $e->getMessage()
                ));
            }
        }
        
        // If no drivers succeeded, fallback to error_log
        if ($writeCount === 0) {
            error_log($entry->getFormattedMessage());
        }
        
        // Track overall performance
        $this->trackPerformance('total', microtime(true) - $startTime);
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function channel(string $channel): self
    {
        $clone = clone $this;
        $clone->channel = $channel;
        return $clone;
    }

    public function flush(): void
    {
        foreach ($this->drivers as $driver) {
            try {
                $driver->flush();
            } catch (\Throwable $e) {
                error_log("Failed to flush driver {$driver->getName()}: " . $e->getMessage());
            }
        }
    }

    public function addDriver(LogDriverInterface $driver): void
    {
        $this->drivers[] = $driver;
        $this->circuitBreakers[$driver->getName()] = new CircuitBreaker(
            $driver->getName(),
            failureThreshold: 5,
            recoveryTime: 60,
            successThreshold: 2
        );
    }

    public function removeDriver(string $name): void
    {
        $this->drivers = array_filter($this->drivers, function($driver) use ($name) {
            return $driver->getName() !== $name;
        });
        unset($this->circuitBreakers[$name]);
    }

    public function getDrivers(): array
    {
        return $this->drivers;
    }

    public function getMetrics(): array
    {
        $driverMetrics = [];
        foreach ($this->drivers as $driver) {
            $driverMetrics[$driver->getName()] = [
                'metrics' => $driver->getMetrics(),
                'healthy' => $driver->isHealthy(),
                'circuit_breaker' => $this->circuitBreakers[$driver->getName()]->getState(),
            ];
        }
        
        return [
            'totals' => $this->metrics,
            'drivers' => $driverMetrics,
            'performance' => $this->getPerformanceStats(),
        ];
    }

    public function getHealthStatus(): array
    {
        $status = [];
        foreach ($this->drivers as $driver) {
            $breaker = $this->circuitBreakers[$driver->getName()];
            $status[$driver->getName()] = [
                'healthy' => $driver->isHealthy(),
                'circuit_state' => $breaker->getState(),
                'can_attempt' => $breaker->canAttempt(),
            ];
        }
        return $status;
    }

    private function dispatchAsync(LogDriverInterface $driver, LogEntry $entry): void
    {
        // In WordPress, we'll use Action Scheduler or WP-Cron
        // For now, just write synchronously
        // TODO: Implement with Action Scheduler
        $driver->write($entry);
    }

    private function canDispatchAsync(): bool
    {
        // Check if we're in a context where async is safe
        if (defined('WP_CLI') && WP_CLI) {
            return false;
        }
        
        if (wp_doing_ajax() || wp_doing_cron()) {
            return false;
        }
        
        return true;
    }

    private function trackPerformance(string $operation, float $duration): void
    {
        if (!isset($this->metrics['performance'][$operation])) {
            $this->metrics['performance'][$operation] = [
                'count' => 0,
                'total' => 0,
                'min' => PHP_FLOAT_MAX,
                'max' => 0,
            ];
        }
        
        $perf = &$this->metrics['performance'][$operation];
        $perf['count']++;
        $perf['total'] += $duration;
        $perf['min'] = min($perf['min'], $duration);
        $perf['max'] = max($perf['max'], $duration);
    }

    private function trackDriverPerformance(string $driver, float $duration): void
    {
        $this->trackPerformance("driver_{$driver}", $duration);
    }

    private function getPerformanceStats(): array
    {
        $stats = [];
        foreach ($this->metrics['performance'] as $operation => $data) {
            $stats[$operation] = [
                'count' => $data['count'],
                'avg_ms' => ($data['total'] / $data['count']) * 1000,
                'min_ms' => $data['min'] * 1000,
                'max_ms' => $data['max'] * 1000,
            ];
        }
        return $stats;
    }

    public function __destruct()
    {
        $this->flush();
    }
}