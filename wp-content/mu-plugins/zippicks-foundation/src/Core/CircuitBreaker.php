<?php
/**
 * Circuit Breaker Pattern Implementation
 * 
 * @package ZipPicks\Foundation\Core
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Core;

/**
 * Circuit breaker for handling failures gracefully
 */
class CircuitBreaker
{
    private string $name;
    private int $failureThreshold;
    private int $recoveryTime;
    private int $successThreshold;
    private int $failureCount = 0;
    private int $successCount = 0;
    private float $lastFailureTime = 0;
    private string $state = 'closed'; // closed, open, half-open

    public function __construct(
        string $name,
        int $failureThreshold = 5,
        int $recoveryTime = 60,
        int $successThreshold = 2
    ) {
        $this->name = $name;
        $this->failureThreshold = $failureThreshold;
        $this->recoveryTime = $recoveryTime;
        $this->successThreshold = $successThreshold;
    }

    public function canAttempt(): bool
    {
        return match($this->state) {
            'closed' => true,
            'open' => $this->shouldAttemptRecovery(),
            'half-open' => true,
            default => false,
        };
    }

    public function recordSuccess(): void
    {
        if ($this->state === 'half-open') {
            $this->successCount++;
            if ($this->successCount >= $this->successThreshold) {
                $this->close();
            }
        } elseif ($this->state === 'closed') {
            $this->failureCount = 0;
        }
    }

    public function recordFailure(): void
    {
        $this->lastFailureTime = microtime(true);
        
        if ($this->state === 'half-open') {
            $this->open();
        } elseif ($this->state === 'closed') {
            $this->failureCount++;
            if ($this->failureCount >= $this->failureThreshold) {
                $this->open();
            }
        }
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function getMetrics(): array
    {
        return [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
            'last_failure' => $this->lastFailureTime,
            'can_attempt' => $this->canAttempt(),
        ];
    }

    private function open(): void
    {
        $this->state = 'open';
        $this->successCount = 0;
        $this->failureCount = 0;
    }

    private function close(): void
    {
        $this->state = 'closed';
        $this->successCount = 0;
        $this->failureCount = 0;
    }

    private function halfOpen(): void
    {
        $this->state = 'half-open';
        $this->successCount = 0;
        $this->failureCount = 0;
    }

    private function shouldAttemptRecovery(): bool
    {
        if ((microtime(true) - $this->lastFailureTime) >= $this->recoveryTime) {
            $this->halfOpen();
            return true;
        }
        return false;
    }
}