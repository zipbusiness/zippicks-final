<?php
/**
 * ZipPicks OpenAPI Documentation Generator
 * 
 * Generates OpenAPI 3.0 specification from API routes and annotations
 * Powers the $100B platform's developer experience
 *
 * @package ZipPicks\Foundation\Api\Documentation
 */

namespace ZipPicks\Foundation\Api\Documentation;

use ZipPicks\Foundation\Api\Gateway\Router;
use ZipPicks\Foundation\Api\Gateway\VersionManager;
use ZipPicks\Foundation\Core\Container;

class OpenApiGenerator
{
    /**
     * API router
     *
     * @var Router
     */
    protected Router $router;

    /**
     * Version manager
     *
     * @var VersionManager
     */
    protected VersionManager $versionManager;

    /**
     * Container instance
     *
     * @var Container
     */
    protected Container $container;

    /**
     * OpenAPI specification
     *
     * @var array
     */
    protected array $spec = [];

    /**
     * API schemas
     *
     * @var array
     */
    protected array $schemas = [];

    /**
     * Create new OpenAPI generator
     *
     * @param Router $router
     * @param VersionManager $versionManager
     * @param Container $container
     */
    public function __construct(Router $router, VersionManager $versionManager, Container $container)
    {
        $this->router = $router;
        $this->versionManager = $versionManager;
        $this->container = $container;
        $this->loadSchemas();
    }

    /**
     * Generate OpenAPI specification
     *
     * @param string $version
     * @return array
     */
    public function generate(string $version = 'v1'): array
    {
        $this->spec = [
            'openapi' => '3.0.3',
            'info' => $this->generateInfo($version),
            'servers' => $this->generateServers(),
            'paths' => $this->generatePaths($version),
            'components' => [
                'schemas' => $this->schemas,
                'securitySchemes' => $this->generateSecuritySchemes(),
                'responses' => $this->generateCommonResponses()
            ],
            'security' => [
                ['apiKey' => []],
                ['bearerAuth' => []]
            ],
            'tags' => $this->generateTags(),
            'externalDocs' => [
                'description' => 'ZipPicks Developer Documentation',
                'url' => 'https://developers.zippicks.com'
            ]
        ];

        return $this->spec;
    }

    /**
     * Generate info section
     *
     * @param string $version
     * @return array
     */
    protected function generateInfo(string $version): array
    {
        $versionInfo = $this->versionManager->getVersionInfo($version);
        
        return [
            'title' => 'ZipPicks API',
            'description' => 'The Taste Layer of the Internet - AI-powered local discovery platform API',
            'version' => $version,
            'termsOfService' => 'https://zippicks.com/terms',
            'contact' => [
                'name' => 'ZipPicks API Support',
                'email' => 'api@zippicks.com',
                'url' => 'https://developers.zippicks.com/support'
            ],
            'license' => [
                'name' => 'Proprietary',
                'url' => 'https://zippicks.com/api-license'
            ],
            'x-api-status' => $versionInfo['status'] ?? 'stable',
            'x-api-deprecated' => $versionInfo['deprecated'] ?? false
        ];
    }

    /**
     * Generate servers section
     *
     * @return array
     */
    protected function generateServers(): array
    {
        $servers = [
            [
                'url' => 'https://api.zippicks.com/v1',
                'description' => 'Production server'
            ]
        ];

        if ($this->container->get('app.debug')) {
            $servers[] = [
                'url' => 'http://localhost:8000/api/v1',
                'description' => 'Development server'
            ];
        }

        return $servers;
    }

    /**
     * Generate paths section
     *
     * @param string $version
     * @return array
     */
    protected function generatePaths(string $version): array
    {
        $paths = [];
        $routes = $this->router->getRoutes();

        foreach ($routes as $route) {
            $path = $route['path'];
            
            // Convert route parameters to OpenAPI format
            $path = preg_replace('/\{([^}]+)\}/', '{$1}', $path);
            
            // Remove version prefix
            $path = preg_replace("#^/api/{$version}#", '', $path);
            
            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }

            $method = strtolower($route['method']);
            $paths[$path][$method] = $this->generateOperation($route, $version);
        }

