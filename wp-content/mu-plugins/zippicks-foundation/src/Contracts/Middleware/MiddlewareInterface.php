<?php
/**
 * Middleware Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Middleware
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Middleware;

use Closure;

interface MiddlewareInterface
{
    /**
     * Handle an incoming request
     *
     * @param RequestInterface $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(RequestInterface $request, Closure $next): mixed;
}