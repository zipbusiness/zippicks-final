<?php
/**
 * Debug script for category filtering
 * Access at: /wp-content/plugins/zippicks-vibes/debug-category-filtering.php
 */

// Load WordPress
require_once(__DIR__ . '/../../../../wp-load.php');

// Security check
if (!current_user_can('manage_options')) {
    wp_die('Access denied');
}

global $wpdb;

// Get all active vibes with their categories
$vibes_query = "
    SELECT v.*, GROUP_CONCAT(vca.category_id SEPARATOR ',') as category_ids
    FROM {$wpdb->prefix}zippicks_vibes v
    LEFT JOIN {$wpdb->prefix}zippicks_vibe_category_assignments vca ON v.id = vca.vibe_id
    WHERE v.is_active = 1
    GROUP BY v.id
    ORDER BY v.order_position ASC, v.name ASC
";
$vibes = $wpdb->get_results($vibes_query);

// Get all categories
$categories_query = "
    SELECT id, name, slug 
    FROM {$wpdb->prefix}zippicks_vibe_categories 
    ORDER BY order_position ASC, name ASC
";
$categories = $wpdb->get_results($categories_query);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Vibe Category Debug</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 30px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .category-tag { display: inline-block; padding: 2px 8px; margin: 2px; background: #e0e0e0; border-radius: 3px; font-size: 12px; }
        .debug-section { margin-bottom: 40px; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>Vibe Category Filtering Debug</h1>
    
    <div class="debug-section">
        <h2>Categories (<?php echo count($categories); ?> total)</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Vibe Count</th>
            </tr>
            <?php 
            foreach ($categories as $category) {
                // Count vibes in this category
                $count_query = $wpdb->prepare("
                    SELECT COUNT(DISTINCT vibe_id) 
                    FROM {$wpdb->prefix}zippicks_vibe_category_assignments 
                    WHERE category_id = %d
                ", $category->id);
                $vibe_count = $wpdb->get_var($count_query);
                ?>
                <tr>
                    <td><?php echo $category->id; ?></td>
                    <td><?php echo esc_html($category->name); ?></td>
                    <td><?php echo esc_html($category->slug); ?></td>
                    <td><?php echo $vibe_count; ?></td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>
    
    <div class="debug-section">
        <h2>Active Vibes (<?php echo count($vibes); ?> total)</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Slug</th>
                <th>Categories</th>
                <th>Category IDs</th>
            </tr>
            <?php 
            foreach ($vibes as $vibe) {
                // Get category names for this vibe
                $category_names = [];
                if (!empty($vibe->category_ids)) {
                    $cat_ids = explode(',', $vibe->category_ids);
                    $cat_query = "
                        SELECT name 
                        FROM {$wpdb->prefix}zippicks_vibe_categories 
                        WHERE id IN (" . implode(',', array_map('intval', $cat_ids)) . ")
                    ";
                    $cat_results = $wpdb->get_results($cat_query);
                    foreach ($cat_results as $cat) {
                        $category_names[] = $cat->name;
                    }
                }
                ?>
                <tr>
                    <td><?php echo $vibe->id; ?></td>
                    <td><?php echo esc_html($vibe->name); ?></td>
                    <td><?php echo esc_html($vibe->slug); ?></td>
                    <td>
                        <?php 
                        foreach ($category_names as $cat_name) {
                            echo '<span class="category-tag">' . esc_html($cat_name) . '</span>';
                        }
                        ?>
                    </td>
                    <td><?php echo esc_html($vibe->category_ids ?: 'None'); ?></td>
                </tr>
                <?php
            }
            ?>
        </table>
    </div>
    
    <div class="debug-section">
        <h2>JavaScript Test Data</h2>
        <p>This shows what the JavaScript should see in the DOM:</p>
        <pre><?php
        // Show what data attributes should look like
        foreach ($vibes as $vibe) {
            if (!empty($vibe->category_ids)) {
                $cat_ids_array = explode(',', $vibe->category_ids);
                $space_separated = implode(' ', $cat_ids_array);
                echo "Vibe: " . esc_html($vibe->name) . "\n";
                echo "data-category=\"" . esc_html($space_separated) . "\"\n\n";
            }
        }
        ?></pre>
    </div>
    
    <div class="debug-section">
        <h2>Test Category Filtering</h2>
        <p>Click a category below to see which vibes should show:</p>
        <?php foreach ($categories as $category) : ?>
            <button onclick="testFilter('<?php echo $category->id; ?>', '<?php echo esc_js($category->name); ?>')" 
                    style="margin: 5px; padding: 5px 10px;">
                <?php echo esc_html($category->name); ?> (ID: <?php echo $category->id; ?>)
            </button>
        <?php endforeach; ?>
        
        <div id="filter-results" style="margin-top: 20px; padding: 10px; background: #f9f9f9;"></div>
    </div>
    
    <script>
    function testFilter(categoryId, categoryName) {
        const vibes = <?php echo json_encode($vibes); ?>;
        const matchingVibes = [];
        
        vibes.forEach(vibe => {
            if (vibe.category_ids) {
                const categoryArray = vibe.category_ids.split(',');
                if (categoryArray.includes(String(categoryId))) {
                    matchingVibes.push(vibe.name);
                }
            }
        });
        
        const resultsDiv = document.getElementById('filter-results');
        resultsDiv.innerHTML = '<h3>Vibes in "' + categoryName + '" (ID: ' + categoryId + '):</h3>';
        
        if (matchingVibes.length > 0) {
            resultsDiv.innerHTML += '<ul>' + matchingVibes.map(name => '<li>' + name + '</li>').join('') + '</ul>';
            resultsDiv.innerHTML += '<p><strong>' + matchingVibes.length + ' vibes found</strong></p>';
        } else {
            resultsDiv.innerHTML += '<p>No vibes found in this category</p>';
        }
    }
    </script>
</body>
</html>