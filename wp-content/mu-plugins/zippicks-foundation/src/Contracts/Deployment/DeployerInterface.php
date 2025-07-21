<?php
/**
 * Deployment Interface
 *
 * @package ZipPicks\Foundation\Contracts\Deployment
 */

namespace ZipPicks\Foundation\Contracts\Deployment;

use Exception;

/**
 * Interface for deployment strategies
 */
interface DeployerInterface
{
    /**
     * Execute deployment
     *
     * @param string $version Version to deploy
     * @param array $options Deployment options
     * @return array Deployment result
     * @throws Exception
     */
    public function deploy(string $version, array $options = []): array;

    /**
     * Rollback to previous deployment
     *
     * @return bool
     */
    public function rollback(): bool;

    /**
     * Get deployment status
     *
     * @param string $deploymentId
     * @return array
     */
    public function getStatus(string $deploymentId): array;
}