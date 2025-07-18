<?php
/**
 * Minimum Length Validation Rule
 * 
 * @package ZipPicks\Foundation\Validation\Rules
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Validation\Rules;

use ZipPicks\Foundation\Validation\RuleInterface;

/**
 * Minimum length validation rule
 */
class MinLengthRule implements RuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $parameters, array $data, string $field): bool
    {
        // Allow empty values (use required rule to enforce presence)
        if (is_null($value) || $value === '') {
            return true;
        }

        // Get minimum length parameter
        $minLength = isset($parameters[0]) ? (int) $parameters[0] : 0;

        if (is_string($value)) {
            return mb_strlen($value) >= $minLength;
        }

        if (is_array($value)) {
            return count($value) >= $minLength;
        }

        // For numeric values, convert to string
        if (is_numeric($value)) {
            return mb_strlen((string) $value) >= $minLength;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function message(string $field, array $parameters): string
    {
        $minLength = isset($parameters[0]) ? $parameters[0] : 0;
        return "The {$field} must be at least {$minLength} characters.";
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'min_length';
    }
}