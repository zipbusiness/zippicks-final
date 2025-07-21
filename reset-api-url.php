<?php
/**
 * Reset API URL to use new default
 * Run this once from WordPress admin or via browser
 */

// Load WordPress
require_once 'wp-config.php';

// Delete the old API URL option so it uses the new default
delete_option('zippicks_bi_api_url');

echo "API URL option reset. The plugin will now use the new default: https://zipbusiness-api.onrender.com/api/v1\n";

// Optional: Set it explicitly
update_option('zippicks_bi_api_url', 'https://zipbusiness-api.onrender.com/api/v1');

echo "API URL has been updated to: " . get_option('zippicks_bi_api_url') . "\n";