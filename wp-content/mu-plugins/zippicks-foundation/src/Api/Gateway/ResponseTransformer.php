<?php
/**
 * ZipPicks API Response Transformer
 * 
 * Transforms API responses based on version and content type
 * Supports JSON, XML, CSV, and custom formats
 *
 * @package ZipPicks\Foundation\Api\Gateway
 */

namespace ZipPicks\Foundation\Api\Gateway;

use ZipPicks\Foundation\Http\Request;
use ZipPicks\Foundation\Http\Response;
use ZipPicks\Foundation\Core\Container;

class ResponseTransformer
{
    /**
     * Container instance
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Response formatters
     *
     * @var array
     */
    protected array $formatters = [];

    /**
     * Default response format
     *
     * @var string
     */
    protected string $defaultFormat = 'json';

    /**
     * Response envelope configuration
     *
     * @var array
     */
    protected array $envelope = [
        'success' => true,
        'data' => null,
        'meta' => [],
        'links' => [],
        'errors' => []
    ];

    /**
     * Create a new response transformer
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->registerDefaultFormatters();
    }

    /**
     * Transform a response
     *
     * @param Response $response
     * @param string $version
     * @param Request $request
     * @return Response
     */
    public function transform(Response $response, string $version, Request $request): Response
    {
        // Get response data
        $data = $response->getData();
        
        // Apply version-specific transformations
        $data = $this->applyVersionTransformations($data, $version);
        
        // Determine response format
        $format = $this->determineFormat($request);
        
        // Format response
        $formatted = $this->format($data, $format, $request);
        
        // Set response content and headers
        $response->setContent($formatted['content']);
        $response->headers->set('Content-Type', $formatted['content_type']);
        
        // Add version deprecation headers if needed
        $this->addDeprecationHeaders($response, $version);
        
        return $response;
    }

    /**
     * Create an error response
     *
     * @param string $message
     * @param int $code
     * @param array $context
     * @param string $version
     * @return Response
     */
    public function error(string $message, int $code = 400, array $context = [], string $version = 'v1'): Response
    {
        $data = [
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'type' => $this->getErrorType($code)
            ]
        ];
        
        if (!empty($context)) {
            $data['error']['context'] = $context;
        }
        
        if ($this->container->get('app.debug')) {
            $data['error']['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }
        
        return new Response($data, $code);
    }

    /**
     * Create a success response
     *
     * @param mixed $data
     * @param array $meta
     * @param int $code
     * @return Response
     */
    public function success($data = null, array $meta = [], int $code = 200): Response
    {
        $response = [
            'success' => true,
            'data' => $data
        ];
        
        if (!empty($meta)) {
            $response['meta'] = $meta;
        }
        
        return new Response($response, $code);
    }

    /**
     * Create a paginated response
     *
     * @param array $items
     * @param int $total
     * @param int $page
     * @param int $perPage
     * @param string $path
     * @return Response
     */
    public function paginated(array $items, int $total, int $page, int $perPage, string $path): Response
    {
        $lastPage = (int) ceil($total / $perPage);
        
        $data = [
            'success' => true,
            'data' => $items,
            'meta' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => $lastPage,
                'from' => ($page - 1) * $perPage + 1,
                'to' => min($page * $perPage, $total)
            ],
            'links' => [
                'self' => $this->buildUrl($path, ['page' => $page]),
                'first' => $this->buildUrl($path, ['page' => 1]),
                'last' => $this->buildUrl($path, ['page' => $lastPage])
            ]
        ];
        
        if ($page > 1) {
            $data['links']['prev'] = $this->buildUrl($path, ['page' => $page - 1]);
        }
        
        if ($page < $lastPage) {
            $data['links']['next'] = $this->buildUrl($path, ['page' => $page + 1]);
        }
        
        return new Response($data);
    }

    /**
     * Apply version-specific transformations
     *
     * @param mixed $data
     * @param string $version
     * @return mixed
     */
    protected function applyVersionTransformations($data, string $version)
    {
        // Version-specific field mappings
        $mappings = [
            'v1' => [
                // V1 uses snake_case
                'fieldStyle' => 'snake_case'
            ],
            'v2' => [
                // V2 uses camelCase
                'fieldStyle' => 'camelCase'
            ]
        ];
        
        if (isset($mappings[$version]['fieldStyle'])) {
            $data = $this->transformFieldStyle($data, $mappings[$version]['fieldStyle']);
        }
        
        return $data;
    }

