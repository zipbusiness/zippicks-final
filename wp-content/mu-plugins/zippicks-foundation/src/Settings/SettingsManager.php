<?php
/**
 * Settings Manager Implementation
 * 
 * @package ZipPicks\Foundation\Settings
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Settings;

/**
 * Manages system-wide configuration values
 */
class SettingsManager
{
    /**
     * Settings storage
     * 
     * @var array<string, mixed>
     */
    protected array $settings = [];

    /**
     * Default settings from config
     * 
     * @var array<string, mixed>
     */
    protected array $defaults = [];

    /**
     * Runtime overrides
     * 
     * @var array<string, mixed>
     */
    protected array $overrides = [];

    /**
     * Create a new settings manager instance
     * 
     * @param array<string, mixed> $defaults Default settings from config
     */
    public function __construct(array $defaults = [])
    {
        $this->defaults = $defaults;
        $this->settings = $defaults;
    }

    /**
     * Get a setting value
     * 
     * @param string $key The setting key using dot notation
     * @param mixed $default Default value if setting doesn't exist
     * 
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        // Check overrides first
        $override = $this->getFromArray($this->overrides, $key);
        if ($override !== null) {
            return $override;
        }

        // Then check settings
        $value = $this->getFromArray($this->settings, $key);
        
        return $value !== null ? $value : $default;
    }

    /**
     * Set a setting value
     * 
     * @param string $key The setting key using dot notation
     * @param mixed $value The value to set
     * 
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->setInArray($this->overrides, $key, $value);
    }

    /**
     * Check if a setting exists
     * 
     * @param string $key The setting key using dot notation
     * 
     * @return bool
     */
    public function has(string $key): bool
    {
        return $this->getFromArray($this->overrides, $key) !== null ||
               $this->getFromArray($this->settings, $key) !== null;
    }

    /**
     * Get all settings including overrides
     * 
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return array_replace_recursive($this->settings, $this->overrides);
    }

    /**
     * Get only the default settings
     * 
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return $this->defaults;
    }

    /**
     * Get only the runtime overrides
     * 
     * @return array<string, mixed>
     */
    public function overrides(): array
    {
        return $this->overrides;
    }

    /**
     * Reset all overrides
     * 
     * @return void
     */
    public function reset(): void
    {
        $this->overrides = [];
    }

    /**
     * Reset a specific setting to its default value
     * 
     * @param string $key The setting key using dot notation
     * 
     * @return void
     */
    public function resetKey(string $key): void
    {
        $this->unsetFromArray($this->overrides, $key);
    }

    /**
     * Merge additional settings
     * 
     * @param array<string, mixed> $settings Settings to merge
     * 
     * @return void
     */
    public function merge(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }

    /**
     * Get a value from an array using dot notation
     * 
     * @param array<string, mixed> $array
     * @param string $key
     * 
     * @return mixed
     */
    protected function getFromArray(array $array, string $key): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        $segments = explode('.', $key);
        $current = $array;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * Set a value in an array using dot notation
     * 
     * @param array<string, mixed> $array
     * @param string $key
     * @param mixed $value
     * 
     * @return void
     */
    protected function setInArray(array &$array, string $key, mixed $value): void
    {
        if (str_contains($key, '.')) {
            $segments = explode('.', $key);
            $current = &$array;

            foreach ($segments as $i => $segment) {
                if (count($segments) === $i + 1) {
                    $current[$segment] = $value;
                } else {
                    if (!isset($current[$segment]) || !is_array($current[$segment])) {
                        $current[$segment] = [];
                    }
                    $current = &$current[$segment];
                }
            }
        } else {
            $array[$key] = $value;
        }
    }

    /**
     * Unset a value from an array using dot notation
     * 
     * @param array<string, mixed> $array
     * @param string $key
     * 
     * @return void
     */
    protected function unsetFromArray(array &$array, string $key): void
    {
        if (!str_contains($key, '.')) {
            unset($array[$key]);
            return;
        }

        $segments = explode('.', $key);
        $lastSegment = array_pop($segments);
        $current = &$array;

        foreach ($segments as $segment) {
            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                return;
            }
            $current = &$current[$segment];
        }

        unset($current[$lastSegment]);
    }
}