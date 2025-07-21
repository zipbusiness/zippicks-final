<?php
/**
 * Guard Interface
 *
 * @package ZipPicks\Foundation\Contracts\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Contracts\Auth;

use ZipPicks\Foundation\Models\User;

/**
 * Contract for authorization guards
 *
 * @since 1.0.0
 */
interface GuardInterface
{
    /**
     * Determine if the current user is authorized for the given ability
     *
     * @param string $ability The ability to check
     * @param mixed ...$arguments Additional arguments for the check
     * @return bool
     */
    public function allows(string $ability, mixed ...$arguments): bool;

    /**
     * Determine if the current user is denied for the given ability
     *
     * @param string $ability The ability to check
     * @param mixed ...$arguments Additional arguments for the check
     * @return bool
     */
    public function denies(string $ability, mixed ...$arguments): bool;

    /**
     * Set the user for the guard
     *
     * @param User|null $user
     * @return self
     */
    public function forUser(?User $user): self;

    /**
     * Get the user for the guard
     *
     * @return User|null
     */
    public function user(): ?User;

    /**
     * Check if the guard has a user
     *
     * @return bool
     */
    public function hasUser(): bool;

    /**
     * Define abilities for the guard
     *
     * @param array<string, callable> $abilities Map of ability names to check functions
     * @return self
     */
    public function define(array $abilities): self;

    /**
     * Define a single ability
     *
     * @param string $ability The ability name
     * @param callable $callback The check function
     * @return self
     */
    public function defineAbility(string $ability, callable $callback): self;
}