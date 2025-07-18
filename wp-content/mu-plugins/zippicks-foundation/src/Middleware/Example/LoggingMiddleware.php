<?php
/**
 * Example Logging Middleware
 * 
 * @package ZipPicks\Foundation\Middleware\Example
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Middleware\Example;

use Closure;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Contracts\Middleware\MiddlewareInterface;
use ZipPicks\Foundation\Contracts\Middleware\RequestInterface;

class LoggingMiddleware implements MiddlewareInterface
{
    /**
     * Logger instance
     *
     * @var ?LoggerInterface
     */
    protected ?LoggerInterface $logger;

    /**
     * Create logging middleware
     *
     * @param ?LoggerInterface $logger
     */
    public function __construct(?LoggerInterface $logger = null)
    {
        $this->logger = $logger ?? foundation()->get('logger');
    }

    /**
     * Handle an incoming request
     *
     * @param RequestInterface $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(RequestInterface $request, Closure $next): mixed
    {
        $startTime = microtime(true);
        $requestId = uniqid('req_', true);
        
        // Log the incoming request
        $this->log('info', 'Incoming request', [
            'request_id' => $requestId,
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'timestamp' => date('Y-m-d H:i:s'),
            'user_agent' => $request->getHeader('User-Agent', 'Unknown'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'context' => $request->getContext()
        ]);
        
        // Add request ID to context for tracing
        $request = $request->withContext('request_id', $requestId);
        
        try {
            // Process the request
            $response = $next($request);
            
            // Log successful completion
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->log('info', 'Request completed', [
                'request_id' => $requestId,
                'duration_ms' => $duration,
                'memory_peak' => $this->formatBytes(memory_get_peak_usage(true))
            ]);
            
            return $response;
        } catch (\Throwable $e) {
            // Log errors
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $this->log('error', 'Request failed', [
                'request_id' => $requestId,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            
            throw $e;
        }
    }

    /**
     * Log a message
     *
     * @param string $level
     * @param string $message
     * @param array<string, mixed> $context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $this->logger->$level('[LoggingMiddleware] ' . $message, $context);
        }
    }

    /**
     * Format bytes into human readable format
     *
     * @param int $bytes
     * @return string
     */
    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        return round($bytes / pow(1024, $pow), 2) . ' ' . $units[$pow];
    }
}