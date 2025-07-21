<?php
/**
 * Object Cache Compatibility Layer
 * Minimal compatibility file to prevent loading issues
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Minimal compatibility - this file exists to prevent include errors
// The actual object cache issue needs to be addressed elsewhere