<?php
/**
 * ZipPicks PHP SDK Generator
 * 
 * Generates enterprise-grade PHP client libraries from OpenAPI specifications
 * Supports PSR-4 autoloading, Guzzle HTTP client, and comprehensive error handling
 *
 * @package ZipPicks\Foundation\Api\SDK\Generators
 */

namespace ZipPicks\Foundation\Api\SDK\Generators;

use ZipPicks\Foundation\Logging\EnterpriseLogger;

class PhpSdkGenerator
{
    /**
     * Generator version
     */
    const VERSION = '1.0.0';

    /**
     * Configuration
     *
     * @var array
     */
    protected array $config;

    /**
     * Logger instance
     *
     * @var EnterpriseLogger
     */
    protected EnterpriseLogger $logger;

    /**
     * Generated files
     *
     * @var array
     */
    protected array $files = [];

    /**
     * Create PHP SDK generator
     *
     * @param array $config
     * @param EnterpriseLogger $logger
     */
    public function __construct(array $config, EnterpriseLogger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /**
     * Generate PHP SDK from OpenAPI specification
     *
     * @param array $openApiSpec
     * @param string $version
     * @param array $options
     * @return array
     */
    public function generate(array $openApiSpec, string $version, array $options = []): array
    {
        $this->logger->info('Generating PHP SDK', [
            'version' => $version,
            'options' => $options
        ]);

        $this->files = [];

        try {
            // Generate main client class
            $this->generateClient($openApiSpec, $version);
            
            // Generate resource classes
            $this->generateResources($openApiSpec, $version);
            
            // Generate model classes
            $this->generateModels($openApiSpec, $version);
            
            // Generate exception classes
            $this->generateExceptions($openApiSpec, $version);
            
            // Generate configuration
            $this->generateConfiguration($openApiSpec, $version);
            
            // Generate composer.json
            $this->generateComposerJson($openApiSpec, $version);
            
            // Generate README and documentation
            $this->generateDocumentation($openApiSpec, $version);
            
            // Generate tests
            $this->generateTests($openApiSpec, $version);

            return [
                'success' => true,
                'language' => 'php',
                'version' => $version,
                'files' => $this->files,
                'package_name' => 'zippicks/php-sdk'
            ];

        } catch (\Exception $e) {
            $this->logger->error('PHP SDK generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Generate main client class
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateClient(array $openApiSpec, string $version): void
    {
        $className = 'ZipPicksClient';
        $resources = $this->extractResources($openApiSpec);
        
        $content = $this->renderTemplate('client.php.twig', [
            'className' => $className,
            'namespace' => 'ZipPicks\\SDK',
            'version' => $version,
            'resources' => $resources,
            'config' => $this->config,
            'openApiSpec' => $openApiSpec
        ]);

        $this->files["src/{$className}.php"] = $content;
    }

    /**
     * Generate resource classes
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateResources(array $openApiSpec, string $version): void
    {
        $resources = $this->extractResources($openApiSpec);

        foreach ($resources as $resource) {
            $className = $resource['className'];
            $methods = $resource['methods'];

            $content = $this->renderTemplate('resource.php.twig', [
                'className' => $className,
                'namespace' => 'ZipPicks\\SDK\\Resources',
                'methods' => $methods,
                'resource' => $resource,
                'config' => $this->config
            ]);

            $this->files["src/Resources/{$className}.php"] = $content;
        }
    }

    /**
     * Generate model classes
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateModels(array $openApiSpec, string $version): void
    {
        $schemas = $openApiSpec['components']['schemas'] ?? [];

        foreach ($schemas as $schemaName => $schema) {
            if ($this->isModelSchema($schema)) {
                $className = $this->pascalCase($schemaName);
                $properties = $this->extractModelProperties($schema);

                $content = $this->renderTemplate('model.php.twig', [
                    'className' => $className,
                    'namespace' => 'ZipPicks\\SDK\\Models',
                    'properties' => $properties,
                    'schema' => $schema,
                    'config' => $this->config
                ]);

                $this->files["src/Models/{$className}.php"] = $content;
            }
        }
    }

    /**
     * Generate exception classes
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateExceptions(array $openApiSpec, string $version): void
    {
        $exceptions = [
            'ZipPicksException' => 'Base exception class',
            'ApiException' => 'API error exception',
            'AuthenticationException' => 'Authentication failed exception',
            'ValidationException' => 'Validation error exception',
            'RateLimitException' => 'Rate limit exceeded exception',
            'NotFoundException' => 'Resource not found exception',
            'ServerException' => 'Server error exception'
        ];

        foreach ($exceptions as $className => $description) {
            $content = $this->renderTemplate('exception.php.twig', [
                'className' => $className,
                'namespace' => 'ZipPicks\\SDK\\Exceptions',
                'description' => $description,
                'config' => $this->config
            ]);

            $this->files["src/Exceptions/{$className}.php"] = $content;
        }
    }

    /**
     * Generate configuration class
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateConfiguration(array $openApiSpec, string $version): void
    {
        $content = $this->renderTemplate('configuration.php.twig', [
            'className' => 'Configuration',
            'namespace' => 'ZipPicks\\SDK',
            'config' => $this->config,
            'version' => $version,
            'servers' => $openApiSpec['servers'] ?? []
        ]);

        $this->files['src/Configuration.php'] = $content;
    }

    /**
     * Generate composer.json
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateComposerJson(array $openApiSpec, string $version): void
    {
        $composerData = [
            'name' => 'zippicks/php-sdk',
            'description' => 'Official PHP SDK for the ZipPicks API - The Taste Layer of the Internet',
            'type' => 'library',
            'keywords' => ['zippicks', 'api', 'sdk', 'local-discovery', 'taste-graph'],
            'homepage' => 'https://developers.zippicks.com',
            'license' => 'MIT',
            'authors' => [
                [
                    'name' => 'ZipPicks',
                    'email' => $this->config['contact_email'],
                    'homepage' => 'https://zippicks.com'
                ]
            ],
            'require' => [
                'php' => '^8.0',
                'guzzlehttp/guzzle' => '^7.0',
                'guzzlehttp/psr7' => '^2.0',
                'psr/http-message' => '^1.0',
                'psr/log' => '^1.0'
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^9.0',
                'squizlabs/php_codesniffer' => '^3.0',
                'phpstan/phpstan' => '^1.0'
            ],
            'autoload' => [
                'psr-4' => [
                    'ZipPicks\\SDK\\' => 'src/'
                ]
            ],
            'autoload-dev' => [
                'psr-4' => [
                    'ZipPicks\\SDK\\Tests\\' => 'tests/'
                ]
            ],
            'scripts' => [
                'test' => 'phpunit',
                'phpcs' => 'phpcs src tests --standard=PSR12',
                'phpstan' => 'phpstan analyse src --level=max'
            ],
            'config' => [
                'sort-packages' => true
            ],
            'minimum-stability' => 'stable',
            'prefer-stable' => true
        ];

        $this->files['composer.json'] = json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate documentation
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateDocumentation(array $openApiSpec, string $version): void
    {
        // Generate README.md
        $readme = $this->renderTemplate('README.md.twig', [
            'config' => $this->config,
            'version' => $version,
            'spec' => $openApiSpec
        ]);
        $this->files['README.md'] = $readme;

        // Generate CHANGELOG.md
        $changelog = $this->renderTemplate('CHANGELOG.md.twig', [
            'config' => $this->config,
            'version' => $version
        ]);
        $this->files['CHANGELOG.md'] = $changelog;

        // Generate examples
        $examples = $this->generateExamples($openApiSpec);
        foreach ($examples as $filename => $content) {
            $this->files["examples/{$filename}"] = $content;
        }
    }

    /**
     * Generate tests
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateTests(array $openApiSpec, string $version): void
    {
        // Generate unit tests for each resource
        $resources = $this->extractResources($openApiSpec);
        
        foreach ($resources as $resource) {
            $className = $resource['className'];
            $content = $this->renderTemplate('resource-test.php.twig', [
                'className' => $className,
                'namespace' => 'ZipPicks\\SDK\\Tests',
                'resource' => $resource,
                'config' => $this->config
            ]);

            $this->files["tests/{$className}Test.php"] = $content;
        }

        // Generate phpunit.xml
        $phpunitXml = $this->renderTemplate('phpunit.xml.twig', [
            'config' => $this->config
        ]);
        $this->files['phpunit.xml'] = $phpunitXml;
    }

    /**
     * Extract resources from OpenAPI spec
     *
     * @param array $openApiSpec
     * @return array
     */
    protected function extractResources(array $openApiSpec): array
    {
        $resources = [];
        $paths = $openApiSpec['paths'] ?? [];

        foreach ($paths as $path => $pathItem) {
            foreach ($pathItem as $method => $operation) {
                if (!is_array($operation)) continue;

                $tags = $operation['tags'] ?? ['General'];
                $tag = $tags[0];
                
                if (!isset($resources[$tag])) {
                    $resources[$tag] = [
                        'name' => $tag,
                        'className' => $this->pascalCase($tag),
                        'methods' => []
                    ];
                }

                $resources[$tag]['methods'][] = [
                    'name' => $operation['operationId'] ?? $this->generateMethodName($method, $path),
                    'method' => strtoupper($method),
                    'path' => $path,
                    'summary' => $operation['summary'] ?? '',
                    'description' => $operation['description'] ?? '',
                    'parameters' => $operation['parameters'] ?? [],
                    'requestBody' => $operation['requestBody'] ?? null,
                    'responses' => $operation['responses'] ?? []
                ];
            }
        }

        return array_values($resources);
    }

    /**
     * Extract model properties from schema
     *
     * @param array $schema
     * @return array
     */
    protected function extractModelProperties(array $schema): array
    {
        $properties = [];
        $schemaProps = $schema['properties'] ?? [];

        foreach ($schemaProps as $propName => $propSchema) {
            $properties[] = [
                'name' => $propName,
                'type' => $this->getPhpType($propSchema),
                'description' => $propSchema['description'] ?? '',
                'required' => in_array($propName, $schema['required'] ?? []),
                'schema' => $propSchema
            ];
        }

        return $properties;
    }

    /**
     * Generate examples
     *
     * @param array $openApiSpec
     * @return array
     */
    protected function generateExamples(array $openApiSpec): array
    {
        $examples = [];

        // Basic usage example
        $examples['basic-usage.php'] = $this->renderTemplate('example-basic.php.twig', [
            'config' => $this->config,
            'spec' => $openApiSpec
        ]);

        // Authentication example
        $examples['authentication.php'] = $this->renderTemplate('example-auth.php.twig', [
            'config' => $this->config
        ]);

        // Error handling example
        $examples['error-handling.php'] = $this->renderTemplate('example-errors.php.twig', [
            'config' => $this->config
        ]);

        return $examples;
    }

    /**
     * Render template
     *
     * @param string $template
     * @param array $variables
     * @return string
     */
    protected function renderTemplate(string $template, array $variables): string
    {
        // For now, generate basic PHP code directly
        // In a full implementation, this would use Twig or another template engine
        
        if ($template === 'client.php.twig') {
            return $this->generateClientCode($variables);
        } elseif ($template === 'resource.php.twig') {
            return $this->generateResourceCode($variables);
        } elseif ($template === 'model.php.twig') {
            return $this->generateModelCode($variables);
        } elseif ($template === 'exception.php.twig') {
            return $this->generateExceptionCode($variables);
        } elseif ($template === 'configuration.php.twig') {
            return $this->generateConfigurationCode($variables);
        } elseif ($template === 'README.md.twig') {
            return $this->generateReadmeCode($variables);
        }

        return "// Template: {$template}\n// Variables: " . json_encode($variables, JSON_PRETTY_PRINT);
    }

    /**
     * Generate client code
     *
     * @param array $variables
     * @return string
     */
    protected function generateClientCode(array $variables): string
    {
        $resources = $variables['resources'];
        $resourceProperties = '';
        $resourceMethods = '';

        foreach ($resources as $resource) {
            $propName = $this->camelCase($resource['name']);
            $className = $resource['className'];
            
            $resourceProperties .= "    protected \\ZipPicks\\SDK\\Resources\\{$className} \${$propName};\n";
            $resourceMethods .= "
    /**
     * Get {$resource['name']} resource
     *
     * @return \\ZipPicks\\SDK\\Resources\\{$className}
     */
    public function {$propName}(): \\ZipPicks\\SDK\\Resources\\{$className}
    {
        return \$this->{$propName};
    }
";
        }

        return "<?php

namespace {$variables['namespace']};

use GuzzleHttp\\Client;
use GuzzleHttp\\Exception\\RequestException;
use ZipPicks\\SDK\\Exceptions\\ApiException;
use ZipPicks\\SDK\\Configuration;

/**
 * ZipPicks API Client
 * 
 * The official PHP SDK for the ZipPicks API
 * The Taste Layer of the Internet
 */
class {$variables['className']}
{
    /**
     * HTTP client
     *
     * @var Client
     */
    protected Client \$client;

    /**
     * Configuration
     *
     * @var Configuration
     */
    protected Configuration \$config;

{$resourceProperties}

    /**
     * Create new ZipPicks client
     *
     * @param array \$config
     */
    public function __construct(array \$config = [])
    {
        \$this->config = new Configuration(\$config);
        \$this->client = new Client([
            'base_uri' => \$this->config->getBaseUrl(),
            'timeout' => \$this->config->getTimeout(),
            'headers' => [
                'User-Agent' => 'ZipPicks-PHP-SDK/' . \$this->config->getSdkVersion(),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ]
        ]);

        \$this->initializeResources();
    }

    /**
     * Initialize resource instances
     *
     * @return void
     */
    protected function initializeResources(): void
    {" . $this->generateResourceInitialization($resources) . "
    }

{$resourceMethods}

    /**
     * Make HTTP request
     *
     * @param string \$method
     * @param string \$path
     * @param array \$options
     * @return array
     */
    public function request(string \$method, string \$path, array \$options = []): array
    {
        try {
            \$response = \$this->client->request(\$method, \$path, \$options);
            return json_decode(\$response->getBody()->getContents(), true);
        } catch (RequestException \$e) {
            throw new ApiException(\$e->getMessage(), \$e->getCode(), \$e);
        }
    }

    /**
     * Get configuration
     *
     * @return Configuration
     */
    public function getConfig(): Configuration
    {
        return \$this->config;
    }
}";
    }

    /**
     * Generate resource initialization code
     *
     * @param array $resources
     * @return string
     */
    protected function generateResourceInitialization(array $resources): string
    {
        $code = '';
        foreach ($resources as $resource) {
            $propName = $this->camelCase($resource['name']);
            $className = $resource['className'];
            $code .= "\n        \$this->{$propName} = new \\ZipPicks\\SDK\\Resources\\{$className}(\$this);";
        }
        return $code;
    }

    /**
     * Generate resource code
     *
     * @param array $variables
     * @return string
     */
    protected function generateResourceCode(array $variables): string
    {
        $methods = '';
        foreach ($variables['methods'] as $method) {
            $methods .= $this->generateResourceMethod($method);
        }

        return "<?php

namespace {$variables['namespace']};

use ZipPicks\\SDK\\ZipPicksClient;
use ZipPicks\\SDK\\Exceptions\\ApiException;

/**
 * {$variables['className']} Resource
 */
class {$variables['className']}
{
    /**
     * ZipPicks client
     *
     * @var ZipPicksClient
     */
    protected ZipPicksClient \$client;

    /**
     * Create new resource instance
     *
     * @param ZipPicksClient \$client
     */
    public function __construct(ZipPicksClient \$client)
    {
        \$this->client = \$client;
    }

{$methods}
}";
    }

    /**
     * Generate resource method code
     *
     * @param array $method
     * @return string
     */
    protected function generateResourceMethod(array $method): string
    {
        $methodName = $this->camelCase($method['name']);
        $params = $this->generateMethodParameters($method);
        $docBlock = $this->generateMethodDocBlock($method);

        return "
{$docBlock}
    public function {$methodName}({$params}): array
    {
        \$options = [];
        
        // Add request body if present
        if (isset(\$data)) {
            \$options['json'] = \$data;
        }
        
        // Add query parameters
        if (isset(\$query)) {
            \$options['query'] = \$query;
        }
        
        return \$this->client->request('{$method['method']}', '{$method['path']}', \$options);
    }
";
    }

    /**
     * Generate method parameters
     *
     * @param array $method
     * @return string
     */
    protected function generateMethodParameters(array $method): string
    {
        $params = [];
        
        // Add path parameters
        preg_match_all('/\{([^}]+)\}/', $method['path'], $matches);
        foreach ($matches[1] as $param) {
            $params[] = "string \${$param}";
        }
        
        // Add data parameter for POST/PUT/PATCH
        if (in_array($method['method'], ['POST', 'PUT', 'PATCH'])) {
            $params[] = 'array $data = []';
        }
        
        // Add query parameter for GET requests
        if ($method['method'] === 'GET') {
            $params[] = 'array $query = []';
        }
        
        return implode(', ', $params);
    }

    /**
     * Generate method doc block
     *
     * @param array $method
     * @return string
     */
    protected function generateMethodDocBlock(array $method): string
    {
        $summary = $method['summary'] ?: $method['name'];
        $description = $method['description'] ? "\n     * \n     * {$method['description']}" : '';
        
        return "    /**
     * {$summary}{$description}
     *
     * @return array
     */";
    }

    /**
     * Generate model code
     *
     * @param array $variables
     * @return string
     */
    protected function generateModelCode(array $variables): string
    {
        $properties = '';
        $gettersSetters = '';

        foreach ($variables['properties'] as $property) {
            $propName = $property['name'];
            $propType = $property['type'];
            $description = $property['description'] ?: "The {$propName} property";

            $properties .= "    /**
     * {$description}
     *
     * @var {$propType}
     */
    protected {$propType} \${$propName};

";

            $methodName = $this->pascalCase($propName);
            $gettersSetters .= "    /**
     * Get {$propName}
     *
     * @return {$propType}
     */
    public function get{$methodName}(): {$propType}
    {
        return \$this->{$propName};
    }

    /**
     * Set {$propName}
     *
     * @param {$propType} \${$propName}
     * @return \$this
     */
    public function set{$methodName}({$propType} \${$propName}): self
    {
        \$this->{$propName} = \${$propName};
        return \$this;
    }

";
        }

        return "<?php

namespace {$variables['namespace']};

/**
 * {$variables['className']} Model
 */
class {$variables['className']}
{
{$properties}
    /**
     * Create new {$variables['className']} instance
     *
     * @param array \$data
     */
    public function __construct(array \$data = [])
    {
        \$this->fill(\$data);
    }

    /**
     * Fill model with data
     *
     * @param array \$data
     * @return \$this
     */
    public function fill(array \$data): self
    {
        foreach (\$data as \$key => \$value) {
            \$method = 'set' . \$this->pascalCase(\$key);
            if (method_exists(\$this, \$method)) {
                \$this->\$method(\$value);
            }
        }
        return \$this;
    }

    /**
     * Convert to array
     *
     * @return array
     */
    public function toArray(): array
    {
        \$result = [];
        \$reflection = new \\ReflectionClass(\$this);
        
        foreach (\$reflection->getProperties() as \$property) {
            \$property->setAccessible(true);
            \$result[\$property->getName()] = \$property->getValue(\$this);
        }
        
        return \$result;
    }

{$gettersSetters}

    /**
     * Convert string to PascalCase
     *
     * @param string \$string
     * @return string
     */
    protected function pascalCase(string \$string): string
    {
        return str_replace(' ', '', ucwords(str_replace('_', ' ', \$string)));
    }
}";
    }

    /**
     * Generate exception code
     *
     * @param array $variables
     * @return string
     */
    protected function generateExceptionCode(array $variables): string
    {
        $parent = $variables['className'] === 'ZipPicksException' ? '\\Exception' : 'ZipPicksException';

        return "<?php

namespace {$variables['namespace']};

/**
 * {$variables['description']}
 */
class {$variables['className']} extends {$parent}
{
    // Exception implementation
}";
    }

    /**
     * Generate configuration code
     *
     * @param array $variables
     * @return string
     */
    protected function generateConfigurationCode(array $variables): string
    {
        return "<?php

namespace {$variables['namespace']};

/**
 * ZipPicks SDK Configuration
 */
class Configuration
{
    /**
     * Default configuration
     *
     * @var array
     */
    protected array \$config = [
        'api_key' => null,
        'base_url' => '{$variables['config']['api_base_url']}',
        'timeout' => 30,
        'debug' => false,
        'user_agent' => 'ZipPicks-PHP-SDK/{$variables['config']['sdk_version']}'
    ];

    /**
     * Create new configuration
     *
     * @param array \$config
     */
    public function __construct(array \$config = [])
    {
        \$this->config = array_merge(\$this->config, \$config);
    }

    /**
     * Get base URL
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return \$this->config['base_url'];
    }

    /**
     * Get API key
     *
     * @return string|null
     */
    public function getApiKey(): ?string
    {
        return \$this->config['api_key'];
    }

    /**
     * Get timeout
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return \$this->config['timeout'];
    }

    /**
     * Get SDK version
     *
     * @return string
     */
    public function getSdkVersion(): string
    {
        return '{$variables['config']['sdk_version']}';
    }

    /**
     * Is debug mode enabled
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return \$this->config['debug'];
    }

    /**
     * Get user agent
     *
     * @return string
     */
    public function getUserAgent(): string
    {
        return \$this->config['user_agent'];
    }
}";
    }

    /**
     * Generate README code
     *
     * @param array $variables
     * @return string
     */
    protected function generateReadmeCode(array $variables): string
    {
        return "# ZipPicks PHP SDK

The official PHP SDK for the ZipPicks API - The Taste Layer of the Internet.

## Installation

Install the SDK using Composer:

```bash
composer require zippicks/php-sdk
```

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use ZipPicks\\SDK\\ZipPicksClient;

// Initialize the client
\$client = new ZipPicksClient([
    'api_key' => 'your-api-key-here'
]);

// Search for businesses
\$businesses = \$client->businesses()->list([
    'zip' => '10001',
    'vibes' => ['trendy', 'romantic']
]);

// Get business details
\$business = \$client->businesses()->get('123');

// Create a review
\$review = \$client->reviews()->create([
    'business_id' => '123',
    'rating' => 8.5,
    'content' => 'Amazing vibe and great food!'
]);
```

## Configuration

The SDK accepts the following configuration options:

- `api_key` (string): Your ZipPicks API key
- `base_url` (string): API base URL (default: `{$variables['config']['api_base_url']}`)
- `timeout` (int): Request timeout in seconds (default: 30)
- `debug` (bool): Enable debug mode (default: false)

## Resources

### Businesses

```php
// List businesses
\$businesses = \$client->businesses()->list(\$query);

// Get business
\$business = \$client->businesses()->get(\$id);

// Create business
\$business = \$client->businesses()->create(\$data);

// Update business
\$business = \$client->businesses()->update(\$id, \$data);

// Delete business
\$client->businesses()->delete(\$id);
```

### Reviews

```php
// List reviews
\$reviews = \$client->reviews()->list(\$query);

// Get review
\$review = \$client->reviews()->get(\$id);

// Create review
\$review = \$client->reviews()->create(\$data);
```

### Vibes

```php
// List all vibes
\$vibes = \$client->vibes()->list();

// Get vibe details
\$vibe = \$client->vibes()->get(\$id);
```

## Error Handling

The SDK throws specific exceptions for different error types:

```php
use ZipPicks\\SDK\\Exceptions\\ApiException;
use ZipPicks\\SDK\\Exceptions\\AuthenticationException;
use ZipPicks\\SDK\\Exceptions\\ValidationException;

try {
    \$business = \$client->businesses()->get('invalid-id');
} catch (AuthenticationException \$e) {
    // Handle authentication error
} catch (ValidationException \$e) {
    // Handle validation error
} catch (ApiException \$e) {
    // Handle general API error
}
```

## Requirements

- PHP 8.0 or higher
- Guzzle HTTP client
- Valid ZipPicks API key

## Support

- Documentation: https://developers.zippicks.com
- Support: {$variables['config']['contact_email']}
- Issues: https://github.com/zippicks/php-sdk/issues

## License

This SDK is released under the MIT License.
";
    }

    /**
     * Get PHP type for OpenAPI schema
     *
     * @param array $schema
     * @return string
     */
    protected function getPhpType(array $schema): string
    {
        $type = $schema['type'] ?? 'mixed';
        
        $typeMap = [
            'string' => 'string',
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'array' => 'array',
            'object' => 'array'
        ];

        $phpType = $typeMap[$type] ?? 'mixed';
        
        if (isset($schema['nullable']) && $schema['nullable']) {
            $phpType = "?{$phpType}";
        }

        return $phpType;
    }

    /**
     * Check if schema represents a model
     *
     * @param array $schema
     * @return bool
     */
    protected function isModelSchema(array $schema): bool
    {
        return isset($schema['type']) && 
               $schema['type'] === 'object' && 
               isset($schema['properties']) &&
               !isset($schema['allOf']);
    }

    /**
     * Generate method name from HTTP method and path
     *
     * @param string $method
     * @param string $path
     * @return string
     */
    protected function generateMethodName(string $method, string $path): string
    {
        $method = strtolower($method);
        $path = str_replace(['/', '{', '}'], ['_', '', ''], $path);
        return $method . $path;
    }

    /**
     * Convert string to PascalCase
     *
     * @param string $string
     * @return string
     */
    protected function pascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string)));
    }

    /**
     * Convert string to camelCase
     *
     * @param string $string
     * @return string
     */
    protected function camelCase(string $string): string
    {
        return lcfirst($this->pascalCase($string));
    }

    /**
     * Get generator version
     *
     * @return string
     */
    public function getVersion(): string
    {
        return self::VERSION;
    }
}