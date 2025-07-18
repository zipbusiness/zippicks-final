<?php
/**
 * Add/Edit Vibe Template - Hardened with accessibility and security
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

// Additional security checks for editing mode
if ($editing) {
    if (empty($vibe) || !is_object($vibe)) {
        wp_die(__('Invalid vibe data provided.', 'zippicks-vibes'));
    }
    
    // Verify user has permission to edit this specific vibe
    if (!apply_filters('zippicks_vibes_user_can_edit_vibe', true, $vibe, get_current_user_id())) {
        wp_die(__('You do not have permission to edit this vibe.', 'zippicks-vibes'));
    }
}

$page_title = $editing ? __('Edit Vibe', 'zippicks-vibes') : __('Add New Vibe', 'zippicks-vibes');
$submit_text = $editing ? __('Update Vibe', 'zippicks-vibes') : __('Create Vibe', 'zippicks-vibes');
$form_action = $editing ? 'edit' : 'add';

// Initialize template helper
$helper = $controller->get_template_helper();
?>

<div class="wrap zippicks-vibes-admin" role="main">
    <div class="zippicks-header">
        <h1><?php echo esc_html($page_title); ?></h1>
        <nav aria-label="<?php esc_attr_e('Page navigation', 'zippicks-vibes'); ?>">
            <a href="<?php echo esc_url(admin_url('admin.php?page=zippicks-vibes')); ?>" 
               class="button"
               aria-label="<?php esc_attr_e('Return to vibes list', 'zippicks-vibes'); ?>">
                <?php esc_html_e('← Back to Vibes', 'zippicks-vibes'); ?>
            </a>
        </nav>
    </div>

    <!-- Live region for dynamic messages -->
    <div id="message-container" class="sr-only" aria-live="polite" aria-atomic="true"></div>

    <form id="vibe-form" class="vibe-form" method="post" novalidate>
        <?php 
        // Single nonce field for AJAX save action - matches backend validation
        wp_nonce_field('zippicks_vibes_admin', 'security');
        ?>
        <input type="hidden" 
               name="vibe_id" 
               value="<?php echo ($editing && $vibe) ? esc_attr($vibe->getId()) : '0'; ?>"
               id="vibe_id_hidden">
        <input type="hidden" 
               name="action" 
               value="zippicks_vibes_save"
               id="form_action_hidden">
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="vibe_name">
                            <?php esc_html_e('Name', 'zippicks-vibes'); ?> 
                            <span class="required" aria-label="<?php esc_attr_e('required', 'zippicks-vibes'); ?>">*</span>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="vibe_name" 
                               name="vibe_name" 
                               value="<?php echo ($editing && $vibe) ? esc_attr($vibe->getName()) : ''; ?>" 
                               class="regular-text" 
                               required
                               maxlength="100"
                               aria-describedby="vibe_name_desc"
                               aria-invalid="false"
                               autocomplete="off">
                        <p class="description" id="vibe_name_desc">
                            <?php esc_html_e('The display name for this vibe. Maximum 100 characters.', 'zippicks-vibes'); ?>
                        </p>
                        <div class="error-message" id="vibe_name_error" role="alert" aria-live="polite"></div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="vibe_slug"><?php esc_html_e('Slug', 'zippicks-vibes'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="vibe_slug" 
                               name="vibe_slug" 
                               value="<?php echo $editing ? esc_attr($vibe->getSlug()) : ''; ?>" 
                               class="regular-text"
                               pattern="[a-z0-9-]*"
                               aria-describedby="vibe_slug_desc"
                               aria-invalid="false"
                               autocomplete="off">
                        <p class="description" id="vibe_slug_desc">
                            <?php esc_html_e('URL-friendly version of the name. Leave blank to auto-generate. Only lowercase letters, numbers, and hyphens allowed.', 'zippicks-vibes'); ?>
                        </p>
                        <div class="error-message" id="vibe_slug_error" role="alert" aria-live="polite"></div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="vibe_description"><?php esc_html_e('Description', 'zippicks-vibes'); ?></label>
                    </th>
                    <td>
                        <textarea id="vibe_description" 
                                  name="vibe_description" 
                                  rows="3" 
                                  class="large-text"
                                  maxlength="500"
                                  aria-describedby="vibe_description_desc"
                                  aria-invalid="false"><?php echo $editing ? esc_textarea($vibe->getDescription()) : ''; ?></textarea>
                        <p class="description" id="vibe_description_desc">
                            <?php esc_html_e('A brief description of this vibe. Maximum 500 characters.', 'zippicks-vibes'); ?>
                        </p>
                        <div class="char-count" id="description_count" aria-live="polite">
                            <span id="description_chars"><?php echo $editing ? strlen($vibe->getDescription()) : 0; ?></span>/500 
                            <?php esc_html_e('characters', 'zippicks-vibes'); ?>
                        </div>
                        <div class="error-message" id="vibe_description_error" role="alert" aria-live="polite"></div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="vibe_color"><?php esc_html_e('Color', 'zippicks-vibes'); ?></label>
                    </th>
                    <td>
                        <input type="text" 
                               id="vibe_color" 
                               name="vibe_color" 
                               value="<?php echo $editing ? esc_attr($vibe->getColor()) : '#194FAD'; ?>" 
                               class="color-picker"
                               pattern="#[0-9A-Fa-f]{6}"
                               aria-describedby="vibe_color_desc"
                               aria-invalid="false">
                        <p class="description" id="vibe_color_desc">
                            <?php esc_html_e('Primary color for this vibe in hex format (e.g., #194FAD).', 'zippicks-vibes'); ?>
                        </p>
                        <div class="error-message" id="vibe_color_error" role="alert" aria-live="polite"></div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="vibe_icon"><?php esc_html_e('Icon', 'zippicks-vibes'); ?></label>
                    </th>
                    <td>
                        <div class="vibe-icon-selector">
                            <select id="vibe_icon" 
                                    name="vibe_icon" 
                                    class="vibe-icon-dropdown"
                                    data-current="<?php echo esc_attr($editing && $vibe ? $vibe->getIcon() : 'default'); ?>"
                                    aria-describedby="vibe_icon_desc"
                                    aria-invalid="false">
                                <option value=""><?php esc_html_e('Select an icon...', 'zippicks-vibes'); ?></option>
                                <?php
                                // Get current icon value
                                $current_icon = ($editing && $vibe) ? $vibe->getIcon() : 'default';
                                ?>
                            </select>
                            <div class="vibe-icon-preview" id="vibe_icon_preview">
                                <!-- Icon preview will be loaded here -->
                            </div>
                        </div>
                        <p class="description" id="vibe_icon_desc">
                            <?php esc_html_e('Select an SVG icon for this vibe.', 'zippicks-vibes'); ?>
                        </p>
                        <div class="error-message" id="vibe_icon_error" role="alert" aria-live="polite"></div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="vibe_order_position"><?php esc_html_e('Order', 'zippicks-vibes'); ?></label>
                    </th>
                    <td>
                        <input type="number" 
                               id="vibe_order_position" 
                               name="vibe_order_position" 
                               value="<?php echo $editing ? esc_attr($vibe->getOrderPosition()) : '0'; ?>" 
                               class="small-text" 
                               min="0" 
                               max="9999"
                               step="1"
                               aria-describedby="vibe_order_desc"
                               aria-invalid="false">
                        <p class="description" id="vibe_order_desc">
                            <?php esc_html_e('Display order position. Lower numbers appear first (0 = first position).', 'zippicks-vibes'); ?>
                        </p>
                        <div class="error-message" id="vibe_order_error" role="alert" aria-live="polite"></div>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <span id="categories_legend"><?php esc_html_e('Categories', 'zippicks-vibes'); ?></span>
                    </th>
                    <td>
                        <fieldset aria-labelledby="categories_legend" aria-describedby="categories_desc">
                            <legend class="sr-only"><?php esc_html_e('Select categories for this vibe', 'zippicks-vibes'); ?></legend>
                            <div class="categories-field" id="categories_field">
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $category): ?>
                                        <label class="category-checkbox">
                                            <input type="checkbox" 
                                                   name="vibe_categories[]" 
                                                   value="<?php echo esc_attr($category->id); ?>"
                                                   <?php if ($editing && in_array($category->id, $vibe->getCategories())): ?>checked<?php endif; ?>
                                                   aria-describedby="category_<?php echo esc_attr($category->id); ?>_desc">
                                            <span><?php echo esc_html($category->name); ?></span>
                                            <?php if (!empty($category->description)): ?>
                                                <span class="sr-only" id="category_<?php echo esc_attr($category->id); ?>_desc">
                                                    <?php echo esc_html($category->description); ?>
                                                </span>
                                            <?php endif; ?>
                                        </label>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="description">
                                        <?php esc_html_e('No categories available.', 'zippicks-vibes'); ?>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=zippicks-vibes-categories')); ?>">
                                            <?php esc_html_e('Create categories first.', 'zippicks-vibes'); ?>
                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <p class="description" id="categories_desc">
                                <?php esc_html_e('Select one or more categories that this vibe belongs to.', 'zippicks-vibes'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php esc_html_e('Status', 'zippicks-vibes'); ?></th>
                    <td>
                        <fieldset aria-describedby="status_desc">
                            <legend class="sr-only"><?php esc_html_e('Vibe status settings', 'zippicks-vibes'); ?></legend>
                            <label for="vibe_status" class="status-label">
                                <input type="checkbox" 
                                       id="vibe_status"
                                       name="vibe_status" 
                                       value="1" 
                                       <?php if ($editing): ?>
                                           <?php checked($vibe->isActive()); ?>
                                       <?php else: ?>
                                           checked
                                       <?php endif; ?>
                                       aria-describedby="status_desc">
                                <span class="status-text">
                                    <?php esc_html_e('Active', 'zippicks-vibes'); ?>
                                </span>
                                <span class="status-indicator" aria-hidden="true"></span>
                            </label>
                            <p class="description" id="status_desc">
                                <?php esc_html_e('Whether this vibe is active and visible to users. Inactive vibes are hidden from public display.', 'zippicks-vibes'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary" id="submit-vibe">
                <span class="submit-text"><?php echo esc_html($submit_text); ?></span>
                <span class="loading-text" style="display: none;" aria-hidden="true">
                    <?php esc_html_e('Saving...', 'zippicks-vibes'); ?>
                </span>
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=zippicks-vibes')); ?>" 
               class="button"
               aria-label="<?php esc_attr_e('Cancel and return to vibes list', 'zippicks-vibes'); ?>">
                <?php esc_html_e('Cancel', 'zippicks-vibes'); ?>
            </a>
        </p>
    </form>
</div>

<!-- Accessible loading overlay -->
<div id="loading-overlay" 
     class="loading-overlay" 
     style="display: none;"
     role="status" 
     aria-live="polite" 
     aria-label="<?php esc_attr_e('Processing, please wait', 'zippicks-vibes'); ?>">
    <div class="spinner" aria-hidden="true"></div>
    <span class="sr-only"><?php esc_html_e('Processing, please wait...', 'zippicks-vibes'); ?></span>
</div>

<!-- Enhanced message container -->
<div id="message" 
     class="notice" 
     style="display: none;" 
     role="alert" 
     aria-live="assertive">
</div>

<?php
// Add extension point for additional form fields
do_action('zippicks_vibes_admin_form_after', $editing ? $vibe : null, $categories);
?>

