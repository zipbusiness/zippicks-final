<?php
/**
 * Auth Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Providers\ServiceProvider;
use ZipPicks\Foundation\Contracts\Auth\UserProviderInterface;
use ZipPicks\Foundation\Contracts\Auth\AuthManagerInterface;
use ZipPicks\Foundation\Contracts\Auth\GuardInterface;
use ZipPicks\Foundation\Auth\WPUserProvider;
use ZipPicks\Foundation\Auth\AuthManager;
use ZipPicks\Foundation\Auth\RoleGuard;

/**
 * Provides authentication services to the foundation
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Services provided by this provider
     *
     * @var array<int, string>
     */
    public array $provides = [
        UserProviderInterface::class,
        AuthManagerInterface::class,
        GuardInterface::class,
        'user',
        'auth',
        'guard',
    ];
    /**
     * Register the auth services
     * 
     * @return void
     */
    public function register(): void
    {
        // Register user provider as singleton
        $this->singleton(UserProviderInterface::class, WPUserProvider::class);

        // Register auth manager as singleton
        $this->singleton(AuthManagerInterface::class, function() {
            $userProvider = $this->foundation->getContainer()->get(UserProviderInterface::class);
            return new AuthManager($userProvider);
        });

        // Register role guard as singleton
        $this->singleton(GuardInterface::class, function() {
            $authManager = $this->foundation->getContainer()->get(AuthManagerInterface::class);
            return new RoleGuard($authManager);
        });

        // Register aliases for easier access
        $container = $this->foundation->getContainer();
        
        if (!$container->has('user')) {
            $container->alias('user', UserProviderInterface::class);
        }

        if (!$container->has('auth')) {
            $container->alias('auth', AuthManagerInterface::class);
        }

        if (!$container->has('guard')) {
            $container->alias('guard', GuardInterface::class);
        }
    }

    /**
     * Bootstrap the auth services
     * 
     * @return void
     */
    public function boot(): void
    {
        // Log auth service initialization if logging is available
        if ($this->has('logger')) {
            $logger = $this->get('logger');
            $auth = $this->get(AuthManagerInterface::class);
            
            $logger->channel('auth')->info('Auth service initialized', [
                'provider' => WPUserProvider::class,
                'manager' => AuthManager::class,
                'guard' => $auth->getGuard(),
                'authenticated' => $auth->check(),
            ]);
        }

        // Configure guards from settings if available
        $this->configureGuards();

        // Register WordPress hooks
        $this->registerHooks();
    }

    /**
     * Configure authentication guards from settings
     * 
     * @return void
     */
    protected function configureGuards(): void
    {
        if (!$this->has('settings')) {
            return;
        }

        $settings = $this->get('settings');
        $guards = $settings->get('auth.guards', []);

        if (empty($guards)) {
            return;
        }

        $authManager = $this->get(AuthManagerInterface::class);

        foreach ($guards as $name => $config) {
            $authManager->addGuard($name, $config);
        }

        // Set default guard if configured
        $defaultGuard = $settings->get('auth.default');
        if ($defaultGuard && $authManager->hasGuard($defaultGuard)) {
            $authManager->guard($defaultGuard);
        }
    }

    /**
     * Register WordPress hooks for auth events
     * 
     * @return void
     */
    protected function registerHooks(): void
    {
        // Clear user cache on login
        $this->action('wp_login', function($username, $user) {
            if ($this->has('user') && $user instanceof \WP_User) {
                $provider = $this->get('user');
                if (method_exists($provider, 'clearUserCache')) {
                    $provider->clearUserCache((int) $user->ID);
                }
            }

            // Reset auth manager cache
            if ($this->has('auth')) {
                $this->get('auth')->reset();
            }
        }, 10, 2);

        // Clear cache on logout
        $this->action('wp_logout', function() {
            if ($this->has('user') && method_exists($this->get('user'), 'clearCache')) {
                $this->get('user')->clearCache();
            }

            if ($this->has('auth')) {
                $this->get('auth')->reset();
            }
        });

        // Clear cache when user is updated
        $this->action('profile_update', function($userId) {
            if ($this->has('user') && method_exists($this->get('user'), 'clearUserCache')) {
                $this->get('user')->clearUserCache($userId);
            }

            // Reset auth manager if current user was updated
            if ($this->has('auth')) {
                $auth = $this->get('auth');
                if ($auth->id() === $userId) {
                    $auth->reset();
                }
            }
        });

        // Clear cache when user is deleted
        $this->action('delete_user', function($userId) {
            if ($this->has('user') && method_exists($this->get('user'), 'clearUserCache')) {
                $this->get('user')->clearUserCache($userId);
            }
        });
    }
}