<?php
/**
 * Database management page view
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
    <h1><?php _e('Database Management', 'zippicks-social'); ?></h1>
    
    <div class="zps-database-status">
        <h2><?php _e('Migration Status', 'zippicks-social'); ?></h2>
        
        <table class="wp-list-table widefat">
            <tbody>
                <tr>
                    <th><?php _e('Current Version', 'zippicks-social'); ?></th>
                    <td>
                        <code><?php echo esc_html($migration_status['current_version']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Target Version', 'zippicks-social'); ?></th>
                    <td>
                        <code><?php echo esc_html($migration_status['target_version']); ?></code>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Status', 'zippicks-social'); ?></th>
                    <td>
                        <?php if ($migration_status['needs_migration']): ?>
                            <span class="dashicons dashicons-warning" style="color: #d63638;"></span>
                            <?php _e('Migration Required', 'zippicks-social'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                            <?php _e('Up to Date', 'zippicks-social'); ?>
                        <?php endif; ?>
                        
                        <?php if ($migration_status['is_locked']): ?>
                            <br>
                            <span class="dashicons dashicons-lock" style="color: #d63638;"></span>
                            <?php _e('Migration is currently locked', 'zippicks-social'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Tables Exist', 'zippicks-social'); ?></th>
                    <td>
                        <?php if ($tables_exist): ?>
                            <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                            <?php _e('All tables present', 'zippicks-social'); ?>
                        <?php else: ?>
                            <span class="dashicons dashicons-dismiss" style="color: #d63638;"></span>
                            <?php _e('Tables missing', 'zippicks-social'); ?>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php if ($migration_status['needs_migration']): ?>
            <h3><?php _e('Pending Migrations', 'zippicks-social'); ?></h3>
            <ul>
                <?php foreach ($migration_status['pending_migrations'] as $version): ?>
                    <li><?php echo esc_html($version); ?></li>
                <?php endforeach; ?>
            </ul>
            
            <p>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zippicks-social-database&action=run-migration'), 'run_migration_action'); ?>" 
                   class="button button-primary">
                    <?php _e('Run Migration', 'zippicks-social'); ?>
                </a>
            </p>
        <?php endif; ?>
        
        <?php if (!$tables_exist): ?>
            <div class="notice notice-error inline">
                <p>
                    <?php _e('Database tables are missing. You can try to create them manually.', 'zippicks-social'); ?>
                </p>
                <p>
                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zippicks-social-database&action=create-tables'), 'create_tables_action'); ?>" 
                       class="button button-primary">
                        <?php _e('Create Tables', 'zippicks-social'); ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="zps-database-info">
        <h2><?php _e('Table Information', 'zippicks-social'); ?></h2>
        
        <?php
        global $wpdb;
        $tables = [
            'follows' => $wpdb->prefix . 'zippicks_follows',
            'follow_stats' => $wpdb->prefix . 'zippicks_follow_stats',
            'activities' => $wpdb->prefix . 'zippicks_activities',
            'suggestions' => $wpdb->prefix . 'zippicks_follow_suggestions'
        ];
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Table', 'zippicks-social'); ?></th>
                    <th><?php _e('Name', 'zippicks-social'); ?></th>
                    <th><?php _e('Status', 'zippicks-social'); ?></th>
                    <th><?php _e('Rows', 'zippicks-social'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tables as $key => $table_name): ?>
                    <?php
                    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
                    $count = $exists ? $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}") : 0;
                    ?>
                    <tr>
                        <td><?php echo esc_html(ucwords(str_replace('_', ' ', $key))); ?></td>
                        <td><code><?php echo esc_html($table_name); ?></code></td>
                        <td>
                            <?php if ($exists): ?>
                                <span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
                                <?php _e('Exists', 'zippicks-social'); ?>
                            <?php else: ?>
                                <span class="dashicons dashicons-dismiss" style="color: #d63638;"></span>
                                <?php _e('Missing', 'zippicks-social'); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo $exists ? number_format_i18n($count) : '-'; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="zps-database-tools">
        <h2><?php _e('Database Tools', 'zippicks-social'); ?></h2>
        
        <div class="card">
            <h3><?php _e('Manual SQL', 'zippicks-social'); ?></h3>
            <p><?php _e('If automatic table creation fails, you can create tables manually using phpMyAdmin or similar:', 'zippicks-social'); ?></p>
            
            <details>
                <summary><?php _e('Show SQL Statements', 'zippicks-social'); ?></summary>
                <pre style="background: #f0f0f1; padding: 10px; overflow-x: auto;">
<?php
require_once ZIPPICKS_SOCIAL_PLUGIN_DIR . 'includes/class-database.php';
$schemas = ZipPicks_Social_Database::get_schema_sql();
foreach ($schemas as $name => $sql) {
    echo "-- " . esc_html(ucwords(str_replace('_', ' ', $name))) . "\n";
    echo esc_html($sql) . "\n\n";
}
?>
                </pre>
            </details>
        </div>
    </div>
</div>

<style>
.zps-database-status,
.zps-database-info,
.zps-database-tools {
    margin: 30px 0;
}

.zps-database-status table th {
    width: 200px;
}

details {
    margin-top: 10px;
}

summary {
    cursor: pointer;
    color: #2271b1;
    text-decoration: underline;
}

summary:hover {
    color: #135e96;
}
</style>