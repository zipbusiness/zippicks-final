<?php
/**
 * ZipPicks Route Not Found Exception
 * 
 * Exception thrown when API route is not found
 *
 * @package ZipPicks\Foundation\Api\Exceptions
 */

namespace ZipPicks\Foundation\Api\Exceptions;

class RouteNotFoundException extends ApiException
{
    /**
     * Error type
     *
     * @var string
     */
    protected string $errorType = 'route_not_found';

    /**
     * HTTP status code
     *
     * @var int
     */
    protected int $statusCode = 404;
}