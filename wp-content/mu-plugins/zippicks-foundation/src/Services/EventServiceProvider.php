<?php
/**
 * Event Service Provider
 * 
 * @package ZipPicks\Foundation\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Services;

use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Events\EventDispatcherInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Events\EventDispatcher;
use ZipPicks\Foundation\Events\Listeners\LoggingListener;
use ZipPicks\Foundation\Providers\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Register services
     *
     * @return void
     */
    public function register(): void
    {
        // Register event dispatcher as singleton
        $this->singleton(EventDispatcherInterface::class, function (ContainerInterface $container) {
            $logger = null;
            
            if ($container->has(LoggerInterface::class)) {
                $logger = $container->get(LoggerInterface::class);
            }
            
            return new EventDispatcher($container, $logger);
        });

        // Create alias for easier access
        if (!$this->has('events')) {
            $this->alias('events', EventDispatcherInterface::class);
        }

        // Log successful registration
        if ($this->has(LoggerInterface::class)) {
            $logger = $this->get(LoggerInterface::class);
            $logger->info('[EventServiceProvider] Event dispatcher registered successfully');
        }
    }

    /**
     * Bootstrap services
     *
     * @return void
     */
    public function boot(): void
    {
        /** @var EventDispatcher $events */
        $events = $this->get('events');

        // Register default listeners
        $this->registerDefaultListeners($events);

        // Enable WordPress mirroring if configured
        if ($this->has('settings')) {
            $settings = $this->get('settings');
            if ($settings->get('events.wordpress_mirror', false)) {
                $events->setWordPressMirroring(true);
            }
        }

        // Fire foundation booted event
        $events->dispatch('foundation.booted', [
            'version' => ZIPPICKS_FOUNDATION_VERSION ?? '1.0.0',
            'timestamp' => microtime(true)
        ]);
    }

    /**
     * Register default event listeners
     *
     * @param EventDispatcher $events
     * @return void
     */
    protected function registerDefaultListeners(EventDispatcher $events): void
    {
        // Register logging listener if logger is available
        if ($this->has(LoggerInterface::class)) {
            $logger = $this->get(LoggerInterface::class);
            
            // Log all events in debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $loggingListener = new LoggingListener($logger, 'debug');
                $loggingListener->logAll();
                $events->listen('*', $loggingListener, -1000);
            } else {
                // Log specific important events
                $loggingListener = new LoggingListener($logger, 'info', [
                    'foundation.booted',
                    'user.registered',
                    'review.submitted',
                    'business.created'
                ]);
                $events->subscribe($loggingListener);
            }
        }

        // Register WordPress compatibility listeners
        $this->registerWordPressListeners($events);
    }

    /**
     * Register WordPress-specific event listeners
     *
     * @param EventDispatcher $events
     * @return void
     */
    protected function registerWordPressListeners(EventDispatcher $events): void
    {
        // Example: Map foundation events to WordPress actions
        $events->listen('user.registered', function ($event, $payload) {
            if (function_exists('do_action')) {
                do_action('zippicks_user_registered', $payload);
            }
        });

        $events->listen('review.submitted', function ($event, $payload) {
            if (function_exists('do_action')) {
                do_action('zippicks_review_submitted', $payload);
            }
        });
    }
}