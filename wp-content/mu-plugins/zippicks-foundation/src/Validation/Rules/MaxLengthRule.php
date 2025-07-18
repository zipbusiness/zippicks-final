<?php
/**
 * Max Length Validation Rule
 *
 * @package ZipPicks\Foundation\Validation\Rules
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Validation\Rules;

use ZipPicks\Foundation\Validation\RuleInterface;

/**
 * Validates maximum length/size/value
 *
 * @since 1.0.0
 */
class MaxLengthRule implements RuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $parameters, array $data, string $field): bool
    {
        // Allow empty values (use 'required' rule to enforce presence)
        if ($value === null || $value === '') {
            return true;
        }

        // Get maximum length parameter
        $max = isset($parameters[0]) ? (int) $parameters[0] : PHP_INT_MAX;

        // String validation
        if (is_string($value)) {
            return mb_strlen($value) <= $max;
        }

        // Array validation
        if (is_array($value)) {
            return count($value) <= $max;
        }

        // Numeric validation
        if (is_numeric($value)) {
            return (float) $value <= $max;
        }

        // File size validation (if value is array with 'size' key)
        if (is_array($value) && isset($value['size'])) {
            return (int) $value['size'] <= $max;
        }

        // Other types fail validation
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function message(string $field, array $parameters): string
    {
        $max = isset($parameters[0]) ? (int) $parameters[0] : PHP_INT_MAX;
        return "The {$field} must not exceed {$max}.";
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'max_length';
    }
}