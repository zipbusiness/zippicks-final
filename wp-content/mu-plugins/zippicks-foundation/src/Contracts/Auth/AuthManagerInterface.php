<?php
/**
 * Auth Manager Interface
 * 
 * @package ZipPicks\Foundation\Contracts\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Auth;

use ZipPicks\Foundation\Models\User;

/**
 * Interface for authentication management
 */
interface AuthManagerInterface
{
    /**
     * Check if a user is authenticated
     * 
     * @return bool True if authenticated, false otherwise
     */
    public function check(): bool;

    /**
     * Check if the current user is a guest (not authenticated)
     * 
     * @return bool True if guest, false if authenticated
     */
    public function guest(): bool;

    /**
     * Get the currently authenticated user
     * 
     * @return User|null The authenticated user or null
     */
    public function user(): ?User;

    /**
     * Get the ID of the currently authenticated user
     * 
     * @return int|null The user ID or null if not authenticated
     */
    public function id(): ?int;
}