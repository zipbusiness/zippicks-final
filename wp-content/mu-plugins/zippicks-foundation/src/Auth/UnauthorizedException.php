<?php
/**
 * Unauthorized Exception
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
 * Exception thrown when user lacks required permissions
 *
 * @since 1.0.0
 */
class UnauthorizedException extends FoundationException implements RenderableExceptionInterface
{
    /**
     * Required ability that was denied
     *
     * @var string|null
     */
    protected ?string $ability = null;

    /**
     * Arguments passed to the ability check
     *
     * @var array<int, mixed>
     */
    protected array $arguments = [];

    /**
     * Constructor
     *
     * @param string $message
     * @param string|null $ability
     * @param array<int, mixed> $arguments
     */
    public function __construct(
        string $message = 'This action is unauthorized.',
        ?string $ability = null,
        array $arguments = []
    ) {
        parent::__construct($message, 403);
        $this->ability = $ability;
        $this->arguments = $arguments;
        $this->statusCode = 403;
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
            $data = [
                'message' => $this->getMessage(),
                'success' => false,
                'error' => 'unauthorized',
            ];

            if ($this->ability !== null) {
                $data['ability'] = $this->ability;
            }

            return $response->json($data, $this->statusCode);
        }

        // For web requests, show error page
        $html = $this->renderErrorPage();
        
        return $response->html($html, $this->statusCode);
    }

    /**
     * Render error page HTML
     *
     * @return string
     */
    protected function renderErrorPage(): string
    {
        $message = htmlspecialchars($this->getMessage());
        $ability = $this->ability ? htmlspecialchars($this->ability) : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Forbidden</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 2rem;
            background: #f5f5f5;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }
        h1 {
            margin: 0 0 1rem 0;
            color: #e74c3c;
            font-size: 3rem;
        }
        p {
            margin: 0.5rem 0;
            line-height: 1.6;
            color: #666;
        }
        .back-link {
            display: inline-block;
            margin-top: 2rem;
            padding: 0.75rem 1.5rem;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.2s;
        }
        .back-link:hover {
            background: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>403</h1>
        <h2>Access Forbidden</h2>
        <p>{$message}</p>
        <a href="javascript:history.back()" class="back-link">Go Back</a>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Set the ability that was denied
     *
     * @param string $ability
     * @param array<int, mixed> $arguments
     * @return self
     */
    public function forAbility(string $ability, array $arguments = []): self
    {
        $this->ability = $ability;
        $this->arguments = $arguments;
        return $this;
    }

    /**
     * Don't report this exception by default
     *
     * @return bool
     */
    public function report(): bool
    {
        return false;
    }
}