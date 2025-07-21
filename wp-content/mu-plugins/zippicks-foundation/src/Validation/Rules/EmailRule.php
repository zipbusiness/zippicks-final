<?php
/**
 * Email Validation Rule
 * 
 * @package ZipPicks\Foundation\Validation\Rules
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Validation\Rules;

use ZipPicks\Foundation\Validation\RuleInterface;

/**
 * Email format validation rule
 */
class EmailRule implements RuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $parameters, array $data, string $field): bool
    {
        if (!is_string($value)) {
            return false;
        }

        // Allow empty values (use required rule to enforce presence)
        if ($value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function message(string $field, array $parameters): string
    {
        return "The {$field} must be a valid email address.";
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'email';
    }
}