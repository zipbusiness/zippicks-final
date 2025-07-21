<?php
/**
 * Log Entry Value Object
 * 
 * @package ZipPicks\Foundation\Logging
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Logging;

/**
 * Immutable log entry representation
 */
final class LogEntry
{
    private string $level;
    private string $message;
    private array $context;
    private string $channel;
    private float $timestamp;
    private array $metadata;

    public function __construct(
        string $level,
        string $message,
        array $context = [],
        string $channel = 'default',
        ?float $timestamp = null,
        array $metadata = []
    ) {
        $this->level = $level;
        $this->message = $message;
        $this->context = $context;
        $this->channel = $channel;
        $this->timestamp = $timestamp ?? microtime(true);
        $this->metadata = array_merge($this->getDefaultMetadata(), $metadata);
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getTimestamp(): float
    {
        return $this->timestamp;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getFormattedMessage(): string
    {
        return $this->interpolate($this->message, $this->context);
    }

    public function toArray(): array
    {
        return [
            'timestamp' => $this->timestamp,
            'datetime' => date('Y-m-d H:i:s', (int)$this->timestamp),
            'microseconds' => substr((string)$this->timestamp, -6),
            'level' => $this->level,
            'severity' => LogLevel::getSeverity($this->level),
            'channel' => $this->channel,
            'message' => $this->message,
            'formatted_message' => $this->getFormattedMessage(),
            'context' => $this->context,
            'metadata' => $this->metadata,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function interpolate(string $message, array $context): string
    {
        $replacements = [];
        
        foreach ($context as $key => $value) {
            $placeholder = '{' . $key . '}';
            
            if (strpos($message, $placeholder) !== false) {
                $replacements[$placeholder] = $this->stringify($value);
            }
        }

        return strtr($message, $replacements);
    }

    private function stringify(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value)) {
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return get_class($value);
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function getDefaultMetadata(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
            'request_id' => $this->getRequestId(),
            'user_id' => get_current_user_id(),
            'url' => $this->getCurrentUrl(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        ];
    }

    private function getRequestId(): string
    {
        static $requestId = null;
        
        if ($requestId === null) {
            $requestId = wp_generate_uuid4();
        }
        
        return $requestId;
    }

    private function getCurrentUrl(): string
    {
        if (defined('WP_CLI') && WP_CLI) {
            return 'cli';
        }

        if (wp_doing_cron()) {
            return 'cron';
        }

        if (wp_doing_ajax()) {
            return 'ajax:' . ($_REQUEST['action'] ?? 'unknown');
        }

        return $_SERVER['REQUEST_URI'] ?? 'unknown';
    }
}