<?php
/**
 * ZipPicks Base API Controller
 * 
 * Base controller class for all API endpoints
 *
 * @package ZipPicks\Foundation\Api\Controllers
 */

namespace ZipPicks\Foundation\Api\Controllers;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Api\Gateway\ResponseTransformer;
use ZipPicks\Foundation\Core\Container;

abstract class ApiController
{
    /**
     * Container instance
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Response transformer
     *
     * @var ResponseTransformer
     */
    protected ResponseTransformer $transformer;

    /**
     * Create new controller instance
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->transformer = $container->make(ResponseTransformer::class);
    }

    /**
     * Return a success response
     *
     * @param mixed $data
     * @param array $meta
     * @param int $code
     * @return Response
     */
    protected function success($data = null, array $meta = [], int $code = 200): Response
    {
        return $this->transformer->success($data, $meta, $code);
    }

    /**
     * Return an error response
     *
     * @param string $message
     * @param int $code
     * @param array $context
     * @return Response
     */
    protected function error(string $message, int $code = 400, array $context = []): Response
    {
        return $this->transformer->error($message, $code, $context, $this->getApiVersion());
    }

    /**
     * Return a paginated response
     *
     * @param array $items
     * @param int $total
     * @param Request $request
     * @return Response
     */
    protected function paginated(array $items, int $total, Request $request): Response
    {
        $page = (int) $request->query->get('page', 1);
        $perPage = (int) $request->query->get('per_page', 20);
        
        // Enforce maximum per page
        $perPage = min($perPage, 100);
        
        return $this->transformer->paginated(
            $items,
            $total,
            $page,
            $perPage,
            $request->path()
        );
    }

    /**
     * Get validated data from request
     *
     * @param Request $request
     * @return array
     */
    protected function validated(Request $request): array
    {
        return $request->attributes->get('validated', []);
    }

    /**
     * Get authenticated user
     *
     * @param Request $request
     * @return \WP_User|null
     */
    protected function user(Request $request): ?\WP_User
    {
        $userId = $request->attributes->get('user_id');
        return $userId ? get_user_by('id', $userId) : null;
    }

    /**
     * Get API version
     *
     * @return string
     */
    protected function getApiVersion(): string
    {
        return request()->attributes->get('api_version', 'v1');
    }

    /**
     * Check if user has permission
     *
     * @param Request $request
     * @param string $capability
     * @return bool
     */
    protected function can(Request $request, string $capability): bool
    {
        $user = $this->user($request);
        return $user && user_can($user, $capability);
    }

    /**
     * Authorize request or fail
     *
     * @param Request $request
     * @param string $capability
     * @return void
     * @throws \Exception
     */
    protected function authorize(Request $request, string $capability): void
    {
        if (!$this->can($request, $capability)) {
            throw new \Exception('Unauthorized', 403);
        }
    }
}