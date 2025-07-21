<?php
/**
 * File Logger Implementation
 * 
 * @package ZipPicks\Foundation\Logging
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Logging;

use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Logging\Drivers\FileLogDriver;

/**
 * File logger implementation using our enterprise logger
 * Backward compatible wrapper for FileLogDriver
 */
class FileLogger extends EnterpriseLogger implements LoggerInterface
{
    /**
     * Create a new file logger instance
     * 
     * @param string $logPath Base directory for log files
     * @param string $minLevel Minimum log level to write
     */
    public function __construct(string $logPath, string $minLevel = LogLevel::DEBUG)
    {
        $driver = new FileLogDriver($logPath, $minLevel);
        parent::__construct([$driver], false); // Sync mode for backward compatibility
    }
}