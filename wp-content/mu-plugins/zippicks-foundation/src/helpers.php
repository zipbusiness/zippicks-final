<?php
/**
 * Foundation Helper Functions
 * 
 * @package ZipPicks\Foundation
 * @since 1.0.0
 */

declare(strict_types=1);

use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Contracts\Container\ContainerInterface;
use ZipPicks\Foundation\Contracts\Auth\AuthManagerInterface;
use ZipPicks\Foundation\Contracts\Auth\GuardInterface;
use ZipPicks\Foundation\Contracts\Http\RequestInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Contracts\Exceptions\HandlerInterface;
use ZipPicks\Foundation\Contracts\Validation\ValidatorInterface;
use ZipPicks\Foundation\Contracts\Logging\LoggerInterface;
use ZipPicks\Foundation\Exceptions\ValidationException;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Models\User;
use Throwable;

if (!function_exists('foundation')) {
    /**
     * Get the foundation container instance
     *
     * @param string|null $abstract
     * @param array<string, mixed> $parameters
     * @return mixed|ContainerInterface
     */
    function foundation(?string $abstract = null, array $parameters = []): mixed
    {
        $container = Foundation::getInstance()->getContainer();
        
        if ($abstract === null) {
            return $container;
        }
        
        return $container->make($abstract, $parameters);
    }
}

if (!function_exists('app')) {
    /**
     * Get the foundation container instance (alias for foundation())
     *
     * @param string|null $abstract
     * @param array<string, mixed> $parameters
     * @return mixed|ContainerInterface
     */
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        return foundation($abstract, $parameters);
    }
}

if (!function_exists('config')) {
    /**
     * Get / set the specified configuration value
     *
     * @param string|array<string, mixed>|null $key
     * @param mixed $default
     * @return mixed
     */
    function config(string|array|null $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return foundation('config');
        }
        
        if (is_array($key)) {
            return foundation('config')->set($key);
        }
        
        return foundation('config')->get($key, $default);
    }
}

if (!function_exists('logger')) {
    /**
     * Get the logger instance
     *
     * @param string|null $channel
     * @return LoggerInterface
     */
    function logger(?string $channel = null): LoggerInterface
    {
        $logger = foundation(LoggerInterface::class);
        
        if ($channel !== null && method_exists($logger, 'channel')) {
            return $logger->channel($channel);
        }
        
        return $logger;
    }
}

