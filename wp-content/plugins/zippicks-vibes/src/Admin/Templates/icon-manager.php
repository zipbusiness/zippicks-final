<?php
/**
 * Icon Manager Template
 *
 * @package ZipPicks\Vibes
 */

namespace ZipPicks\Vibes\Admin\Templates;

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>Vibe Icon Manager</h1>
    
    <div class="notice notice-info">
        <p>Upload and manage white SVG icons for vibes. All icons should be optimized SVG files with white fill color.</p>
    </div>

    <!-- Upload Section -->
    <div class="zippicks-icon-upload-section" style="background: #fff; padding: 20px; margin-bottom: 20px; border: 1px solid #ccd0d4;">
        <h2>Upload New Icon</h2>
        <form id="zippicks-icon-upload-form" enctype="multipart/form-data">
            <?php wp_nonce_field('zippicks_upload_icon', 'zippicks_icon_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="icon_file">SVG File</label>
                    </th>
                    <td>
                        <input type="file" name="icon_file" id="icon_file" accept=".svg" required>
                        <p class="description">Only SVG files are allowed. File will be sanitized for security.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="icon_name">Icon Name</label>
                    </th>
                    <td>
                        <input type="text" name="icon_name" id="icon_name" class="regular-text" pattern="[a-z0-9-]+" required>
                        <p class="description">Use lowercase letters, numbers, and hyphens only (e.g., "wine-glass", "date-night")</p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">Upload Icon</button>
                <span class="spinner" style="float: none;"></span>
            </p>
        </form>
    </div>

    <!-- Icon Library -->
    <div class="zippicks-icon-library" style="background: #fff; padding: 20px; border: 1px solid #ccd0d4;">
        <h2>Icon Library</h2>
        <div id="icon-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 20px; margin-top: 20px;">
            <!-- Icons will be loaded here via AJAX -->
        </div>
    </div>
</div>

<style>
.icon-item {
    text-align: center;
    padding: 15px;
    border: 1px solid #ddd;
    border-radius: 4px;
    position: relative;
    background: #f9f9f9;
}

.icon-item:hover {
    background: #f0f0f0;
    border-color: #999;
}

.icon-preview {
    width: 60px;
    height: 60px;
    margin: 0 auto 10px;
    background: #333;
    padding: 10px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-preview svg {
    max-width: 100%;
    max-height: 100%;
    fill: white;
}

.icon-name {
    font-size: 12px;
    word-break: break-word;
    margin-bottom: 10px;
}

.icon-usage {
    font-size: 11px;
    color: #666;
    margin-bottom: 10px;
}

.icon-actions {
    display: flex;
    gap: 5px;
    justify-content: center;
}

.delete-icon {
    color: #d63638;
    text-decoration: none;
    font-size: 11px;
}

.delete-icon:hover {
    color: #a02222;
    text-decoration: underline;
}

.delete-icon.disabled {
    color: #999;
    cursor: not-allowed;
    text-decoration: none;
}

.delete-icon.disabled:hover {
    color: #999;
    text-decoration: none;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Load icons on page load
    loadIcons();
    
    // Handle icon upload
    $('#zippicks-icon-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData(this);
        formData.append('action', 'zippicks_upload_icon');
        
        var $spinner = $(this).find('.spinner');
        var $submitButton = $(this).find('button[type="submit"]');
        
        $spinner.addClass('is-active');
        $submitButton.prop('disabled', true);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    alert('Icon uploaded successfully!');
                    $('#icon_file').val('');
                    $('#icon_name').val('');
                    loadIcons();
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while uploading the icon.');
            },
            complete: function() {
                $spinner.removeClass('is-active');
                $submitButton.prop('disabled', false);
            }
        });
    });
    
    // Handle icon deletion
    $(document).on('click', '.delete-icon:not(.disabled)', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to delete this icon? This cannot be undone.')) {
            return;
        }
        
        var iconName = $(this).data('icon');
        var $iconItem = $(this).closest('.icon-item');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'zippicks_delete_icon',
                icon: iconName,
                nonce: '<?php echo wp_create_nonce('zippicks_delete_icon'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $iconItem.fadeOut(300, function() {
                        $(this).remove();
                    });
                } else {
                    alert('Error: ' + response.data);
                }
            },
            error: function() {
                alert('An error occurred while deleting the icon.');
            }
        });
    });
    
    // Load icons function
    function loadIcons() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'zippicks_get_icons',
                nonce: '<?php echo wp_create_nonce('zippicks_get_icons'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var html = '';
                    response.data.forEach(function(icon) {
                        html += '<div class="icon-item">';
                        html += '<div class="icon-preview">' + icon.svg + '</div>';
                        html += '<div class="icon-name">' + icon.name + '</div>';
                        html += '<div class="icon-usage">Used by ' + icon.usage_count + ' vibes</div>';
                        html += '<div class="icon-actions">';
                        
                        if (icon.usage_count === 0 && icon.name !== 'default') {
                            html += '<a href="#" class="delete-icon" data-icon="' + icon.name + '">Delete</a>';
                        } else {
                            html += '<span class="delete-icon disabled" title="Cannot delete icons in use or default icon">Delete</span>';
                        }
                        
                        html += '</div>';
                        html += '</div>';
                    });
                    $('#icon-grid').html(html);
                }
            }
        });
    }
});
</script>