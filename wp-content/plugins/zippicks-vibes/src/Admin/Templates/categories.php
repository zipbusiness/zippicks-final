<?php
/**
 * Categories Management Template - Hardened with accessibility and security
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
        <h1 id="categories-heading"><?php esc_html_e('Vibe Categories', 'zippicks-vibes'); ?></h1>
        <nav aria-labelledby="categories-heading" class="header-actions">
            <button type="button" 
                    class="button button-primary" 
                    id="add-category-btn"
                    aria-label="<?php esc_attr_e('Add a new category', 'zippicks-vibes'); ?>">
                <?php esc_html_e('Add New Category', 'zippicks-vibes'); ?>
            </button>
        </nav>
    </div>

    <!-- Categories List -->
    <div class="categories-list">
        <?php if (empty($categories)): ?>
            <div class="empty-state" role="region" aria-labelledby="empty-categories-heading">
                <h3 id="empty-categories-heading"><?php esc_html_e('No categories found', 'zippicks-vibes'); ?></h3>
                <p><?php esc_html_e('Categories help organize your vibes into logical groups. Create your first category to get started organizing your content.', 'zippicks-vibes'); ?></p>
                <button type="button" 
                        class="button button-primary" 
                        id="create-first-category"
                        aria-label="<?php esc_attr_e('Create your first category', 'zippicks-vibes'); ?>">
                    <?php esc_html_e('Create First Category', 'zippicks-vibes'); ?>
                </button>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped categories-table">
                <thead>
                    <tr>
                        <th class="column-id" style="width: 50px;"><?php _e('ID', 'zippicks-vibes'); ?></th>
                        <th class="column-name"><?php _e('Name', 'zippicks-vibes'); ?></th>
                        <th class="column-slug"><?php _e('Slug', 'zippicks-vibes'); ?></th>
                        <th class="column-description"><?php _e('Description', 'zippicks-vibes'); ?></th>
                        <th class="column-parent" style="width: 80px;"><?php _e('Parent', 'zippicks-vibes'); ?></th>
                        <th class="column-order" style="width: 80px;"><?php _e('Order', 'zippicks-vibes'); ?></th>
                        <th class="column-count" style="width: 80px;"><?php _e('Vibes', 'zippicks-vibes'); ?></th>
                        <th class="column-actions" style="width: 150px;"><?php _e('Actions', 'zippicks-vibes'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr data-category-id="<?php echo esc_attr($category->id); ?>">
                            <td class="column-id">
                                <?php echo esc_html($category->id); ?>
                            </td>
                            <td class="column-name">
                                <strong><?php echo esc_html($category->name); ?></strong>
                            </td>
                            <td class="column-slug">
                                <code><?php echo esc_html($category->slug); ?></code>
                            </td>
                            <td class="column-description">
                                <?php echo esc_html($category->description ?: '—'); ?>
                            </td>
                            <td class="column-parent">
                                <?php echo esc_html($category->parent_id ?: '—'); ?>
                            </td>
                            <td class="column-order">
                                <?php echo esc_html($category->order_position); ?>
                            </td>
                            <td class="column-count">
                                <span class="count"><?php echo esc_html($category->vibe_count ?? 0); ?></span>
                            </td>
                            <td class="column-actions">
                                <button type="button" 
                                        class="button button-small edit-category" 
                                        data-category-id="<?php echo esc_attr($category->id); ?>"
                                        data-category-name="<?php echo esc_attr($category->name); ?>"
                                        data-category-description="<?php echo esc_attr($category->description); ?>"
                                        data-category-slug="<?php echo esc_attr($category->slug); ?>"
                                        data-category-parent="<?php echo esc_attr($category->parent_id); ?>"
                                        data-category-order="<?php echo esc_attr($category->order_position); ?>">
                                    <?php _e('Edit', 'zippicks-vibes'); ?>
                                </button>
                                <button type="button" 
                                        class="button button-small button-link-delete delete-category" 
                                        data-category-id="<?php echo esc_attr($category->id); ?>">
                                    <?php _e('Delete', 'zippicks-vibes'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Accessible Category Modal -->
<div id="category-modal" 
     class="modal" 
     style="display: none;"
     role="dialog" 
     aria-labelledby="modal-title"
     aria-modal="true"
     aria-hidden="true">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title"><?php esc_html_e('Add Category', 'zippicks-vibes'); ?></h2>
            <button type="button" 
                    class="close" 
                    id="close-modal"
                    aria-label="<?php esc_attr_e('Close dialog', 'zippicks-vibes'); ?>">&times;</button>
        </div>
        <div class="modal-body">
            <form id="category-form" method="post" novalidate>
                <?php 
                wp_nonce_field('zippicks_vibes_admin', 'nonce', false, true);
                wp_nonce_field('zippicks_vibes_admin', '_wpnonce', false, true);
                wp_nonce_field('zippicks_vibes_admin', 'security', false, true);
                ?>
                <input type="hidden" id="category_id" name="category_id" value="0">
                <input type="hidden" name="action" value="zippicks_vibes_save_category">
                
                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="category_name">
                                    <?php esc_html_e('Name', 'zippicks-vibes'); ?> 
                                    <span class="required" aria-label="<?php esc_attr_e('required', 'zippicks-vibes'); ?>">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="category_name" 
                                       name="category_name" 
                                       class="regular-text" 
                                       required
                                       maxlength="100"
                                       aria-describedby="category_name_desc"
                                       aria-invalid="false"
                                       autocomplete="off">
                                <p class="description" id="category_name_desc">
                                    <?php esc_html_e('The display name for this category. Maximum 100 characters.', 'zippicks-vibes'); ?>
                                </p>
                                <div class="error-message" id="category_name_error" role="alert" aria-live="polite"></div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="category_slug"><?php esc_html_e('Slug', 'zippicks-vibes'); ?></label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="category_slug" 
                                       name="category_slug" 
                                       class="regular-text" 
                                       maxlength="100"
                                       aria-describedby="category_slug_desc"
                                       aria-invalid="false"
                                       pattern="[a-z0-9-]+"
                                       autocomplete="off">
                                <p class="description" id="category_slug_desc">
                                    <?php esc_html_e('URL-friendly version of the name. Will be auto-generated if left empty.', 'zippicks-vibes'); ?>
                                </p>
                                <div class="error-message" id="category_slug_error" role="alert" aria-live="polite"></div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="category_description"><?php esc_html_e('Description', 'zippicks-vibes'); ?></label>
                            </th>
                            <td>
                                <textarea id="category_description" 
                                          name="category_description" 
                                          rows="3" 
                                          class="large-text"
                                          maxlength="500"
                                          aria-describedby="category_description_desc"
                                          aria-invalid="false"></textarea>
                                <p class="description" id="category_description_desc">
                                    <?php esc_html_e('A brief description of this category. Maximum 500 characters.', 'zippicks-vibes'); ?>
                                </p>
                                <div class="error-message" id="category_description_error" role="alert" aria-live="polite"></div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="category_parent"><?php esc_html_e('Parent Category', 'zippicks-vibes'); ?></label>
                            </th>
                            <td>
                                <select id="category_parent" 
                                        name="category_parent" 
                                        class="regular-text"
                                        aria-describedby="category_parent_desc">
                                    <option value="0"><?php esc_html_e('— No Parent —', 'zippicks-vibes'); ?></option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo esc_attr($cat->id); ?>">
                                            <?php echo esc_html($cat->name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description" id="category_parent_desc">
                                    <?php esc_html_e('Select a parent category to create a hierarchy. Parent categories group related subcategories.', 'zippicks-vibes'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="category_order"><?php esc_html_e('Order Position', 'zippicks-vibes'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="category_order" 
                                       name="category_order" 
                                       class="small-text" 
                                       min="0"
                                       max="999"
                                       value="0"
                                       aria-describedby="category_order_desc">
                                <p class="description" id="category_order_desc">
                                    <?php esc_html_e('Display order for this category. Lower numbers appear first.', 'zippicks-vibes'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary" id="save-category">
                        <?php _e('Save Category', 'zippicks-vibes'); ?>
                    </button>
                    <button type="button" class="button" id="cancel-category">
                        <?php _e('Cancel', 'zippicks-vibes'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>
</div>

<!-- Messages -->
<div id="message" class="notice" style="display: none;"></div>