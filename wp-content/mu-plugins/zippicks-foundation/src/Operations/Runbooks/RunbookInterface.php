<?php
/**
 * Runbook Interface
 * 
 * @package ZipPicks\Foundation\Operations\Runbooks
 */

namespace ZipPicks\Foundation\Operations\Runbooks;

interface RunbookInterface
{
    /**
     * Get runbook name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get runbook description
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get runbook category
     *
     * @return string
     */
    public function getCategory(): string;

    /**
     * Get runbook criticality level
     *
     * @return string
     */
    public function getCriticality(): string;

    /**
     * Get estimated duration in minutes
     *
     * @return int
     */
    public function getEstimatedDuration(): int;

    /**
     * Get runbook steps
     *
     * @return array
     */
    public function getSteps(): array;

    /**
     * Get rollback steps
     *
     * @return array
     */
    public function getRollbackSteps(): array;

    /**
     * Get prerequisites
     *
     * @return array
     */
    public function getPrerequisites(): array;

    /**
     * Get expected outcomes
     *
     * @return array
     */
    public function getExpectedOutcomes(): array;

    /**
     * Validate context for execution
     *
     * @param array $context
     * @return array
     */
    public function validateContext(array $context): array;
}