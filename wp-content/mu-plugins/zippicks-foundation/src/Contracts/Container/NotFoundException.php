<?php
/**
 * Container Not Found Exception
 * 
 * @package ZipPicks\Foundation\Contracts\Container
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Container;

use Psr\Container\NotFoundExceptionInterface;
use Exception;

/**
 * Exception thrown when a requested entry is not found in the container
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface
{
}