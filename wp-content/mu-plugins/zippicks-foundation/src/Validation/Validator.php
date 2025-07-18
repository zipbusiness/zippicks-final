<?php
/**
 * Validator Implementation
 * 
 * @package ZipPicks\Foundation\Validation
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Validation;

use ZipPicks\Foundation\Contracts\Validation\ValidatorInterface;
use ZipPicks\Foundation\Validation\Rules\RequiredRule;
use ZipPicks\Foundation\Validation\Rules\EmailRule;
use ZipPicks\Foundation\Validation\Rules\MinLengthRule;
use ZipPicks\Foundation\Validation\Rules\MaxLengthRule;

/**
 * Core validator implementation
 */
class Validator implements ValidatorInterface
{
    /**
     * Registered validation rules
     * 
     * @var array<string, RuleInterface>
     */
    protected array $rules = [];

    /**
     * Validation errors
     * 
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    /**
     * Validated data from last validation
     * 
     * @var array<string, mixed>
     */
    protected array $validatedData = [];

    /**
     * Custom error messages
     * 
     * @var array<string, string>
     */
    protected array $customMessages = [];

    /**
     * Custom attribute names
     * 
     * @var array<string, string>
     */
    protected array $customAttributes = [];

    /**
     * Create a new validator instance
     */
    public function __construct()
    {
        $this->registerDefaultRules();
    }

    /**
     * {@inheritdoc}
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];
        $this->validatedData = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $this->validateField($field, $value, $fieldRules, $data);
            
            // Add to validated data if no errors
            if (!isset($this->errors[$field])) {
                $this->validatedData[$field] = $value;
            }
        }

        return empty($this->errors);
    }

    /**
     * {@inheritdoc}
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * {@inheritdoc}
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * {@inheritdoc}
     */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function allErrors(): array
    {
        $allErrors = [];
        foreach ($this->errors as $fieldErrors) {
            $allErrors = array_merge($allErrors, $fieldErrors);
        }
        return $allErrors;
    }

    /**
     * {@inheritdoc}
     */
    public function addRule(string $name, RuleInterface $rule): void
    {
        $this->rules[$name] = $rule;
    }

    /**
     * {@inheritdoc}
     */
    public function hasRule(string $name): bool
    {
        return isset($this->rules[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * {@inheritdoc}
     */
    public function setMessages(array $messages): void
    {
        $this->customMessages = $messages;
    }

    /**
     * {@inheritdoc}
     */
    public function setAttributes(array $attributes): void
    {
        $this->customAttributes = $attributes;
    }

    /**
     * Validate a single field
     * 
     * @param string $field
     * @param mixed $value
     * @param string|array<string> $rules
     * @param array<string, mixed> $data
     * 
     * @return void
     */
    protected function validateField(string $field, mixed $value, string|array $rules, array $data): void
    {
        $rules = is_string($rules) ? explode('|', $rules) : $rules;

        foreach ($rules as $rule) {
            $this->applyRule($field, $value, $rule, $data);
        }
    }

    /**
     * Apply a single rule to a field
     * 
     * @param string $field
     * @param mixed $value
     * @param string $rule
     * @param array<string, mixed> $data
     * 
     * @return void
     */
    protected function applyRule(string $field, mixed $value, string $rule, array $data): void
    {
        // Parse rule and parameters
        [$ruleName, $parameters] = $this->parseRule($rule);

        if (!isset($this->rules[$ruleName])) {
            throw new \InvalidArgumentException("Validation rule '{$ruleName}' does not exist.");
        }

        $ruleInstance = $this->rules[$ruleName];

        if (!$ruleInstance->validate($value, $parameters, $data, $field)) {
            $this->addError($field, $ruleName, $parameters);
        }
    }

    /**
     * Parse a rule string into name and parameters
     * 
     * @param string $rule
     * 
     * @return array{0: string, 1: array<string>}
     */
    protected function parseRule(string $rule): array
    {
        if (strpos($rule, ':') === false) {
            return [$rule, []];
        }

        [$name, $paramString] = explode(':', $rule, 2);
        $parameters = explode(',', $paramString);

        return [$name, $parameters];
    }

    /**
     * Add an error message
     * 
     * @param string $field
     * @param string $ruleName
     * @param array<string> $parameters
     * 
     * @return void
     */
    protected function addError(string $field, string $ruleName, array $parameters): void
    {
        $message = $this->getErrorMessage($field, $ruleName, $parameters);
        
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Get error message for a failed rule
     * 
     * @param string $field
     * @param string $ruleName
     * @param array<string> $parameters
     * 
     * @return string
     */
    protected function getErrorMessage(string $field, string $ruleName, array $parameters): string
    {
        // Check for custom message
        $customKey = "{$field}.{$ruleName}";
        if (isset($this->customMessages[$customKey])) {
            return $this->formatMessage($this->customMessages[$customKey], $field, $parameters);
        }

        // Get default message from rule
        $rule = $this->rules[$ruleName];
        $message = $rule->message($field, $parameters);

        // Replace field name with custom attribute if set
        $displayName = $this->customAttributes[$field] ?? $field;
        $message = str_replace($field, $displayName, $message);

        return $message;
    }

    /**
     * Format a message with placeholders
     * 
     * @param string $message
     * @param string $field
     * @param array<string> $parameters
     * 
     * @return string
     */
    protected function formatMessage(string $message, string $field, array $parameters): string
    {
        $replacements = [
            ':field' => $this->customAttributes[$field] ?? $field,
            ':attribute' => $this->customAttributes[$field] ?? $field,
        ];

        // Add parameter placeholders
        foreach ($parameters as $index => $parameter) {
            $replacements[':param' . ($index + 1)] = $parameter;
            $replacements[':' . $index] = $parameter;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Register default validation rules
     * 
     * @return void
     */
    protected function registerDefaultRules(): void
    {
        $this->addRule('required', new RequiredRule());
        $this->addRule('email', new EmailRule());
        $this->addRule('min_length', new MinLengthRule());
        $this->addRule('max_length', new MaxLengthRule());
    }

    /**
     * Get all registered rules
     * 
     * @return array<string, RuleInterface>
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * Create a new validator instance with data and rules
     * 
     * @param array<string, mixed> $data
     * @param array<string, string|array<string>> $rules
     * 
     * @return static
     */
    public static function make(array $data, array $rules): static
    {
        $validator = new static();
        $validator->validate($data, $rules);
        return $validator;
    }

    /**
     * Check if validation passed
     * 
     * @return bool
     */
    public function passes(): bool
    {
        return !$this->hasErrors();
    }

    /**
     * Check if validation failed
     * 
     * @return bool
     */
    public function fails(): bool
    {
        return $this->hasErrors();
    }

    /**
     * Get errors for a specific field
     * 
     * @param string $field
     * 
     * @return array<string>
     */
    public function getErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a specific field has errors
     * 
     * @param string $field
     * 
     * @return bool
     */
    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }
}