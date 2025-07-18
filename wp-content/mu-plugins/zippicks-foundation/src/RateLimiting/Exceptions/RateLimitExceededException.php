<?php

namespace ZipPicks\Foundation\RateLimiting\Exceptions;

use Exception;
use ZipPicks\Foundation\Exceptions\RenderableExceptionInterface;

/**
 * RateLimitExceededException - Thrown when rate limits are exceeded
 * 
 * Provides rich context for upsell opportunities and user education
 * about our tier system driving $100M ARR
 */
class RateLimitExceededException extends Exception implements RenderableExceptionInterface
{
    /**
     * @var string The rate limit key that was exceeded
     */
    protected string $key;

    /**
     * @var int Seconds until the rate limit resets
     */
    protected int $retryAfter;

    /**
     * @var array Usage statistics
     */
    protected array $usage;

    /**
     * @var string|null Upgrade path suggestion
     */
    protected ?string $upgradePath;

    /**
     * @var array Additional context
     */
    protected array $context;

    /**
     * Constructor
     * 
     * @param string $key The rate limit key
     * @param int $retryAfter Seconds until reset
     * @param array $usage Current usage stats
     * @param string|null $upgradePath Suggested upgrade
     * @param array $context Additional context
     */
    public function __construct(
        string $key,
        int $retryAfter,
        array $usage = [],
        ?string $upgradePath = null,
        array $context = []
    ) {
        $this->key = $key;
        $this->retryAfter = $retryAfter;
        $this->usage = $usage;
        $this->upgradePath = $upgradePath;
        $this->context = $context;

        $message = $this->buildMessage();
        parent::__construct($message, 429);
    }

    /**
     * Build user-friendly error message
     * 
     * @return string
     */
    protected function buildMessage(): string
    {
        $base = sprintf(
            'Rate limit exceeded for %s. Please retry after %d seconds.',
            $this->key,
            $this->retryAfter
        );

        if ($this->upgradePath) {
            $base .= sprintf(' Upgrade to %s for higher limits.', $this->upgradePath);
        }

        return $base;
    }

    /**
     * Get the rate limit key
     * 
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Get retry after seconds
     * 
     * @return int
     */
    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Get usage statistics
     * 
     * @return array
     */
    public function getUsage(): array
    {
        return $this->usage;
    }

    /**
     * Get upgrade path suggestion
     * 
     * @return string|null
     */
    public function getUpgradePath(): ?string
    {
        return $this->upgradePath;
    }

    /**
     * Get additional context
     * 
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Render exception for HTTP response
     * 
     * @return array
     */
    public function render(): array
    {
        return [
            'error' => 'rate_limit_exceeded',
            'message' => $this->getMessage(),
            'retry_after' => $this->retryAfter,
            'usage' => $this->usage,
            'upgrade_path' => $this->upgradePath,
            'headers' => $this->getHeaders()
        ];
    }

    /**
     * Get HTTP headers for rate limit response
     * 
     * @return array
     */
    public function getHeaders(): array
    {
        $headers = [
            'X-RateLimit-Limit' => $this->usage['limit'] ?? 0,
            'X-RateLimit-Remaining' => $this->usage['remaining'] ?? 0,
            'X-RateLimit-Reset' => $this->usage['reset_at'] ?? time(),
            'Retry-After' => $this->retryAfter,
        ];

        if ($this->upgradePath) {
            $headers['X-RateLimit-Upgrade'] = $this->upgradePath;
        }

        return $headers;
    }

    /**
     * Log context for analytics
     * 
     * @return array
     */
    public function getLogContext(): array
    {
        return [
            'rate_limit_key' => $this->key,
            'retry_after' => $this->retryAfter,
            'usage' => $this->usage,
            'upgrade_path' => $this->upgradePath,
            'user_tier' => $this->context['tier'] ?? 'unknown',
            'resource' => $this->context['resource'] ?? 'unknown',
            'cost' => $this->context['cost'] ?? 1,
        ];
    }
}