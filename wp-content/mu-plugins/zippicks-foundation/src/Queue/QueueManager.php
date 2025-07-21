<?php
/**
 * Queue Manager
 * 
 * Central orchestrator for all queue operations in the ZipPicks platform.
 * Manages multiple queue drivers and provides a unified interface.
 * 
 * @package ZipPicks\Foundation\Queue
 * @since 2.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Queue;

use ZipPicks\Foundation\Contracts\Queue\QueueManagerInterface;
use ZipPicks\Foundation\Contracts\Queue\QueueDriverInterface;
use ZipPicks\Foundation\Contracts\Queue\JobInterface;
use ZipPicks\Foundation\Contracts\Queue\JobBatchInterface;
use ZipPicks\Foundation\Contracts\Queue\JobChainInterface;
use ZipPicks\Foundation\Contracts\Queue\FailedJobProviderInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Core\Container;
use Closure;
use InvalidArgumentException;

/**
 * Queue Manager
 * 
 * Manages queue connections and dispatches jobs
 */
class QueueManager implements QueueManagerInterface
{
    /**
     * Container instance
     */
    protected Container $container;
    
    /**
     * Configuration array
     */
    protected array $config;
    
    /**
     * Logger instance
     */
    protected ?LoggerInterface $logger;
    
    /**
     * Array of resolved queue connections
     */
    protected array $connections = [];
    
    /**
     * Array of queue connectors
     */
    protected array $connectors = [];
    
    /**
     * Failed job provider
     */
    protected ?FailedJobProviderInterface $failedJobProvider = null;
    
    /**
     * Create a new queue manager
     * 
     * @param Container $container Container instance
     * @param array $config Queue configuration
     * @param LoggerInterface|null $logger Logger instance
     */
    public function __construct(
        Container $container,
        array $config,
        ?LoggerInterface $logger = null
    ) {
        $this->container = $container;
        $this->config = $config;
        $this->logger = $logger;
        
        $this->registerDefaultConnectors();
    }
    
    /**
     * {@inheritdoc}
     */
    public function connection(?string $name = null): QueueDriverInterface
    {
        $name = $name ?: $this->getDefaultConnection();
        
        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }
        
