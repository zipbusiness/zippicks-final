# Admin Pages Quick Start Guide

## 🚀 30-Minute Quick Start

### Step 1: Business Management Page (10 minutes)

```php
// src/Admin/BusinessManagementPage.php
<?php
namespace ZipPicks\Core\Admin;

class BusinessManagementPage {
    private $businessService;
    
    public function __construct($container) {
        $this->businessService = $container->get('zippicks.core.service.business');
    }
    
    public function init() {
        add_action('admin_menu', [$this, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        
        // AJAX handlers
        add_action('wp_ajax_zippicks_quick_edit_business', [$this, 'ajaxQuickEdit']);
        add_action('wp_ajax_zippicks_feature_business', [$this, 'ajaxFeatureBusiness']);
        add_action('wp_ajax_zippicks_import_businesses', [$this, 'ajaxImportBusinesses']);
    }
    
    public function renderPage() {
        // 1. Get businesses
        $businesses = $this->businessService->searchBusinesses([
            'limit' => 20,
            'page' => $_GET['paged'] ?? 1
        ]);
        
        // 2. Render table
        include __DIR__ . '/views/business-management.php';
    }
}
```

### Step 2: Review Moderation Page (10 minutes)

```php
// src/Admin/ReviewModerationPage.php
<?php
namespace ZipPicks\Core\Admin;

class ReviewModerationPage {
    private $reviewService;
    
    public function __construct($container) {
        $this->reviewService = $container->get('zippicks.core.service.review');
    }
    
    public function renderPage() {
        // 1. Get pending reviews
        $reviews = $this->reviewService->getReviews([
            'status' => $_GET['status'] ?? 'pending',
            'limit' => 20
        ]);
        
        // 2. Show moderation interface
        include __DIR__ . '/views/review-moderation.php';
    }
    
    public function ajaxModerateReview() {
        check_ajax_referer('zippicks_admin_nonce');
        
        $reviewId = intval($_POST['review_id']);
        $action = sanitize_text_field($_POST['action']);
        
        $result = $this->reviewService->moderateReview($reviewId, $action, '');
        
        wp_send_json_success(['message' => 'Review moderated']);
    }
}
```

### Step 3: Basic Views (10 minutes)

```php
// views/business-management.php
<div class="wrap">
    <h1>Business Management</h1>
    
    <div class="tablenav top">
        <div class="alignleft actions">
            <button class="button" onclick="ZipPicksAdmin.Business.showImport()">
                Import CSV
            </button>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Business Name</th>
                <th>Location</th>
                <th>Score</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($businesses as $business): ?>
            <tr>
                <td><?php echo esc_html($business->name); ?></td>
                <td><?php echo esc_html($business->city . ', ' . $business->state); ?></td>
                <td><?php echo number_format($business->score, 1); ?></td>
                <td>
                    <?php if ($business->is_featured): ?>
                        <span class="badge featured">Featured</span>
                    <?php endif; ?>
                </td>
                <td>
                    <button class="button-link" onclick="ZipPicksAdmin.Business.quickEdit(<?php echo $business->id; ?>)">
                        Quick Edit
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
```

---

## 📝 Copy-Paste Templates

### WP_List_Table Implementation

```php
// src/Admin/Tables/BusinessListTable.php
class BusinessListTable extends \WP_List_Table {
    
    public function get_columns() {
        return [
            'cb' => '<input type="checkbox" />',
            'name' => 'Business Name',
            'location' => 'Location',
            'score' => 'Score',
            'reviews' => 'Reviews',
            'status' => 'Status',
            'date' => 'Added'
        ];
    }
    
    public function prepare_items() {
        $this->_column_headers = [
            $this->get_columns(),
            [], // hidden columns
            $this->get_sortable_columns()
        ];
        
        // Get data from BusinessService
        $businessService = zippicks()->get('zippicks.core.service.business');
        $this->items = $businessService->searchBusinesses([
            'limit' => 20,
            'page' => $this->get_pagenum()
        ]);
    }
    
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'score':
                return number_format($item->score, 1);
            case 'location':
                return esc_html($item->city . ', ' . $item->state);
            default:
                return esc_html($item->$column_name);
        }
    }
}
```

### AJAX Handler Template

```javascript
// assets/js/admin.js
var ZipPicksAdmin = {
    Business: {
        quickEdit: function(businessId) {
            jQuery.post(ajaxurl, {
                action: 'zippicks_get_business',
                business_id: businessId,
                nonce: zippicks_admin.nonce
            }, function(response) {
                if (response.success) {
                    // Show modal with business data
                    ZipPicksAdmin.showModal('quick-edit', response.data);
                }
            });
        },
        
        save: function() {
            var data = jQuery('#quick-edit-form').serialize();
            data += '&action=zippicks_save_business&nonce=' + zippicks_admin.nonce;
            
            jQuery.post(ajaxurl, data, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        }
    },
    
    Reviews: {
        moderate: function(reviewId, action) {
            if (!confirm('Are you sure?')) return;
            
            jQuery.post(ajaxurl, {
                action: 'zippicks_moderate_review',
                review_id: reviewId,
                mod_action: action,
                nonce: zippicks_admin.nonce
            }, function(response) {
                if (response.success) {
                    jQuery('#review-' + reviewId).fadeOut();
                }
            });
        }
    }
};
```

### CSS Starter

```css
/* assets/css/admin.css */

/* Status Badges */
.zippicks-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.zippicks-badge.featured {
    background: #0073aa;
    color: white;
}

.zippicks-badge.pending {
    background: #ffb900;
    color: #23282d;
}

/* Trust Score Meter */
.zippicks-trust-meter {
    width: 100px;
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}

.zippicks-trust-meter-fill {
    height: 100%;
    background: linear-gradient(to right, #dc3545, #ffc107, #28a745);
    transition: width 0.3s ease;
}

/* Quick Edit Modal */
.zippicks-modal {
    display: none;
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 100000;
    max-width: 600px;
    width: 90%;
}
```

---

## 🎯 Minimal Viable Implementation (2 Hours)

### Hour 1: Business Management
1. Create basic listing page (30 min)
2. Add quick edit AJAX (20 min)
3. Implement feature business action (10 min)

### Hour 2: Review Moderation
1. Create review queue page (30 min)
2. Add approve/reject buttons (20 min)
3. Style trust score display (10 min)

### Skip for MVP:
- Bulk actions (add later)
- Import/Export (add later)
- Keyboard shortcuts (add later)
- Real-time updates (add later)

---

## 🐛 Common Issues & Solutions

### Issue: AJAX returns 0
**Solution**: Check action hook name matches exactly
```php
add_action('wp_ajax_zippicks_moderate_review', [$this, 'ajaxModerateReview']);
// JavaScript must use same action name
```

### Issue: Permissions error
**Solution**: Add capability checks
```php
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}
```

### Issue: Nonce verification fails
**Solution**: Use correct nonce name
```php
check_ajax_referer('zippicks_admin_nonce', 'nonce');
```

---

## 📚 Resources

- [WP_List_Table Example](https://github.com/WordPress/WordPress/blob/master/wp-admin/includes/class-wp-posts-list-table.php)
- [Admin Page Generator](https://wppb.me/)
- [AJAX in Plugins Handbook](https://developer.wordpress.org/plugins/javascript/ajax/)

---

**Start simple, iterate fast, ship early!** 🚀