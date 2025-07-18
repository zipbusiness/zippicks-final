<?php
/**
 * Storage Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Contracts\Storage\FilesystemInterface;
use ZipPicks\Foundation\Storage\LocalFilesystem;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;

/**
 * Provides storage services to the foundation
 */
class StorageServiceProvider extends ServiceProvider
{
    /**
     * Register the storage services
     * 
     * @return void
     */
    public function register(): void
    {
        $this->singleton(FilesystemInterface::class, function() {
            // Get settings if available
            $settings = null;
            if ($this->foundation->getContainer()->has('settings')) {
                $settings = $this->foundation->getContainer()->get('settings');
            }

            // Get logger if available
            $logger = null;
            if ($this->foundation->getContainer()->has(LoggerInterface::class)) {
                $logger = $this->foundation->getContainer()->get(LoggerInterface::class);
            }

            // Determine storage path
            $storagePath = ZIPPICKS_FOUNDATION_PATH . '/storage';
            if ($settings) {
                $configuredPath = $settings->get('storage.path');
                if ($configuredPath) {
                    $storagePath = $configuredPath;
                }
            }

            // Create filesystem instance based on disk configuration
            $disk = $settings ? $settings->get('storage.default', 'local') : 'local';

            switch ($disk) {
                case 'local':
                default:
                    return new LocalFilesystem($storagePath, $logger);
                    // Future: Add support for S3, FTP, etc.
            }
        });

        // Register alias for easier access if not already defined
        $container = $this->foundation->getContainer();
        if (!$container->has('filesystem')) {
            $container->alias('filesystem', FilesystemInterface::class);
        }
    }

    /**
     * Bootstrap the storage services
     * 
     * @return void
     */
    public function boot(): void
    {
        // Log storage service initialization if logging is available
        if ($this->has('logger')) {
            $logger = $this->get('logger');
            $filesystem = $this->get(FilesystemInterface::class);
            
            $logger->channel('storage')->info('Storage service initialized', [
                'adapter' => get_class($filesystem),
                'base_path' => method_exists($filesystem, 'getBasePath') ? $filesystem->getBasePath() : 'unknown',
            ]);
        }

        // Ensure required directories exist
        $this->ensureStorageDirectories();
    }

    /**
     * Ensure required storage directories exist
     * 
     * @return void
     */
    protected function ensureStorageDirectories(): void
    {
        $filesystem = $this->get(FilesystemInterface::class);
        
        // Get settings if available
        $settings = null;
        if ($this->has('settings')) {
            $settings = $this->get('settings');
        }

        // Default directories to create
        $directories = [
            'cache',
            'logs',
            'temp',
            'uploads',
        ];

        // Add custom directories from settings
        if ($settings) {
            $customDirs = $settings->get('storage.directories', []);
            $directories = array_merge($directories, $customDirs);
        }

        // Create each directory
        foreach ($directories as $directory) {
            $filesystem->makeDirectory($directory);
        }
    }
}