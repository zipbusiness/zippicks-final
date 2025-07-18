<?php
/**
 * Auth Helper Functions
 *
 * @package ZipPicks\Foundation\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

use ZipPicks\Foundation\Contracts\Auth\AuthManagerInterface;
use ZipPicks\Foundation\Contracts\Auth\GuardInterface;
use ZipPicks\Foundation\Models\User;

if (!function_exists('auth')) {
    /**
     * Get the auth manager instance
     *
     * @param string|null $guard Optional guard name
     * @return AuthManagerInterface
     */
    function auth(?string $guard = null): AuthManagerInterface
    {
        $auth = foundation()->get(AuthManagerInterface::class);
        
        if ($guard !== null) {
            return $auth->guard($guard);
        }
        
        return $auth;
    }
}

if (!function_exists('guard')) {
    /**
     * Get the guard instance
     *
     * @param string|null $name Optional guard name
     * @return GuardInterface
     */
    function guard(?string $name = null): GuardInterface
    {
        return foundation()->get(GuardInterface::class);
    }
}

if (!function_exists('user')) {
    /**
     * Get the currently authenticated user
     *
     * @return User|null
     */
    function user(): ?User
    {
        return auth()->user();
    }
}

if (!function_exists('check')) {
    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    function check(): bool
    {
        return auth()->check();
    }
}

if (!function_exists('guest')) {
    /**
     * Check if user is a guest
     *
     * @return bool
     */
    function guest(): bool
    {
        return auth()->guest();
    }
}

if (!function_exists('can')) {
    /**
     * Check if the current user has an ability
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return bool
     */
    function can(string $ability, mixed ...$arguments): bool
    {
        return guard()->allows($ability, ...$arguments);
    }
}

if (!function_exists('cannot')) {
    /**
     * Check if the current user lacks an ability
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return bool
     */
    function cannot(string $ability, mixed ...$arguments): bool
    {
        return guard()->denies($ability, ...$arguments);
    }
}