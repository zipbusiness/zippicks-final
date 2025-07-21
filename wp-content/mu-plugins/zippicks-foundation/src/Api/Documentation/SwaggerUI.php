<?php
/**
 * ZipPicks Swagger UI Integration
 * 
 * Provides interactive API documentation using Swagger UI
 *
 * @package ZipPicks\Foundation\Api\Documentation
 */

namespace ZipPicks\Foundation\Api\Documentation;

class SwaggerUI
{
    /**
     * OpenAPI generator
     *
     * @var OpenApiGenerator
     */
    protected OpenApiGenerator $generator;

    /**
     * Swagger UI version
     *
     * @var string
     */
    protected string $version = '5.10.3';

    /**
     * Create new Swagger UI instance
     *
     * @param OpenApiGenerator $generator
     */
    public function __construct(OpenApiGenerator $generator)
    {
        $this->generator = $generator;
    }

    /**
     * Render Swagger UI
     *
     * @param string $apiVersion
     * @return void
     */
    public function render(string $apiVersion = 'v1'): void
    {
        $spec = $this->generator->generate($apiVersion);
        $specJson = json_encode($spec);
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>ZipPicks API Documentation</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@<?php echo $this->version; ?>/swagger-ui.css">
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .swagger-ui .topbar {
                    background-color: #1a1a1a;
                    padding: 10px 0;
                }
                .swagger-ui .topbar .wrapper {
                    padding: 0 20px;
                }
                .swagger-ui .topbar .topbar-wrapper {
                    align-items: center;
                }
                .swagger-ui .topbar .topbar-wrapper .link {
                    display: flex;
                    align-items: center;
                }
                .swagger-ui .topbar .topbar-wrapper .link img {
                    height: 40px;
                    margin-right: 10px;
                }
                .swagger-ui .topbar .topbar-wrapper .link span {
                    color: white;
                    font-size: 20px;
                    font-weight: bold;
                }
                .api-key-notice {
                    background: #fff3cd;
                    border: 1px solid #ffeaa7;
                    color: #856404;
                    padding: 15px 20px;
                    margin: 20px;
                    border-radius: 4px;
                }
                .api-key-notice a {
                    color: #533f03;
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div id="swagger-ui"></div>
            
            <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@<?php echo $this->version; ?>/swagger-ui-bundle.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@<?php echo $this->version; ?>/swagger-ui-standalone-preset.js"></script>
            <script>
                window.onload = function() {
                    const spec = <?php echo $specJson; ?>;
                    
                    const ui = SwaggerUIBundle({
                        spec: spec,
                        dom_id: '#swagger-ui',
                        deepLinking: true,
                        presets: [
                            SwaggerUIBundle.presets.apis,
                            SwaggerUIStandalonePreset
                        ],
                        plugins: [
                            SwaggerUIBundle.plugins.DownloadUrl
                        ],
                        layout: "StandaloneLayout",
                        validatorUrl: null,
                        tryItOutEnabled: true,
                        requestInterceptor: (request) => {
                            // Add custom headers if needed
                            return request;
                        },
                        responseInterceptor: (response) => {
                            // Handle responses
                            return response;
                        },
                        onComplete: () => {
                            // Add API key notice
                            const info = document.querySelector('.info');
                            if (info) {
                                const notice = document.createElement('div');
                                notice.className = 'api-key-notice';
                                notice.innerHTML = '<strong>🔑 API Key Required:</strong> Most endpoints require authentication. <a href="/wp-admin/admin.php?page=zippicks-api-keys">Get your API key</a> to start making requests.';
                                info.appendChild(notice);
                            }
                            
                            // Customize topbar
                            const topbar = document.querySelector('.topbar-wrapper');
                            if (topbar) {
                                topbar.innerHTML = `
                                    <a class="link" href="/">
                                        <span>ZipPicks API v${spec.info.version}</span>
                                    </a>
                                    <div style="margin-left: auto; display: flex; gap: 20px;">
                                        <a href="/wp-admin/admin.php?page=zippicks-api-keys" style="color: white; text-decoration: none;">API Keys</a>
                                        <a href="https://developers.zippicks.com" style="color: white; text-decoration: none;">Developer Portal</a>
                                        <a href="https://github.com/zippicks/api-examples" style="color: white; text-decoration: none;">Examples</a>
                                    </div>
                                `;
                            }
                        }
                    });
                    
                    window.ui = ui;
                };
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Render Swagger UI in WordPress admin
     *
     * @return void
     */
    public function renderAdmin(): void
    {
        ?>
        <div class="wrap">
            <h1>ZipPicks API Documentation</h1>
            <div style="background: white; padding: 20px; margin-top: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                <?php $this->renderEmbed(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render embedded Swagger UI
     *
     * @param string $apiVersion
     * @return void
     */
    public function renderEmbed(string $apiVersion = 'v1'): void
    {
        $spec = $this->generator->generate($apiVersion);
        $specJson = json_encode($spec);
        $containerId = 'swagger-ui-' . uniqid();
        
        ?>
        <div id="<?php echo $containerId; ?>"></div>
        
        <script>
            (function() {
                if (typeof SwaggerUIBundle === 'undefined') {
                    // Load Swagger UI if not already loaded
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@<?php echo $this->version; ?>/swagger-ui.css';
                    document.head.appendChild(link);
                    
                    const script1 = document.createElement('script');
                    script1.src = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@<?php echo $this->version; ?>/swagger-ui-bundle.js';
                    script1.onload = function() {
                        const script2 = document.createElement('script');
                        script2.src = 'https://cdn.jsdelivr.net/npm/swagger-ui-dist@<?php echo $this->version; ?>/swagger-ui-standalone-preset.js';
                        script2.onload = function() {
                            initSwaggerUI();
                        };
                        document.body.appendChild(script2);
                    };
                    document.body.appendChild(script1);
                } else {
                    initSwaggerUI();
                }
                
                function initSwaggerUI() {
                    const spec = <?php echo $specJson; ?>;
                    
                    SwaggerUIBundle({
                        spec: spec,
                        dom_id: '#<?php echo $containerId; ?>',
                        deepLinking: false,
                        presets: [
                            SwaggerUIBundle.presets.apis
                        ],
                        plugins: [
                            SwaggerUIBundle.plugins.DownloadUrl
                        ],
                        tryItOutEnabled: true,
                        validatorUrl: null
                    });
                }
            })();
        </script>
        <?php
    }

    /**
     * Get OpenAPI specification endpoint
     *
     * @param string $format
     * @return void
     */
    public function serveSpec(string $format = 'json'): void
    {
        $version = $_GET['version'] ?? 'v1';
        
        if ($format === 'yaml') {
            header('Content-Type: application/x-yaml');
            echo $this->generator->toYaml($version);
        } else {
            header('Content-Type: application/json');
            echo $this->generator->toJson($version);
        }
        
        exit;
    }
}