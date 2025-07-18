<?php
/**
 * ZipPicks API Version Manager
 * 
 * Handles API versioning through headers, URL paths, and content negotiation
 *
 * @package ZipPicks\Foundation\Api\Gateway
 */

namespace ZipPicks\Foundation\Api\Gateway;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Api\Exceptions\ApiVersionException;

class VersionManager
{
    /**
     * Supported API versions
     *
     * @var array
     */
    protected array $versions = [
        'v1' => [
            'status' => 'stable',
            'deprecated' => false,
            'sunset' => null,
            'features' => [
                'businesses',
                'reviews',
                'taste_graph',
                'vibes',
                'search'
            ]
        ],
        'v2' => [
            'status' => 'beta',
            'deprecated' => false,
            'sunset' => null,
            'features' => [
                'businesses',
                'reviews',
                'taste_graph',
                'vibes',
                'search',
                'recommendations',
                'analytics'
            ]
        ]
    ];

    /**
     * Default version
     *
     * @var string
     */
    protected string $defaultVersion = 'v1';

    /**
     * Current version
     *
     * @var string|null
     */
    protected ?string $currentVersion = null;

    /**
     * Version detection strategies
     *
     * @var array
     */
    protected array $strategies = [
        'header',
        'url',
        'query',
        'accept'
    ];

    /**
     * Container instance
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Create a new version manager
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Detect API version from request
     *
     * @param Request $request
     * @return string|null
     */
    public function detect(Request $request): ?string
    {
        // Try each detection strategy
        foreach ($this->strategies as $strategy) {
            $version = $this->{"detect{$strategy}"}($request);
            if ($version && $this->isValidVersion($version)) {
                $this->currentVersion = $version;
                return $version;
            }
        }
        
        // Return default version if no version detected
        return $this->defaultVersion;
    }

    /**
     * Detect version from header
     *
     * @param Request $request
     * @return string|null
     */
    protected function detectHeader(Request $request): ?string
    {
        // Check X-API-Version header
        if ($request->headers->has('X-API-Version')) {
            return $request->headers->get('X-API-Version');
        }
        
        // Check Accept-Version header
        if ($request->headers->has('Accept-Version')) {
            return $request->headers->get('Accept-Version');
        }
        
        return null;
    }

    /**
     * Detect version from URL path
     *
     * @param Request $request
     * @return string|null
     */
    protected function detectUrl(Request $request): ?string
    {
        $path = $request->path();
        
        // Match /api/v1, /api/v2, etc.
        if (preg_match('#^/api/(v\d+)#', $path, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Detect version from query parameter
     *
     * @param Request $request
     * @return string|null
     */
    protected function detectQuery(Request $request): ?string
    {
        return $request->query->get('version') ?: $request->query->get('v');
    }

    /**
     * Detect version from Accept header
     *
     * @param Request $request
     * @return string|null
     */
    protected function detectAccept(Request $request): ?string
    {
        $accept = $request->headers->get('Accept', '');
        
        // Match application/vnd.zippicks.v1+json
        if (preg_match('/application\/vnd\.zippicks\.(v\d+)\+json/', $accept, $matches)) {
            return $matches[1];
        }
        
        return null;
    }

    /**
     * Check if a version is valid
     *
     * @param string $version
     * @return bool
     */
    public function isValidVersion(string $version): bool
    {
        return isset($this->versions[$version]);
    }

    /**
     * Check if a version is deprecated
     *
     * @param string $version
     * @return bool
     */
    public function isDeprecated(string $version): bool
    {
        return $this->versions[$version]['deprecated'] ?? false;
    }

    /**
     * Get sunset date for a version
     *
     * @param string $version
     * @return string|null
     */
    public function getSunsetDate(string $version): ?string
    {
        return $this->versions[$version]['sunset'] ?? null;
    }

    /**
     * Check if a feature is available in a version
     *
     * @param string $version
     * @param string $feature
     * @return bool
     */
    public function hasFeature(string $version, string $feature): bool
    {
        if (!$this->isValidVersion($version)) {
            return false;
        }
        
        return in_array($feature, $this->versions[$version]['features'] ?? []);
    }

    /**
     * Get all supported versions
     *
     * @return array
     */
    public function getSupportedVersions(): array
    {
        return array_keys($this->versions);
    }

    /**
     * Get version information
     *
     * @param string $version
     * @return array|null
     */
    public function getVersionInfo(string $version): ?array
    {
        return $this->versions[$version] ?? null;
    }

    /**
     * Get current version
     *
     * @return string|null
     */
    public function getCurrentVersion(): ?string
    {
        return $this->currentVersion;
    }

    /**
     * Set current version
     *
     * @param string $version
     * @return void
     * @throws ApiVersionException
     */
    public function setCurrentVersion(string $version): void
    {
        if (!$this->isValidVersion($version)) {
            throw new ApiVersionException("Invalid API version: {$version}");
        }
        
        $this->currentVersion = $version;
    }

    /**
     * Get default version
     *
     * @return string
     */
    public function getDefaultVersion(): string
    {
        return $this->defaultVersion;
    }

    /**
     * Set default version
     *
     * @param string $version
     * @return void
     * @throws ApiVersionException
     */
    public function setDefaultVersion(string $version): void
    {
        if (!$this->isValidVersion($version)) {
            throw new ApiVersionException("Invalid API version: {$version}");
        }
        
        $this->defaultVersion = $version;
    }

    /**
     * Register a new version
     *
     * @param string $version
     * @param array $config
     * @return void
     */
    public function registerVersion(string $version, array $config): void
    {
        $this->versions[$version] = array_merge([
            'status' => 'stable',
            'deprecated' => false,
            'sunset' => null,
            'features' => []
        ], $config);
    }

    /**
     * Deprecate a version
     *
     * @param string $version
     * @param string|null $sunset
     * @return void
     */
    public function deprecateVersion(string $version, ?string $sunset = null): void
    {
        if ($this->isValidVersion($version)) {
            $this->versions[$version]['deprecated'] = true;
            $this->versions[$version]['sunset'] = $sunset;
        }
    }

    /**
     * Get version comparison
     *
     * @param string $version1
     * @param string $version2
     * @return int
     */
    public function compareVersions(string $version1, string $version2): int
    {
        // Extract numeric parts
        $v1 = (int) str_replace('v', '', $version1);
        $v2 = (int) str_replace('v', '', $version2);
        
        return $v1 <=> $v2;
    }

    /**
     * Check if version meets minimum requirement
     *
     * @param string $version
     * @param string $minimum
     * @return bool
     */
    public function meetsMinimum(string $version, string $minimum): bool
    {
        return $this->compareVersions($version, $minimum) >= 0;
    }
}