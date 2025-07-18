<?php
/**
 * Authorization Middleware
 *
 * @package ZipPicks\Foundation\Middleware\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Middleware\Auth;

use Closure;
use ZipPicks\Foundation\Contracts\Middleware\MiddlewareInterface;
use ZipPicks\Foundation\Contracts\Middleware\RequestInterface;
use ZipPicks\Foundation\Contracts\Auth\GuardInterface;
use ZipPicks\Foundation\Auth\UnauthorizedException;

/**
 * Ensures user has required permissions
 *
 * @since 1.0.0
 */
class Authorize implements MiddlewareInterface
{
    /**
     * Guard instance
     *
     * @var GuardInterface
     */
    protected GuardInterface $guard;

    /**
     * Ability to check
     *
     * @var string|null
     */
    protected ?string $ability = null;

    /**
     * Arguments for ability check
     *
     * @var array<int, mixed>
     */
    protected array $arguments = [];

    /**
     * Constructor
     *
     * @param GuardInterface $guard
     */
    public function __construct(GuardInterface $guard)
    {
        $this->guard = $guard;
    }

    /**
     * Handle the request
     *
     * @param RequestInterface $request
     * @param Closure $next
     * @return mixed
     * @throws UnauthorizedException
     */
    public function handle(RequestInterface $request, Closure $next): mixed
    {
        if ($this->ability !== null) {
            $this->authorize($this->ability, $this->arguments);
        }

        return $next($request);
    }

    /**
     * Set the ability to check
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return self
     */
    public function ability(string $ability, mixed ...$arguments): self
    {
        $this->ability = $ability;
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * Check if user has required role
     *
     * @param string $role
     * @return self
     */
    public function role(string $role): self
    {
        return $this->ability('role', $role);
    }

    /**
     * Check if user has any of the required roles
     *
     * @param array<int, string> $roles
     * @return self
     */
    public function anyRole(array $roles): self
    {
        return $this->ability('roles', $roles);
    }

    /**
     * Check if user is admin
     *
     * @return self
     */
    public function admin(): self
    {
        return $this->ability('admin');
    }

    /**
     * Check if user is a critic
     *
     * @return self
     */
    public function critic(): self
    {
        return $this->ability('critic');
    }

    /**
     * Check if user is a business owner
     *
     * @return self
     */
    public function businessOwner(): self
    {
        return $this->ability('business_owner');
    }

    /**
     * Check if user can manage reviews
     *
     * @return self
     */
    public function canManageReviews(): self
    {
        return $this->ability('manage_reviews');
    }

    /**
     * Check if user can manage businesses
     *
     * @return self
     */
    public function canManageBusinesses(): self
    {
        return $this->ability('manage_businesses');
    }

    /**
     * Authorize the ability
     *
     * @param string $ability
     * @param array<int, mixed> $arguments
     * @return void
     * @throws UnauthorizedException
     */
    protected function authorize(string $ability, array $arguments = []): void
    {
        if (!$this->guard->hasUser()) {
            throw new UnauthorizedException(
                'Authentication required to access this resource.',
                $ability,
                $arguments
            );
        }

        if ($this->guard->denies($ability, ...$arguments)) {
            throw (new UnauthorizedException(
                'You do not have permission to access this resource.',
                $ability,
                $arguments
            ))->forAbility($ability, $arguments);
        }
    }

    /**
     * Create a new instance with ability
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return static
     */
    public static function using(string $ability, mixed ...$arguments): static
    {
        $instance = app(static::class);
        return $instance->ability($ability, ...$arguments);
    }
}