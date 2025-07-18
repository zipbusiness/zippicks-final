<?php
/**
 * Auth Manager Implementation
 * 
 * @package ZipPicks\Foundation\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Auth;

use ZipPicks\Foundation\Contracts\Auth\AuthManagerInterface;
use ZipPicks\Foundation\Contracts\Auth\UserProviderInterface;
use ZipPicks\Foundation\Models\User;

/**
 * Core authentication manager
 */
class AuthManager implements AuthManagerInterface
{
    /**
     * User provider instance
     * 
     * @var UserProviderInterface
     */
    protected UserProviderInterface $userProvider;

    /**
     * Current authentication guard
     * 
     * @var string
     */
    protected string $guard = 'session';

    /**
     * Cached current user
     * 
     * @var User|null|false
     */
    protected User|null|false $currentUser = false;

    /**
     * Available guards configuration
     * 
     * @var array<string, array<string, mixed>>
     */
    protected array $guards = [
        'session' => [
            'driver' => 'session',
            'provider' => 'wp',
        ],
    ];

    /**
     * Create a new auth manager instance
     * 
     * @param UserProviderInterface $userProvider
     */
    public function __construct(UserProviderInterface $userProvider)
    {
        $this->userProvider = $userProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * {@inheritdoc}
     */
    public function user(): ?User
    {
        // If we haven't checked yet
        if ($this->currentUser === false) {
            $this->currentUser = $this->userProvider->getCurrentUser();
        }

        return $this->currentUser;
    }

    /**
     * {@inheritdoc}
     */
    public function id(): ?int
    {
        $user = $this->user();
        return $user ? $user->getId() : null;
    }

    /**
     * Set the current guard
     * 
     * @param string $guard
     * 
     * @return self
     */
    public function guard(string $guard): self
    {
        if (!isset($this->guards[$guard])) {
            throw new \InvalidArgumentException("Auth guard '{$guard}' is not defined.");
        }

        $this->guard = $guard;
        $this->currentUser = false; // Reset cached user

        return $this;
    }

    /**
     * Get the current guard name
     * 
     * @return string
     */
    public function getGuard(): string
    {
        return $this->guard;
    }

    /**
     * Register a new guard
     * 
     * @param string $name
     * @param array<string, mixed> $config
     * 
     * @return void
     */
    public function addGuard(string $name, array $config): void
    {
        $this->guards[$name] = $config;
    }

    /**
     * Check if a guard exists
     * 
     * @param string $name
     * 
     * @return bool
     */
    public function hasGuard(string $name): bool
    {
        return isset($this->guards[$name]);
    }

    /**
     * Get all registered guards
     * 
     * @return array<string, array<string, mixed>>
     */
    public function getGuards(): array
    {
        return $this->guards;
    }

    /**
     * Reset the cached user
     * 
     * @return void
     */
    public function reset(): void
    {
        $this->currentUser = false;
    }

    /**
     * Check if the current user has a specific role
     * 
     * @param string $role
     * 
     * @return bool
     */
    public function hasRole(string $role): bool
    {
        $user = $this->user();
        return $user ? $user->hasRole($role) : false;
    }

    /**
     * Check if the current user has any of the specified roles
     * 
     * @param array<string> $roles
     * 
     * @return bool
     */
    public function hasAnyRole(array $roles): bool
    {
        $user = $this->user();
        return $user ? $user->hasAnyRole($roles) : false;
    }

    /**
     * Check if the current user has all of the specified roles
     * 
     * @param array<string> $roles
     * 
     * @return bool
     */
    public function hasAllRoles(array $roles): bool
    {
        $user = $this->user();
        return $user ? $user->hasAllRoles($roles) : false;
    }

    /**
     * Check if the current user is an administrator
     * 
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->hasRole('administrator');
    }

    /**
     * Get the user provider
     * 
     * @return UserProviderInterface
     */
    public function getUserProvider(): UserProviderInterface
    {
        return $this->userProvider;
    }

    /**
     * Set the user provider
     * 
     * @param UserProviderInterface $userProvider
     * 
     * @return void
     */
    public function setUserProvider(UserProviderInterface $userProvider): void
    {
        $this->userProvider = $userProvider;
        $this->reset();
    }
}