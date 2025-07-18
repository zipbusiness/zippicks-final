<?php
/**
 * ZipPicks API Keys Management
 * 
 * Interface for creating and managing API keys
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

// Get current user
$currentUser = wp_get_current_user();

// Handle actions
$action = $_GET['action'] ?? 'list';
$keyId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!wp_verify_nonce($_POST['_wpnonce'], 'zippicks_api_key_action')) {
        wp_die('Security check failed');
    }
    
    $postAction = $_POST['action'] ?? '';
    
    switch ($postAction) {
        case 'create':
            $keyData = $keyManager->generate(
                $currentUser->ID,
                sanitize_text_field($_POST['name']),
                [
                    'tier' => sanitize_text_field($_POST['tier']),
                    'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' 23:59:59' : null
                ]
            );
            
            // Store the new key temporarily to show to user
            set_transient('zippicks_new_api_key_' . $currentUser->ID, $keyData['key'], 60);
            
            wp_redirect(admin_url('admin.php?page=zippicks-api-keys&action=created&id=' . $keyData['id']));
            exit;
            
        case 'update':
            $keyManager->update(
                intval($_POST['key_id']),
                $currentUser->ID,
                [
                    'name' => sanitize_text_field($_POST['name']),
                    'tier' => sanitize_text_field($_POST['tier']),
                    'expires_at' => !empty($_POST['expires_at']) ? $_POST['expires_at'] . ' 23:59:59' : null
                ]
            );
            
            wp_redirect(admin_url('admin.php?page=zippicks-api-keys&updated=1'));
            exit;
            
        case 'delete':
            $keyManager->revoke(intval($_POST['key_id']), $currentUser->ID);
            wp_redirect(admin_url('admin.php?page=zippicks-api-keys&deleted=1'));
            exit;
    }
}

// Get user's API keys for listing
$userKeys = $keyManager->listUserKeys($currentUser->ID);

?>

<div class="wrap">
    <h1 class="wp-heading-inline">API Keys</h1>
    
    <?php if ($action === 'list'): ?>
        <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys&action=new'); ?>" class="page-title-action">
            Add New Key
        </a>
    <?php endif; ?>
    
    <hr class="wp-header-end">
    
    <?php
    // Show notices
    if (isset($_GET['created'])):
        $newKey = get_transient('zippicks_new_api_key_' . $currentUser->ID);
        if ($newKey):
            delete_transient('zippicks_new_api_key_' . $currentUser->ID);
    ?>
        <div class="notice notice-success is-dismissible">
            <p><strong>API Key created successfully!</strong></p>
            <p>Please copy your API key now. You won't be able to see it again:</p>
            <p style="background: #f0f0f1; padding: 10px; font-family: monospace; word-break: break-all;">
                <?php echo esc_html($newKey); ?>
            </p>
        </div>
    <?php
        endif;
    endif;
    
    if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>API Key updated successfully!</p>
        </div>
    <?php endif;
    
    if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible">
            <p>API Key deleted successfully!</p>
        </div>
    <?php endif; ?>
    
    <?php if ($action === 'list'): ?>
        <!-- List API Keys -->
        <div style="background: white; padding: 20px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
            <h2>Your API Keys</h2>
            
            <?php if (empty($userKeys)): ?>
                <p>You don't have any API keys yet.</p>
                <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys&action=new'); ?>" class="button button-primary">
                    Create Your First API Key
                </a>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 200px;">Name</th>
                            <th style="width: 120px;">Key Prefix</th>
                            <th style="width: 100px;">Tier</th>
                            <th style="width: 150px;">Rate Limits</th>
                            <th style="width: 120px;">Last Used</th>
                            <th style="width: 120px;">Expires</th>
                            <th style="width: 100px;">Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userKeys as $key): ?>
                            <?php
                            $isExpired = $key->expires_at && strtotime($key->expires_at) < time();
                            $limits = $key->rate_limits;
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($key->name); ?></strong>
                                    <br>
                                    <small>Created <?php echo human_time_diff(strtotime($key->created_at)); ?> ago</small>
                                </td>
                                <td>
                                    <code><?php echo esc_html($key->key_prefix); ?>...</code>
                                </td>
                                <td>
                                    <span class="tier-badge tier-<?php echo esc_attr($key->tier); ?>">
                                        <?php echo esc_html(ucfirst($key->tier)); ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <?php if ($key->tier === 'enterprise'): ?>
                                            Unlimited
                                        <?php else: ?>
                                            <?php echo number_format($limits['requests_per_day'] ?? 0); ?>/day<br>
                                            <?php echo number_format($limits['requests_per_hour'] ?? 0); ?>/hour
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo $key->last_used_at ? human_time_diff(strtotime($key->last_used_at)) . ' ago' : 'Never'; ?>
                                </td>
                                <td>
                                    <?php if ($key->expires_at): ?>
                                        <?php echo date('M j, Y', strtotime($key->expires_at)); ?>
                                    <?php else: ?>
                                        Never
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isExpired): ?>
                                        <span style="color: #d63638;">Expired</span>
                                    <?php else: ?>
                                        <span style="color: #46b450;">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys&action=view&id=' . $key->id); ?>" 
                                       class="button button-small">View</a>
                                    <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys&action=edit&id=' . $key->id); ?>" 
                                       class="button button-small">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        
        <!-- API Key Usage Guide -->
        <div style="background: #f0f6fc; padding: 20px; margin-top: 20px; border: 1px solid #0073aa; border-radius: 4px;">
            <h3 style="margin-top: 0;">How to Use Your API Key</h3>
            
            <p>Include your API key in the request header:</p>
            <pre style="background: white; padding: 10px; border-radius: 3px;"><code>X-API-Key: YOUR_API_KEY</code></pre>
            
            <p>Example cURL request:</p>
            <pre style="background: white; padding: 10px; border-radius: 3px;"><code>curl -X GET https://api.zippicks.com/v1/businesses \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Accept: application/json"</code></pre>
            
            <p>Example JavaScript request:</p>
            <pre style="background: white; padding: 10px; border-radius: 3px;"><code>fetch('https://api.zippicks.com/v1/businesses', {
  headers: {
    'X-API-Key': 'YOUR_API_KEY',
    'Accept': 'application/json'
  }
})
.then(response => response.json())
.then(data => console.log(data));</code></pre>
        </div>
        
    <?php elseif ($action === 'new'): ?>
        <!-- Create New API Key -->
        <form method="post" action="">
            <?php wp_nonce_field('zippicks_api_key_action'); ?>
            <input type="hidden" name="action" value="create">
            
            <div style="background: white; padding: 20px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2>Create New API Key</h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="name">Key Name</label>
                        </th>
                        <td>
                            <input type="text" id="name" name="name" class="regular-text" required 
                                   placeholder="e.g., Production App" maxlength="255">
                            <p class="description">A friendly name to identify this API key</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="tier">Tier</label>
                        </th>
                        <td>
                            <select id="tier" name="tier" required>
                                <option value="free">Free - 10,000 requests/day</option>
                                <option value="starter">Starter - 100,000 requests/day ($49/mo)</option>
                                <option value="growth">Growth - 500,000 requests/day ($199/mo)</option>
                                <option value="scale">Scale - 2,000,000 requests/day ($499/mo)</option>
                                <?php if (current_user_can('manage_options')): ?>
                                    <option value="enterprise">Enterprise - Unlimited</option>
                                <?php endif; ?>
                            </select>
                            <p class="description">Select the appropriate tier for your usage</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="expires_at">Expiration Date</label>
                        </th>
                        <td>
                            <input type="date" id="expires_at" name="expires_at" 
                                   min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                            <p class="description">Leave empty for no expiration</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Create API Key</button>
                    <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys'); ?>" class="button">Cancel</a>
                </p>
            </div>
        </form>
        
    <?php elseif ($action === 'edit' && $keyId): ?>
        <?php
        // Get the key
        $keys = array_filter($userKeys, fn($k) => $k->id == $keyId);
        $key = reset($keys);
        
        if (!$key):
        ?>
            <div class="notice notice-error">
                <p>API key not found or you don't have permission to edit it.</p>
            </div>
        <?php else: ?>
            <!-- Edit API Key -->
            <form method="post" action="">
                <?php wp_nonce_field('zippicks_api_key_action'); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="key_id" value="<?php echo $key->id; ?>">
                
                <div style="background: white; padding: 20px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                    <h2>Edit API Key</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Key Prefix</th>
                            <td>
                                <code><?php echo esc_html($key->key_prefix); ?>...</code>
                                <p class="description">The full key cannot be retrieved</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="name">Key Name</label>
                            </th>
                            <td>
                                <input type="text" id="name" name="name" class="regular-text" required 
                                       value="<?php echo esc_attr($key->name); ?>" maxlength="255">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="tier">Tier</label>
                            </th>
                            <td>
                                <select id="tier" name="tier" required>
                                    <option value="free" <?php selected($key->tier, 'free'); ?>>
                                        Free - 10,000 requests/day
                                    </option>
                                    <option value="starter" <?php selected($key->tier, 'starter'); ?>>
                                        Starter - 100,000 requests/day ($49/mo)
                                    </option>
                                    <option value="growth" <?php selected($key->tier, 'growth'); ?>>
                                        Growth - 500,000 requests/day ($199/mo)
                                    </option>
                                    <option value="scale" <?php selected($key->tier, 'scale'); ?>>
                                        Scale - 2,000,000 requests/day ($499/mo)
                                    </option>
                                    <?php if (current_user_can('manage_options')): ?>
                                        <option value="enterprise" <?php selected($key->tier, 'enterprise'); ?>>
                                            Enterprise - Unlimited
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="expires_at">Expiration Date</label>
                            </th>
                            <td>
                                <input type="date" id="expires_at" name="expires_at" 
                                       value="<?php echo $key->expires_at ? date('Y-m-d', strtotime($key->expires_at)) : ''; ?>"
                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                <p class="description">Leave empty for no expiration</p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">Update API Key</button>
                        <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys'); ?>" class="button">Cancel</a>
                    </p>
                </div>
            </form>
            
            <!-- Delete Key -->
            <div style="background: white; padding: 20px; margin-top: 20px; border: 1px solid #d63638; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3 style="color: #d63638;">Delete API Key</h3>
                <p>Deleting this API key will immediately revoke access. This action cannot be undone.</p>
                
                <form method="post" action="" onsubmit="return confirm('Are you sure you want to delete this API key?');">
                    <?php wp_nonce_field('zippicks_api_key_action'); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="key_id" value="<?php echo $key->id; ?>">
                    
                    <button type="submit" class="button button-link-delete">Delete API Key</button>
                </form>
            </div>
        <?php endif; ?>
        
    <?php elseif ($action === 'view' && $keyId): ?>
        <?php
        // Get the key
        $keys = array_filter($userKeys, fn($k) => $k->id == $keyId);
        $key = reset($keys);
        
        if (!$key):
        ?>
            <div class="notice notice-error">
                <p>API key not found or you don't have permission to view it.</p>
            </div>
        <?php else: ?>
            <!-- View API Key Details -->
            <div style="background: white; padding: 20px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h2><?php echo esc_html($key->name); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Key Prefix</th>
                        <td><code><?php echo esc_html($key->key_prefix); ?>...</code></td>
                    </tr>
                    <tr>
                        <th scope="row">Tier</th>
                        <td>
                            <span class="tier-badge tier-<?php echo esc_attr($key->tier); ?>">
                                <?php echo esc_html(ucfirst($key->tier)); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Rate Limits</th>
                        <td>
                            <?php if ($key->tier === 'enterprise'): ?>
                                Unlimited
                            <?php else: ?>
                                <?php $limits = $key->rate_limits; ?>
                                <ul style="margin: 0;">
                                    <li><?php echo number_format($limits['requests_per_minute'] ?? 0); ?> requests/minute</li>
                                    <li><?php echo number_format($limits['requests_per_hour'] ?? 0); ?> requests/hour</li>
                                    <li><?php echo number_format($limits['requests_per_day'] ?? 0); ?> requests/day</li>
                                </ul>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Created</th>
                        <td><?php echo date('F j, Y g:i A', strtotime($key->created_at)); ?></td>
                    </tr>
                    <tr>
                        <th scope="row">Last Used</th>
                        <td>
                            <?php echo $key->last_used_at 
                                ? date('F j, Y g:i A', strtotime($key->last_used_at)) 
                                : 'Never'; ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Expires</th>
                        <td>
                            <?php if ($key->expires_at): ?>
                                <?php echo date('F j, Y', strtotime($key->expires_at)); ?>
                                <?php if (strtotime($key->expires_at) < time()): ?>
                                    <span style="color: #d63638;">(Expired)</span>
                                <?php endif; ?>
                            <?php else: ?>
                                Never
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <p>
                    <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys&action=edit&id=' . $key->id); ?>" 
                       class="button">Edit Key</a>
                    <a href="<?php echo admin_url('admin.php?page=zippicks-api-keys'); ?>" class="button">Back to Keys</a>
                </p>
            </div>
            
            <!-- Usage Statistics -->
            <?php
            $startDate = date('Y-m-d', strtotime('-30 days'));
            $endDate = date('Y-m-d');
            $stats = $keyManager->getUsageStats($key->id, $startDate, $endDate);
            ?>
            
            <div style="background: white; padding: 20px; margin-top: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
                <h3>Usage Statistics (Last 30 Days)</h3>
                
                <?php if (empty($stats)): ?>
                    <p>No usage data available yet.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Endpoint</th>
                                <th>Total Requests</th>
                                <th>Errors</th>
                                <th>Error Rate</th>
                                <th>Avg Latency</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($stats as $stat): ?>
                                <tr>
                                    <td><code><?php echo esc_html($stat['endpoint']); ?></code></td>
                                    <td><?php echo number_format($stat['total_requests']); ?></td>
                                    <td><?php echo number_format($stat['total_errors']); ?></td>
                                    <td>
                                        <?php 
                                        $errorRate = $stat['total_requests'] > 0 
                                            ? ($stat['total_errors'] / $stat['total_requests']) * 100 
                                            : 0;
                                        echo number_format($errorRate, 2) . '%';
                                        ?>
                                    </td>
                                    <td><?php echo number_format($stat['avg_latency'], 2); ?>ms</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
    <?php endif; ?>
</div>

<style>
    .tier-badge {
        display: inline-block;
        padding: 3px 8px;
        font-size: 12px;
        line-height: 1.4;
        border-radius: 3px;
        font-weight: 600;
        text-transform: uppercase;
    }
    
    .tier-free {
        background: #dcdcde;
        color: #2c3338;
    }
    
    .tier-starter {
        background: #d6f0ff;
        color: #0073aa;
    }
    
    .tier-growth {
        background: #d7f5d7;
        color: #008a00;
    }
    
    .tier-scale {
        background: #ffd6d6;
        color: #d63638;
    }
    
    .tier-enterprise {
        background: #f5e6ff;
        color: #6c2eb9;
    }
    
    pre code {
        display: block;
        overflow-x: auto;
    }
</style>