<?php
/**
 * Settings page view
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
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('zippicks_social_settings'); ?>
    
    <form method="post" action="">
        <?php wp_nonce_field('zippicks_social_settings'); ?>
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="zippicks_social_enable_notifications">
                            <?php _e('Enable Notifications', 'zippicks-social'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="zippicks_social_enable_notifications"
                                   name="zippicks_social_enable_notifications" 
                                   value="yes" 
                                   <?php checked($settings['enable_notifications'], 'yes'); ?>>
                            <?php _e('Send notifications for new followers and activities', 'zippicks-social'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="zippicks_social_enable_activity_feed">
                            <?php _e('Enable Activity Feed', 'zippicks-social'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="zippicks_social_enable_activity_feed"
                                   name="zippicks_social_enable_activity_feed" 
                                   value="yes" 
                                   <?php checked($settings['enable_activity_feed'], 'yes'); ?>>
                            <?php _e('Show activity feed on user profiles', 'zippicks-social'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="zippicks_social_enable_suggestions">
                            <?php _e('Enable Follow Suggestions', 'zippicks-social'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" 
                                   id="zippicks_social_enable_suggestions"
                                   name="zippicks_social_enable_suggestions" 
                                   value="yes" 
                                   <?php checked($settings['enable_suggestions'], 'yes'); ?>>
                            <?php _e('Show personalized follow suggestions to users', 'zippicks-social'); ?>
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="zippicks_social_follow_rate_limit">
                            <?php _e('Follow Rate Limit', 'zippicks-social'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="zippicks_social_follow_rate_limit"
                               name="zippicks_social_follow_rate_limit" 
                               value="<?php echo esc_attr($settings['follow_rate_limit']); ?>"
                               min="1"
                               max="500"
                               class="small-text">
                        <p class="description">
                            <?php _e('Maximum follows per hour per user (prevents spam)', 'zippicks-social'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="zippicks_social_activity_retention_days">
                            <?php _e('Activity Retention', 'zippicks-social'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="zippicks_social_activity_retention_days"
                               name="zippicks_social_activity_retention_days" 
                               value="<?php echo esc_attr($settings['activity_retention_days']); ?>"
                               min="7"
                               max="365"
                               class="small-text">
                        <?php _e('days', 'zippicks-social'); ?>
                        <p class="description">
                            <?php _e('How long to keep activity data before automatic cleanup', 'zippicks-social'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="zippicks_social_cache_duration">
                            <?php _e('Cache Duration', 'zippicks-social'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number" 
                               id="zippicks_social_cache_duration"
                               name="zippicks_social_cache_duration" 
                               value="<?php echo esc_attr($settings['cache_duration']); ?>"
                               min="60"
                               max="3600"
                               class="small-text">
                        <?php _e('seconds', 'zippicks-social'); ?>
                        <p class="description">
                            <?php _e('How long to cache follow counts and data', 'zippicks-social'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <div class="zps-admin-section">
        <h2><?php _e('Quick Links', 'zippicks-social'); ?></h2>
        <ul>
            <li>
                <a href="<?php echo admin_url('admin.php?page=zippicks-social-analytics'); ?>">
                    <?php _e('View Analytics Dashboard', 'zippicks-social'); ?>
                </a>
            </li>
            <li>
                <a href="<?php echo admin_url('admin.php?page=zippicks-social-database'); ?>">
                    <?php _e('Database Management', 'zippicks-social'); ?>
                </a>
            </li>
        </ul>
    </div>
</div>