        return $paths;
    }

    /**
     * Generate operation details
     *
     * @param array $route
     * @param string $version
     * @return array
     */
    protected function generateOperation(array $route, string $version): array
    {
        $operation = [
            'operationId' => $route['name'] ?? $this->generateOperationId($route),
            'summary' => $this->generateSummary($route),
            'description' => $this->generateDescription($route),
            'tags' => $this->getRouteTags($route),
            'parameters' => $this->generateParameters($route),
            'responses' => $this->generateResponses($route)
        ];

        // Add request body for POST/PUT/PATCH
        if (in_array($route['method'], ['POST', 'PUT', 'PATCH'])) {
            $operation['requestBody'] = $this->generateRequestBody($route);
        }

        // Add security requirements
        if ($this->routeRequiresAuth($route)) {
            $operation['security'] = [
                ['apiKey' => []],
                ['bearerAuth' => []]
            ];
        } else {
            $operation['security'] = [];
        }

        return $operation;
    }

    /**
     * Generate operation ID
     *
     * @param array $route
     * @return string
     */
    protected function generateOperationId(array $route): string
    {
        $path = str_replace(['/', '{', '}'], ['_', '', ''], $route['path']);
        return strtolower($route['method']) . $path;
    }

    /**
     * Generate operation summary
     *
     * @param array $route
     * @return string
     */
    protected function generateSummary(array $route): string
    {
        $summaries = [
            'GET:/businesses' => 'List businesses',
            'POST:/businesses' => 'Create a business',
            'GET:/businesses/{id}' => 'Get business details',
            'PUT:/businesses/{id}' => 'Update business',
            'DELETE:/businesses/{id}' => 'Delete business',
            'GET:/reviews' => 'List reviews',
            'POST:/reviews' => 'Create a review',
            'GET:/vibes' => 'List all vibes',
            'GET:/search' => 'Search businesses and reviews',
            'GET:/taste-graph/profile/{user_id}' => 'Get user taste profile',
            'POST:/taste-graph/preferences' => 'Update taste preferences'
        ];

        $key = $route['method'] . ':' . $route['path'];
        return $summaries[$key] ?? ucfirst(strtolower($route['method'])) . ' ' . $route['path'];
    }

    /**
     * Generate operation description
     *
     * @param array $route
     * @return string
     */
    protected function generateDescription(array $route): string
    {
        $descriptions = [
            'GET:/businesses' => 'Retrieve a paginated list of businesses with optional filtering by location, vibes, and other criteria.',
            'POST:/businesses' => 'Create a new business listing. Requires authentication and appropriate permissions.',
            'GET:/vibes' => 'Get all available vibes in the ZipPicks taxonomy. Vibes are mood-based categories that power discovery.',
            'GET:/search' => 'Search across businesses, reviews, and vibes using our AI-powered search engine.',
            'GET:/taste-graph/profile/{user_id}' => 'Retrieve the taste profile for a specific user, including their vibe preferences and recommendation weights.'
        ];

        $key = $route['method'] . ':' . $route['path'];
        return $descriptions[$key] ?? '';
    }

    /**
     * Get route tags
     *
     * @param array $route
     * @return array
     */
    protected function getRouteTags(array $route): array
    {
        $path = $route['path'];
        
        if (str_contains($path, 'businesses')) return ['Businesses'];
        if (str_contains($path, 'reviews')) return ['Reviews'];
        if (str_contains($path, 'vibes')) return ['Vibes'];
        if (str_contains($path, 'taste-graph')) return ['Taste Graph'];
        if (str_contains($path, 'search')) return ['Search'];
        if (str_contains($path, 'analytics')) return ['Analytics'];
        
        return ['General'];
    }

    /**
     * Generate parameters
     *
     * @param array $route
     * @return array
     */
    protected function generateParameters(array $route): array
    {
        $parameters = [];
        
        // Extract path parameters
        preg_match_all('/\{([^}]+)\}/', $route['path'], $matches);
        foreach ($matches[1] as $param) {
            $parameters[] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
                'description' => ucfirst($param) . ' identifier'
            ];
        }

        // Add common query parameters for GET requests
        if ($route['method'] === 'GET' && str_contains($route['path'], '/businesses')) {
            $parameters = array_merge($parameters, [
                [
                    'name' => 'page',
                    'in' => 'query',
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1],
                    'description' => 'Page number for pagination'
                ],
                [
                    'name' => 'per_page',
                    'in' => 'query',
                    'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                    'description' => 'Number of items per page'
                ],
                [
                    'name' => 'zip',
                    'in' => 'query',
                    'schema' => ['type' => 'string', 'pattern' => '^\d{5}$'],
                    'description' => '5-digit ZIP code for location-based filtering'
                ],
                [
                    'name' => 'vibes',
                    'in' => 'query',
                    'schema' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'style' => 'form',
                    'explode' => true,
                    'description' => 'Filter by vibe slugs'
                ]
            ]);
        }

        return $parameters;
    }

    /**
     * Generate responses
     *
     * @param array $route
     * @return array
     */
    protected function generateResponses(array $route): array
    {
        $responses = [];

        // Success response
        if ($route['method'] === 'GET') {
            $responses['200'] = [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => $this->getResponseSchema($route)
                    ]
                ]
            ];
        } elseif ($route['method'] === 'POST') {
            $responses['201'] = [
                'description' => 'Resource created successfully',
                'content' => [
                    'application/json' => [
                        'schema' => $this->getResponseSchema($route)
                    ]
                ]
            ];
        } elseif ($route['method'] === 'DELETE') {
            $responses['204'] = [
                'description' => 'Resource deleted successfully'
            ];
        }

        // Common error responses
        $responses['400'] = ['$ref' => '#/components/responses/BadRequest'];
        $responses['401'] = ['$ref' => '#/components/responses/Unauthorized'];
        $responses['404'] = ['$ref' => '#/components/responses/NotFound'];
        $responses['422'] = ['$ref' => '#/components/responses/ValidationError'];
        $responses['429'] = ['$ref' => '#/components/responses/RateLimitExceeded'];
        $responses['500'] = ['$ref' => '#/components/responses/InternalError'];

        return $responses;
    }

    /**
     * Generate request body
     *
     * @param array $route
     * @return array
     */
    protected function generateRequestBody(array $route): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => $this->getRequestSchema($route)
                ]
            ]
        ];
    }

    /**
     * Get response schema
     *
     * @param array $route
     * @return array
     */
    protected function getResponseSchema(array $route): array
    {
        if (str_contains($route['path'], '/businesses')) {
            if (str_contains($route['path'], '{id}')) {
                return ['$ref' => '#/components/schemas/Business'];
            }
            return [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Business']
                    ],
                    'meta' => ['$ref' => '#/components/schemas/PaginationMeta']
                ]
            ];
        }

        return ['$ref' => '#/components/schemas/ApiResponse'];
    }

    /**
     * Get request schema
     *
     * @param array $route
     * @return array
     */
    protected function getRequestSchema(array $route): array
    {
        if (str_contains($route['path'], '/businesses')) {
            return ['$ref' => '#/components/schemas/CreateBusinessRequest'];
        }
        if (str_contains($route['path'], '/reviews')) {
            return ['$ref' => '#/components/schemas/CreateReviewRequest'];
        }

        return ['type' => 'object'];
    }

    /**
     * Generate security schemes
     *
     * @return array
     */
    protected function generateSecuritySchemes(): array
    {
        return [
            'apiKey' => [
                'type' => 'apiKey',
                'in' => 'header',
                'name' => 'X-API-Key',
                'description' => 'API key for authentication. Get your key at https://developers.zippicks.com'
            ],
            'bearerAuth' => [
                'type' => 'http',
                'scheme' => 'bearer',
                'bearerFormat' => 'JWT',
                'description' => 'OAuth2 bearer token for user authentication'
            ]
        ];
    }

    /**
     * Generate common responses
     *
     * @return array
     */
    protected function generateCommonResponses(): array
    {
        return [
            'BadRequest' => [
                'description' => 'Bad request',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                    ]
                ]
            ],
            'Unauthorized' => [
                'description' => 'Authentication required',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                    ]
                ]
            ],
            'NotFound' => [
                'description' => 'Resource not found',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                    ]
                ]
            ],
            'ValidationError' => [
                'description' => 'Validation error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ValidationErrorResponse']
                    ]
                ]
            ],
            'RateLimitExceeded' => [
                'description' => 'Rate limit exceeded',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/RateLimitResponse']
                    ]
                ]
            ],
            'InternalError' => [
                'description' => 'Internal server error',
                'content' => [
                    'application/json' => [
                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse']
                    ]
                ]
            ]
        ];
    }

    /**
     * Generate tags
     *
     * @return array
     */
    protected function generateTags(): array
    {
        return [
            [
                'name' => 'Businesses',
                'description' => 'Operations related to business listings'
            ],
            [
                'name' => 'Reviews',
                'description' => 'Operations related to reviews and ratings'
            ],
            [
                'name' => 'Vibes',
                'description' => 'Operations related to the vibe taxonomy system'
            ],
            [
                'name' => 'Taste Graph',
                'description' => 'Operations related to user taste profiles and recommendations'
            ],
            [
                'name' => 'Search',
                'description' => 'Search operations across all content types'
            ],
            [
                'name' => 'Analytics',
                'description' => 'Analytics and insights operations (v2+)'
            ]
        ];
    }

    /**
     * Load API schemas
     *
     * @return void
     */
    protected function loadSchemas(): void
    {
        $this->schemas = [
            'Business' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'address' => ['type' => 'string'],
                    'city' => ['type' => 'string'],
                    'state' => ['type' => 'string'],
                    'zip' => ['type' => 'string'],
                    'phone' => ['type' => 'string', 'nullable' => true],
                    'website' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                    'vibes' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/Vibe']
                    ],
                    'master_critic_score' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'maximum' => 10],
                    'pillars' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/ScoringPillar']
                    ],
                    'created_at' => ['type' => 'string', 'format' => 'date-time'],
                    'updated_at' => ['type' => 'string', 'format' => 'date-time']
                ]
            ],
            'Vibe' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'slug' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'category' => ['type' => 'string'],
                    'description' => ['type' => 'string']
                ]
            ],
            'ScoringPillar' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'score' => ['type' => 'number', 'format' => 'float', 'minimum' => 0, 'maximum' => 10],
                    'weight' => ['type' => 'number', 'format' => 'float']
                ]
            ],
            'CreateBusinessRequest' => [
                'type' => 'object',
                'required' => ['name', 'address', 'city', 'state', 'zip'],
                'properties' => [
                    'name' => ['type' => 'string', 'maxLength' => 255],
                    'address' => ['type' => 'string'],
                    'city' => ['type' => 'string'],
                    'state' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 2],
                    'zip' => ['type' => 'string', 'pattern' => '^\d{5}$'],
                    'phone' => ['type' => 'string', 'pattern' => '^\d{10}$', 'nullable' => true],
                    'website' => ['type' => 'string', 'format' => 'uri', 'nullable' => true],
                    'vibes' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'maxItems' => 5
                    ]
                ]
            ],
            'ApiResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'data' => ['type' => 'object', 'nullable' => true],
                    'meta' => ['type' => 'object', 'nullable' => true]
                ]
            ],
            'ErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                            'code' => ['type' => 'integer']
                        ]
                    ]
                ]
            ],
            'ValidationErrorResponse' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/ErrorResponse'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'error' => [
                                'type' => 'object',
                                'properties' => [
                                    'errors' => ['type' => 'object']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'RateLimitResponse' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/ErrorResponse'],
                    [
                        'type' => 'object',
                        'properties' => [
                            'error' => [
                                'type' => 'object',
                                'properties' => [
                                    'retry_after' => ['type' => 'integer']
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'PaginationMeta' => [
                'type' => 'object',
                'properties' => [
                    'total' => ['type' => 'integer'],
                    'per_page' => ['type' => 'integer'],
                    'current_page' => ['type' => 'integer'],
                    'last_page' => ['type' => 'integer'],
                    'from' => ['type' => 'integer'],
                    'to' => ['type' => 'integer']
                ]
            ]
        ];
    }

    /**
     * Check if route requires authentication
     *
     * @param array $route
     * @return bool
     */
    protected function routeRequiresAuth(array $route): bool
    {
        // Public endpoints
        $publicEndpoints = [
            'GET:/businesses',
            'GET:/businesses/{id}',
            'GET:/vibes',
            'GET:/search',
            'GET:/health'
        ];

        $key = $route['method'] . ':' . $route['path'];
        return !in_array($key, $publicEndpoints);
    }

    /**
     * Export specification as JSON
     *
     * @param string $version
     * @return string
     */
    public function toJson(string $version = 'v1'): string
    {
        return json_encode($this->generate($version), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Export specification as YAML
     *
     * @param string $version
     * @return string
     */
    public function toYaml(string $version = 'v1'): string
    {
        // Would require a YAML library like symfony/yaml
        // For now, return JSON
        return $this->toJson($version);
    }
}