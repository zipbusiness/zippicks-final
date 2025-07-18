<?php
/**
 * ZipPicks SDK Generator
 * 
 * Enterprise-grade SDK generation system for the $100B platform
 * Auto-generates client libraries in multiple languages from OpenAPI spec
 *
 * @package ZipPicks\Foundation\Api\SDK
 */

namespace ZipPicks\Foundation\Api\SDK;

use ZipPicks\Foundation\Api\Documentation\OpenApiGenerator;
use ZipPicks\Foundation\Api\SDK\Generators\PhpSdkGenerator;
use ZipPicks\Foundation\Api\SDK\Generators\JavaScriptSdkGenerator;
use ZipPicks\Foundation\Api\SDK\Generators\PythonSdkGenerator;
use ZipPicks\Foundation\Core\Container;
use ZipPicks\Foundation\Logging\EnterpriseLogger;

class SdkGenerator
{
    /**
     * OpenAPI generator
     *
     * @var OpenApiGenerator
     */
    protected OpenApiGenerator $openApiGenerator;

    /**
     * Container instance
     *
     * @var Container
     */
    protected Container $container;

    /**
     * Logger instance
     *
     * @var EnterpriseLogger
     */
    protected EnterpriseLogger $logger;

    /**
     * Available SDK generators
     *
     * @var array
     */
    protected array $generators = [];

    /**
     * SDK build configuration
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Create new SDK generator
     *
     * @param OpenApiGenerator $openApiGenerator
     * @param Container $container
     * @param EnterpriseLogger $logger
     */
    public function __construct(OpenApiGenerator $openApiGenerator, Container $container, EnterpriseLogger $logger)
    {
        $this->openApiGenerator = $openApiGenerator;
        $this->container = $container;
        $this->logger = $logger;
        
        $this->loadConfiguration();
        $this->registerGenerators();
    }

