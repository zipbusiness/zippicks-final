<?php
/**
 * Custom Validation Rule
 *
 * @package ZipPicks\Foundation\Validation\Rules
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Validation\Rules;

use Closure;
use ZipPicks\Foundation\Validation\RuleInterface;

/**
 * Example of a custom validation rule with closure support
 *
 * This demonstrates how to create custom validation rules
 * that can be added to the validator at runtime.
 *
 * @since 1.0.0
 */
class CustomRule implements RuleInterface
{
    /**
     * Rule name
     *
     * @var string
     */
    protected string $name;

    /**
     * Validation closure
     *
     * @var Closure
     */
    protected Closure $validator;

    /**
     * Message closure or string
     *
     * @var Closure|string
     */
    protected Closure|string $messageHandler;

    /**
     * Constructor
     *
     * @param string $name Rule name
     * @param Closure $validator Validation closure (value, parameters, data, field) => bool
     * @param Closure|string $message Message closure (field, parameters) => string or static string
     */
    public function __construct(string $name, Closure $validator, Closure|string $message = '')
    {
        $this->name = $name;
        $this->validator = $validator;
        $this->messageHandler = $message ?: function($field, $parameters) use ($name) {
            return "The {$field} field failed the {$name} validation.";
        };
    }

    /**
     * {@inheritdoc}
     */
    public function validate(mixed $value, array $parameters, array $data, string $field): bool
    {
        return ($this->validator)($value, $parameters, $data, $field);
    }

    /**
     * {@inheritdoc}
     */
    public function message(string $field, array $parameters): string
    {
        if (is_string($this->messageHandler)) {
            return $this->messageHandler;
        }

        return ($this->messageHandler)($field, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Example: Create an "in" rule
     *
     * Usage: 'status' => 'in:active,pending,completed'
     *
     * @return static
     */
    public static function createInRule(): static
    {
        $validator = function($value, $parameters, $data, $field) {
            if (empty($parameters)) {
                return false;
            }
            
            // Parameters come as array from the validator
            $allowedValues = $parameters;
            return in_array($value, $allowedValues, true);
        };

        $message = function($field, $parameters) {
            $values = implode(', ', $parameters);
            return "The {$field} must be one of: {$values}.";
        };

        return new static('in', $validator, $message);
    }

    /**
     * Example: Create a "confirmed" rule
     *
     * Usage: 'password' => 'confirmed' (checks password_confirmation)
     *
     * @return static
     */
    public static function createConfirmedRule(): static
    {
        $validator = function($value, $parameters, $data, $field) {
            $confirmField = $field . '_confirmation';
            return isset($data[$confirmField]) && $value === $data[$confirmField];
        };

        $message = function($field, $parameters) {
            return "The {$field} confirmation does not match.";
        };

        return new static('confirmed', $validator, $message);
    }

    /**
     * Example: Create a "different" rule
     *
     * Usage: 'email' => 'different:username'
     *
     * @return static
     */
    public static function createDifferentRule(): static
    {
        $validator = function($value, $parameters, $data, $field) {
            if (empty($parameters[0])) {
                return false;
            }
            
            $otherField = $parameters[0];
            return !isset($data[$otherField]) || $value !== $data[$otherField];
        };

        $message = function($field, $parameters) {
            $otherField = $parameters[0] ?? 'other field';
            return "The {$field} must be different from {$otherField}.";
        };

        return new static('different', $validator, $message);
    }

    /**
     * Example: Create a "regex" rule
     *
     * Usage: 'username' => 'regex:/^[a-zA-Z0-9_]+$/'
     *
     * @return static
     */
    public static function createRegexRule(): static
    {
        $validator = function($value, $parameters, $data, $field) {
            if (!is_string($value) || empty($parameters[0])) {
                return false;
            }
            
            $pattern = $parameters[0];
            return preg_match($pattern, $value) === 1;
        };

        $message = function($field, $parameters) {
            return "The {$field} format is invalid.";
        };

        return new static('regex', $validator, $message);
    }
}