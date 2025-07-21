<?php
/**
 * Load Test Scenario Interface
 * 
 * @package ZipPicks\Foundation\Testing\Performance\Scenarios
 */

namespace ZipPicks\Foundation\Testing\Performance\Scenarios;

interface LoadTestScenarioInterface
{
    /**
     * Execute the load test scenario
     *
     * @param array $config
     * @return array
     */
    public function execute(array $config): array;

    /**
     * Get scenario name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get scenario description
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get default configuration
     *
     * @return array
     */
    public function getDefaultConfig(): array;

    /**
     * Validate scenario configuration
     *
     * @param array $config
     * @return array
     */
    public function validateConfig(array $config): array;
}