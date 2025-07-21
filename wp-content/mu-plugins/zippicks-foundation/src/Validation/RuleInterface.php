<?php
/**
 * Validation Rule Interface
 * 
 * @package ZipPicks\Foundation\Validation
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Validation;

/**
 * Interface for validation rules
 */
interface RuleInterface
{
    /**
     * Validate the given value
     * 
     * @param mixed $value The value to validate
     * @param array<string, mixed> $parameters Rule parameters
     * @param array<string, mixed> $data All validation data
     * @param string $field The field being validated
     * 
     * @return bool True if validation passes
     */
    public function validate(mixed $value, array $parameters, array $data, string $field): bool;

    /**
     * Get the validation error message
     * 
     * @param string $field The field name
     * @param array<string, mixed> $parameters Rule parameters
     * 
     * @return string The error message
     */
    public function message(string $field, array $parameters): string;

    /**
     * Get the rule name
     * 
     * @return string
     */
    public function getName(): string;
}