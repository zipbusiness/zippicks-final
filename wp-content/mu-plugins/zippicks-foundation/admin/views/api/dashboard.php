<?php
/**
 * ZipPicks API Dashboard
 * 
 * Main dashboard for API Gateway showing metrics and status
 *
 * @package ZipPicks\Foundation
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get services from container
$container = zippicks_foundation()->getContainer();
$keyManager = $container->get('api.keys');
$versionManager = $container->get('api.versions');
$cache = $container->get('cache');

// Get current user
$currentUser = wp_get_current_user();

// Get user's API keys
$userKeys = $keyManager->listUserKeys($currentUser->ID);

// Get API metrics from cache
$metrics = $cache->get('api_metrics_dashboard') ?: [
    'total_requests_today' => 0,
    'active_keys' => count($userKeys),
    'avg_response_time' => 0,
    'error_rate' => 0
];

?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-cloud" style="font-size: 36px; width: 36px; height: 36px; margin-right: 10px;"></span>
        ZipPicks API Gateway
    </h1>

    <hr class="wp-header-end">

    <!-- Status Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
        
        <!-- API Status -->
        <div class="card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0; color: #23282d;">
                <span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span>
                API Status
            </h3>
            <p style="font-size: 24px; margin: 10px 0; color: #46b450;">Operational</p>
            <p style="color: #666; margin: 0;">All systems running normally</p>
        </div>

        <!-- Today's Requests -->
        <div class="card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0; color: #23282d;">
                <span class="dashicons dashicons-chart-bar"></span>
                Today's Requests
            </h3>
            <p style="font-size: 24px; margin: 10px 0; color: #0073aa;">
                <?php echo number_format($metrics['total_requests_today']); ?>
            </p>
            <p style="color: #666; margin: 0;">Total API calls today</p>
        </div>

        <!-- Active API Keys -->
        <div class="card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0; color: #23282d;">
                <span class="dashicons dashicons-admin-network"></span>
                Active API Keys
            </h3>
            <p style="font-size: 24px; margin: 10px 0; color: #0073aa;">
                <?php echo $metrics['active_keys']; ?>
            </p>
            <p style="color: #666; margin: 0;">Your active API keys</p>
        </div>

        <!-- Average Response Time -->
        <div class="card" style="background: white; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h3 style="margin-top: 0; color: #23282d;">
                <span class="dashicons dashicons-clock"></span>
                Avg Response Time
            </h3>
            <p style="font-size: 24px; margin: 10px 0; color: #0073aa;">
                <?php echo round($metrics['avg_response_time'], 2); ?>ms
            </p>
            <p style="color: #666; margin: 0;">Average latency</p>
        </div>

    </div>

    <!-- Quick Actions -->
    <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2>Quick Actions</h2>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys'); ?>" class="button button-primary">
                <span class="dashicons dashicons-admin-network" style="margin-top: 3px;"></span>
                Manage API Keys
            </a>
            <a href="<?php echo admin_url('admin.php?page=zippicks-api-docs'); ?>" class="button">
                <span class="dashicons dashicons-media-document" style="margin-top: 3px;"></span>
                View API Documentation
            </a>
            <a href="<?php echo site_url('/api/v1/openapi.json'); ?>" class="button" target="_blank">
                <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                Download OpenAPI Spec
            </a>
            <a href="https://developers.zippicks.com" class="button" target="_blank">
                <span class="dashicons dashicons-external" style="margin-top: 3px;"></span>
                Developer Portal
            </a>
        </div>
    </div>

    <!-- Your API Keys -->
    <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2>Your API Keys</h2>
        
        <?php if (empty($userKeys)): ?>
            <p>You haven't created any API keys yet.</p>
            <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys&action=new'); ?>" class="button button-primary">
                Create Your First API Key
            </a>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Key Prefix</th>
                        <th>Tier</th>
                        <th>Last Used</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($userKeys as $key): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($key->name); ?></strong>
                                <?php if ($key->expires_at && strtotime($key->expires_at) < time()): ?>
                                    <span style="color: #d63638;">(Expired)</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code><?php echo esc_html($key->key_prefix); ?>...</code>
                            </td>
                            <td>
                                <span class="badge" style="background: #dcdcde; padding: 3px 8px; border-radius: 3px;">
                                    <?php echo esc_html(ucfirst($key->tier)); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $key->last_used_at ? human_time_diff(strtotime($key->last_used_at)) . ' ago' : 'Never'; ?>
                            </td>
                            <td>
                                <?php echo date('M j, Y', strtotime($key->created_at)); ?>
                            </td>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys&action=view&id=' . $key->id); ?>">
                                    View
                                </a> |
                                <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys&action=edit&id=' . $key->id); ?>">
                                    Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <p style="margin-top: 10px;">
                <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys'); ?>">Manage all keys →</a>
            </p>
        <?php endif; ?>
    </div>

    <!-- API Versions -->
    <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <h2>API Versions</h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Features</th>
                    <th>Documentation</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($versionManager->getSupportedVersions() as $version): ?>
                    <?php $info = $versionManager->getVersionInfo($version); ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($version); ?></strong>
                            <?php if ($version === 'v1'): ?>
                                <span style="color: #666;">(Current)</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($info['deprecated']): ?>
                                <span style="color: #d63638;">Deprecated</span>
                                <?php if ($info['sunset']): ?>
                                    <br><small>Sunset: <?php echo esc_html($info['sunset']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #46b450;"><?php echo ucfirst($info['status']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo count($info['features']); ?> endpoints
                        </td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=zippicks-api-docs&version=' . $version); ?>">
                                View Docs
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Getting Started -->
    <div style="background: #f0f6fc; padding: 20px; margin: 20px 0; border: 1px solid #0073aa; border-radius: 4px;">
        <h2 style="margin-top: 0;">🚀 Getting Started with ZipPicks API</h2>
        
        <ol>
            <li><strong>Create an API Key:</strong> Go to <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys'); ?>">API Keys</a> to create your first key</li>
            <li><strong>Read the Documentation:</strong> Check out our <a href="<?php echo admin_url('admin.php?page=zippicks-api-docs'); ?>">interactive API docs</a></li>
            <li><strong>Make Your First Request:</strong> Try this simple cURL command:
                <pre style="background: white; padding: 10px; margin: 10px 0; border-radius: 3px;"><code>curl -X GET https://api.zippicks.com/v1/businesses \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Accept: application/json"</code></pre>
            </li>
            <li><strong>Download an SDK:</strong> We have SDKs for <a href="#">PHP</a>, <a href="#">JavaScript</a>, and <a href="#">Python</a></li>
        </ol>
        
        <p style="margin-bottom: 0;">
            Need help? Visit our <a href="https://developers.zippicks.com" target="_blank">Developer Portal</a> or 
            <a href="mailto:api@zippicks.com">contact our API support team</a>.
        </p>
    </div>

</div>

<style>
    .badge {
        display: inline-block;
        padding: 3px 8px;
        font-size: 12px;
        line-height: 1.4;
        border-radius: 3px;
        font-weight: 600;
    }
    
    .card h3 {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .button .dashicons {
        width: 16px;
        height: 16px;
        font-size: 16px;
    }
    
    pre code {
        display: block;
        overflow-x: auto;
    }
</style>