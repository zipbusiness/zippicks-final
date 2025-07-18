<?php
/**
 * Required Validation Rule
 * 
 * @package ZipPicks\Foundation\Validation\Rules
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Validation\Rules;

use ZipPicks\Foundation\Validation\RuleInterface;

/**
 * Required field validation rule
 */
class RequiredRule implements RuleInterface
{
    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $parameters, array $data, string $field): bool
    {
        if (is_null($value)) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && count($value) === 0) {
            return false;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function message(string $field, array $parameters): string
    {
        return "The {$field} field is required.";
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'required';
    }
}