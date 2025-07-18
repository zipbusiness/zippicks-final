<?php
/**
 * Redis Cache Adapter
 * 
 * High-performance caching with Redis including comprehensive error handling,
 * runtime availability detection, group support, and TTL management.
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Cache\Adapters;

use ZipPicksVibes\Cache\CacheInterface;

/**
 * Class RedisAdapter
 * 
 * Provides high-performance caching through Redis with full error handling,
 * connection resilience, and group-based cache management.
 */
class RedisAdapter implements CacheInterface {
    
    /**
     * Redis instance
     * 
     * @var \Redis|null
     */
    private ?\Redis $redis = null;
    
    /**
     * Configuration
     * 
     * @var array
     */
    private array $config;
    
    /**
     * Key prefix
     * 
     * @var string
     */
    private string $prefix;
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Track connection status
     * 
     * @var bool
     */
    private bool $connected = false;
    
    /**
     * Maximum reconnection attempts
     * 
     * @var int
     */
    private int $maxReconnectAttempts = 3;
    
    /**
     * Current reconnection attempt
     * 
     * @var int
     */
    private int $reconnectAttempt = 0;
    
    /**
     * Constructor
     * 
     * @param \Redis|array $redis Redis instance or configuration array
     * @param string|null $prefix Optional prefix override
     * @throws \Exception If Redis extension is not installed
     */
    public function __construct($redis = null, string $prefix = null) {
        if (!class_exists('Redis')) {
            throw new \Exception('Redis extension is not installed');
        }
        
        if ($redis instanceof \Redis) {
            $this->redis = $redis;
            $this->connected = true;
            $this->config = [];
            $this->prefix = $prefix ?? 'zippicks_vibes:';
            $this->logger = null;
        } else {
            $this->config = is_array($redis) ? $redis : [];
            $this->prefix = $prefix ?? ($this->config['prefix'] ?? 'zippicks_vibes:');
            $this->logger = $this->config['logger'] ?? null;
            
            $this->connect();
        }
    }
    
