<?php
/**
 * ZipPicks API Versioning Middleware
 * 
 * Handles API version validation and deprecation warnings
 *
 * @package ZipPicks\Foundation\Api\Middleware
 */

namespace ZipPicks\Foundation\Api\Middleware;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Middleware\Middleware;
use ZipPicks\Foundation\Api\Gateway\VersionManager;
use ZipPicks\Foundation\Logging\Logger;

class ApiVersioning extends Middleware
{
    /**
     * Version manager
     *
     * @var VersionManager
     */
    protected VersionManager $versionManager;

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Create new versioning middleware
     *
     * @param VersionManager $versionManager
     * @param Logger $logger
     */
    public function __construct(VersionManager $versionManager, Logger $logger)
    {
        $this->versionManager = $versionManager;
        $this->logger = $logger;
    }

    /**
     * Handle the request
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        // Version should already be detected by gateway
        $version = $request->attributes->get('api_version');
        
        if (!$version) {
            $version = $this->versionManager->detect($request);
            $request->attributes->set('api_version', $version);
        }
        
        // Log deprecated version usage
        if ($this->versionManager->isDeprecated($version)) {
            $this->logger->warning('Deprecated API version used', [
                'version' => $version,
                'path' => $request->path(),
                'user_id' => $request->attributes->get('user_id'),
                'sunset' => $this->versionManager->getSunsetDate($version)
            ]);
        }
        
        // Process request
        $response = $next($request);
        
        // Add version headers
        $this->addVersionHeaders($response, $version);
        
        return $response;
    }

    /**
     * Add version-related headers
     *
     * @param Response $response
     * @param string $version
     * @return void
     */
    protected function addVersionHeaders(Response $response, string $version): void
    {
        // Add current version
        $response->headers->set('X-API-Version', $version);
        
        // Add supported versions
        $response->headers->set(
            'X-API-Versions', 
            implode(', ', $this->versionManager->getSupportedVersions())
        );
        
        // Add deprecation headers if needed
        if ($this->versionManager->isDeprecated($version)) {
            $response->headers->set('X-API-Deprecated', 'true');
            
            if ($sunset = $this->versionManager->getSunsetDate($version)) {
                $response->headers->set('X-API-Sunset', $sunset);
                $response->headers->set(
                    'X-API-Deprecation-Info',
                    "This API version is deprecated and will be sunset on {$sunset}"
                );
            }
        }
    }
}