<?php
/**
 * ZipPicks API Version Exception
 * 
 * Exception thrown when API version is invalid or unsupported
 *
 * @package ZipPicks\Foundation\Api\Exceptions
 */

namespace ZipPicks\Foundation\Api\Exceptions;

class ApiVersionException extends ApiException
{
    /**
     * Error type
     *
     * @var string
     */
    protected string $errorType = 'invalid_api_version';

    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode = 400;
}