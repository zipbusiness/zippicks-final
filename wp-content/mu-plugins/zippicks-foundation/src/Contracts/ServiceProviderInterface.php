<?php
/**
 * Service Provider Interface
 * 
 * @package ZipPicks\Foundation\Contracts
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts;

/**
 * Interface for service providers
 */
interface ServiceProviderInterface
{
    /**
     * Register services into the container
     * 
     * @return void
     */
    public function register(): void;

    /**
     * Bootstrap services after all providers are registered
     * 
     * @return void
     */
    public function boot(): void;
}