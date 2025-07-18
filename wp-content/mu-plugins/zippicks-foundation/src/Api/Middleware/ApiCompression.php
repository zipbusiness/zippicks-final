<?php
/**
 * ZipPicks API Compression Middleware
 * 
 * Handles response compression for reduced bandwidth usage
 *
 * @package ZipPicks\Foundation\Api\Middleware
 */

namespace ZipPicks\Foundation\Api\Middleware;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Middleware\Middleware;

class ApiCompression extends Middleware
{
    /**
     * Minimum content length for compression (bytes)
     *
     * @var int
     */
    protected int $minLength = 1024; // 1KB

    /**
     * Supported compression methods
     *
     * @var array
     */
    protected array $methods = ['gzip', 'deflate'];

    /**
     * Handle the request
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // Process request
        $response = $next($request);
        
        // Check if compression should be applied
        if (!$this->shouldCompress($request, $response)) {
            return $response;
        }
        
        // Determine compression method
        $method = $this->negotiateCompression($request);
        
        if (!$method) {
            return $response;
        }
        
        // Compress response
        $compressed = $this->compress($response->getContent(), $method);
        
        if ($compressed !== false) {
            $response->setContent($compressed);
            $response->headers->set('Content-Encoding', $method);
            $response->headers->set('Vary', 'Accept-Encoding');
            
            // Remove Content-Length as it's no longer accurate
            $response->headers->remove('Content-Length');
        }
        
        return $response;
    }

    /**
     * Check if response should be compressed
     *
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    protected function shouldCompress(Request $request, Response $response): bool
    {
        // Don't compress if already encoded
        if ($response->headers->has('Content-Encoding')) {
            return false;
        }
        
        // Check content type
        $contentType = $response->headers->get('Content-Type', '');
        if (!$this->isCompressibleContentType($contentType)) {
            return false;
        }
        
        // Check content length
        $content = $response->getContent();
        if (strlen($content) < $this->minLength) {
            return false;
        }
        
        // Check if client accepts compression
        if (!$request->headers->has('Accept-Encoding')) {
            return false;
        }
        
        return true;
    }

    /**
     * Negotiate compression method
     *
     * @param Request $request
     * @return string|null
     */
    protected function negotiateCompression(Request $request): ?string
    {
        $acceptEncoding = $request->headers->get('Accept-Encoding', '');
        
        foreach ($this->methods as $method) {
            if (stripos($acceptEncoding, $method) !== false) {
                return $method;
            }
        }
        
        return null;
    }

    /**
     * Compress content
     *
     * @param string $content
     * @param string $method
     * @return string|false
     */
    protected function compress(string $content, string $method): string|false
    {
        switch ($method) {
            case 'gzip':
                return gzencode($content, 6); // Level 6 for good balance
                
            case 'deflate':
                return gzdeflate($content, 6);
                
            default:
                return false;
        }
    }

    /**
     * Check if content type is compressible
     *
     * @param string $contentType
     * @return bool
     */
    protected function isCompressibleContentType(string $contentType): bool
    {
        $compressibleTypes = [
            'application/json',
            'application/xml',
            'text/plain',
            'text/csv',
            'text/html',
            'application/javascript'
        ];
        
        foreach ($compressibleTypes as $type) {
            if (stripos($contentType, $type) !== false) {
                return true;
            }
        }
        
        return false;
    }
}