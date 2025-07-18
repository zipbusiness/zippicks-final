<?php
/**
 * Security Dashboard Template
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access prohibited');
}

// Security validation
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'zippicks-vibes'));
}

// Initialize template helper
$helper = $controller->get_template_helper();
?>

<div class="wrap zippicks-vibes-admin" role="main">
    <div class="zippicks-header">
        <h1 id="security-heading"><?php esc_html_e('Security Dashboard', 'zippicks-vibes'); ?></h1>
        <nav aria-labelledby="security-heading" class="header-actions">
            <button type="button" 
                    class="button" 
                    id="refresh-security-data"
                    aria-label="<?php esc_attr_e('Refresh security data display', 'zippicks-vibes'); ?>">
                <?php esc_html_e('Refresh Data', 'zippicks-vibes'); ?>
            </button>
        </nav>
    </div>

    <!-- Security Stats -->
    <div class="stats-row">
        <div class="stat-card security-stat">
            <span class="stat-number"><?php echo esc_html($security_stats['total_requests_today'] ?? 0); ?></span>
            <span class="stat-label"><?php _e('Requests Today', 'zippicks-vibes'); ?></span>
        </div>
        <div class="stat-card security-stat">
            <span class="stat-number"><?php echo esc_html($security_stats['blocked_requests_today'] ?? 0); ?></span>
            <span class="stat-label"><?php _e('Blocked Today', 'zippicks-vibes'); ?></span>
        </div>
        <div class="stat-card security-stat">
            <span class="stat-number"><?php echo esc_html($security_stats['suspicious_activity_today'] ?? 0); ?></span>
            <span class="stat-label"><?php _e('Suspicious Activity', 'zippicks-vibes'); ?></span>
        </div>
        <div class="stat-card security-stat">
            <span class="stat-number"><?php echo esc_html($security_stats['blocked_ips_active'] ?? 0); ?></span>
            <span class="stat-label"><?php _e('Blocked IPs', 'zippicks-vibes'); ?></span>
        </div>
    </div>

    <!-- Security Features Status -->
    <div class="security-features">
        <h2><?php _e('Security Features Status', 'zippicks-vibes'); ?></h2>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Feature', 'zippicks-vibes'); ?></th>
                    <th><?php _e('Status', 'zippicks-vibes'); ?></th>
                    <th><?php _e('Description', 'zippicks-vibes'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php _e('Rate Limiting', 'zippicks-vibes'); ?></strong></td>
                    <td><span class="status-active">✓ <?php _e('Active', 'zippicks-vibes'); ?></span></td>
                    <td><?php _e('Limits requests per IP to prevent abuse', 'zippicks-vibes'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('User Agent Filtering', 'zippicks-vibes'); ?></strong></td>
                    <td><span class="status-active">✓ <?php _e('Active', 'zippicks-vibes'); ?></span></td>
                    <td><?php _e('Blocks known bot and scraper user agents', 'zippicks-vibes'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Referrer Validation', 'zippicks-vibes'); ?></strong></td>
                    <td><span class="status-active">✓ <?php _e('Active', 'zippicks-vibes'); ?></span></td>
                    <td><?php _e('Validates request referrers to prevent unauthorized access', 'zippicks-vibes'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Content Watermarking', 'zippicks-vibes'); ?></strong></td>
                    <td><span class="status-active">✓ <?php _e('Active', 'zippicks-vibes'); ?></span></td>
                    <td><?php _e('Embeds invisible watermarks in content for tracking', 'zippicks-vibes'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php _e('Security Headers', 'zippicks-vibes'); ?></strong></td>
                    <td><span class="status-active">✓ <?php _e('Active', 'zippicks-vibes'); ?></span></td>
                    <td><?php _e('Adds security headers to prevent content scraping', 'zippicks-vibes'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Recent Security Events -->
    <div class="security-events">
        <h2><?php _e('Recent Security Events', 'zippicks-vibes'); ?></h2>
        
        <?php if (empty($recent_attempts)): ?>
            <div class="empty-state">
                <h3><?php _e('No security events recorded', 'zippicks-vibes'); ?></h3>
                <p><?php _e('This is good! No suspicious activity has been detected recently.', 'zippicks-vibes'); ?></p>
            </div>
        <?php else: ?>
            <div class="security-log">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'zippicks-vibes'); ?></th>
                            <th><?php _e('Type', 'zippicks-vibes'); ?></th>
                            <th><?php _e('IP Address', 'zippicks-vibes'); ?></th>
                            <th><?php _e('User Agent', 'zippicks-vibes'); ?></th>
                            <th><?php _e('Path', 'zippicks-vibes'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($recent_attempts, 0, 20) as $attempt): ?>
                            <tr>
                                <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($attempt['timestamp']))); ?></td>
                                <td>
                                    <span class="event-type event-type-<?php echo esc_attr($attempt['type']); ?>">
                                        <?php echo esc_html(str_replace('_', ' ', $attempt['type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?php echo esc_html($attempt['ip_address']); ?></code>
                                </td>
                                <td>
                                    <span title="<?php echo esc_attr($attempt['user_agent']); ?>">
                                        <?php echo esc_html(substr($attempt['user_agent'], 0, 50)); ?><?php if (strlen($attempt['user_agent']) > 50): ?>...<?php endif; ?>
                                    </span>
                                </td>
                                <td>
                                    <code><?php echo esc_html($attempt['request_path']); ?></code>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <?php if (count($recent_attempts) > 20): ?>
                    <p class="description">
                        <?php printf(__('Showing latest 20 of %d total events.', 'zippicks-vibes'), count($recent_attempts)); ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Security Recommendations -->
    <div class="security-recommendations">
        <h2><?php _e('Security Recommendations', 'zippicks-vibes'); ?></h2>
        
        <div class="recommendation-cards">
            <div class="recommendation-card">
                <h3><?php _e('Monitor Regularly', 'zippicks-vibes'); ?></h3>
                <p><?php _e('Check this dashboard regularly to monitor for suspicious activity and blocked requests.', 'zippicks-vibes'); ?></p>
            </div>
            
            <div class="recommendation-card">
                <h3><?php _e('Keep Updated', 'zippicks-vibes'); ?></h3>
                <p><?php _e('Ensure the plugin is kept up to date with the latest security patches and improvements.', 'zippicks-vibes'); ?></p>
            </div>
            
            <div class="recommendation-card">
                <h3><?php _e('Strong Authentication', 'zippicks-vibes'); ?></h3>
                <p><?php _e('Use strong passwords and consider two-factor authentication for admin accounts.', 'zippicks-vibes'); ?></p>
            </div>
        </div>
    </div>
</div>

<style>
.status-active {
    color: #28a745;
    font-weight: bold;
}

.event-type {
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 11px;
    text-transform: uppercase;
}

.event-type-blocked {
    background: #dc3545;
    color: white;
}

.event-type-suspicious {
    background: #ffc107;
    color: #212529;
}

.event-type-rate_limited {
    background: #fd7e14;
    color: white;
}

.recommendation-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.recommendation-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
}

.recommendation-card h3 {
    margin-top: 0;
    color: #194FAD;
}
</style>