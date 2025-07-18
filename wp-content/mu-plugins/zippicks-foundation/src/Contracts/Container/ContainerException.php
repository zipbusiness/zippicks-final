<?php
/**
 * Container Exception
 * 
 * @package ZipPicks\Foundation\Contracts\Container
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Container;

use Psr\Container\ContainerExceptionInterface;
use Exception;

/**
 * Exception thrown for general container errors
 */
class ContainerException extends Exception implements ContainerExceptionInterface
{
}