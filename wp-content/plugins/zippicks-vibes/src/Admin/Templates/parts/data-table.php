<?php
/**
 * Data Table Template Part
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 * @var array $table_data Table configuration data
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access prohibited');
}

// Default table configuration
$defaults = array(
    'id' => 'data-table',
    'class' => 'widefat fixed striped',
    'columns' => array(),
    'rows' => array(),
    'sortable' => false,
    'bulk_actions' => false,
    'pagination' => false,
    'empty_message' => __('No items found.', 'zippicks-vibes'),
    'aria_label' => __('Data table', 'zippicks-vibes')
);

$table_data = wp_parse_args($table_data ?? array(), $defaults);

if (empty($table_data['columns'])) {
    return;
}
?>

<div class="table-container">
    <?php if ($table_data['bulk_actions'] && !empty($table_data['bulk_actions'])): ?>
        <div class="bulk-actions" role="toolbar" aria-label="<?php esc_attr_e('Bulk actions', 'zippicks-vibes'); ?>">
            <label for="bulk-action-selector" class="sr-only">
                <?php esc_html_e('Select bulk action', 'zippicks-vibes'); ?>
            </label>
            <select id="bulk-action-selector" name="action">
                <option value=""><?php esc_html_e('Bulk Actions', 'zippicks-vibes'); ?></option>
                <?php foreach ($table_data['bulk_actions'] as $value => $label): ?>
                    <option value="<?php echo esc_attr($value); ?>">
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <button type="button" 
                    id="bulk-action-apply" 
                    class="button"
                    aria-describedby="bulk-action-description">
                <?php esc_html_e('Apply', 'zippicks-vibes'); ?>
            </button>
            
            <div id="bulk-action-description" class="sr-only">
                <?php esc_html_e('Apply the selected action to checked items', 'zippicks-vibes'); ?>
            </div>
            
            <span class="selected-count" aria-live="polite" aria-atomic="true">
                <span id="selected-count-text"><?php esc_html_e('0 items selected', 'zippicks-vibes'); ?></span>
            </span>
        </div>
    <?php endif; ?>
    
    <table id="<?php echo esc_attr($table_data['id']); ?>" 
           class="<?php echo esc_attr($table_data['class']); ?>" 
           role="table"
           aria-label="<?php echo esc_attr($table_data['aria_label']); ?>">
        
        <thead>
            <tr role="row">
                <?php if ($table_data['bulk_actions']): ?>
                    <th scope="col" class="check-column">
                        <label for="cb-select-all" class="sr-only">
                            <?php esc_html_e('Select all items', 'zippicks-vibes'); ?>
                        </label>
                        <input type="checkbox" 
                               id="cb-select-all" 
                               class="bulk-select-all"
                               aria-describedby="select-all-description" />
                        <div id="select-all-description" class="sr-only">
                            <?php esc_html_e('Select all items in the table', 'zippicks-vibes'); ?>
                        </div>
                    </th>
                <?php endif; ?>
                
                <?php foreach ($table_data['columns'] as $key => $column): ?>
                    <th scope="col" 
                        class="column-<?php echo esc_attr($key); ?><?php echo $table_data['sortable'] ? ' sortable' : ''; ?>"
                        <?php if (!empty($column['width'])): ?>
                            style="width: <?php echo esc_attr($column['width']); ?>"
                        <?php endif; ?>
                        <?php if ($table_data['sortable']): ?>
                            tabindex="0"
                            role="button"
                            aria-sort="none"
                            data-column="<?php echo esc_attr($key); ?>"
                        <?php endif; ?>>
                        <?php echo esc_html($column['title']); ?>
                        <?php if ($table_data['sortable']): ?>
                            <span class="sort-indicator" aria-hidden="true"></span>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        
        <tbody>
            <?php if (!empty($table_data['rows'])): ?>
                <?php foreach ($table_data['rows'] as $row_index => $row): ?>
                    <tr role="row" 
                        <?php if (!empty($row['id'])): ?>
                            data-row-id="<?php echo esc_attr($row['id']); ?>"
                        <?php endif; ?>
                        <?php if (!empty($row['class'])): ?>
                            class="<?php echo esc_attr($row['class']); ?>"
                        <?php endif; ?>>
                        
                        <?php if ($table_data['bulk_actions']): ?>
                            <th scope="row" class="check-column">
                                <label for="cb-select-<?php echo esc_attr($row_index); ?>" class="sr-only">
                                    <?php printf(
                                        esc_html__('Select item %s', 'zippicks-vibes'), 
                                        esc_html($row['title'] ?? $row_index + 1)
                                    ); ?>
                                </label>
                                <input type="checkbox" 
                                       id="cb-select-<?php echo esc_attr($row_index); ?>" 
                                       class="bulk-checkbox"
                                       value="<?php echo esc_attr($row['id'] ?? $row_index); ?>" />
                            </th>
                        <?php endif; ?>
                        
                        <?php foreach ($table_data['columns'] as $key => $column): ?>
                            <td class="column-<?php echo esc_attr($key); ?>"
                                <?php if (!empty($column['data_type'])): ?>
                                    data-type="<?php echo esc_attr($column['data_type']); ?>"
                                <?php endif; ?>>
                                
                                <?php if (isset($row['data'][$key])): ?>
                                    <?php if ($column['type'] === 'html'): ?>
                                        <?php echo wp_kses_post($row['data'][$key]); ?>
                                    <?php else: ?>
                                        <?php echo esc_html($row['data'][$key]); ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="<?php echo count($table_data['columns']) + ($table_data['bulk_actions'] ? 1 : 0); ?>" 
                        class="no-items">
                        <p class="empty-message">
                            <?php echo esc_html($table_data['empty_message']); ?>
                        </p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if ($table_data['pagination'] && !empty($table_data['pagination'])): ?>
        <div class="table-pagination" role="navigation" aria-label="<?php esc_attr_e('Table pagination', 'zippicks-vibes'); ?>">
            <?php 
            $pagination = $table_data['pagination'];
            if ($pagination['total_pages'] > 1):
            ?>
                <div class="pagination-info">
                    <?php printf(
                        esc_html__('Page %1$d of %2$d (%3$d total items)', 'zippicks-vibes'),
                        $pagination['current_page'],
                        $pagination['total_pages'],
                        $pagination['total_items']
                    ); ?>
                </div>
                
                <div class="pagination-links">
                    <?php if ($pagination['current_page'] > 1): ?>
                        <a href="<?php echo esc_url($pagination['prev_url']); ?>" 
                           class="button"
                           aria-label="<?php esc_attr_e('Go to previous page', 'zippicks-vibes'); ?>">
                            <?php esc_html_e('Previous', 'zippicks-vibes'); ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
                        <a href="<?php echo esc_url($pagination['next_url']); ?>" 
                           class="button"
                           aria-label="<?php esc_attr_e('Go to next page', 'zippicks-vibes'); ?>">
                            <?php esc_html_e('Next', 'zippicks-vibes'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>