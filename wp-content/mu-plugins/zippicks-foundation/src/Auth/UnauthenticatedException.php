<?php
/**
 * Unauthenticated Exception
 *
 * @package ZipPicks\Foundation\Auth
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Auth;

use ZipPicks\Foundation\Exceptions\FoundationException;
use ZipPicks\Foundation\Exceptions\RenderableExceptionInterface;
use ZipPicks\Foundation\Contracts\Http\RequestInterface;
use ZipPicks\Foundation\Contracts\Http\ResponseInterface;
use ZipPicks\Foundation\Http\Response;

/**
 * Exception thrown when authentication is required
 *
 * @since 1.0.0
 */
class UnauthenticatedException extends FoundationException implements RenderableExceptionInterface
{
    /**
     * Guards that were checked
     *
     * @var array<int, string>
     */
    protected array $guards = [];

    /**
     * Redirect path
     *
     * @var string|null
     */
    protected ?string $redirectTo = null;

    /**
     * Constructor
     *
     * @param string $message
     * @param array<int, string> $guards
     * @param string|null $redirectTo
     */
    public function __construct(
        string $message = 'Unauthenticated.',
        array $guards = [],
        ?string $redirectTo = null
    ) {
        parent::__construct($message, 401);
        $this->guards = $guards;
        $this->redirectTo = $redirectTo;
        $this->statusCode = 401;
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

        // For JSON/AJAX requests, return JSON error
        if ($request->expectsJson() || $request->isAjax()) {
            return $response->json([
                'message' => $this->getMessage(),
                'success' => false,
                'error' => 'unauthenticated',
                'guards' => $this->guards,
            ], $this->statusCode);
        }

        // For web requests, redirect to login
        $redirectUrl = $this->getRedirectUrl($request);
        
        return $response
            ->setStatus(302, 'Found')
            ->header('Location', $redirectUrl)
            ->html('');
    }

    /**
     * Get the redirect URL
     *
     * @param RequestInterface $request
     * @return string
     */
    protected function getRedirectUrl(RequestInterface $request): string
    {
        // Use custom redirect if set
        if ($this->redirectTo !== null) {
            return $this->redirectTo;
        }

        // Get WordPress login URL
        $loginUrl = wp_login_url($request->fullUrl());

        // Add context for better UX
        $context = $request->context();
        if (isset($context['is_admin']) && $context['is_admin']) {
            $loginUrl = admin_url('admin.php?page=login&redirect_to=' . urlencode($request->fullUrl()));
        }

        return $loginUrl;
    }

    /**
     * Set the guards that were checked
     *
     * @param array<int, string> $guards
     * @return self
     */
    public function guards(array $guards): self
    {
        $this->guards = $guards;
        return $this;
    }

    /**
     * Set the redirect path
     *
     * @param string $redirectTo
     * @return self
     */
    public function redirectTo(string $redirectTo): self
    {
        $this->redirectTo = $redirectTo;
        return $this;
    }

    /**
     * Don't report this exception
     *
     * @return bool
     */
    public function report(): bool
    {
        return false;
    }
}