    /**
     * Transform field naming style
     *
     * @param mixed $data
     * @param string $style
     * @return mixed
     */
    protected function transformFieldStyle($data, string $style)
    {
        if (!is_array($data)) {
            return $data;
        }
        
        $transformed = [];
        foreach ($data as $key => $value) {
            $newKey = $style === 'camelCase' 
                ? $this->toCamelCase($key) 
                : $this->toSnakeCase($key);
            
            $transformed[$newKey] = is_array($value) 
                ? $this->transformFieldStyle($value, $style) 
                : $value;
        }
        
        return $transformed;
    }

    /**
     * Determine response format
     *
     * @param Request $request
     * @return string
     */
    protected function determineFormat(Request $request): string
    {
        // Check format query parameter
        if ($format = $request->query->get('format')) {
            return $format;
        }
        
        // Check Accept header
        $accept = $request->headers->get('Accept', 'application/json');
        
        if (strpos($accept, 'application/json') !== false) {
            return 'json';
        }
        
        if (strpos($accept, 'application/xml') !== false) {
            return 'xml';
        }
        
        if (strpos($accept, 'text/csv') !== false) {
            return 'csv';
        }
        
        return $this->defaultFormat;
    }

    /**
     * Format response data
     *
     * @param mixed $data
     * @param string $format
     * @param Request $request
     * @return array
     */
    protected function format($data, string $format, Request $request): array
    {
        if (!isset($this->formatters[$format])) {
            $format = $this->defaultFormat;
        }
        
        return call_user_func($this->formatters[$format], $data, $request);
    }

    /**
     * Register default formatters
     *
     * @return void
     */
    protected function registerDefaultFormatters(): void
    {
        // JSON formatter
        $this->formatters['json'] = function ($data, Request $request) {
            $options = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
            
            if ($request->query->get('pretty') || $this->container->get('app.debug')) {
                $options |= JSON_PRETTY_PRINT;
            }
            
            return [
                'content' => json_encode($data, $options),
                'content_type' => 'application/json'
            ];
        };
        
        // XML formatter
        $this->formatters['xml'] = function ($data, Request $request) {
            $xml = new \SimpleXMLElement('<?xml version="1.0"?><response></response>');
            $this->arrayToXml($data, $xml);
            
            return [
                'content' => $xml->asXML(),
                'content_type' => 'application/xml'
            ];
        };
        
        // CSV formatter
        $this->formatters['csv'] = function ($data, Request $request) {
            if (!isset($data['data']) || !is_array($data['data'])) {
                throw new \RuntimeException('CSV format requires array data');
            }
            
            $output = fopen('php://temp', 'r+');
            
            // Write headers
            if (!empty($data['data'])) {
                fputcsv($output, array_keys(reset($data['data'])));
            }
            
            // Write data
            foreach ($data['data'] as $row) {
                fputcsv($output, $row);
            }
            
            rewind($output);
            $csv = stream_get_contents($output);
            fclose($output);
            
            return [
                'content' => $csv,
                'content_type' => 'text/csv'
            ];
        };
    }

    /**
     * Convert array to XML
     *
     * @param array $data
     * @param \SimpleXMLElement $xml
     * @return void
     */
    protected function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item' . $key;
            }
            
            if (is_array($value)) {
                $subnode = $xml->addChild($key);
                $this->arrayToXml($value, $subnode);
            } else {
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }

    /**
     * Add deprecation headers
     *
     * @param Response $response
     * @param string $version
     * @return void
     */
    protected function addDeprecationHeaders(Response $response, string $version): void
    {
        $versionManager = $this->container->make(VersionManager::class);
        
        if ($versionManager->isDeprecated($version)) {
            $response->headers->set('X-API-Deprecated', 'true');
            
            if ($sunset = $versionManager->getSunsetDate($version)) {
                $response->headers->set('X-API-Sunset', $sunset);
            }
        }
    }

    /**
     * Get error type from code
     *
     * @param int $code
     * @return string
     */
    protected function getErrorType(int $code): string
    {
        $types = [
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            422 => 'validation_error',
            429 => 'rate_limit_exceeded',
            500 => 'internal_error',
            503 => 'service_unavailable'
        ];
        
        return $types[$code] ?? 'unknown_error';
    }

    /**
     * Build URL with query parameters
     *
     * @param string $path
     * @param array $params
     * @return string
     */
    protected function buildUrl(string $path, array $params): string
    {
        $query = http_build_query($params);
        return $path . ($query ? '?' . $query : '');
    }

    /**
     * Convert string to camelCase
     *
     * @param string $string
     * @return string
     */
    protected function toCamelCase(string $string): string
    {
        $string = str_replace('_', ' ', $string);
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);
        return lcfirst($string);
    }

    /**
     * Convert string to snake_case
     *
     * @param string $string
     * @return string
     */
    protected function toSnakeCase(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }
}