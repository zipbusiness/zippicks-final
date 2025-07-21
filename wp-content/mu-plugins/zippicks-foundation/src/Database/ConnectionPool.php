<?php
/**
 * Database Connection Pool for High Concurrency
 *
 * @package ZipPicks\Foundation\Database
 */

namespace ZipPicks\Foundation\Database;

use ZipPicks\Foundation\Contracts\Database\ConnectionPoolInterface;
use ZipPicks\Foundation\Logging\LoggerInterface;
use ZipPicks\Foundation\Observability\OpenTelemetryService;
use ZipPicks\Foundation\Exceptions\DatabaseException;
use PDO;
use PDOException;
use SplQueue;
use Exception;

/**
 * Enterprise-grade database connection pooling for WordPress
 * Supports read/write splitting and connection reuse
 */
class ConnectionPool implements ConnectionPoolInterface
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var OpenTelemetryService
     */
    protected $telemetry;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var SplQueue[]
     */
    protected $pools = [];

    /**
     * @var array
     */
    protected $activeConnections = [];

    /**
     * @var array
     */
    protected $connectionStats = [];

    /**
     * @var int
     */
    protected $totalConnections = 0;

    /**
     * @var array
     */
    protected $waitQueue = [];

    /**
     * @var float
     */
    protected $lastHealthCheck = 0;

    /**
     * Connection types
     */
    const TYPE_WRITE = 'write';
    const TYPE_READ = 'read';

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param OpenTelemetryService $telemetry
     * @param array $config
     */
    public function __construct(
        LoggerInterface $logger,
        OpenTelemetryService $telemetry,
        array $config = []
    ) {
        $this->logger = $logger;
        $this->telemetry = $telemetry;
        $this->config = array_merge($this->getDefaultConfig(), $config);
        
        $this->initialize();
    }

    /**
     * Get a database connection
     *
     * @param string $type Connection type (read/write)
     * @param array $options Connection options
     * @return PDO
     * @throws DatabaseException
     */
    public function getConnection(string $type = self::TYPE_READ, array $options = []): PDO
    {
        $span = $this->telemetry->startSpan('db_pool_get_connection', [
            'db.connection_type' => $type,
        ]);

        try {
            // Validate connection type
            if (!in_array($type, [self::TYPE_READ, self::TYPE_WRITE])) {
                throw new DatabaseException("Invalid connection type: {$type}");
            }

            // Check if we need to run health checks
            $this->performHealthCheckIfNeeded();

            // Try to get connection from pool
            $connection = $this->getFromPool($type);
            
            if ($connection && $this->isConnectionHealthy($connection)) {
                $this->telemetry->addEvent('connection_reused');
                return $connection;
            }

            // Create new connection if pool is empty or connection is unhealthy
            if ($this->canCreateNewConnection()) {
                $connection = $this->createConnection($type);
                $this->telemetry->addEvent('connection_created');
                return $connection;
            }

            // Wait for available connection
            $connection = $this->waitForConnection($type, $options['timeout'] ?? $this->config['wait_timeout']);
            
            if (!$connection) {
                throw new DatabaseException('Connection pool exhausted and timeout reached');
            }

            return $connection;

        } catch (Exception $e) {
            $this->telemetry->recordException($e);
            $this->logger->error('Failed to get database connection', [
                'type' => $type,
                'error' => $e->getMessage(),
                'active_connections' => $this->totalConnections,
            ]);
            throw $e;
        } finally {
            $this->telemetry->endSpan('db_pool_get_connection');
        }
    }

    /**
     * Release connection back to pool
     *
     * @param PDO $connection
     * @return void
     */
    public function releaseConnection(PDO $connection): void
    {
        $span = $this->telemetry->startSpan('db_pool_release_connection');

        try {
            $connectionId = spl_object_id($connection);
            
            if (!isset($this->activeConnections[$connectionId])) {
                $this->logger->warning('Attempting to release unknown connection');
                return;
            }

            $connectionInfo = $this->activeConnections[$connectionId];
            $type = $connectionInfo['type'];

            // Check if connection is still healthy
            if ($this->isConnectionHealthy($connection)) {
                // Reset connection state
                $this->resetConnection($connection);
                
                // Return to pool
                $this->pools[$type]->enqueue($connection);
                
                // Update stats
                $this->connectionStats[$type]['releases']++;
                
                $this->logger->debug('Connection released to pool', [
                    'type' => $type,
                    'pool_size' => $this->pools[$type]->count(),
                ]);
            } else {
                // Close unhealthy connection
                $this->closeConnection($connection);
            }

            // Remove from active connections
            unset($this->activeConnections[$connectionId]);

            // Check wait queue
            $this->processWaitQueue();

        } finally {
            $this->telemetry->endSpan('db_pool_release_connection');
        }
    }

    /**
     * Execute query with automatic connection management
     *
     * @param callable $callback
     * @param string $type
     * @return mixed
     * @throws DatabaseException
     */
    public function execute(callable $callback, string $type = self::TYPE_READ)
    {
        $connection = null;
        
        try {
            $connection = $this->getConnection($type);
            $result = $callback($connection);
            return $result;
        } finally {
            if ($connection) {
                $this->releaseConnection($connection);
            }
        }
    }

    /**
     * Get pool statistics
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $stats = [
            'total_connections' => $this->totalConnections,
            'active_connections' => count($this->activeConnections),
            'pools' => [],
            'wait_queue_size' => count($this->waitQueue),
        ];

        foreach ([self::TYPE_READ, self::TYPE_WRITE] as $type) {
            $stats['pools'][$type] = [
                'size' => $this->pools[$type]->count(),
                'created' => $this->connectionStats[$type]['created'] ?? 0,
                'reused' => $this->connectionStats[$type]['reused'] ?? 0,
                'releases' => $this->connectionStats[$type]['releases'] ?? 0,
                'failures' => $this->connectionStats[$type]['failures'] ?? 0,
            ];
        }

        return $stats;
    }

    /**
     * Close all connections
     *
     * @return void
     */
    public function closeAll(): void
    {
        $this->logger->info('Closing all database connections');

        // Close pooled connections
        foreach ($this->pools as $type => $pool) {
            while (!$pool->isEmpty()) {
                $connection = $pool->dequeue();
                $this->closeConnection($connection);
            }
        }

        // Close active connections
        foreach ($this->activeConnections as $info) {
            $this->closeConnection($info['connection']);
        }

        $this->activeConnections = [];
        $this->totalConnections = 0;
    }

    /**
     * Initialize connection pools
     *
     * @return void
     */
    protected function initialize(): void
    {
        $this->pools[self::TYPE_READ] = new SplQueue();
        $this->pools[self::TYPE_WRITE] = new SplQueue();

        $this->connectionStats = [
            self::TYPE_READ => ['created' => 0, 'reused' => 0, 'releases' => 0, 'failures' => 0],
            self::TYPE_WRITE => ['created' => 0, 'reused' => 0, 'releases' => 0, 'failures' => 0],
        ];

        // Pre-warm pools with minimum connections
        $this->warmPools();

        // Register shutdown handler
        register_shutdown_function([$this, 'closeAll']);
    }

    /**
     * Pre-warm connection pools
     *
     * @return void
     */
    protected function warmPools(): void
    {
        $this->logger->info('Pre-warming connection pools');

        try {
            // Create minimum connections for write pool
            for ($i = 0; $i < $this->config['min_connections']['write']; $i++) {
                $connection = $this->createConnection(self::TYPE_WRITE);
                $this->pools[self::TYPE_WRITE]->enqueue($connection);
            }

            // Create minimum connections for read pool
            for ($i = 0; $i < $this->config['min_connections']['read']; $i++) {
                $connection = $this->createConnection(self::TYPE_READ);
                $this->pools[self::TYPE_READ]->enqueue($connection);
            }

            $this->logger->info('Connection pools warmed', [
                'write_pool' => $this->pools[self::TYPE_WRITE]->count(),
                'read_pool' => $this->pools[self::TYPE_READ]->count(),
            ]);

        } catch (Exception $e) {
            $this->logger->error('Failed to warm connection pools', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get connection from pool
     *
     * @param string $type
     * @return PDO|null
     */
    protected function getFromPool(string $type): ?PDO
    {
        if ($this->pools[$type]->isEmpty()) {
            return null;
        }

        $connection = $this->pools[$type]->dequeue();
        $connectionId = spl_object_id($connection);

        // Track active connection
        $this->activeConnections[$connectionId] = [
            'connection' => $connection,
            'type' => $type,
            'acquired_at' => microtime(true),
        ];

        $this->connectionStats[$type]['reused']++;

        return $connection;
    }

    /**
     * Create new database connection
     *
     * @param string $type
     * @return PDO
     * @throws DatabaseException
     */
    protected function createConnection(string $type): PDO
    {
        $config = $this->getConnectionConfig($type);
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                PDO::ATTR_PERSISTENT => $this->config['persistent_connections'],
            ];

            // Add SSL options if configured
            if (!empty($config['ssl'])) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $config['ssl']['ca'];
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $config['ssl']['verify'];
            }

            $connection = new PDO($dsn, $config['username'], $config['password'], $options);

            // Set connection attributes
            $connection->setAttribute(PDO::ATTR_TIMEOUT, $this->config['connection_timeout']);
            
            // Set session variables
            $this->initializeConnection($connection, $type);

            $this->totalConnections++;
            $this->connectionStats[$type]['created']++;

            $connectionId = spl_object_id($connection);
            $this->activeConnections[$connectionId] = [
                'connection' => $connection,
                'type' => $type,
                'acquired_at' => microtime(true),
            ];

            $this->logger->debug('Created new database connection', [
                'type' => $type,
                'total_connections' => $this->totalConnections,
            ]);

            return $connection;

        } catch (PDOException $e) {
            $this->connectionStats[$type]['failures']++;
            throw new DatabaseException(
                "Failed to create database connection: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get connection configuration
     *
     * @param string $type
     * @return array
     */
    protected function getConnectionConfig(string $type): array
    {
        if ($type === self::TYPE_WRITE) {
            return $this->config['connections']['write'];
        }

        // For read connections, use load balancing
        $readConfigs = $this->config['connections']['read'];
        
        if (count($readConfigs) === 1) {
            return $readConfigs[0];
        }

        // Simple round-robin load balancing
        static $readIndex = 0;
        $config = $readConfigs[$readIndex % count($readConfigs)];
        $readIndex++;

        return $config;
    }

    /**
     * Initialize connection with session settings
     *
     * @param PDO $connection
     * @param string $type
     * @return void
     */
    protected function initializeConnection(PDO $connection, string $type): void
    {
        // Set timezone
        $connection->exec("SET time_zone = '+00:00'");

        // Set SQL mode
        $connection->exec("SET sql_mode = 'TRADITIONAL'");

        // Set transaction isolation level
        if ($type === self::TYPE_WRITE) {
            $connection->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED");
        } else {
            $connection->exec("SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED");
        }

        // Set connection character set
        $connection->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    /**
     * Check if connection is healthy
     *
     * @param PDO $connection
     * @return bool
     */
    protected function isConnectionHealthy(PDO $connection): bool
    {
        try {
            $stmt = $connection->query('SELECT 1');
            $stmt->closeCursor();
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Reset connection state
     *
     * @param PDO $connection
     * @return void
     */
    protected function resetConnection(PDO $connection): void
    {
        try {
            // Rollback any open transactions
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            // Reset session variables
            $connection->exec("SET SESSION sql_mode = DEFAULT");
            $connection->exec("SET SESSION time_zone = DEFAULT");

            // Clear any temporary tables
            $connection->exec("DROP TEMPORARY TABLE IF EXISTS tmp_*");

        } catch (PDOException $e) {
            $this->logger->warning('Failed to reset connection', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Close database connection
     *
     * @param PDO $connection
     * @return void
     */
    protected function closeConnection(PDO $connection): void
    {
        try {
            // PDO doesn't have explicit close, nullify to trigger destructor
            $connection = null;
            $this->totalConnections--;
        } catch (Exception $e) {
            $this->logger->error('Error closing connection', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if we can create new connection
     *
     * @return bool
     */
    protected function canCreateNewConnection(): bool
    {
        return $this->totalConnections < $this->config['max_connections'];
    }

    /**
     * Wait for available connection
     *
     * @param string $type
     * @param int $timeout
     * @return PDO|null
     */
    protected function waitForConnection(string $type, int $timeout): ?PDO
    {
        $waitId = uniqid('wait_', true);
        $startTime = microtime(true);

        $this->waitQueue[$waitId] = [
            'type' => $type,
            'started_at' => $startTime,
        ];

        $this->logger->warning('Connection pool exhausted, waiting for connection', [
            'type' => $type,
            'wait_queue_size' => count($this->waitQueue),
        ]);

        while ((microtime(true) - $startTime) < $timeout) {
            // Check if connection is available
            if (!$this->pools[$type]->isEmpty()) {
                unset($this->waitQueue[$waitId]);
                return $this->getFromPool($type);
            }

            // Sleep briefly
            usleep(10000); // 10ms
        }

        unset($this->waitQueue[$waitId]);
        
        $this->logger->error('Connection wait timeout exceeded', [
            'type' => $type,
            'timeout' => $timeout,
        ]);

        return null;
    }

    /**
     * Process wait queue
     *
     * @return void
     */
    protected function processWaitQueue(): void
    {
        if (empty($this->waitQueue)) {
            return;
        }

        // Notify waiting threads (in real implementation)
        $this->logger->debug('Processing wait queue', [
            'queue_size' => count($this->waitQueue),
        ]);
    }

    /**
     * Perform health check if needed
     *
     * @return void
     */
    protected function performHealthCheckIfNeeded(): void
    {
        $now = microtime(true);
        
        if (($now - $this->lastHealthCheck) < $this->config['health_check_interval']) {
            return;
        }

        $this->lastHealthCheck = $now;
        
        $span = $this->telemetry->startSpan('db_pool_health_check');

        try {
            foreach ($this->pools as $type => $pool) {
                $healthy = [];
                $unhealthy = 0;

                while (!$pool->isEmpty()) {
                    $connection = $pool->dequeue();
                    
                    if ($this->isConnectionHealthy($connection)) {
                        $healthy[] = $connection;
                    } else {
                        $this->closeConnection($connection);
                        $unhealthy++;
                    }
                }

                // Return healthy connections to pool
                foreach ($healthy as $connection) {
                    $pool->enqueue($connection);
                }

                if ($unhealthy > 0) {
                    $this->logger->warning('Removed unhealthy connections', [
                        'type' => $type,
                        'count' => $unhealthy,
                    ]);
                }
            }
        } finally {
            $this->telemetry->endSpan('db_pool_health_check');
        }
    }

    /**
     * Get default configuration
     *
     * @return array
     */
    protected function getDefaultConfig(): array
    {
        return [
            'max_connections' => 100,
            'min_connections' => [
                'read' => 10,
                'write' => 5,
            ],
            'connection_timeout' => 5,
            'wait_timeout' => 30,
            'idle_timeout' => 300,
            'health_check_interval' => 60,
            'persistent_connections' => false,
            'connections' => [
                'write' => [
                    'host' => DB_HOST,
                    'port' => 3306,
                    'database' => DB_NAME,
                    'username' => DB_USER,
                    'password' => DB_PASSWORD,
                    'charset' => 'utf8mb4',
                ],
                'read' => [
                    [
                        'host' => defined('DB_READ_HOST_1') ? DB_READ_HOST_1 : DB_HOST,
                        'port' => 3306,
                        'database' => DB_NAME,
                        'username' => DB_USER,
                        'password' => DB_PASSWORD,
                        'charset' => 'utf8mb4',
                    ],
                ],
            ],
        ];
    }
}