<?php
/**
 * Logging Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Logging\EnterpriseLogger;
use ZipPicks\Foundation\Logging\LogLevel;
use ZipPicks\Foundation\Logging\Drivers\FileLogDriver;
use ZipPicks\Foundation\Logging\Drivers\DatabaseLogDriver;

/**
 * Provides logging services to the foundation
 */
class LoggingServiceProvider extends ServiceProvider
{
    /**
     * Register the logging services
     * 
     * @return void
     */
    public function register(): void
    {
        // Register enterprise logger with multiple drivers
        $this->singleton(LoggerInterface::class, function() {
            $drivers = [];
            
            // File driver - always enabled
            $logPath = ZIPPICKS_FOUNDATION_PATH . '/logs';
            $fileMinLevel = defined('WP_DEBUG') && WP_DEBUG ? LogLevel::DEBUG : LogLevel::INFO;
            $drivers[] = new FileLogDriver($logPath, $fileMinLevel);
            
            // Database driver - only for important logs
            if (defined('ZIPPICKS_LOG_TO_DB') && ZIPPICKS_LOG_TO_DB) {
                $drivers[] = new DatabaseLogDriver(
                    'zippicks_logs',
                    LogLevel::WARNING,
                    30, // 30 days retention
                    50  // Buffer size
                );
            }
            
            // Create enterprise logger
            $asyncEnabled = !wp_doing_cron() && !wp_doing_ajax();
            return new EnterpriseLogger($drivers, $asyncEnabled);
        });

        // Register alias for easier access if not already defined
        $container = $this->foundation->getContainer();
        if (!$container->has('logger')) {
            $container->alias('logger', LoggerInterface::class);
        }
    }

    /**
     * Bootstrap the logging services
     * 
     * @return void
     */
    public function boot(): void
    {
        try {
            // Ensure logs directory exists with robust fallback
            $logPath = ZIPPICKS_FOUNDATION_PATH . '/logs';
            
            if (!is_dir($logPath)) {
                // Try WordPress function first
                if (function_exists('wp_mkdir_p')) {
                    $created = wp_mkdir_p($logPath);
                } else {
                    // Fallback to PHP mkdir
                    $created = @mkdir($logPath, 0755, true);
                }
                
                if (!$created && !is_dir($logPath)) {
                    throw new \RuntimeException("Failed to create log directory: {$logPath}");
                }
            }

            // Verify directory is writable
            if (!is_writable($logPath)) {
                throw new \RuntimeException("Log directory is not writable: {$logPath}");
            }

            // Log foundation boot event
            if ($this->has(LoggerInterface::class)) {
                $logger = $this->get(LoggerInterface::class);
                $logger->info('Foundation logging service initialized', [
                    'log_path' => $logPath,
                    'drivers' => array_map(function($driver) {
                        return $driver->getName();
                    }, $logger->getDrivers()),
                    'php_version' => PHP_VERSION,
                    'wordpress_version' => get_bloginfo('version'),
                ]);
            }
        } catch (\Throwable $e) {
            // Last resort: use error_log if logging setup fails
            error_log('[ZipPicks Foundation] LoggingServiceProvider boot failed: ' . $e->getMessage());
            
            // Re-throw in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                throw $e;
            }
        }
    }
}