<?php
/**
 * Audit Logger Interface
 *
 * @package ZipPicks\Foundation\Contracts\Audit
 */

namespace ZipPicks\Foundation\Contracts\Audit;

/**
 * Interface for audit logging
 */
interface AuditLoggerInterface
{
    /**
     * Log audit event
     *
     * @param string $event
     * @param array $context
     * @param string|null $userId
     * @return bool
     */
    public function log(string $event, array $context = [], ?string $userId = null): bool;

    /**
     * Log authentication attempt
     *
     * @param string $username
     * @param bool $success
     * @param array $context
     * @return bool
     */
    public function logAuth(string $username, bool $success, array $context = []): bool;

    /**
     * Log API key operation
     *
     * @param string $operation
     * @param string $keyId
     * @param array $context
     * @return bool
     */
    public function logApiKey(string $operation, string $keyId, array $context = []): bool;

    /**
     * Log permission change
     *
     * @param string $action
     * @param string $permission
     * @param string $targetUserId
     * @param array $context
     * @return bool
     */
    public function logPermission(string $action, string $permission, string $targetUserId, array $context = []): bool;

    /**
     * Log data operation
     *
     * @param string $operation
     * @param string $dataType
     * @param array $context
     * @return bool
     */
    public function logDataOperation(string $operation, string $dataType, array $context = []): bool;

    /**
     * Query audit log
     *
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function query(array $filters = [], int $limit = 100, int $offset = 0): array;

    /**
     * Get audit statistics
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array;

    /**
     * Cleanup old audit logs
     *
     * @param int $daysToKeep
     * @return int
     */
    public function cleanup(int $daysToKeep = 90): int;
}