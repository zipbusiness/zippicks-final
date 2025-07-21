<?php
/**
 * Validator Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Validation
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Validation;

use ZipPicks\Foundation\Validation\RuleInterface;

/**
 * Interface for validation services
 */
interface ValidatorInterface
{
    /**
     * Validate data against a set of rules
     * 
     * @param array<string, mixed> $data The data to validate
     * @param array<string, string|array<string>> $rules The validation rules
     * 
     * @return bool True if validation passes
     */
    public function validate(array $data, array $rules): bool;

    /**
     * Get validation errors from the last validation
     * 
     * @return array<string, array<string>> Field => array of error messages
     */
    public function errors(): array;

    /**
     * Check if validation has errors
     * 
     * @return bool
     */
    public function hasErrors(): bool;

    /**
     * Get the first error message for a field
     * 
     * @param string $field The field name
     * 
     * @return string|null The first error message or null if no errors
     */
    public function first(string $field): ?string;

    /**
     * Get all error messages as a flat array
     * 
     * @return array<string>
     */
    public function allErrors(): array;

    /**
     * Add a custom validation rule
     * 
     * @param string $name The rule name
     * @param RuleInterface $rule The rule implementation
     * 
     * @return void
     */
    public function addRule(string $name, RuleInterface $rule): void;

    /**
     * Check if a rule exists
     * 
     * @param string $name The rule name
     * 
     * @return bool
     */
    public function hasRule(string $name): bool;

    /**
     * Get validated data from the last validation
     * 
     * @return array<string, mixed>
     */
    public function validated(): array;

    /**
     * Set custom error messages
     * 
     * @param array<string, string> $messages Field.rule => message format
     * 
     * @return void
     */
    public function setMessages(array $messages): void;

    /**
     * Set custom attribute names
     * 
     * @param array<string, string> $attributes Field => display name
     * 
     * @return void
     */
    public function setAttributes(array $attributes): void;
}