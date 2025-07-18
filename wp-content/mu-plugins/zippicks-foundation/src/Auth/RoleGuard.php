<?php
/**
 * Role-based Guard Implementation
 *
 * @package ZipPicks\Foundation\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Auth;

use ZipPicks\Foundation\Contracts\Auth\GuardInterface;
use ZipPicks\Foundation\Contracts\Auth\AuthManagerInterface;
use ZipPicks\Foundation\Models\User;

/**
 * Guard implementation for role and capability checking
 *
 * @since 1.0.0
 */
class RoleGuard implements GuardInterface
{
    /**
     * Auth manager instance
     *
     * @var AuthManagerInterface
     */
    protected AuthManagerInterface $auth;

    /**
     * User to check against
     *
     * @var User|null
     */
    protected ?User $user = null;

    /**
     * Defined abilities
     *
     * @var array<string, callable>
     */
    protected array $abilities = [];

    /**
     * Constructor
     *
     * @param AuthManagerInterface $auth
     */
    public function __construct(AuthManagerInterface $auth)
    {
        $this->auth = $auth;
        $this->registerDefaultAbilities();
    }

    /**
     * Determine if the current user is authorized for the given ability
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return bool
     */
    public function allows(string $ability, mixed ...$arguments): bool
    {
        $user = $this->getUser();

        if ($user === null) {
            return false;
        }

        // Check custom defined abilities first
        if (isset($this->abilities[$ability])) {
            return call_user_func($this->abilities[$ability], $user, ...$arguments);
        }

        // Check WordPress capabilities
        if (function_exists('user_can')) {
            return user_can($user->id, $ability, ...$arguments);
        }

        // Check role-based abilities
        return $this->checkRoleAbility($ability, $user);
    }

    /**
     * Determine if the current user is denied for the given ability
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return bool
     */
    public function denies(string $ability, mixed ...$arguments): bool
    {
        return !$this->allows($ability, ...$arguments);
    }

    /**
     * Set the user for the guard
     *
     * @param User|null $user
     * @return self
     */
    public function forUser(?User $user): self
    {
        $clone = clone $this;
        $clone->user = $user;
        return $clone;
    }

    /**
     * Get the user for the guard
     *
     * @return User|null
     */
    public function user(): ?User
    {
        return $this->getUser();
    }

    /**
     * Check if the guard has a user
     *
     * @return bool
     */
    public function hasUser(): bool
    {
        return $this->getUser() !== null;
    }

    /**
     * Define abilities for the guard
     *
     * @param array<string, callable> $abilities
     * @return self
     */
    public function define(array $abilities): self
    {
        $this->abilities = array_merge($this->abilities, $abilities);
        return $this;
    }

    /**
     * Define a single ability
     *
     * @param string $ability
     * @param callable $callback
     * @return self
     */
    public function defineAbility(string $ability, callable $callback): self
    {
        $this->abilities[$ability] = $callback;
        return $this;
    }

    /**
     * Get the current user
     *
     * @return User|null
     */
    protected function getUser(): ?User
    {
        if ($this->user !== null) {
            return $this->user;
        }

        return $this->auth->user();
    }

    /**
     * Register default abilities
     *
     * @return void
     */
    protected function registerDefaultAbilities(): void
    {
        // Check if user has a specific role
        $this->defineAbility('role', function (User $user, string $role): bool {
            return $user->hasRole($role);
        });

        // Check if user has any of the given roles
        $this->defineAbility('roles', function (User $user, array $roles): bool {
            return $user->hasAnyRole($roles);
        });

        // Check if user is admin
        $this->defineAbility('admin', function (User $user): bool {
            return $user->isAdmin();
        });

        // Check if user is a specific user
        $this->defineAbility('user', function (User $user, int $userId): bool {
            return $user->id === $userId;
        });

        // Check if user is authenticated
        $this->defineAbility('authenticated', function (User $user): bool {
            return true; // If we have a user, they're authenticated
        });

        // ZipPicks specific abilities
        $this->defineAbility('critic', function (User $user): bool {
            return $user->hasRole('zippicks_critic') || $user->hasRole('administrator');
        });

        $this->defineAbility('business_owner', function (User $user): bool {
            return $user->hasRole('zippicks_business_owner') || $user->hasRole('administrator');
        });

        $this->defineAbility('manage_reviews', function (User $user): bool {
            return $user->hasAnyRole(['zippicks_critic', 'editor', 'administrator']);
        });

        $this->defineAbility('manage_businesses', function (User $user): bool {
            return $user->hasAnyRole(['zippicks_business_owner', 'editor', 'administrator']);
        });
    }

    /**
     * Check role-based abilities
     *
     * @param string $ability
     * @param User $user
     * @return bool
     */
    protected function checkRoleAbility(string $ability, User $user): bool
    {
        // Map common abilities to roles
        $abilityRoleMap = [
            'edit_posts' => ['editor', 'administrator'],
            'publish_posts' => ['author', 'editor', 'administrator'],
            'manage_options' => ['administrator'],
            'moderate_comments' => ['editor', 'administrator'],
        ];

        if (isset($abilityRoleMap[$ability])) {
            return $user->hasAnyRole($abilityRoleMap[$ability]);
        }

        return false;
    }
}