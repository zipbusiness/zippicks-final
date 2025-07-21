<?php
/**
 * Renderable Exception Interface
 *
 * @package ZipPicks\Foundation\Exceptions
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Exceptions;

use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Contracts\Http\RequestInterface;

/**
 * Contract for exceptions that can render themselves
 *
 * @since 1.0.0
 */
interface RenderableExceptionInterface
{
    /**
     * Render the exception into an HTTP response
     *
     * @param RequestInterface $request The current request
     * @return ResponseInterface|null Returns null if unable to render
     */
    public function render(RequestInterface $request): ?ResponseInterface;
}