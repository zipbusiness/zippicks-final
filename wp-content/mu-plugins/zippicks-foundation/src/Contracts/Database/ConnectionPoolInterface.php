<?php
/**
 * Database Connection Pool Interface
 *
 * @package ZipPicks\Foundation\Contracts\Database
 */

namespace ZipPicks\Foundation\Contracts\Database;

use PDO;

/**
 * Interface for database connection pooling
 */
interface ConnectionPoolInterface
{
    /**
     * Get a database connection
     *
     * @param string $type Connection type (read/write)
     * @param array $options Connection options
     * @return PDO
     */
    public function getConnection(string $type = 'read', array $options = []): PDO;

    /**
     * Release connection back to pool
     *
     * @param PDO $connection
     * @return void
     */
    public function releaseConnection(PDO $connection): void;

    /**
     * Execute query with automatic connection management
     *
     * @param callable $callback
     * @param string $type
     * @return mixed
     */
    public function execute(callable $callback, string $type = 'read');

    /**
     * Get pool statistics
     *
     * @return array
     */
    public function getStatistics(): array;

    /**
     * Close all connections
     *
     * @return void
     */
    public function closeAll(): void;
}