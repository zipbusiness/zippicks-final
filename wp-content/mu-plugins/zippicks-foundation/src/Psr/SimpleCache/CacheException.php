<?php
/**
 * PSR-16 Cache Exception
 * 
 * @package ZipPicks\Foundation\Psr\SimpleCache
 * @since 2.0.0
 */

declare(strict_types=1);

namespace Psr\SimpleCache;

/**
 * Exception interface for all exceptions thrown by cache implementations
 */
interface CacheException extends \Throwable
{
}