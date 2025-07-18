<?php
/**
 * ZipPicks JavaScript SDK Generator
 * 
 * Generates enterprise-grade JavaScript/TypeScript client libraries
 * Supports Node.js, browsers, and modern ES6+ features
 *
 * @package ZipPicks\Foundation\Api\SDK\Generators
 */

namespace ZipPicks\Foundation\Api\SDK\Generators;

use ZipPicks\Foundation\Logging\EnterpriseLogger;

class JavaScriptSdkGenerator
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
     * Create JavaScript SDK generator
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
     * Generate JavaScript SDK from OpenAPI specification
     *
     * @param array $openApiSpec
     * @param string $version
     * @param array $options
     * @return array
     */
    public function generate(array $openApiSpec, string $version, array $options = []): array
    {
        $this->logger->info('Generating JavaScript SDK', [
            'version' => $version,
            'options' => $options
        ]);

        $this->files = [];

        try {
            // Generate main client files
            $this->generateClient($openApiSpec, $version);
            
            // Generate TypeScript definitions
            $this->generateTypeScriptDefinitions($openApiSpec, $version);
            
            // Generate resource modules
            $this->generateResources($openApiSpec, $version);
            
            // Generate models/interfaces
            $this->generateModels($openApiSpec, $version);
            
            // Generate error classes
            $this->generateErrors($openApiSpec, $version);
            
            // Generate utilities
            $this->generateUtilities($openApiSpec, $version);
            
            // Generate package.json
            $this->generatePackageJson($openApiSpec, $version);
            
            // Generate build configuration
            $this->generateBuildConfig($openApiSpec, $version);
            
            // Generate documentation
            $this->generateDocumentation($openApiSpec, $version);
            
            // Generate tests
            $this->generateTests($openApiSpec, $version);

            return [
                'success' => true,
                'language' => 'javascript',
                'version' => $version,
                'files' => $this->files,
                'package_name' => '@zippicks/javascript-sdk'
            ];

        } catch (\Exception $e) {
            $this->logger->error('JavaScript SDK generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Generate main client files
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateClient(array $openApiSpec, string $version): void
    {
        $resources = $this->extractResources($openApiSpec);

        // Main client class (ES6)
        $this->files['src/ZipPicksClient.js'] = $this->generateClientES6($resources, $version);
        
        // CommonJS version
        $this->files['src/ZipPicksClient.cjs'] = $this->generateClientCommonJS($resources, $version);
        
        // Browser-compatible version
        $this->files['dist/zippicks-sdk.js'] = $this->generateClientBrowser($resources, $version);
        
        // Minified browser version
        $this->files['dist/zippicks-sdk.min.js'] = $this->generateClientBrowserMinified($resources, $version);
    }

    /**
     * Generate TypeScript definitions
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateTypeScriptDefinitions(array $openApiSpec, string $version): void
    {
        $interfaces = $this->generateTypeScriptInterfaces($openApiSpec);
        $resources = $this->extractResources($openApiSpec);

        $content = "// ZipPicks JavaScript SDK TypeScript Definitions
// Version: {$version}
// Generated: " . date('c') . "

export interface ZipPicksClientConfig {
  apiKey?: string;
  baseUrl?: string;
  timeout?: number;
  debug?: boolean;
  userAgent?: string;
}

export interface ApiResponse<T = any> {
  success: boolean;
  data: T;
  meta?: {
    total?: number;
    per_page?: number;
    current_page?: number;
    last_page?: number;
  };
}

export interface ApiError {
  type: string;
  message: string;
  code: number;
  errors?: Record<string, string[]>;
}

{$interfaces}

{$this->generateResourceInterfaces($resources)}

export declare class ZipPicksClient {
  constructor(config?: ZipPicksClientConfig);
  
{$this->generateClientMethodSignatures($resources)}
  
  request<T = any>(method: string, path: string, options?: any): Promise<ApiResponse<T>>;
}

export default ZipPicksClient;
";

        $this->files['src/index.d.ts'] = $content;
        $this->files['types/index.d.ts'] = $content;
    }

    /**
     * Generate resource modules
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateResources(array $openApiSpec, string $version): void
    {
        $resources = $this->extractResources($openApiSpec);

        foreach ($resources as $resource) {
            $resourceName = $this->camelCase($resource['name']);
            
            // ES6 module
            $this->files["src/resources/{$resourceName}.js"] = $this->generateResourceModule($resource);
            
            // TypeScript definitions
            $this->files["src/resources/{$resourceName}.d.ts"] = $this->generateResourceTypeDefinitions($resource);
        }
    }

    /**
     * Generate models/interfaces
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
                $modelName = $this->pascalCase($schemaName);
                
                // JavaScript model class
                $this->files["src/models/{$modelName}.js"] = $this->generateModelClass($schemaName, $schema);
                
                // TypeScript interface
                $this->files["src/models/{$modelName}.d.ts"] = $this->generateModelInterface($schemaName, $schema);
            }
        }
    }

    /**
     * Generate error classes
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateErrors(array $openApiSpec, string $version): void
    {
        $errorClasses = [
            'ZipPicksError' => 'Base error class for ZipPicks SDK',
            'ApiError' => 'API error response',
            'AuthenticationError' => 'Authentication failed',
            'ValidationError' => 'Request validation failed',
            'RateLimitError' => 'Rate limit exceeded',
            'NotFoundError' => 'Resource not found',
            'ServerError' => 'Internal server error'
        ];

        foreach ($errorClasses as $className => $description) {
            $this->files["src/errors/{$className}.js"] = $this->generateErrorClass($className, $description);
        }

        // Combined errors module
        $this->files['src/errors/index.js'] = $this->generateErrorsIndex($errorClasses);
    }

    /**
     * Generate utilities
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateUtilities(array $openApiSpec, string $version): void
    {
        // HTTP client utility
        $this->files['src/utils/httpClient.js'] = $this->generateHttpClientUtility();
        
        // Response transformer
        $this->files['src/utils/responseTransformer.js'] = $this->generateResponseTransformer();
        
        // Request interceptor
        $this->files['src/utils/requestInterceptor.js'] = $this->generateRequestInterceptor();
        
        // Utilities index
        $this->files['src/utils/index.js'] = $this->generateUtilitiesIndex();
    }

    /**
     * Generate package.json
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generatePackageJson(array $openApiSpec, string $version): void
    {
        $packageData = [
            'name' => '@zippicks/javascript-sdk',
            'version' => $this->config['sdk_version'],
            'description' => 'Official JavaScript SDK for the ZipPicks API - The Taste Layer of the Internet',
            'main' => 'src/ZipPicksClient.cjs',
            'module' => 'src/ZipPicksClient.js',
            'browser' => 'dist/zippicks-sdk.js',
            'types' => 'src/index.d.ts',
            'files' => [
                'src/',
                'dist/',
                'types/',
                'README.md',
                'CHANGELOG.md'
            ],
            'keywords' => [
                'zippicks',
                'api',
                'sdk',
                'javascript',
                'typescript',
                'local-discovery',
                'taste-graph'
            ],
            'homepage' => 'https://developers.zippicks.com',
            'repository' => [
                'type' => 'git',
                'url' => 'https://github.com/zippicks/javascript-sdk.git'
            ],
            'bugs' => [
                'url' => 'https://github.com/zippicks/javascript-sdk/issues',
                'email' => $this->config['contact_email']
            ],
            'license' => 'MIT',
            'author' => [
                'name' => 'ZipPicks',
                'email' => $this->config['contact_email'],
                'url' => 'https://zippicks.com'
            ],
            'engines' => [
                'node' => '>=14.0.0'
            ],
            'dependencies' => [
                'axios' => '^1.0.0',
                'form-data' => '^4.0.0'
            ],
            'devDependencies' => [
                '@types/node' => '^20.0.0',
                'typescript' => '^5.0.0',
                'jest' => '^29.0.0',
                '@types/jest' => '^29.0.0',
                'eslint' => '^8.0.0',
                'prettier' => '^3.0.0',
                'webpack' => '^5.0.0',
                'webpack-cli' => '^5.0.0',
                'terser-webpack-plugin' => '^5.0.0'
            ],
            'scripts' => [
                'build' => 'npm run build:types && npm run build:browser',
                'build:types' => 'tsc --declaration --emitDeclarationOnly --outDir types',
                'build:browser' => 'webpack --mode=production',
                'test' => 'jest',
                'test:coverage' => 'jest --coverage',
                'lint' => 'eslint src/**/*.js',
                'format' => 'prettier --write src/**/*.js',
                'prepublishOnly' => 'npm run build && npm test'
            ],
            'jest' => [
                'testEnvironment' => 'node',
                'coverageDirectory' => 'coverage',
                'collectCoverageFrom' => [
                    'src/**/*.js',
                    '!src/**/*.test.js'
                ]
            ]
        ];

        $this->files['package.json'] = json_encode($packageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate build configuration
     *
     * @param array $openApiSpec
     * @param string $version
     * @return void
     */
    protected function generateBuildConfig(array $openApiSpec, string $version): void
    {
        // Webpack configuration
        $this->files['webpack.config.js'] = $this->generateWebpackConfig();
        
        // TypeScript configuration
        $this->files['tsconfig.json'] = $this->generateTsConfig();
        
        // ESLint configuration
        $this->files['.eslintrc.js'] = $this->generateEslintConfig();
        
        // Prettier configuration
        $this->files['.prettierrc'] = $this->generatePrettierConfig();
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
        // README.md
        $this->files['README.md'] = $this->generateReadme($openApiSpec, $version);
        
        // CHANGELOG.md
        $this->files['CHANGELOG.md'] = $this->generateChangelog($version);
        
        // Examples
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
        $resources = $this->extractResources($openApiSpec);

        // Main client test
        $this->files['src/__tests__/ZipPicksClient.test.js'] = $this->generateClientTest($resources);

        // Resource tests
        foreach ($resources as $resource) {
            $resourceName = $this->camelCase($resource['name']);
            $this->files["src/__tests__/{$resourceName}.test.js"] = $this->generateResourceTest($resource);
        }

        // Test setup
        $this->files['src/__tests__/setup.js'] = $this->generateTestSetup();
    }

    /**
     * Generate ES6 client
     *
     * @param array $resources
     * @param string $version
     * @return string
     */
    protected function generateClientES6(array $resources, string $version): string
    {
        $imports = '';
        $resourceProperties = '';
        $resourceInitialization = '';

        foreach ($resources as $resource) {
            $resourceName = $this->camelCase($resource['name']);
            $className = $this->pascalCase($resource['name']);
            
            $imports .= "import {$className} from './resources/{$resourceName}.js';\n";
            $resourceInitialization .= "    this.{$resourceName} = new {$className}(this);\n";
        }

        return "import axios from 'axios';
import { ZipPicksError, ApiError } from './errors/index.js';

{$imports}

/**
 * ZipPicks JavaScript SDK Client
 * The Taste Layer of the Internet
 * 
 * @version {$version}
 */
export default class ZipPicksClient {
  /**
   * Create new ZipPicks client instance
   * 
   * @param {Object} config - Configuration options
   * @param {string} config.apiKey - Your ZipPicks API key
   * @param {string} [config.baseUrl] - API base URL
   * @param {number} [config.timeout] - Request timeout in milliseconds
   * @param {boolean} [config.debug] - Enable debug mode
   */
  constructor(config = {}) {
    this.config = {
      apiKey: null,
      baseUrl: '{$this->config['api_base_url']}',
      timeout: 30000,
      debug: false,
      userAgent: 'ZipPicks-JS-SDK/{$this->config['sdk_version']}',
      ...config
    };

    // Create axios instance
    this.httpClient = axios.create({
      baseURL: this.config.baseUrl,
      timeout: this.config.timeout,
      headers: {
        'User-Agent': this.config.userAgent,
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      }
    });

    // Add request interceptor for authentication
    this.httpClient.interceptors.request.use(
      (config) => {
        if (this.config.apiKey) {
          config.headers['X-API-Key'] = this.config.apiKey;
        }
        return config;
      },
      (error) => Promise.reject(error)
    );

    // Add response interceptor for error handling
    this.httpClient.interceptors.response.use(
      (response) => response.data,
      (error) => {
        if (error.response) {
          const apiError = new ApiError(
            error.response.data?.error?.message || 'API Error',
            error.response.status,
            error.response.data
          );
          return Promise.reject(apiError);
        }
        return Promise.reject(new ZipPicksError(error.message));
      }
    );

    // Initialize resources
{$resourceInitialization}
  }

  /**
   * Make HTTP request
   * 
   * @param {string} method - HTTP method
   * @param {string} path - Request path
   * @param {Object} [options] - Request options
   * @returns {Promise<Object>} Response data
   */
  async request(method, path, options = {}) {
    try {
      const response = await this.httpClient.request({
        method: method.toUpperCase(),
        url: path,
        ...options
      });
      return response;
    } catch (error) {
      if (this.config.debug) {
        console.error('ZipPicks API Error:', error);
      }
      throw error;
    }
  }

  /**
   * Set API key
   * 
   * @param {string} apiKey - Your ZipPicks API key
   */
  setApiKey(apiKey) {
    this.config.apiKey = apiKey;
  }

  /**
   * Get current configuration
   * 
   * @returns {Object} Current configuration
   */
  getConfig() {
    return { ...this.config };
  }
}";
    }

    /**
     * Generate CommonJS client
     *
     * @param array $resources
     * @param string $version
     * @return string
     */
    protected function generateClientCommonJS(array $resources, string $version): string
    {
        // Convert ES6 to CommonJS format
        $es6Client = $this->generateClientES6($resources, $version);
        
        return str_replace(
            ['import ', 'export default ', 'export '],
            ['const ', 'module.exports = ', 'module.exports.'],
            $es6Client
        );
    }

    /**
     * Generate browser client
     *
     * @param array $resources
     * @param string $version
     * @return string
     */
    protected function generateClientBrowser(array $resources, string $version): string
    {
        return "// ZipPicks JavaScript SDK - Browser Version
// Version: {$version}
// The Taste Layer of the Internet

(function(global) {
  'use strict';

  // Browser-compatible ZipPicks SDK
  const ZipPicks = {
    version: '{$version}',
    
    Client: function(config) {
      return new ZipPicksClient(config || {});
    }
  };

  // Main client implementation would go here
  // This would be the compiled/bundled version

  if (typeof module !== 'undefined' && module.exports) {
    module.exports = ZipPicks;
  } else if (typeof define === 'function' && define.amd) {
    define(function() { return ZipPicks; });
  } else {
    global.ZipPicks = ZipPicks;
  }

})(typeof window !== 'undefined' ? window : this);";
    }

    /**
     * Generate minified browser client
     *
     * @param array $resources
     * @param string $version
     * @return string
     */
    protected function generateClientBrowserMinified(array $resources, string $version): string
    {
        // This would be the minified version in production
        return "/*! ZipPicks JS SDK v{$version} */\n(function(g){g.ZipPicks={version:'{$version}',Client:function(c){return new ZipPicksClient(c||{})}}})(window);";
    }

    /**
     * Generate resource module
     *
     * @param array $resource
     * @return string
     */
    protected function generateResourceModule(array $resource): string
    {
        $className = $this->pascalCase($resource['name']);
        $methods = '';

        foreach ($resource['methods'] as $method) {
            $methods .= $this->generateResourceMethod($method) . "\n\n";
        }

        return "/**
 * {$className} Resource
 * 
 * Handles all {$resource['name']}-related API operations
 */
export default class {$className} {
  /**
   * Create new {$className} resource
   * 
   * @param {ZipPicksClient} client - ZipPicks client instance
   */
  constructor(client) {
    this.client = client;
  }

{$methods}}";
    }

    /**
     * Generate resource method
     *
     * @param array $method
     * @return string
     */
    protected function generateResourceMethod(array $method): string
    {
        $methodName = $this->camelCase($method['name']);
        $params = $this->generateJSMethodParameters($method);
        $pathParams = $this->extractPathParameters($method['path']);
        $httpMethod = strtoupper($method['method']);

        $bodyParam = '';
        if (in_array($httpMethod, ['POST', 'PUT', 'PATCH'])) {
            $bodyParam = ', data';
        }

        $queryParam = '';
        if ($httpMethod === 'GET') {
            $queryParam = ', query = {}';
        }

        $docBlock = $this->generateJSMethodDocBlock($method);

        return "  {$docBlock}
  async {$methodName}({$params}{$bodyParam}{$queryParam}) {
    const options = {};
    
    " . ($bodyParam ? "if (data) {\n      options.data = data;\n    }\n    " : "") . "
    " . ($queryParam ? "if (Object.keys(query).length > 0) {\n      options.params = query;\n    }\n    " : "") . "
    
    const path = `{$method['path']}`" . ($pathParams ? $this->generatePathReplacement($pathParams) : '') . ";
    
    return this.client.request('{$httpMethod}', path, options);
  }";
    }

    /**
     * Generate JavaScript method parameters
     *
     * @param array $method
     * @return string
     */
    protected function generateJSMethodParameters(array $method): string
    {
        $pathParams = $this->extractPathParameters($method['path']);
        return implode(', ', $pathParams);
    }

    /**
     * Generate JavaScript method doc block
     *
     * @param array $method
     * @return string
     */
    protected function generateJSMethodDocBlock(array $method): string
    {
        $summary = $method['summary'] ?: $method['name'];
        $description = $method['description'] ? "\n   * \n   * {$method['description']}" : '';
        
        return "/**
   * {$summary}{$description}
   *
   * @returns {Promise<Object>} API response
   */";
    }

    /**
     * Extract path parameters
     *
     * @param string $path
     * @return array
     */
    protected function extractPathParameters(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);
        return $matches[1];
    }

    /**
     * Generate path replacement code
     *
     * @param array $pathParams
     * @return string
     */
    protected function generatePathReplacement(array $pathParams): string
    {
        $replacements = '';
        foreach ($pathParams as $param) {
            $replacements .= ".replace('{{$param}}', {$param})";
        }
        return $replacements;
    }

    /**
     * Generate TypeScript interfaces
     *
     * @param array $openApiSpec
     * @return string
     */
    protected function generateTypeScriptInterfaces(array $openApiSpec): string
    {
        $interfaces = '';
        $schemas = $openApiSpec['components']['schemas'] ?? [];

        foreach ($schemas as $schemaName => $schema) {
            if ($this->isModelSchema($schema)) {
                $interfaces .= $this->generateTypeScriptInterface($schemaName, $schema) . "\n\n";
            }
        }

        return $interfaces;
    }

    /**
     * Generate TypeScript interface
     *
     * @param string $name
     * @param array $schema
     * @return string
     */
    protected function generateTypeScriptInterface(string $name, array $schema): string
    {
        $interfaceName = $this->pascalCase($name);
        $properties = '';

        foreach ($schema['properties'] ?? [] as $propName => $propSchema) {
            $tsType = $this->getTypeScriptType($propSchema);
            $optional = in_array($propName, $schema['required'] ?? []) ? '' : '?';
            $description = isset($propSchema['description']) ? "  /** {$propSchema['description']} */\n" : '';
            
            $properties .= "{$description}  {$propName}{$optional}: {$tsType};\n";
        }

        return "export interface {$interfaceName} {
{$properties}}";
    }

    /**
     * Get TypeScript type for schema
     *
     * @param array $schema
     * @return string
     */
    protected function getTypeScriptType(array $schema): string
    {
        $type = $schema['type'] ?? 'any';
        
        $typeMap = [
            'string' => 'string',
            'integer' => 'number',
            'number' => 'number',
            'boolean' => 'boolean',
            'array' => 'any[]',
            'object' => 'object'
        ];

        $tsType = $typeMap[$type] ?? 'any';
        
        if (isset($schema['nullable']) && $schema['nullable']) {
            $tsType = "{$tsType} | null";
        }

        return $tsType;
    }

    /**
     * Generate README
     *
     * @param array $openApiSpec
     * @param string $version
     * @return string
     */
    protected function generateReadme(array $openApiSpec, string $version): string
    {
        return "# ZipPicks JavaScript SDK

The official JavaScript/TypeScript SDK for the ZipPicks API - The Taste Layer of the Internet.

## Installation

### Node.js / NPM

```bash
npm install @zippicks/javascript-sdk
```

### Browser (CDN)

```html
<script src=\"https://cdn.jsdelivr.net/npm/@zippicks/javascript-sdk@{$this->config['sdk_version']}/dist/zippicks-sdk.min.js\"></script>
```

## Quick Start

### ES6 / TypeScript

```javascript
import ZipPicksClient from '@zippicks/javascript-sdk';

// Initialize the client
const client = new ZipPicksClient({
  apiKey: 'your-api-key-here'
});

// Search for businesses
const businesses = await client.businesses.list({
  zip: '10001',
  vibes: ['trendy', 'romantic']
});

// Get business details
const business = await client.businesses.get('123');

// Create a review
const review = await client.reviews.create({
  business_id: '123',
  rating: 8.5,
  content: 'Amazing vibe and great food!'
});
```

### CommonJS

```javascript
const ZipPicksClient = require('@zippicks/javascript-sdk');

const client = new ZipPicksClient({
  apiKey: 'your-api-key-here'
});

client.businesses.list({ zip: '10001' })
  .then(businesses => console.log(businesses))
  .catch(error => console.error(error));
```

### Browser

```html
<script src=\"https://cdn.jsdelivr.net/npm/@zippicks/javascript-sdk\"></script>
<script>
  const client = new ZipPicks.Client({
    apiKey: 'your-api-key-here'
  });
  
  client.businesses.list({ zip: '10001' })
    .then(businesses => console.log(businesses));
</script>
```

## Configuration

The SDK accepts the following configuration options:

- `apiKey` (string): Your ZipPicks API key
- `baseUrl` (string): API base URL (default: `{$this->config['api_base_url']}`)
- `timeout` (number): Request timeout in milliseconds (default: 30000)
- `debug` (boolean): Enable debug mode (default: false)

## Resources

### Businesses

```javascript
// List businesses
const businesses = await client.businesses.list(query);

// Get business
const business = await client.businesses.get(id);

// Create business
const business = await client.businesses.create(data);

// Update business
const business = await client.businesses.update(id, data);

// Delete business
await client.businesses.delete(id);
```

### Reviews

```javascript
// List reviews
const reviews = await client.reviews.list(query);

// Get review
const review = await client.reviews.get(id);

// Create review
const review = await client.reviews.create(data);
```

### Vibes

```javascript
// List all vibes
const vibes = await client.vibes.list();

// Get vibe details
const vibe = await client.vibes.get(id);
```

## Error Handling

The SDK throws specific errors for different error types:

```javascript
import { ApiError, AuthenticationError, ValidationError } from '@zippicks/javascript-sdk';

try {
  const business = await client.businesses.get('invalid-id');
} catch (error) {
  if (error instanceof AuthenticationError) {
    // Handle authentication error
  } else if (error instanceof ValidationError) {
    // Handle validation error
  } else if (error instanceof ApiError) {
    // Handle general API error
  }
}
```

## TypeScript Support

The SDK includes full TypeScript definitions:

```typescript
import ZipPicksClient, { Business, Review, ApiResponse } from '@zippicks/javascript-sdk';

const client = new ZipPicksClient({ apiKey: 'your-key' });

const businesses: ApiResponse<Business[]> = await client.businesses.list();
const business: Business = businesses.data[0];
```

## Requirements

- Node.js 14+ (for server-side usage)
- Modern browser with ES6 support (for browser usage)
- Valid ZipPicks API key

## Support

- Documentation: https://developers.zippicks.com
- Support: {$this->config['contact_email']}
- Issues: https://github.com/zippicks/javascript-sdk/issues

## License

This SDK is released under the MIT License.
";
    }

    /**
     * Helper methods
     */
    protected function extractResources(array $openApiSpec): array
    {
        // Same logic as PHP generator
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

    protected function isModelSchema(array $schema): bool
    {
        return isset($schema['type']) && 
               $schema['type'] === 'object' && 
               isset($schema['properties']) &&
               !isset($schema['allOf']);
    }

    protected function generateMethodName(string $method, string $path): string
    {
        $method = strtolower($method);
        $path = str_replace(['/', '{', '}'], ['_', '', ''], $path);
        return $method . $path;
    }

    protected function pascalCase(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $string)));
    }

    protected function camelCase(string $string): string
    {
        return lcfirst($this->pascalCase($string));
    }

    public function getVersion(): string
    {
        return self::VERSION;
    }

    // Placeholder methods for remaining generators
    protected function generateResourceTypeDefinitions($resource) { return '// TypeScript definitions'; }
    protected function generateModelClass($name, $schema) { return '// Model class'; }
    protected function generateModelInterface($name, $schema) { return '// Model interface'; }
    protected function generateErrorClass($name, $description) { return '// Error class'; }
    protected function generateErrorsIndex($classes) { return '// Errors index'; }
    protected function generateHttpClientUtility() { return '// HTTP client utility'; }
    protected function generateResponseTransformer() { return '// Response transformer'; }
    protected function generateRequestInterceptor() { return '// Request interceptor'; }
    protected function generateUtilitiesIndex() { return '// Utilities index'; }
    protected function generateWebpackConfig() { return '// Webpack config'; }
    protected function generateTsConfig() { return '// TypeScript config'; }
    protected function generateEslintConfig() { return '// ESLint config'; }
    protected function generatePrettierConfig() { return '// Prettier config'; }
    protected function generateChangelog($version) { return '// Changelog'; }
    protected function generateExamples($spec) { return []; }
    protected function generateClientTest($resources) { return '// Client test'; }
    protected function generateResourceTest($resource) { return '// Resource test'; }
    protected function generateTestSetup() { return '// Test setup'; }
    protected function generateResourceInterfaces($resources) { return '// Resource interfaces'; }
    protected function generateClientMethodSignatures($resources) { return '// Client method signatures'; }
}