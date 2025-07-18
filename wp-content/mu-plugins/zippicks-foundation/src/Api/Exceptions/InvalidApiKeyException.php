<?php
/**
 * ZipPicks Invalid API Key Exception
 * 
 * Exception thrown when API key is invalid or unauthorized
 *
 * @package ZipPicks\Foundation\Api\Exceptions
 */

namespace ZipPicks\Foundation\Api\Exceptions;

class InvalidApiKeyException extends ApiException
{
    /**
     * Error type
     *
     * @var string
     */
    protected string $errorType = 'invalid_api_key';

    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode = 401;
}