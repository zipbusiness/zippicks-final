<?php
/**
 * Admin Dashboard Template - Hardened with accessibility and security
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

// Only verify nonce for form submissions, not for regular page visits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !wp_verify_nonce($nonce ?? '', 'zippicks_vibes_dashboard')) {
    wp_die(__('Security check failed', 'zippicks-vibes'));
}

// Initialize template helper
$helper = $controller->get_template_helper();
?>

<div class="wrap zippicks-vibes-admin" role="main">
    <div class="zippicks-header">
        <div class="header-title">
            <span class="heart-icon">💙</span>
            <h1 id="main-heading"><?php esc_html_e('ZipPicks Vibes', 'zippicks-vibes'); ?> <span class="count">(<?php echo esc_html($stats['total_vibes']); ?>)</span></h1>
        </div>
        <nav aria-labelledby="main-heading" class="header-actions">
            <a href="<?php echo esc_url(admin_url('admin.php?page=zippicks-vibes-add')); ?>" 
               class="button button-primary add-new-vibe-btn"
               aria-label="<?php esc_attr_e('Create a new vibe', 'zippicks-vibes'); ?>">
                <span class="plus-icon">+</span> <?php esc_html_e('Add New Vibe', 'zippicks-vibes'); ?>
            </a>
        </nav>
    </div>

    <!-- Dashboard Overview Statistics -->
    <section aria-labelledby="stats-heading" class="dashboard-stats">
        <h2 id="stats-heading" class="sr-only"><?php esc_html_e('Dashboard Statistics', 'zippicks-vibes'); ?></h2>
        <div class="stats-row" role="list">
            <div class="stat-card" role="listitem">
                <span class="stat-number" aria-label="<?php esc_attr_e('Total vibes count', 'zippicks-vibes'); ?>">
                    <?php echo esc_html(number_format_i18n($stats['total_vibes'])); ?>
                </span>
                <span class="stat-label"><?php esc_html_e('Total Vibes', 'zippicks-vibes'); ?></span>
            </div>
            <div class="stat-card" role="listitem">
                <span class="stat-number" aria-label="<?php esc_attr_e('Active vibes count', 'zippicks-vibes'); ?>">
                    <?php echo esc_html(number_format_i18n($stats['active_vibes'])); ?>
                </span>
                <span class="stat-label"><?php esc_html_e('Active', 'zippicks-vibes'); ?></span>
            </div>
            <div class="stat-card" role="listitem">
                <span class="stat-number" aria-label="<?php esc_attr_e('Inactive vibes count', 'zippicks-vibes'); ?>">
                    <?php echo esc_html(number_format_i18n($stats['total_vibes'] - $stats['active_vibes'])); ?>
                </span>
                <span class="stat-label"><?php esc_html_e('Inactive', 'zippicks-vibes'); ?></span>
            </div>
            <div class="stat-card" role="listitem">
                <span class="stat-number" aria-label="<?php esc_attr_e('Categories count', 'zippicks-vibes'); ?>">
                    <?php echo esc_html(number_format_i18n($stats['total_categories'])); ?>
                </span>
                <span class="stat-label"><?php esc_html_e('Categories', 'zippicks-vibes'); ?></span>
            </div>
        </div>
    </section>

    <!-- Vibes Management Section -->
    <section aria-labelledby="vibes-heading" class="vibes-list">
        <h2 id="vibes-heading"><?php esc_html_e('All Vibes', 'zippicks-vibes'); ?></h2>
        
        <?php if (empty($vibes)): ?>
            <div class="empty-state" role="region" aria-labelledby="empty-heading">
                <h3 id="empty-heading"><?php esc_html_e('No vibes found', 'zippicks-vibes'); ?></h3>
                <p><?php esc_html_e('Get started by creating your first vibe! Vibes help categorize and organize your content based on mood and style.', 'zippicks-vibes'); ?></p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=zippicks-vibes-add')); ?>" 
                   class="button button-primary"
                   aria-label="<?php esc_attr_e('Create your first vibe', 'zippicks-vibes'); ?>">
                    <?php esc_html_e('Create First Vibe', 'zippicks-vibes'); ?>
                </a>
            </div>
        <?php else: ?>
            <!-- Bulk actions form -->
            <?php if ($capabilities['can_bulk_edit'] ?? false): ?>
            <form method="post" id="bulk-actions-form" aria-label="<?php esc_attr_e('Bulk actions for vibes', 'zippicks-vibes'); ?>">
                <?php wp_nonce_field('zippicks_vibes_bulk_action', '_wpnonce_bulk'); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <label for="bulk-action-selector-top" class="sr-only"><?php esc_html_e('Select bulk action', 'zippicks-vibes'); ?></label>
                        <select name="action" id="bulk-action-selector-top">
                            <option value="-1"><?php esc_html_e('Bulk actions', 'zippicks-vibes'); ?></option>
                            <option value="activate"><?php esc_html_e('Activate', 'zippicks-vibes'); ?></option>
                            <option value="deactivate"><?php esc_html_e('Deactivate', 'zippicks-vibes'); ?></option>
                            <option value="delete"><?php esc_html_e('Delete', 'zippicks-vibes'); ?></option>
                        </select>
                        <?php submit_button(__('Apply', 'zippicks-vibes'), 'action', 'doaction', false, ['id' => 'doaction-top']); ?>
                    </div>
                </div>
            <?php endif; ?>

            <table class="wp-list-table widefat fixed striped vibes-table" 
                   role="table" 
                   aria-labelledby="vibes-heading"
                   aria-describedby="vibes-table-desc">
                <caption id="vibes-table-desc" class="sr-only">
                    <?php esc_html_e('Table of all vibes with their properties and management actions', 'zippicks-vibes'); ?>
                </caption>
                <thead>
                    <tr role="row">
                        <?php if ($capabilities['can_bulk_edit'] ?? false): ?>
                        <th scope="col" class="manage-column column-cb check-column">
                            <label class="sr-only" for="cb-select-all-1"><?php esc_html_e('Select All', 'zippicks-vibes'); ?></label>
                            <input id="cb-select-all-1" type="checkbox" aria-label="<?php esc_attr_e('Select all vibes', 'zippicks-vibes'); ?>">
                        </th>
                        <?php endif; ?>
                        <th scope="col" class="column-sort" role="columnheader">
                            <?php esc_html_e('Order', 'zippicks-vibes'); ?>
                        </th>
                        <th scope="col" class="column-name" role="columnheader">
                            <?php esc_html_e('Name', 'zippicks-vibes'); ?>
                        </th>
                        <th scope="col" class="column-categories" role="columnheader">
                            <?php esc_html_e('Categories', 'zippicks-vibes'); ?>
                        </th>
                        <th scope="col" class="column-color" role="columnheader">
                            <?php esc_html_e('Color', 'zippicks-vibes'); ?>
                        </th>
                        <th scope="col" class="column-icon" role="columnheader">
                            <?php esc_html_e('Icon', 'zippicks-vibes'); ?>
                        </th>
                        <th scope="col" class="column-status" role="columnheader">
                            <?php esc_html_e('Status', 'zippicks-vibes'); ?>
                        </th>
                        <th scope="col" class="column-businesses" role="columnheader">
                            <?php esc_html_e('Businesses', 'zippicks-vibes'); ?>
                        </th>
                        <th scope="col" class="column-actions" role="columnheader">
                            <?php esc_html_e('Actions', 'zippicks-vibes'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody id="sortable-vibes">
                    <?php foreach ($vibes as $vibe): ?>
                        <tr role="row" data-vibe-id="<?php echo esc_attr($vibe->getId()); ?>">
                            <?php if ($capabilities['can_bulk_edit'] ?? false): ?>
                            <th scope="row" class="check-column">
                                <label class="sr-only" for="cb-select-<?php echo esc_attr($vibe->getId()); ?>">
                                    <?php printf(esc_html__('Select %s', 'zippicks-vibes'), esc_html($vibe->getName())); ?>
                                </label>
                                <input id="cb-select-<?php echo esc_attr($vibe->getId()); ?>" 
                                       type="checkbox" 
                                       name="vibe_ids[]" 
                                       value="<?php echo esc_attr($vibe->getId()); ?>"
                                       aria-describedby="vibe-name-<?php echo esc_attr($vibe->getId()); ?>">
                            </th>
                            <?php endif; ?>
                            <td class="column-sort">
                                <span class="drag-handle" 
                                      role="button" 
                                      tabindex="0"
                                      aria-label="<?php esc_attr_e('Drag to reorder this vibe', 'zippicks-vibes'); ?>"
                                      title="<?php esc_attr_e('Drag and drop to reorder', 'zippicks-vibes'); ?>">
                                    ☰
                                </span>
                                <span class="order-position" aria-label="<?php esc_attr_e('Position', 'zippicks-vibes'); ?>">
                                    <?php echo esc_html($vibe->getOrderPosition()); ?>
                                </span>
                            </td>
                            <td class="column-name">
                                <strong id="vibe-name-<?php echo esc_attr($vibe->getId()); ?>"><?php echo esc_html($vibe->getName()); ?></strong>
                                <div class="vibe-slug"><code><?php echo esc_html($vibe->getSlug()); ?></code></div>
                                <?php if (!empty($vibe->getDescription())): ?>
                                    <div class="description"><?php echo esc_html($vibe->getDescription()); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="column-categories">
                                <?php 
                                $categoryIds = $vibe->getCategories();
                                if (!empty($categoryIds)) {
                                    $categoryNames = [];
                                    // Find category names from the categories array passed to the template
                                    foreach ($categoryIds as $catId) {
                                        foreach ($categories as $category) {
                                            if ($category->id == $catId) {
                                                $categoryNames[] = esc_html($category->name);
                                                break;
                                            }
                                        }
                                    }
                                    echo !empty($categoryNames) ? implode(', ', $categoryNames) : '—';
                                } else {
                                    echo '—';
                                }
                                ?>
                            </td>
                            <td class="column-color">
                                <div class="color-display">
                                    <span class="color-swatch" 
                                          style="background-color: <?php echo esc_attr($vibe->getColor()); ?>"
                                          aria-label="<?php esc_attr_e('Color swatch', 'zippicks-vibes'); ?>"></span>
                                    <span class="color-code"><?php echo esc_html($vibe->getColor()); ?></span>
                                </div>
                            </td>
                            <td class="column-icon">
                                <div class="icon-display">
                                    <?php 
                                    $icon_name = $vibe->getIcon() ?: 'default';
                                    $icon_path = ZIPPICKS_VIBES_DIR . 'assets/icons/vibes/' . $icon_name . '.svg';
                                    
                                    if (file_exists($icon_path)): 
                                        $svg_content = file_get_contents($icon_path);
                                        ?>
                                        <div class="vibe-icon-display" 
                                             style="width: 24px; height: 24px;" 
                                             aria-label="<?php echo esc_attr(sprintf(__('Icon for %s', 'zippicks-vibes'), $vibe->getName())); ?>">
                                            <?php echo wp_kses($svg_content, [
                                                'svg' => ['width' => [], 'height' => [], 'viewBox' => [], 'xmlns' => [], 'fill' => []],
                                                'path' => ['d' => [], 'fill' => []],
                                                'g' => ['fill' => []],
                                                'circle' => ['cx' => [], 'cy' => [], 'r' => [], 'fill' => []],
                                                'rect' => ['x' => [], 'y' => [], 'width' => [], 'height' => [], 'fill' => []]
                                            ]); ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="vibe-icon-text" aria-label="<?php esc_attr_e('Vibe icon', 'zippicks-vibes'); ?>">
                                            <?php echo esc_html($icon_name); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="column-status">
                                <div class="status-wrapper">
                                    <label class="status-toggle" 
                                           aria-label="<?php esc_attr_e('Toggle status for', 'zippicks-vibes'); ?> <?php echo esc_attr($vibe->getName()); ?>">
                                        <input type="checkbox" 
                                               class="status-checkbox" 
                                               data-vibe-id="<?php echo esc_attr($vibe->getId()); ?>"
                                               <?php checked($vibe->isActive()); ?>
                                               aria-describedby="status-<?php echo esc_attr($vibe->getId()); ?>">
                                        <span class="slider" aria-hidden="true"></span>
                                    </label>
                                    <span class="status-label" id="status-<?php echo esc_attr($vibe->getId()); ?>">
                                        <?php echo $vibe->isActive() ? 
                                            esc_html__('Active', 'zippicks-vibes') : 
                                            esc_html__('Inactive', 'zippicks-vibes'); ?>
                                    </span>
                                </div>
                            </td>
                            <td class="column-businesses">
                                <span class="business-count" aria-label="<?php esc_attr_e('Number of businesses using this vibe', 'zippicks-vibes'); ?>">
                                    <?php echo esc_html(number_format_i18n($vibe->getBusinessCount())); ?>
                                </span>
                            </td>
                            <td class="column-actions">
                                <a href="<?php echo esc_url(admin_url('admin.php?page=zippicks-vibes-add&edit=' . $vibe->getId())); ?>" 
                                   class="button button-small"
                                   aria-label="<?php esc_attr_e('Edit', 'zippicks-vibes'); ?> <?php echo esc_attr($vibe->getName()); ?>">
                                    <?php esc_html_e('Edit', 'zippicks-vibes'); ?>
                                </a>
                                <button type="button" 
                                        class="button button-small button-link-delete delete-vibe" 
                                        data-vibe-id="<?php echo esc_attr($vibe->getId()); ?>"
                                        data-vibe-name="<?php echo esc_attr($vibe->getName()); ?>"
                                        aria-label="<?php esc_attr_e('Delete', 'zippicks-vibes'); ?> <?php echo esc_attr($vibe->getName()); ?>">
                                    <?php esc_html_e('Delete', 'zippicks-vibes'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php if ($capabilities['can_bulk_edit'] ?? false): ?>
            </form>
            <?php endif; ?>
        <?php endif; ?>
        
        <!-- Pagination -->
        <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $page_links = paginate_links([
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;',
                    'current' => $pagination['current_page'],
                    'total' => $pagination['total_pages'],
                    'type' => 'array'
                ]);
                
                if ($page_links) {
                    echo '<span class="displaying-num">' . 
                         sprintf(
                             esc_html(_n('%s item', '%s items', $pagination['total_items'], 'zippicks-vibes')),
                             number_format_i18n($pagination['total_items'])
                         ) . '</span>';
                    echo '<span class="pagination-links">' . implode('', array_map('wp_kses_post', $page_links)) . '</span>';
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
    </section>
</div>

<!-- Enhanced message container -->
<div id="message" 
     class="notice" 
     style="display: none;" 
     role="alert" 
     aria-live="assertive">
</div>

<?php
// Add extension point for additional dashboard content
do_action('zippicks_vibes_dashboard_after', $stats, $vibes);
?>