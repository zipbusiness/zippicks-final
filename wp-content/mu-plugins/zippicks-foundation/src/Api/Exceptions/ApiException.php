<?php
/**
 * ZipPicks API Exception
 * 
 * Base exception class for API-related errors
 *
 * @package ZipPicks\Foundation\Api\Exceptions
 */

namespace ZipPicks\Foundation\Api\Exceptions;

class ApiException extends \Exception
{
    /**
     * Error context
     *
     * @var array
     */
    protected array $context = [];

    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode = 400;

    /**
     * Error type
     *
     * @var string
     */
    protected string $errorType = 'api_error';

    /**
     * Create a new API exception
     *
     * @param string $message
     * @param int $statusCode
     * @param array $context
     * @param \Throwable|null $previous
     */
    public function __construct(
        string $message = '',
        int $statusCode = 400,
        array $context = [],
        \Throwable $previous = null
    ) {
        $this->statusCode = $statusCode;
        $this->context = $context;
        
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Get error context
     *
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
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
     * Get error type
     *
     * @return string
     */
    public function getErrorType(): string
    {
        return $this->errorType;
    }

    /**
     * Convert exception to array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'error' => [
                'type' => $this->errorType,
                'message' => $this->getMessage(),
                'code' => $this->statusCode,
                'context' => $this->context
            ]
        ];
    }

    /**
     * Convert exception to JSON
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}