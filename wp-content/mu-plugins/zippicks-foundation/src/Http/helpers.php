<?php
/**
 * HTTP Helper Functions
 * 
 * @package ZipPicks\Foundation\Http
 * @since 1.0.0
 */

declare(strict_types=1);

use ZipPicks\Foundation\Contracts\Http\RequestInterface;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Contracts\Exceptions\HandlerInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Core\Foundation;
use ZipPicks\Foundation\Contracts\Validation\ValidatorInterface;
use ZipPicks\Foundation\Exceptions\ValidationException;
use Throwable;

if (!function_exists('foundation')) {
    /**
     * Get the foundation container instance
     *
     * @return \ZipPicks\Foundation\Contracts\Container\ContainerInterface
     */
    function foundation(): \ZipPicks\Foundation\Contracts\Container\ContainerInterface
    {
        return Foundation::getInstance()->getContainer();
    }
}

if (!function_exists('request')) {
    /**
     * Get the request instance
     *
     * @param ?string $key
     * @param mixed $default
     * @return RequestInterface|mixed
     */
    function request(?string $key = null, mixed $default = null): mixed
    {
        $request = foundation()->get('request');
        
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
        $factory = foundation()->get('response');
        return $factory($content, $status, $headers);
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
            // Fallback to error_log if handler not available
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
        $validator = foundation()->get(ValidatorInterface::class);
        
        if ($messages !== null) {
            $validator->setMessages($messages);
        }
        
        if ($attributes !== null) {
            $validator->setAttributes($attributes);
        }
        
        $isValid = $validator->validate($data, $rules);
        
        if (!$isValid) {
            // Fire validation failed event
            if (foundation()->has('events')) {
                foundation()->get('events')->dispatch('validation.failed', [
                    'validator' => $validator,
                    'data' => $data,
                    'rules' => $rules,
                    'errors' => $validator->errors(),
                ]);
            }
            
            // Log validation failure
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