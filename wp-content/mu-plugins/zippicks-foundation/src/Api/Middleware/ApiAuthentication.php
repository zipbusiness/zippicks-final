<?php
/**
 * ZipPicks API Authentication Middleware
 * 
 * Handles API authentication via API keys and OAuth tokens
 *
 * @package ZipPicks\Foundation\Api\Middleware
 */

namespace ZipPicks\Foundation\Api\Middleware;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Middleware\Middleware;
use ZipPicks\Foundation\Auth\AuthManager;
use ZipPicks\Foundation\Api\Keys\ApiKeyManager;
use ZipPicks\Foundation\Api\Exceptions\InvalidApiKeyException;
use ZipPicks\Foundation\Logging\Logger;

class ApiAuthentication extends Middleware
{
    /**
     * Auth manager
     *
     * @var AuthManager
     */
    protected AuthManager $auth;

    /**
     * API key manager
     *
     * @var ApiKeyManager
     */
    protected ApiKeyManager $keyManager;

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Create new authentication middleware
     *
     * @param AuthManager $auth
     * @param ApiKeyManager $keyManager
     * @param Logger $logger
     */
    public function __construct(AuthManager $auth, ApiKeyManager $keyManager, Logger $logger)
    {
        $this->auth = $auth;
        $this->keyManager = $keyManager;
        $this->logger = $logger;
    }

    /**
     * Handle the request
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, \Closure $next): Response
    {
        try {
            // Check for API key authentication
            if ($apiKey = $this->extractApiKey($request)) {
                return $this->authenticateWithApiKey($request, $apiKey, $next);
            }

            // Check for OAuth token authentication
            if ($token = $this->extractBearerToken($request)) {
                return $this->authenticateWithToken($request, $token, $next);
            }

            // Check if route requires authentication
            if ($this->routeRequiresAuth($request)) {
                throw new InvalidApiKeyException('Authentication required');
            }

            // Continue without authentication for public routes
            return $next($request);

        } catch (InvalidApiKeyException $e) {
            $this->logger->warning('API authentication failed', [
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'path' => $request->path()
            ]);

            return new Response([
                'error' => [
                    'type' => 'authentication_failed',
                    'message' => $e->getMessage()
                ]
            ], 401, ['WWW-Authenticate' => 'Bearer']);
        }
    }

    /**
     * Extract API key from request
     *
     * @param Request $request
     * @return string|null
     */
    protected function extractApiKey(Request $request): ?string
    {
        // Check X-API-Key header
        if ($request->headers->has('X-API-Key')) {
            return $request->headers->get('X-API-Key');
        }

        // Check query parameter
        if ($request->query->has('api_key')) {
            return $request->query->get('api_key');
        }

        return null;
    }

    /**
     * Extract bearer token from request
     *
     * @param Request $request
     * @return string|null
     */
    protected function extractBearerToken(Request $request): ?string
    {
        $authorization = $request->headers->get('Authorization', '');
        
        if (preg_match('/Bearer\s+(.+)/', $authorization, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Authenticate with API key
     *
     * @param Request $request
     * @param string $apiKey
     * @param \Closure $next
     * @return Response
     * @throws InvalidApiKeyException
     */
    protected function authenticateWithApiKey(Request $request, string $apiKey, \Closure $next): Response
    {
        // Validate API key
        $keyData = $this->keyManager->validate($apiKey);
        
        if (!$keyData) {
            throw new InvalidApiKeyException('Invalid API key');
        }

        // Check if key is expired
        if ($keyData->expires_at && strtotime($keyData->expires_at) < time()) {
            throw new InvalidApiKeyException('API key has expired');
        }

        // Set authentication data on request
        $request->attributes->set('api_key', $keyData);
        $request->attributes->set('api_key_id', $keyData->id);
        $request->attributes->set('api_tier', $keyData->tier);
        $request->attributes->set('user_id', $keyData->user_id);

        // Update last used timestamp
        $this->keyManager->updateLastUsed($keyData->id);

        // Log successful authentication
        $this->logger->info('API key authentication successful', [
            'api_key_id' => $keyData->id,
            'tier' => $keyData->tier,
            'path' => $request->path()
        ]);

        return $next($request);
    }

    /**
     * Authenticate with OAuth token
     *
     * @param Request $request
     * @param string $token
     * @param \Closure $next
     * @return Response
     * @throws InvalidApiKeyException
     */
    protected function authenticateWithToken(Request $request, string $token, \Closure $next): Response
    {
        // Validate OAuth token
        $user = $this->auth->validateToken($token);
        
        if (!$user) {
            throw new InvalidApiKeyException('Invalid access token');
        }

        // Set authentication data on request
        $request->attributes->set('authenticated_user', $user);
        $request->attributes->set('user_id', $user->ID);
        $request->attributes->set('auth_method', 'oauth');

        // Log successful authentication
        $this->logger->info('OAuth authentication successful', [
            'user_id' => $user->ID,
            'path' => $request->path()
        ]);

        return $next($request);
    }

    /**
     * Check if route requires authentication
     *
     * @param Request $request
     * @return bool
     */
    protected function routeRequiresAuth(Request $request): bool
    {
        // Public endpoints that don't require authentication
        $publicEndpoints = [
            '/api/v1/health',
            '/api/v1/vibes',
            '/api/v1/businesses',
            '/api/v1/search'
        ];

        $path = $request->path();

        // Check if endpoint is public
        foreach ($publicEndpoints as $endpoint) {
            if (str_starts_with($path, $endpoint)) {
                // Some methods on public endpoints still require auth
                if (in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
                    return true;
                }
                return false;
            }
        }

        // All other endpoints require authentication
        return true;
    }
}