        return $this->connections[$name];
    }
    
    /**
     * {@inheritdoc}
     */
    public function push($job, $data = '', ?string $queue = null)
    {
        return $this->connection()->push($job, $data, $queue);
    }
    
    /**
     * {@inheritdoc}
     */
    public function later($delay, $job, $data = '', ?string $queue = null)
    {
        return $this->connection()->later($delay, $job, $data, $queue);
    }
    
    /**
     * {@inheritdoc}
     */
    public function pushOn(string $queue, $job, $data = '')
    {
        return $this->connection()->push($job, $data, $queue);
    }
    
    /**
     * {@inheritdoc}
     */
    public function laterOn(string $queue, $delay, $job, $data = '')
    {
        return $this->connection()->later($delay, $job, $data, $queue);
    }
    
    /**
     * {@inheritdoc}
     */
    public function bulk(array $jobs, $data = '', ?string $queue = null): void
    {
        $this->connection()->bulk($jobs, $data, $queue);
    }
    
    /**
     * {@inheritdoc}
     */
    public function batch(array $jobs): JobBatchInterface
    {
        return new JobBatch($this, $jobs, $this->logger);
    }
    
    /**
     * {@inheritdoc}
     */
    public function chain(array $jobs): JobChainInterface
    {
        return new JobChain($this, $jobs, $this->logger);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getConnectionName(?string $queue = null): string
    {
        return $queue ? $this->getQueueConnection($queue) : $this->getDefaultConnection();
    }
    
    /**
     * {@inheritdoc}
     */
    public function addConnector(string $driver, Closure $resolver): void
    {
        $this->connectors[$driver] = $resolver;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getConnections(): array
    {
        return $this->connections;
    }
    
    /**
     * {@inheritdoc}
     */
    public function setDefaultConnection(string $name): void
    {
        $this->config['default'] = $name;
    }
    
    /**
     * {@inheritdoc}
     */
    public function getDefaultConnection(): string
    {
        return $this->config['default'] ?? 'sync';
    }
    
    /**
     * {@inheritdoc}
     */
    public function getStatistics(?string $connection = null, ?string $queue = null): array
    {
        $driver = $this->connection($connection);
        $metadata = $driver->getMetadata($queue);
        
        // Calculate additional statistics
        $throughput = $this->calculateThroughput($connection, $queue);
        $latency = $this->calculateLatency($connection, $queue);
        
        return array_merge($metadata, [
            'throughput' => $throughput,
            'latency' => $latency,
            'failed' => $this->getFailedJobCount($connection, $queue)
        ]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function clear(string $queue, ?string $connection = null): int
    {
        return $this->connection($connection)->clear($queue);
    }
    
    /**
     * {@inheritdoc}
     */
    public function getFailedJobRepository(): FailedJobProviderInterface
    {
        if (!$this->failedJobProvider) {
            $this->failedJobProvider = $this->createFailedJobProvider();
        }
        
        return $this->failedJobProvider;
    }
    
    /**
     * {@inheritdoc}
     */
    public function connected(string $name): bool
    {
        return isset($this->connections[$name]);
    }
    
    /**
     * {@inheritdoc}
     */
    public function disconnect(?string $name = null): void
    {
        $name = $name ?: $this->getDefaultConnection();
        
        unset($this->connections[$name]);
    }
    
    /**
     * Resolve a queue connection
     * 
     * @param string $name Connection name
     * @return QueueDriverInterface
     * @throws InvalidArgumentException
     */
    protected function resolve(string $name): QueueDriverInterface
    {
        $config = $this->getConfig($name);
        
        if (!isset($config['driver'])) {
            throw new InvalidArgumentException("Queue connection [{$name}] not configured.");
        }
        
        $driver = $config['driver'];
        
        if (!isset($this->connectors[$driver])) {
            throw new InvalidArgumentException("Queue driver [{$driver}] not supported.");
        }
        
        $this->logger?->info('Resolving queue connection', [
            'connection' => $name,
            'driver' => $driver
        ]);
        
        return call_user_func($this->connectors[$driver], $config, $name);
    }
    
    /**
     * Get the connector configuration
     * 
     * @param string $name Connection name
     * @return array
     */
    protected function getConfig(string $name): array
    {
        if (!isset($this->config['connections'][$name])) {
            throw new InvalidArgumentException("Queue connection [{$name}] not configured.");
        }
        
        return $this->config['connections'][$name];
    }
    
    /**
     * Get queue connection name
     * 
     * @param string $queue Queue name
     * @return string
     */
    protected function getQueueConnection(string $queue): string
    {
        // Check if specific connection is configured for this queue
        $queueConfig = $this->config['queue_connections'] ?? [];
        
        return $queueConfig[$queue] ?? $this->getDefaultConnection();
    }
    
    /**
     * Register default queue connectors
     * 
     * @return void
     */
    protected function registerDefaultConnectors(): void
    {
        // Sync connector
        $this->addConnector('sync', function ($config, $name) {
            return new \ZipPicks\Foundation\Queue\Drivers\SyncQueue(
                $this->logger
            );
        });
        
        // Database connector
        $this->addConnector('database', function ($config, $name) {
            global $wpdb;
            
            return new \ZipPicks\Foundation\Queue\Drivers\DatabaseQueue(
                $wpdb,
                $wpdb->prefix . ($config['table'] ?? 'zippicks_jobs'),
                $config['queue'] ?? 'default',
                $config['retry_after'] ?? 90,
                $name,
                $this->logger,
                $this->container->has('circuit_breaker') 
                    ? $this->container->get('circuit_breaker') 
                    : null
            );
        });
        
        // WordPress connector (using Action Scheduler)
        $this->addConnector('wordpress', function ($config, $name) {
            return new \ZipPicks\Foundation\Queue\Drivers\WordPressQueue(
                $config['queue'] ?? 'default',
                $this->logger,
                $this->container->has('circuit_breaker') 
                    ? $this->container->get('circuit_breaker') 
                    : null
            );
        });
    }
    
    /**
     * Create failed job provider
     * 
     * @return FailedJobProviderInterface
     */
    protected function createFailedJobProvider(): FailedJobProviderInterface
    {
        $config = $this->config['failed'] ?? [];
        $driver = $config['driver'] ?? 'database';
        
        if ($driver === 'database') {
            global $wpdb;
            
            return new \ZipPicks\Foundation\Queue\Failed\DatabaseFailedJobProvider(
                $wpdb,
                $wpdb->prefix . ($config['table'] ?? 'zippicks_failed_jobs'),
                $this->logger
            );
        }
        
        throw new InvalidArgumentException("Failed job driver [{$driver}] not supported.");
    }
    
    /**
     * Calculate queue throughput
     * 
     * @param string|null $connection Connection name
     * @param string|null $queue Queue name
     * @return float Jobs per minute
     */
    protected function calculateThroughput(?string $connection, ?string $queue): float
    {
        // This would query metrics to calculate throughput
        // For now, return a placeholder
        return 0.0;
    }
    
    /**
     * Calculate queue latency
     * 
     * @param string|null $connection Connection name
     * @param string|null $queue Queue name
     * @return float Average latency in ms
     */
    protected function calculateLatency(?string $connection, ?string $queue): float
    {
        // This would query metrics to calculate latency
        // For now, return a placeholder
        return 0.0;
    }
    
    /**
     * Get failed job count
     * 
     * @param string|null $connection Connection name
     * @param string|null $queue Queue name
     * @return int
     */
    protected function getFailedJobCount(?string $connection, ?string $queue): int
    {
        try {
            return $this->getFailedJobRepository()->count($queue);
        } catch (\Throwable $e) {
            return 0;
        }
    }
    
    /**
     * Dispatch a job using global helper
     * 
     * @param JobInterface $job Job to dispatch
     * @return mixed Job ID
     */
    public function dispatch(JobInterface $job)
    {
        if (method_exists($job, 'setQueueManager')) {
            $job->setQueueManager($this);
        }
        
        $connection = $job->connection();
        $queue = $job->queue();
        $delay = $job->delay();
        
        if ($delay !== null && $delay > 0) {
            return $this->connection($connection)->later($delay, $job, '', $queue);
        }
        
        return $this->connection($connection)->push($job, '', $queue);
    }
}