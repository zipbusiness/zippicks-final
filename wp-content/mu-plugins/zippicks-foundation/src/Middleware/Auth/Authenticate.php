<?php
/**
 * Authentication Middleware
 *
 * @package ZipPicks\Foundation\Middleware\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Middleware\Auth;

use Closure;
use ZipPicks\Foundation\Contracts\Middleware\MiddlewareInterface;
use ZipPicks\Foundation\Contracts\Middleware\RequestInterface;
use ZipPicks\Foundation\Contracts\Auth\AuthManagerInterface;
use ZipPicks\Foundation\Auth\UnauthenticatedException;

/**
 * Ensures user is authenticated
 *
 * @since 1.0.0
 */
class Authenticate implements MiddlewareInterface
{
    /**
     * Auth manager instance
     *
     * @var AuthManagerInterface
     */
    protected AuthManagerInterface $auth;

    /**
     * Guards to check
     *
     * @var array<int, string>
     */
    protected array $guards = [];

    /**
     * Constructor
     *
     * @param AuthManagerInterface $auth
     */
    public function __construct(AuthManagerInterface $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle the request
     *
     * @param RequestInterface $request
     * @param Closure $next
     * @return mixed
     * @throws UnauthenticatedException
     */
    public function handle(RequestInterface $request, Closure $next): mixed
    {
        $this->authenticate($request, $this->guards);

        return $next($request);
    }

    /**
     * Set the guards to check
     *
     * @param array<int, string>|string $guards
     * @return self
     */
    public function guards(array|string $guards): self
    {
        $this->guards = is_array($guards) ? $guards : [$guards];
        return $this;
    }

    /**
     * Determine if the user is logged in to any of the given guards
     *
     * @param RequestInterface $request
     * @param array<int, string> $guards
     * @return void
     * @throws UnauthenticatedException
     */
    protected function authenticate(RequestInterface $request, array $guards): void
    {
        if (empty($guards)) {
            // Check default guard
            if ($this->auth->check()) {
                return;
            }
        } else {
            // Check specific guards
            foreach ($guards as $guard) {
                if ($this->auth->guard($guard)->check()) {
                    return;
                }
            }
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * Handle unauthenticated requests
     *
     * @param RequestInterface $request
     * @param array<int, string> $guards
     * @return void
     * @throws UnauthenticatedException
     */
    protected function unauthenticated(RequestInterface $request, array $guards): void
    {
        throw (new UnauthenticatedException(
            'Authentication required.',
            $guards
        ))->withContext('url', $request->fullUrl());
    }
}