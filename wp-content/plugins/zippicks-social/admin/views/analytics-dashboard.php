<?php
/**
 * Analytics dashboard view
 *
 * @package ZipPicks_Social
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Social Analytics', 'zippicks-social'); ?></h1>
    
    <div class="zps-analytics-grid">
        <div class="zps-stat-card">
            <h3><?php _e('Total Follows', 'zippicks-social'); ?></h3>
            <p class="zps-stat-number"><?php echo number_format_i18n($stats['total_follows']); ?></p>
        </div>
        
        <div class="zps-stat-card">
            <h3><?php _e('New Follows Today', 'zippicks-social'); ?></h3>
            <p class="zps-stat-number"><?php echo number_format_i18n($stats['today_follows']); ?></p>
        </div>
        
        <div class="zps-stat-card">
            <h3><?php _e('Recent Activities', 'zippicks-social'); ?></h3>
            <p class="zps-stat-number"><?php echo number_format_i18n($stats['recent_activities']); ?></p>
            <p class="zps-stat-label"><?php _e('Last 7 days', 'zippicks-social'); ?></p>
        </div>
    </div>
    
    <div class="zps-analytics-section">
        <h2><?php _e('Most Followed', 'zippicks-social'); ?></h2>
        
        <?php if (!empty($stats['most_followed'])): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Entity', 'zippicks-social'); ?></th>
                        <th><?php _e('Type', 'zippicks-social'); ?></th>
                        <th><?php _e('Followers', 'zippicks-social'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['most_followed'] as $entity): ?>
                        <tr>
                            <td>
                                <?php
                                switch ($entity->entity_type) {
                                    case 'user':
                                    case 'critic':
                                        $user = get_userdata($entity->entity_id);
                                        if ($user) {
                                            echo esc_html($user->display_name);
                                            echo ' <a href="' . get_author_posts_url($entity->entity_id) . '" target="_blank">&rarr;</a>';
                                        } else {
                                            echo __('Unknown User', 'zippicks-social');
                                        }
                                        break;
                                        
                                    case 'business':
                                        $post = get_post($entity->entity_id);
                                        if ($post) {
                                            echo esc_html($post->post_title);
                                            echo ' <a href="' . get_permalink($entity->entity_id) . '" target="_blank">&rarr;</a>';
                                        } else {
                                            echo __('Unknown Business', 'zippicks-social');
                                        }
                                        break;
                                        
                                    case 'list':
                                        $post = get_post($entity->entity_id);
                                        if ($post) {
                                            echo esc_html($post->post_title);
                                            echo ' <a href="' . get_permalink($entity->entity_id) . '" target="_blank">&rarr;</a>';
                                        } else {
                                            echo __('Unknown List', 'zippicks-social');
                                        }
                                        break;
                                }
                                ?>
                            </td>
                            <td>
                                <span class="zps-entity-type zps-type-<?php echo esc_attr($entity->entity_type); ?>">
                                    <?php echo esc_html(ucfirst($entity->entity_type)); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo number_format_i18n($entity->followers_count); ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php _e('No follow data available yet.', 'zippicks-social'); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="zps-analytics-actions">
        <h3><?php _e('Actions', 'zippicks-social'); ?></h3>
        <p>
            <a href="<?php echo admin_url('admin.php?page=zippicks-social'); ?>" class="button">
                <?php _e('Configure Settings', 'zippicks-social'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=zippicks-social-database'); ?>" class="button">
                <?php _e('Database Management', 'zippicks-social'); ?>
            </a>
        </p>
    </div>
</div>

<style>
.zps-analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.zps-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    text-align: center;
}

.zps-stat-card h3 {
    margin: 0 0 10px;
    color: #23282d;
    font-size: 14px;
    font-weight: 600;
}

.zps-stat-number {
    font-size: 32px;
    font-weight: bold;
    margin: 0;
    color: #0073aa;
}

.zps-stat-label {
    margin: 5px 0 0;
    color: #666;
    font-size: 12px;
}

.zps-analytics-section {
    margin: 40px 0;
}

.zps-entity-type {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 500;
}

.zps-type-user { background: #e8f5e9; color: #2e7d32; }
.zps-type-critic { background: #e3f2fd; color: #1565c0; }
.zps-type-business { background: #fff3e0; color: #e65100; }
.zps-type-list { background: #f3e5f5; color: #6a1b9a; }

.zps-analytics-actions {
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid #ccd0d4;
}
</style>