    /**
     * Generate SDK for specific language
     *
     * @param string $language
     * @param string $version
     * @param array $options
     * @return array
     */
    public function generate(string $language, string $version = 'v1', array $options = []): array
    {
        $this->logger->info('Starting SDK generation', [
            'language' => $language,
            'version' => $version,
            'options' => $options
        ]);

        $startTime = microtime(true);

        try {
            // Validate language support
            if (!isset($this->generators[$language])) {
                throw new \InvalidArgumentException("Unsupported language: {$language}");
            }

            // Get OpenAPI specification
            $openApiSpec = $this->openApiGenerator->generate($version);
            
            // Generate SDK using appropriate generator
            $generator = $this->generators[$language];
            $result = $generator->generate($openApiSpec, $version, $options);

            // Add build metadata
            $result['build_info'] = [
                'generated_at' => date('c'),
                'generation_time' => round((microtime(true) - $startTime) * 1000, 2) . 'ms',
                'api_version' => $version,
                'sdk_version' => $this->config['sdk_version'],
                'language' => $language,
                'generator_version' => $generator->getVersion()
            ];

            $this->logger->info('SDK generation completed successfully', [
                'language' => $language,
                'version' => $version,
                'files_generated' => count($result['files'] ?? []),
                'generation_time' => $result['build_info']['generation_time']
            ]);

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('SDK generation failed', [
                'language' => $language,
                'version' => $version,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Generate all supported SDKs
     *
     * @param string $version
     * @param array $options
     * @return array
     */
    public function generateAll(string $version = 'v1', array $options = []): array
    {
        $results = [];
        $languages = array_keys($this->generators);

        $this->logger->info('Starting batch SDK generation', [
            'languages' => $languages,
            'version' => $version
        ]);

        foreach ($languages as $language) {
            try {
                $results[$language] = $this->generate($language, $version, $options);
            } catch (\Exception $e) {
                $results[$language] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Package SDK for distribution
     *
     * @param string $language
     * @param array $sdkFiles
     * @param string $version
     * @return string
     */
    public function package(string $language, array $sdkFiles, string $version = 'v1'): string
    {
        $this->logger->info('Starting SDK packaging', [
            'language' => $language,
            'version' => $version,
            'file_count' => count($sdkFiles)
        ]);

        $packager = $this->getPackager($language);
        $packagePath = $packager->package($sdkFiles, $version);

        $this->logger->info('SDK packaging completed', [
            'language' => $language,
            'package_path' => $packagePath
        ]);

        return $packagePath;
    }

    /**
     * Publish SDK to repositories
     *
     * @param string $language
     * @param string $packagePath
     * @param array $options
     * @return bool
     */
    public function publish(string $language, string $packagePath, array $options = []): bool
    {
        $this->logger->info('Starting SDK publishing', [
            'language' => $language,
            'package_path' => $packagePath,
            'options' => $options
        ]);

        $publisher = $this->getPublisher($language);
        $success = $publisher->publish($packagePath, $options);

        if ($success) {
            $this->logger->info('SDK published successfully', [
                'language' => $language,
                'package_path' => $packagePath
            ]);
        } else {
            $this->logger->error('SDK publishing failed', [
                'language' => $language,
                'package_path' => $packagePath
            ]);
        }

        return $success;
    }

    /**
     * Get SDK download statistics
     *
     * @param string $language
     * @param string $timeframe
     * @return array
     */
    public function getDownloadStats(string $language = null, string $timeframe = '30d'): array
    {
        $stats = [];
        $languages = $language ? [$language] : array_keys($this->generators);

        foreach ($languages as $lang) {
            $stats[$lang] = $this->fetchDownloadStats($lang, $timeframe);
        }

        return $language ? $stats[$language] : $stats;
    }

    /**
     * Validate SDK configuration
     *
     * @param array $config
     * @return array
     */
    public function validateConfiguration(array $config): array
    {
        $errors = [];

        // Required fields
        $required = ['sdk_version', 'api_base_url', 'contact_email'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Validate version format
        if (!empty($config['sdk_version']) && !preg_match('/^\d+\.\d+\.\d+$/', $config['sdk_version'])) {
            $errors[] = 'SDK version must follow semantic versioning (x.y.z)';
        }

        // Validate URL format
        if (!empty($config['api_base_url']) && !filter_var($config['api_base_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'API base URL must be a valid URL';
        }

        return $errors;
    }

    /**
     * Get available languages
     *
     * @return array
     */
    public function getAvailableLanguages(): array
    {
        return array_keys($this->generators);
    }

    /**
     * Get generator for language
     *
     * @param string $language
     * @return object|null
     */
    public function getGenerator(string $language): ?object
    {
        return $this->generators[$language] ?? null;
    }

    /**
     * Load SDK configuration
     *
     * @return void
     */
    protected function loadConfiguration(): void
    {
        $this->config = [
            'sdk_version' => '1.0.0',
            'api_base_url' => 'https://api.zippicks.com',
            'contact_email' => 'developers@zippicks.com',
            'support_url' => 'https://developers.zippicks.com/support',
            'documentation_url' => 'https://developers.zippicks.com/docs',
            'github_org' => 'zippicks',
            'package_registry' => [
                'php' => 'packagist.org',
                'javascript' => 'npmjs.com',
                'python' => 'pypi.org'
            ],
            'build_directory' => WP_CONTENT_DIR . '/sdk-builds',
            'template_directory' => __DIR__ . '/Templates'
        ];

        // Allow configuration override
        $customConfig = $this->container->get('sdk.config', []);
        $this->config = array_merge($this->config, $customConfig);
    }

    /**
     * Register SDK generators
     *
     * @return void
     */
    protected function registerGenerators(): void
    {
        $this->generators = [
            'php' => new PhpSdkGenerator($this->config, $this->logger),
            'javascript' => new JavaScriptSdkGenerator($this->config, $this->logger),
            'python' => new PythonSdkGenerator($this->config, $this->logger)
        ];
    }

    /**
     * Get packager for language
     *
     * @param string $language
     * @return object
     */
    protected function getPackager(string $language): object
    {
        $packagers = [
            'php' => new \ZipPicks\Foundation\Api\SDK\Packagers\ComposerPackager($this->config),
            'javascript' => new \ZipPicks\Foundation\Api\SDK\Packagers\NpmPackager($this->config),
            'python' => new \ZipPicks\Foundation\Api\SDK\Packagers\PipPackager($this->config)
        ];

        if (!isset($packagers[$language])) {
            throw new \InvalidArgumentException("No packager available for language: {$language}");
        }

        return $packagers[$language];
    }

    /**
     * Get publisher for language
     *
     * @param string $language
     * @return object
     */
    protected function getPublisher(string $language): object
    {
        $publishers = [
            'php' => new \ZipPicks\Foundation\Api\SDK\Publishers\PackagistPublisher($this->config),
            'javascript' => new \ZipPicks\Foundation\Api\SDK\Publishers\NpmPublisher($this->config),
            'python' => new \ZipPicks\Foundation\Api\SDK\Publishers\PypiPublisher($this->config)
        ];

        if (!isset($publishers[$language])) {
            throw new \InvalidArgumentException("No publisher available for language: {$language}");
        }

        return $publishers[$language];
    }

    /**
     * Fetch download statistics
     *
     * @param string $language
     * @param string $timeframe
     * @return array
     */
    protected function fetchDownloadStats(string $language, string $timeframe): array
    {
        // This would integrate with package registry APIs
        // For now, return mock data
        return [
            'total_downloads' => 0,
            'downloads_last_30d' => 0,
            'downloads_last_7d' => 0,
            'latest_version' => $this->config['sdk_version'],
            'last_updated' => date('c')
        ];
    }
}