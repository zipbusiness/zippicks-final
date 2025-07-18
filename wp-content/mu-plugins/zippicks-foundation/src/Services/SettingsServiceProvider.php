<?php
/**
 * Settings Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Settings\SettingsManager;

/**
 * Provides settings management services to the foundation
 */
class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Register the settings services
     * 
     * @return void
     */
    public function register(): void
    {
        $this->singleton(SettingsManager::class, function() {
            $configPath = ZIPPICKS_FOUNDATION_PATH . '/config/settings.php';
            $defaults = [];
            
            if (file_exists($configPath)) {
                $defaults = require $configPath;
            }
            
            return new SettingsManager($defaults);
        });

        // Register alias for easier access if not already defined
        $container = $this->foundation->getContainer();
        if (!$container->has('settings')) {
            $container->alias('settings', SettingsManager::class);
        }
    }

    /**
     * Bootstrap the settings services
     * 
     * @return void
     */
    public function boot(): void
    {
        // Settings are loaded during registration, nothing to boot
    }
}