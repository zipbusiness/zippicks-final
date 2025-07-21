<?php
/**
 * Validation Exception
 *
 * @package ZipPicks\Foundation\Exceptions
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Exceptions;

use ZipPicks\Foundation\Contracts\Http\RequestInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Contracts\Validation\ValidatorInterface;
use ZipPicks\Foundation\Http\Response;

/**
 * Exception thrown when validation fails
 *
 * @since 1.0.0
 */
class ValidationException extends FoundationException implements RenderableExceptionInterface
{
    /**
     * Validation errors
     *
     * @var array<string, array<int, string>>
     */
    protected array $errors = [];

    /**
     * Redirect URL
     *
     * @var string|null
     */
    protected ?string $redirectTo = null;

    /**
     * Error bag name
     *
     * @var string
     */
    protected string $errorBag = 'default';

    /**
     * Constructor
     *
     * @param ValidatorInterface|array<string, array<int, string>> $validator Validator instance or errors array
     * @param string $message Exception message
     * @param string|null $redirectTo Redirect URL for web requests
     */
    public function __construct(
        ValidatorInterface|array $validator,
        string $message = 'The given data was invalid.',
        ?string $redirectTo = null
    ) {
        parent::__construct($message, 422);
        
        if ($validator instanceof ValidatorInterface) {
            $this->errors = $validator->errors();
        } else {
            $this->errors = $validator;
        }
        
        $this->redirectTo = $redirectTo;
        $this->statusCode = 422;
    }

    /**
     * Render the exception
     *
     * @param RequestInterface $request
     * @return ResponseInterface|null
     */
    public function render(RequestInterface $request): ?ResponseInterface
    {
        $response = new Response();

        // For JSON/AJAX requests, return JSON error response
        if ($request->expectsJson() || $request->isAjax()) {
            return $this->renderJsonResponse($response);
        }

        // For web requests, redirect back with errors
        return $this->renderRedirectResponse($request, $response);
    }

    /**
     * Render JSON validation response
     *
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function renderJsonResponse(ResponseInterface $response): ResponseInterface
    {
        $data = [
            'message' => $this->getMessage(),
            'errors' => $this->errors,
            'success' => false,
            'error_bag' => $this->errorBag,
        ];

        // Add debug info if WP_DEBUG is enabled
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $data['debug'] = [
                'file' => $this->getFile(),
                'line' => $this->getLine(),
            ];
        }

        return $response->json($data, $this->statusCode);
    }

    /**
     * Render redirect response with errors
     *
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     */
    protected function renderRedirectResponse(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Store errors in transient for next request
        $errorKey = 'validation_errors_' . wp_generate_uuid4();
        set_transient($errorKey, [
            'errors' => $this->errors,
            'old_input' => $request->all(),
            'error_bag' => $this->errorBag,
        ], 60); // Expire after 1 minute

        // Determine redirect URL
        $redirectUrl = $this->getRedirectUrl($request);
        
        // Add error key to URL
        $separator = strpos($redirectUrl, '?') !== false ? '&' : '?';
        $redirectUrl .= $separator . 'validation_errors=' . $errorKey;

        return $response
            ->setStatus(302, 'Found')
            ->header('Location', $redirectUrl)
            ->html('');
    }

    /**
     * Get redirect URL
     *
     * @param RequestInterface $request
     * @return string
     */
    protected function getRedirectUrl(RequestInterface $request): string
    {
        if ($this->redirectTo !== null) {
            return $this->redirectTo;
        }

        // Get referrer or fallback to current URL
        $referrer = $request->header('Referer');
        if ($referrer && is_string($referrer)) {
            return $referrer;
        }

        // Fallback to current URL without query params
        return $request->url();
    }

    /**
     * Get validation errors
     *
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Set the redirect URL
     *
     * @param string $url
     * @return self
     */
    public function redirectTo(string $url): self
    {
        $this->redirectTo = $url;
        return $this;
    }

    /**
     * Set the error bag name
     *
     * @param string $errorBag
     * @return self
     */
    public function errorBag(string $errorBag): self
    {
        $this->errorBag = $errorBag;
        return $this;
    }

    /**
     * Don't report validation exceptions by default
     *
     * @return bool
     */
    public function report(): bool
    {
        return false;
    }

    /**
     * Create from validator
     *
     * @param ValidatorInterface $validator
     * @param string|null $redirectTo
     * @return static
     */
    public static function withValidator(ValidatorInterface $validator, ?string $redirectTo = null): static
    {
        return new static($validator, 'The given data was invalid.', $redirectTo);
    }

    /**
     * Create with custom message
     *
     * @param ValidatorInterface $validator
     * @param string $message
     * @param string|null $redirectTo
     * @return static
     */
    public static function withMessage(ValidatorInterface $validator, string $message, ?string $redirectTo = null): static
    {
        return new static($validator, $message, $redirectTo);
    }
}