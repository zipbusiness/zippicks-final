<?php
/**
 * Minimal Service Provider Configuration
 * 
 * Temporary file to fix boot issues
 * 
 * @package ZipPicks\Foundation
 */

return [
    // Only essential providers
    \ZipPicks\Foundation\Services\ExceptionServiceProvider::class,
    \ZipPicks\Foundation\Services\LoggingServiceProvider::class,
    \ZipPicks\Foundation\Services\SettingsServiceProvider::class,
    \ZipPicks\Foundation\Services\CacheServiceProvider::class,
    \ZipPicks\Foundation\Services\EventServiceProvider::class,
];