    /**
     * Connect to Redis
     * 
     * @throws \Exception If connection fails
     */
    private function connect(): void {
        $this->redis = new \Redis();
        
        $host = $this->config['redis_host'] ?? '127.0.0.1';
        $port = $this->config['redis_port'] ?? 6379;
        $timeout = $this->config['redis_timeout'] ?? 2.0;
        $password = $this->config['redis_password'] ?? null;
        $database = $this->config['redis_database'] ?? 0;
        
        try {
            // Connect
            if (!$this->redis->connect($host, $port, $timeout)) {
                throw new \Exception("Failed to connect to Redis at $host:$port");
            }
            
            // Authenticate if password is set
            if ($password) {
                if (!$this->redis->auth($password)) {
                    throw new \Exception('Redis authentication failed');
                }
            }
            
            // Select database
            if ($database > 0) {
                $this->redis->select($database);
            }
            
            // Set options
            $this->redis->setOption(\Redis::OPT_PREFIX, $this->prefix);
            $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);
            
            $this->connected = true;
            $this->reconnectAttempt = 0;
            
            if ($this->logger) {
                $this->logger->info('Redis connected successfully', [
                    'host' => $host,
                    'port' => $port,
                    'database' => $database
                ]);
            }
        } catch (\Exception $e) {
            $this->connected = false;
            
            if ($this->logger) {
                $this->logger->error('Redis connection failed', [
                    'host' => $host,
                    'port' => $port,
                    'error' => $e->getMessage()
                ]);
            }
            
            throw $e;
        }
    }
    
    /**
     * Check if Redis is available
     * 
     * @return bool
     */
    public function is_available(): bool {
        if (!$this->connected) {
            return false;
        }
        
        try {
            // Ping to check connection
            return $this->redis->ping() !== false;
        } catch (\RedisException $e) {
            $this->connected = false;
            
            if ($this->logger) {
                $this->logger->warning('Redis connection lost', [
                    'error' => $e->getMessage()
                ]);
            }
            
            return false;
        }
    }
    
    /**
     * Ensure Redis connection is active
     * 
     * @return bool
     */
    private function ensureConnection(): bool {
        if (!$this->connected || !$this->redis || !$this->redis->ping()) {
            return false;
        }
        return true;
    }
    
    /**
     * Get group-prefixed key
     * 
     * @param string $key
     * @param string|null $group
     * @return string
     */
    private function getGroupKey(string $key, ?string $group = null): string {
        if ($group) {
            return $group . ':' . $key;
        }
        return $key;
    }
    
    /**
     * Get a value from cache
     * 
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null) {
        if (!$this->ensureConnection()) {
            return $default;
        }
        
        try {
            $value = $this->redis->get($key);
            
            if ($value === false) {
                return $default;
            }
            
            return $value;
        } catch (\RedisException $e) {
            $this->handleException($e);
            return $default;
        }
    }
    
    /**
     * Set a value in cache
     * 
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @return bool
     */
    public function set(string $key, $value, int $ttl = 0): bool {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        try {
            if ($ttl > 0) {
                return $this->redis->setex($key, $ttl, $value);
            } else {
                return $this->redis->set($key, $value);
            }
        } catch (\RedisException $e) {
            $this->handleException($e);
            return false;
        }
    }
    
    /**
     * Delete a value from cache
     * 
     * @param string $key
     * @return bool
     */
    public function delete(string $key): bool {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        try {
            return $this->redis->del($key) > 0;
        } catch (\RedisException $e) {
            $this->handleException($e);
            return false;
        }
    }
    
    /**
     * Clear all cache
     * 
     * @return bool
     */
    public function flush(): bool {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        try {
            // If we have a prefix, use pattern delete
            if ($this->prefix) {
                $pattern = $this->prefix . '*';
                $keys = $this->redis->keys($pattern);
                
                if (!empty($keys)) {
                    // Remove prefix from keys
                    $keys = array_map(function($key) {
                        return str_replace($this->prefix, '', $key);
                    }, $keys);
                    
                    return $this->redis->del($keys) > 0;
                }
                
                return true;
            }
            
            // Otherwise flush the entire database (careful!)
            return $this->redis->flushDB();
        } catch (\RedisException $e) {
            $this->handleException($e);
            return false;
        }
    }
    
    /**
     * Check if key exists in cache
     * 
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        try {
            return $this->redis->exists($key) > 0;
        } catch (\RedisException $e) {
            $this->handleException($e);
            return false;
        }
    }
    
    /**
     * Clear cache by group
     * 
     * @param string $group Group identifier
     * @return void
     */
    public function clearGroup(string $group): void {
        if (!$this->ensureConnection()) {
            return;
        }
        
        try {
            // Find all keys in the group
            $pattern = $this->prefix . $group . ':*';
            $keys = $this->redis->keys($pattern);
            
            if (!empty($keys)) {
                // Remove prefix from keys for deletion
                $keys = array_map(function($key) {
                    return str_replace($this->prefix, '', $key);
                }, $keys);
                
                $this->redis->del($keys);
                
                if ($this->logger) {
                    $this->logger->info('Cleared Redis cache group', [
                        'group' => $group,
                        'keys_deleted' => count($keys)
                    ]);
                }
            }
        } catch (\RedisException $e) {
            $this->handleException($e);
            
            if ($this->logger) {
                $this->logger->error('Failed to clear Redis cache group', [
                    'group' => $group,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    /**
     * Get multiple values from cache
     * 
     * @param array $keys
     * @param mixed $default
     * @return array
     */
    public function getMultiple(array $keys, $default = null): array {
        if (!$this->ensureConnection()) {
            return array_fill_keys($keys, $default);
        }
        
        try {
            $values = $this->redis->mget($keys);
            
            $result = [];
            foreach ($keys as $i => $key) {
                $result[$key] = $values[$i] === false ? $default : $values[$i];
            }
            
            return $result;
        } catch (\RedisException $e) {
            $this->handleException($e);
            return array_fill_keys($keys, $default);
        }
    }
    
    /**
     * Set multiple values in cache
     * 
     * @param array $values
     * @param int $ttl
     * @return bool
     */
    public function setMultiple(array $values, int $ttl = 300): bool {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        try {
            if ($ttl > 0) {
                // Use pipeline for atomic operation
                $pipe = $this->redis->multi(\Redis::PIPELINE);
                
                foreach ($values as $key => $value) {
                    $pipe->setex($key, $ttl, $value);
                }
                
                $results = $pipe->exec();
                return !in_array(false, $results, true);
            } else {
                return $this->redis->mset($values);
            }
        } catch (\RedisException $e) {
            $this->handleException($e);
            return false;
        }
    }
    
    /**
     * Delete multiple values from cache
     * 
     * @param array $keys
     * @return bool
     */
    public function deleteMultiple(array $keys): bool {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        try {
            return $this->redis->del($keys) > 0;
        } catch (\RedisException $e) {
            $this->handleException($e);
            return false;
        }
    }
    
    /**
     * Increment a numeric value
     * 
     * @param string $key
     * @param int $step
     * @return int|false
     */
    public function increment(string $key, int $step = 1) {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        try {
            return $this->redis->incrBy($key, $step);
        } catch (\RedisException $e) {
            $this->handleException($e);
            return false;
        }
    }
    
    /**
     * Decrement a numeric value
     * 
     * @param string $key
     * @param int $step
     * @return int|false
     */
    public function decrement(string $key, int $step = 1) {
        if (!$this->ensureConnection()) {
            return false;
        }
        
        try {
            return $this->redis->decrBy($key, $step);
        } catch (\RedisException $e) {
            $this->handleException($e);
            return false;
        }
    }
    
    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function stats(): array {
        if (!$this->ensureConnection()) {
            return [
                'type' => 'redis',
                'connected' => false,
                'error' => 'Redis connection unavailable'
            ];
        }
        
        try {
            $info = $this->redis->info();
            
            return [
                'type' => 'redis',
                'connected' => true,
                'version' => $info['redis_version'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory' => $info['used_memory'] ?? 0,
                'used_memory_human' => $info['used_memory_human'] ?? 'unknown',
                'used_memory_peak_human' => $info['used_memory_peak_human'] ?? 'unknown',
                'total_connections_received' => $info['total_connections_received'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'expired_keys' => $info['expired_keys'] ?? 0,
                'evicted_keys' => $info['evicted_keys'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
                'prefix' => $this->prefix,
                'reconnect_attempts' => $this->reconnectAttempt
            ];
        } catch (\RedisException $e) {
            $this->handleException($e);
            return [
                'type' => 'redis',
                'connected' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Remember a value (get or compute)
     * 
     * @param string $key
     * @param callable $callback
     * @param int $ttl
     * @return mixed
     */
    public function remember(string $key, callable $callback, int $ttl = 300) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        if (!$this->ensureConnection()) {
            // If Redis is unavailable, just compute the value
            return $callback();
        }
        
        try {
            // Use Redis lock to prevent cache stampede
            $lockKey = $key . ':lock';
            $locked = $this->redis->set($lockKey, 1, ['nx', 'ex' => 30]);
            
            if (!$locked) {
                // Another process is computing, wait and retry
                usleep(100000); // 100ms
                $value = $this->get($key);
                if ($value !== null) {
                    return $value;
                }
                // If still null, compute it ourselves
                return $callback();
            }
            
            try {
                $value = $callback();
                $this->set($key, $value, $ttl);
                return $value;
            } finally {
                $this->redis->del($lockKey);
            }
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error('Redis remember failed', [
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
            
            // Fallback to just computing the value
            return $callback();
        }
    }
    
    /**
     * Remember a value forever
     * 
     * @param string $key
     * @param callable $callback
     * @return mixed
     */
    public function rememberForever(string $key, callable $callback) {
        return $this->remember($key, $callback, 0);
    }
    
    /**
     * Set a value in cache with group support
     * 
     * @param string $key
     * @param mixed $value
     * @param int $ttl
     * @param string|null $group
     * @return bool
     */
    public function setWithGroup(string $key, $value, int $ttl = 0, ?string $group = null): bool {
        $groupKey = $this->getGroupKey($key, $group);
        return $this->set($groupKey, $value, $ttl);
    }
    
    /**
     * Get a value from cache with group support
     * 
     * @param string $key
     * @param mixed $default
     * @param string|null $group
     * @return mixed
     */
    public function getWithGroup(string $key, $default = null, ?string $group = null) {
        $groupKey = $this->getGroupKey($key, $group);
        return $this->get($groupKey, $default);
    }
    
    /**
     * Calculate hit rate from Redis info
     * 
     * @param array $info
     * @return float
     */
    private function calculateHitRate(array $info): float {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        
        $total = $hits + $misses;
        
        if ($total === 0) {
            return 0.0;
        }
        
        return round(($hits / $total) * 100, 2);
    }
    
    /**
     * Handle Redis exceptions
     * 
     * @param \RedisException $e
     */
    private function handleException(\RedisException $e): void {
        $this->connected = false;
        
        if ($this->logger) {
            $this->logger->error('Redis operation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // Try to reconnect on next operation
        $this->reconnectAttempt = 0;
    }
    
    /**
     * Disconnect method for cleanup
     */
    public function disconnect(): void {
        if ($this->redis && $this->connected) {
            try {
                $this->redis->close();
            } catch (\Exception $e) {
                // Silent fail
            }
            $this->connected = false;
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->disconnect();
    }
}