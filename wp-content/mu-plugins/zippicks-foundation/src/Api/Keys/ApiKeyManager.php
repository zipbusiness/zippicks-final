<?php
/**
 * ZipPicks API Key Manager
 * 
 * Manages API keys for the enterprise platform
 * Handles generation, validation, and usage tracking
 *
 * @package ZipPicks\Foundation\Api\Keys
 */

namespace ZipPicks\Foundation\Api\Keys;

use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Logging\Logger;
use ZipPicks\Foundation\Cache\CacheManager;

class ApiKeyManager
{
    /**
     * API key repository
     *
     * @var ApiKeyRepository
     */
    protected ApiKeyRepository $repository;

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected CacheManager $cache;

    /**
     * API key prefix length
     *
     * @var int
     */
    protected int $prefixLength = 7;

    /**
     * API key length
     *
     * @var int
     */
    protected int $keyLength = 32;

    /**
     * Create new API key manager
     *
     * @param ApiKeyRepository $repository
     * @param Logger $logger
     * @param CacheManager $cache
     */
    public function __construct(ApiKeyRepository $repository, Logger $logger, CacheManager $cache)
    {
        $this->repository = $repository;
        $this->logger = $logger;
        $this->cache = $cache;
    }

    /**
     * Generate a new API key
     *
     * @param int $userId
     * @param string $name
     * @param array $options
     * @return array
     */
    public function generate(int $userId, string $name, array $options = []): array
    {
        // Generate secure random key
        $plainKey = $this->generateSecureKey();
        
        // Extract prefix for easy identification
        $prefix = substr($plainKey, 0, $this->prefixLength);
        
        // Hash the key for storage
        $hashedKey = hash('sha256', $plainKey);
        
        // Prepare key data
        $keyData = [
            'key_hash' => $hashedKey,
            'key_prefix' => $prefix,
            'user_id' => $userId,
            'name' => $name,
            'tier' => $options['tier'] ?? 'free',
            'permissions' => json_encode($options['permissions'] ?? []),
            'rate_limits' => json_encode($options['rate_limits'] ?? $this->getDefaultRateLimits($options['tier'] ?? 'free')),
            'expires_at' => $options['expires_at'] ?? null
        ];
        
        // Save to database
        $keyId = $this->repository->create($keyData);
        
        // Log key creation
        $this->logger->info('API key created', [
            'key_id' => $keyId,
            'user_id' => $userId,
            'tier' => $keyData['tier'],
            'prefix' => $prefix
        ]);
        
        // Return the plain key (only shown once)
        return [
            'id' => $keyId,
            'key' => $plainKey,
            'prefix' => $prefix,
            'tier' => $keyData['tier'],
            'created_at' => time()
        ];
    }

    /**
     * Validate an API key
     *
     * @param string $apiKey
     * @return object|null
     */
    public function validate(string $apiKey): ?object
    {
        // Check cache first
        $cacheKey = 'api_key:' . substr($apiKey, 0, 16);
        $cached = $this->cache->get($cacheKey);
        
        if ($cached !== null) {
            return $cached ?: null;
        }
        
        // Hash the provided key
        $hashedKey = hash('sha256', $apiKey);
        
        // Look up in database
        $keyData = $this->repository->findByHash($hashedKey);
        
        // Cache the result (including failures)
        $this->cache->put($cacheKey, $keyData ?: false, 300); // 5 minutes
        
        return $keyData;
    }

    /**
     * Revoke an API key
     *
     * @param int $keyId
     * @param int $userId
     * @return bool
     */
    public function revoke(int $keyId, int $userId): bool
    {
        // Verify ownership
        $key = $this->repository->find($keyId);
        
        if (!$key || $key->user_id !== $userId) {
            return false;
        }
        
        // Delete the key
        $result = $this->repository->delete($keyId);
        
        if ($result) {
            // Clear cache
            $this->clearKeyCache($key->key_prefix);
            
            // Log revocation
            $this->logger->info('API key revoked', [
                'key_id' => $keyId,
                'user_id' => $userId
            ]);
        }
        
        return $result;
    }

