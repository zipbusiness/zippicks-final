<?php
/**
 * ZipPicks API Validation Middleware
 * 
 * Validates API request data and parameters
 *
 * @package ZipPicks\Foundation\Api\Middleware
 */

namespace ZipPicks\Foundation\Api\Middleware;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Middleware\Middleware;
use ZipPicks\Foundation\Validation\Validator;
use ZipPicks\Foundation\Logging\Logger;

class ApiValidation extends Middleware
{
    /**
     * Validator instance
     *
     * @var Validator
     */
    protected Validator $validator;

    /**
     * Logger instance
     *
     * @var Logger
     */
    protected Logger $logger;

    /**
     * Validation rules per endpoint
     *
     * @var array
     */
    protected array $rules = [];

    /**
     * Create new validation middleware
     *
     * @param Validator $validator
     * @param Logger $logger
     */
    public function __construct(Validator $validator, Logger $logger)
    {
        $this->validator = $validator;
        $this->logger = $logger;
        $this->loadValidationRules();
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
        // Get validation rules for endpoint
        $rules = $this->getRulesForEndpoint($request);
        
        if (empty($rules)) {
            // No validation needed
            return $next($request);
        }
        
        // Combine all request data
        $data = array_merge(
            $request->query->all(),
            $request->request->all(),
            $request->attributes->all()
        );
        
        // Validate data
        $validation = $this->validator->make($data, $rules);
        
        if ($validation->fails()) {
            $this->logger->warning('API validation failed', [
                'path' => $request->path(),
                'errors' => $validation->errors()->all(),
                'data' => $data
            ]);
            
            return $this->validationErrorResponse($validation->errors()->toArray());
        }
        
        // Add validated data to request
        $request->attributes->set('validated', $validation->validated());
        
        return $next($request);
    }

    /**
     * Load validation rules
     *
     * @return void
     */
    protected function loadValidationRules(): void
    {
        // Business endpoints
        $this->rules['GET:/api/v1/businesses'] = [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'zip' => 'string|regex:/^\d{5}$/',
            'radius' => 'numeric|min:0.1|max:50',
            'vibes' => 'array',
            'vibes.*' => 'string|exists:vibes,slug'
        ];
        
        $this->rules['POST:/api/v1/businesses'] = [
            'name' => 'required|string|max:255',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string|size:2',
            'zip' => 'required|string|regex:/^\d{5}$/',
            'phone' => 'string|regex:/^\d{10}$/',
            'website' => 'url',
            'vibes' => 'array|max:5',
            'vibes.*' => 'string|exists:vibes,slug'
        ];
        
        // Review endpoints
        $this->rules['POST:/api/v1/reviews'] = [
            'business_id' => 'required|integer|exists:businesses,id',
            'rating' => 'required|numeric|min:0|max:10',
            'pillars' => 'required|array|size:6',
            'pillars.*.name' => 'required|string',
            'pillars.*.score' => 'required|numeric|min:0|max:10',
            'content' => 'required|string|min:50|max:5000'
        ];
        
        // Search endpoints
        $this->rules['GET:/api/v1/search'] = [
            'q' => 'required|string|min:2|max:100',
            'zip' => 'string|regex:/^\d{5}$/',
            'type' => 'string|in:business,review,vibe',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50'
        ];
        
        // Taste Graph endpoints
        $this->rules['POST:/api/v1/taste-graph/preferences'] = [
            'vibes' => 'required|array|min:3|max:10',
            'vibes.*' => 'string|exists:vibes,slug',
            'cuisines' => 'array',
            'cuisines.*' => 'string|in:italian,mexican,chinese,japanese,thai,indian,american,french',
            'price_range' => 'array|size:2',
            'price_range.0' => 'integer|min:1|max:4',
            'price_range.1' => 'integer|min:1|max:4|gte:price_range.0'
        ];
    }

    /**
     * Get validation rules for endpoint
     *
     * @param Request $request
     * @return array
     */
    protected function getRulesForEndpoint(Request $request): array
    {
        $key = $request->method() . ':' . $request->path();
        
        // Try exact match first
        if (isset($this->rules[$key])) {
            return $this->rules[$key];
        }
        
        // Try pattern matching for dynamic routes
        foreach ($this->rules as $pattern => $rules) {
            if ($this->matchesPattern($key, $pattern)) {
                return $rules;
            }
        }
        
        return [];
    }

    /**
     * Check if key matches pattern
     *
     * @param string $key
     * @param string $pattern
     * @return bool
     */
    protected function matchesPattern(string $key, string $pattern): bool
    {
        // Convert route pattern to regex
        $regex = preg_replace('/\{[^}]+\}/', '[^/]+', $pattern);
        $regex = str_replace('/', '\/', $regex);
        $regex = '/^' . $regex . '$/';
        
        return preg_match($regex, $key) === 1;
    }

    /**
     * Create validation error response
     *
     * @param array $errors
     * @return Response
     */
    protected function validationErrorResponse(array $errors): Response
    {
        return new Response([
            'error' => [
                'type' => 'validation_error',
                'message' => 'The given data was invalid.',
                'errors' => $errors
            ]
        ], 422);
    }
}