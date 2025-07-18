<?php
/**
 * Log Level Constants
 * 
 * PSR-3 compatible log levels without requiring external package
 * 
 * @package ZipPicks\Foundation\Logging
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Logging;

/**
 * PSR-3 compatible log level constants
 */
class LogLevel
{
    const EMERGENCY = 'emergency';
    const ALERT = 'alert';
    const CRITICAL = 'critical';
    const ERROR = 'error';
    const WARNING = 'warning';
    const NOTICE = 'notice';
    const INFO = 'info';
    const DEBUG = 'debug';

    /**
     * Get numeric severity for a log level
     */
    public static function getSeverity(string $level): int
    {
        return match($level) {
            self::EMERGENCY => 800,
            self::ALERT => 700,
            self::CRITICAL => 600,
            self::ERROR => 500,
            self::WARNING => 400,
            self::NOTICE => 300,
            self::INFO => 200,
            self::DEBUG => 100,
            default => 0
        };
    }

    /**
     * Check if a level meets minimum severity
     */
    public static function meetsThreshold(string $level, string $threshold): bool
    {
        return self::getSeverity($level) >= self::getSeverity($threshold);
    }
}