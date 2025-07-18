<?php
/**
 * ZipPicks WordPress Admin Enhancements
 * File: admin/admin-enhancements.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZipPicksAdminEnhancements {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'addAdminMenus'));
        add_action('admin_post_zippicks_approve_user', array($this, 'handleUserApproval'));
        add_action('admin_post_zippicks_reject_user', array($this, 'handleUserRejection'));
        add_action('admin_notices', array($this, 'showAdminNotices'));
        add_action('wp_dashboard_setup', array($this, 'addDashboardWidget'));
    }
    
    public function addAdminMenus() {
        // Add ZipPicks submenu under Users
        add_users_page(
            'ZipPicks Management',
            'ZipPicks Users',
            'manage_options',
            'zippicks-management',
            array($this, 'renderManagementPage')
        );
    }
    
    public function renderManagementPage() {
        require_once ZIPPICKS_PLUGIN_DIR . 'includes/user-management.php';
        
        // Handle pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get ZipPicks users
        $users = ZipPicksUserManagement::getUsers(array(
            'number' => $per_page,
            'offset' => $offset
        ));
        
        $total_users = ZipPicksUserManagement::getUserCount();
        $total_pages = ceil($total_users / $per_page);
        $stats = ZipPicksUserManagement::getUserStats();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">ZipPicks User Management</h1>
            
            <!-- Stats Cards -->
            <div class="zp-stats-grid">
                <div class="zp-stat-card">
                    <h3>Total Users</h3>
                    <div class="zp-stat-number"><?php echo number_format($stats['total']); ?></div>
                </div>
                <div class="zp-stat-card">
                    <h3>Verified</h3>
                    <div class="zp-stat-number"><?php echo number_format($stats['by_status']['verified']); ?></div>
                </div>
                <div class="zp-stat-card">
                    <h3>Approved</h3>
                    <div class="zp-stat-number"><?php echo number_format($stats['by_status']['approved']); ?></div>
                </div>
                <div class="zp-stat-card">
                    <h3>Pending Approval</h3>
                    <div class="zp-stat-number"><?php echo number_format($stats['by_status']['pending_approval']); ?></div>
                </div>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="notice notice-info">
                    <p>No ZipPicks users found.</p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Type</th>
                            <th>ZIP</th>
                            <th>Verified</th>
                            <th>Approved</th>
                            <th>Registered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): 
                            $profile = ZipPicksUserManagement::getUserProfile($user->ID);
                        ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $user->ID); ?>">
                                            <?php echo esc_html($user->display_name); ?>
                                        </a>
                                    </strong>
                                    <br><small><?php echo esc_html($user->user_login); ?></small>
                                </td>
                                <td>
                                    <a href="mailto:<?php echo esc_attr($user->user_email); ?>">
                                        <?php echo esc_html($user->user_email); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="zp-badge zp-badge-<?php echo esc_attr($profile['user_type']); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $profile['user_type']))); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($profile['zip']); ?></td>
                                <td>
                                    <?php if ($profile['email_verified']): ?>
                                        <span class="zp-status verified">✅ Yes</span>
                                    <?php else: ?>
                                        <span class="zp-status unverified">❌ No</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($profile['approved']): ?>
                                        <span class="zp-status approved">✅ Yes</span>
                                    <?php else: ?>
                                        <span class="zp-status pending">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html(date('M j, Y', strtotime($user->user_registered))); ?>
                                </td>
                                <td>
                                    <div class="zp-actions">
                                        <?php if ($profile['email_verified'] && !$profile['approved'] && in_array($profile['user_type'], ['critic', 'business_owner'])): ?>
                                            <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=zippicks_approve_user&user_id=' . $user->ID), 'zippicks_approve_' . $user->ID); ?>" 
                                               class="button button-primary button-small"
                                               onclick="return confirm('Approve this user?')">
                                                Approve
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=zippicks_reject_user&user_id=' . $user->ID), 'zippicks_reject_' . $user->ID); ?>" 
                                           class="button button-secondary button-small"
                                           onclick="return confirm('Remove this user? This action cannot be undone.')">
                                            Remove
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <?php
                            $page_links = paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page
                            ));
                            
                            if ($page_links) {
                                echo '<span class="displaying-num">' . sprintf(
                                    _n('%s item', '%s items', $total_users),
                                    number_format_i18n($total_users)
                                ) . '</span>';
                                echo $page_links;
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <style>
        .zp-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .zp-stat-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .zp-stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }
        
        .zp-stat-number {
            font-size: 28px;
            font-weight: bold;
            color: #23282d;
        }
        
        .zp-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .zp-badge-zipper { background: #e3f2fd; color: #1565c0; }
        .zp-badge-critic { background: #f3e5f5; color: #7b1fa2; }
        .zp-badge-business_owner { background: #e8f5e8; color: #2e7d32; }
        
        .zp-status.verified { color: #00a32a; }
        .zp-status.approved { color: #00a32a; }
        .zp-status.unverified { color: #d63638; }
        .zp-status.pending { color: #dba617; }
        
        .zp-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        
        .button-small {
            padding: 2px 8px !important;
            font-size: 11px !important;
            line-height: 1.4 !important;
            height: auto !important;
        }
        </style>
        <?php
    }
    
    public function handleUserApproval() {
        $user_id = intval($_GET['user_id']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'zippicks_approve_' . $user_id)) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions');
        }
        
        require_once ZIPPICKS_PLUGIN_DIR . 'includes/user-management.php';
        $result = ZipPicksUserManagement::approveUser($user_id);
        
        if (is_wp_error($result)) {
            $message = urlencode('Error: ' . $result->get_error_message());
            $type = 'error';
        } else {
            $message = urlencode('User approved successfully!');
            $type = 'success';
        }
        
        wp_redirect(admin_url('admin.php?page=zippicks-management&message=' . $message . '&type=' . $type));
        exit;
    }
    
    public function handleUserRejection() {
        $user_id = intval($_GET['user_id']);
        
        if (!wp_verify_nonce($_GET['_wpnonce'], 'zippicks_reject_' . $user_id)) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions');
        }
        
        require_once ZIPPICKS_PLUGIN_DIR . 'includes/user-management.php';
        $result = ZipPicksUserManagement::rejectUser($user_id);
        
        if ($result) {
            $message = urlencode('User removed successfully!');
            $type = 'success';
        } else {
            $message = urlencode('Failed to remove user.');
            $type = 'error';
        }
        
        wp_redirect(admin_url('admin.php?page=zippicks-management&message=' . $message . '&type=' . $type));
        exit;
    }
    
    public function showAdminNotices() {
        if (isset($_GET['message']) && isset($_GET['type'])) {
            $message = sanitize_text_field($_GET['message']);
            $type = sanitize_text_field($_GET['type']);
            $class = $type === 'success' ? 'notice-success' : 'notice-error';
            
            echo '<div class="notice ' . $class . ' is-dismissible"><p>' . esc_html(urldecode($message)) . '</p></div>';
        }
    }
    
    public function addDashboardWidget() {
        wp_add_dashboard_widget(
            'zippicks_dashboard_widget',
            'ZipPicks User Activity',
            array($this, 'renderDashboardWidget')
        );
    }
    
    public function renderDashboardWidget() {
        require_once ZIPPICKS_PLUGIN_DIR . 'includes/user-management.php';
        
        $stats = ZipPicksUserManagement::getUserStats();
        $awaiting_approval = ZipPicksUserManagement::getUsersAwaitingApproval();
        
        echo '<div class="zp-dashboard-widget">';
        echo '<p><strong>Total ZipPicks Users:</strong> ' . number_format($stats['total']) . '</p>';
        echo '<p><strong>Awaiting Approval:</strong> ' . count($awaiting_approval) . '</p>';
        
        if (count($awaiting_approval) > 0) {
            echo '<p><a href="' . admin_url('admin.php?page=zippicks-management') . '" class="button button-primary">Review Pending Users</a></p>';
        }
        
        echo '<p><a href="' . admin_url('admin.php?page=zippicks-management') . '">Manage All Users</a></p>';
        echo '</div>';
    }
}

// Initialize admin enhancements
new ZipPicksAdminEnhancements();