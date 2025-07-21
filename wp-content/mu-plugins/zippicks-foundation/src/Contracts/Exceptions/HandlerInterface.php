<?php
/**
 * Exception Handler Interface
 *
 * @package ZipPicks\Foundation\Contracts\Exceptions
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Exceptions;

use Throwable;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;

/**
 * Contract for exception handling
 *
 * @since 1.0.0
 */
interface HandlerInterface
{
    /**
     * Report or log an exception
     *
     * @param Throwable $e The exception to report
     * @return void
     * @throws Throwable When the handler cannot handle the exception
     */
    public function report(Throwable $e): void;

    /**
     * Render an exception into an HTTP response
     *
     * @param Throwable $e The exception to render
     * @return ResponseInterface|null Returns null when rendering handled elsewhere
     * @throws Throwable When the handler cannot render the exception
     */
    public function render(Throwable $e): ?ResponseInterface;

    /**
     * Determine if the exception should be reported
     *
     * @param Throwable $e The exception to check
     * @return bool True if the exception should be reported
     */
    public function shouldReport(Throwable $e): bool;
}