    /**
     * Update API key
     *
     * @param int $keyId
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function update(int $keyId, int $userId, array $data): bool
    {
        // Verify ownership
        $key = $this->repository->find($keyId);
        
        if (!$key || $key->user_id !== $userId) {
            return false;
        }
        
        // Update allowed fields
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        
        if (isset($data['tier'])) {
            $updateData['tier'] = $data['tier'];
            $updateData['rate_limits'] = json_encode($this->getDefaultRateLimits($data['tier']));
        }
        
        if (isset($data['permissions'])) {
            $updateData['permissions'] = json_encode($data['permissions']);
        }
        
        if (isset($data['expires_at'])) {
            $updateData['expires_at'] = $data['expires_at'];
        }
        
        // Update in database
        $result = $this->repository->update($keyId, $updateData);
        
        if ($result) {
            // Clear cache
            $this->clearKeyCache($key->key_prefix);
            
            // Log update
            $this->logger->info('API key updated', [
                'key_id' => $keyId,
                'user_id' => $userId,
                'changes' => array_keys($updateData)
            ]);
        }
        
        return $result;
    }

    /**
     * List user's API keys
     *
     * @param int $userId
     * @param array $filters
     * @return array
     */
    public function listUserKeys(int $userId, array $filters = []): array
    {
        return $this->repository->findByUserId($userId, $filters);
    }

    /**
     * Update last used timestamp
     *
     * @param int $keyId
     * @return void
     */
    public function updateLastUsed(int $keyId): void
    {
        $this->repository->updateLastUsed($keyId);
    }

    /**
     * Track API key usage
     *
     * @param int $keyId
     * @param string $endpoint
     * @param float $latency
     * @param bool $error
     * @return void
     */
    public function trackUsage(int $keyId, string $endpoint, float $latency, bool $error = false): void
    {
        $this->repository->trackUsage($keyId, $endpoint, $latency, $error);
    }

    /**
     * Get API key usage statistics
     *
     * @param int $keyId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getUsageStats(int $keyId, string $startDate, string $endDate): array
    {
        return $this->repository->getUsageStats($keyId, $startDate, $endDate);
    }

    /**
     * Generate secure random key
     *
     * @return string
     */
    protected function generateSecureKey(): string
    {
        $prefix = 'zp_' . ($this->isProduction() ? 'live' : 'test') . '_';
        $randomBytes = random_bytes($this->keyLength);
        $key = $prefix . bin2hex($randomBytes);
        
        return substr($key, 0, 40); // Standardize length
    }

    /**
     * Get default rate limits for tier
     *
     * @param string $tier
     * @return array
     */
    protected function getDefaultRateLimits(string $tier): array
    {
        $limits = [
            'free' => [
                'requests_per_minute' => 60,
                'requests_per_hour' => 1000,
                'requests_per_day' => 10000
            ],
            'starter' => [
                'requests_per_minute' => 300,
                'requests_per_hour' => 10000,
                'requests_per_day' => 100000
            ],
            'growth' => [
                'requests_per_minute' => 1000,
                'requests_per_hour' => 50000,
                'requests_per_day' => 500000
            ],
            'scale' => [
                'requests_per_minute' => 5000,
                'requests_per_hour' => 200000,
                'requests_per_day' => 2000000
            ],
            'enterprise' => [
                'requests_per_minute' => null, // Unlimited
                'requests_per_hour' => null,
                'requests_per_day' => null
            ]
        ];
        
        return $limits[$tier] ?? $limits['free'];
    }

    /**
     * Clear key cache
     *
     * @param string $keyPrefix
     * @return void
     */
    protected function clearKeyCache(string $keyPrefix): void
    {
        // Clear all possible cache entries for this key
        for ($i = 0; $i < 10; $i++) {
            $this->cache->forget('api_key:' . $keyPrefix . $i);
        }
    }

    /**
     * Check if in production environment
     *
     * @return bool
     */
    protected function isProduction(): bool
    {
        return defined('WP_ENV') && WP_ENV === 'production';
    }
}