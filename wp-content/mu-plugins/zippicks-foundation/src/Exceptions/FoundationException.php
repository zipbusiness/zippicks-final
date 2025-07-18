<?php
/**
 * Foundation Exception
 * 
 * @package ZipPicks\Foundation\Exceptions
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for foundation errors with metadata support
 *
 * @since 1.0.0
 */
class FoundationException extends Exception
{
    /**
     * Exception context metadata
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode = 500;

    /**
     * Constructor
     *
     * @param string $message Exception message
     * @param int $code Exception code
     * @param Throwable|null $previous Previous exception
     * @param array<string, mixed> $context Additional context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get exception context
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set exception context
     *
     * @param array<string, mixed> $context
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add to exception context
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function withContext(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get HTTP status code
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set HTTP status code
     *
     * @param int $statusCode
     * @return self
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Create exception with context
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @param int $code
     * @param Throwable|null $previous
     * @return static
     */
    public static function createWithContext(
        string $message,
        array $context = [],
        int $code = 0,
        ?Throwable $previous = null
    ): static {
        return new static($message, $code, $previous, $context);
    }
}