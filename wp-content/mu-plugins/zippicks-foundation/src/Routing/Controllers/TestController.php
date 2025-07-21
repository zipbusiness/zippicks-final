<?php
/**
 * Test Controller Example
 * 
 * @package ZipPicks\Foundation\Routing\Controllers
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ZipPicks\Foundation\Routing\Controllers;

use ZipPicks\Foundation\Contracts\Middleware\RequestInterface;

class TestController
{
    /**
     * Handle the incoming request (invokable controller)
     *
     * @param RequestInterface $request
     * @return array<string, mixed>
     */
    public function __invoke(RequestInterface $request): array
    {
        return [
            'message' => 'Response from TestController',
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
            'timestamp' => date('Y-m-d H:i:s'),
            'context' => $request->getContext()
        ];
    }

    /**
     * Index method example
     *
     * @param RequestInterface $request
     * @return array<string, mixed>
     */
    public function index(RequestInterface $request): array
    {
        return [
            'message' => 'TestController@index',
            'data' => [
                'items' => ['Item 1', 'Item 2', 'Item 3'],
                'total' => 3
            ]
        ];
    }

    /**
     * Show method example
     *
     * @param RequestInterface $request
     * @return array<string, mixed>
     */
    public function show(RequestInterface $request): array
    {
        $id = $request->getContext()['route.id'] ?? 'unknown';
        
        return [
            'message' => 'TestController@show',
            'id' => $id,
            'data' => [
                'name' => 'Test Item ' . $id,
                'description' => 'This is a test item'
            ]
        ];
    }

    /**
     * Store method example
     *
     * @param RequestInterface $request
     * @return array<string, mixed>
     */
    public function store(RequestInterface $request): array
    {
        return [
            'message' => 'TestController@store',
            'status' => 'created',
            'data' => $request->getBodyParams()
        ];
    }
}