if (!function_exists('auth')) {
    /**
     * Get the auth manager instance
     *
     * @param string|null $guard Optional guard name
     * @return AuthManagerInterface|GuardInterface
     */
    function auth(?string $guard = null): AuthManagerInterface|GuardInterface
    {
        $auth = foundation(AuthManagerInterface::class);
        
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
        return auth($name);
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

if (!function_exists('request')) {
    /**
     * Get the request instance
     *
     * @param string|null $key
     * @param mixed $default
     * @return RequestInterface|mixed
     */
    function request(?string $key = null, mixed $default = null): mixed
    {
        $request = foundation('request');
        
        if ($key === null) {
            return $request;
        }
        
        return $request->input($key, $default);
    }
}

if (!function_exists('response')) {
    /**
     * Create a new response instance
     *
     * @param mixed $content
     * @param int $status
     * @param array<string, string> $headers
     * @return Response
     */
    function response(mixed $content = '', int $status = 200, array $headers = []): Response
    {
        $factory = foundation('response');
        return $factory($content, $status, $headers);
    }
}

if (!function_exists('validate')) {
    /**
     * Validate data with given rules
     *
     * @param array<string, mixed> $data Data to validate
     * @param array<string, string|array<int, string>> $rules Validation rules
     * @param array<string, string>|null $messages Custom error messages
     * @param array<string, string>|null $attributes Custom attribute names
     * @return array<string, mixed> Validated data
     * @throws ValidationException
     */
    function validate(
        array $data,
        array $rules,
        ?array $messages = null,
        ?array $attributes = null
    ): array {
        $validator = foundation(ValidatorInterface::class);
        
        if ($messages !== null) {
            $validator->setMessages($messages);
        }
        
        if ($attributes !== null) {
            $validator->setAttributes($attributes);
        }
        
        $isValid = $validator->validate($data, $rules);
        
        if (!$isValid) {
            if (foundation()->has('events')) {
                foundation()->get('events')->dispatch('validation.failed', [
                    'validator' => $validator,
                    'data' => $data,
                    'rules' => $rules,
                    'errors' => $validator->errors(),
                ]);
            }
            
            if (foundation()->has('logger')) {
                foundation()->get('logger')->channel('validation')->notice('Validation failed', [
                    'errors' => $validator->errors(),
                    'input' => array_keys($data),
                ]);
            }
            
            throw ValidationException::withValidator($validator);
        }
        
        return $validator->validated();
    }
}

if (!function_exists('report')) {
    /**
     * Report an exception
     *
     * @param Throwable $exception
     * @return void
     */
    function report(Throwable $exception): void
    {
        if (foundation()->has(HandlerInterface::class)) {
            foundation()->get(HandlerInterface::class)->report($exception);
        } else {
            error_log($exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
        }
    }
}

if (!function_exists('render')) {
    /**
     * Render an exception into an HTTP response
     *
     * @param Throwable $exception
     * @return ResponseInterface|null
     */
    function render(Throwable $exception): ?ResponseInterface
    {
        if (foundation()->has(HandlerInterface::class)) {
            return foundation()->get(HandlerInterface::class)->render($exception);
        }
        
        return null;
    }
}

if (!function_exists('env')) {
    /**
     * Gets the value of an environment variable
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }
        
        if (preg_match('/\A([\'"])(.*)\1\z/', $value, $matches)) {
            return $matches[2];
        }
        
        return $value;
    }
}

if (!function_exists('setting')) {
    /**
     * Get or set a setting value
     *
     * @param string|array<string, mixed>|null $key
     * @param mixed $default
     * @return mixed
     */
    function setting(string|array|null $key = null, mixed $default = null): mixed
    {
        if (foundation()->has('settings')) {
            $settings = foundation()->get('settings');
            
            if ($key === null) {
                return $settings;
            }
            
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    $settings->set($k, $v);
                }
                return null;
            }
            
            return $settings->get($key, $default);
        }
        
        return $default;
    }
}

if (!function_exists('cache')) {
    /**
     * Get the cache instance
     *
     * @param string|null $store
     * @return mixed
     */
    function cache(?string $store = null): mixed
    {
        if (foundation()->has('cache')) {
            $cache = foundation()->get('cache');
            
            if ($store !== null && method_exists($cache, 'store')) {
                return $cache->store($store);
            }
            
            return $cache;
        }
        
        return null;
    }
}

if (!function_exists('event')) {
    /**
     * Dispatch an event
     *
     * @param string|object $event
     * @param mixed $payload
     * @return void
     */
    function event(string|object $event, mixed $payload = null): void
    {
        if (foundation()->has('events')) {
            foundation()->get('events')->dispatch($event, $payload);
        }
    }
}

if (!function_exists('health')) {
    /**
     * Get the health check service or run a health check
     *
     * @param bool|null $check If true, run health check immediately
     * @return mixed
     */
    function health(?bool $check = null): mixed
    {
        if (!foundation()->has('health.service')) {
            return null;
        }
        
        $healthService = foundation()->get('health.service');
        
        if ($check === true) {
            return $healthService->check();
        }
        
        return $healthService;
    }
}

// Include additional helper files
require_once __DIR__ . '/Auth/helpers.php';
require_once __DIR__ . '/Cache/helpers.php';
require_once __DIR__ . '/Http/helpers.php';
require_once __DIR__ . '/Queue/helpers.php';
require_once __DIR__ . '/RateLimiting/helpers.php';