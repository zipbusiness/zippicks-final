<?php
/**
 * User Provider Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Auth;

use ZipPicks\Foundation\Models\User;

/**
 * Interface for user provider services
 */
interface UserProviderInterface
{
    /**
     * Get the currently authenticated user
     * 
     * @return User|null The current user or null if not authenticated
     */
    public function getCurrentUser(): ?User;

    /**
     * Find a user by their ID
     * 
     * @param int $id The user ID
     * 
     * @return User|null The user or null if not found
     */
    public function findById(int $id): ?User;

    /**
     * Find a user by their email address
     * 
     * @param string $email The email address
     * 
     * @return User|null The user or null if not found
     */
    public function findByEmail(string $email): ?User;
}