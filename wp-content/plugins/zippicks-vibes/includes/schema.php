<?php
/**
 * ZipPicks Vibes - Database Schema Definition
 * 
 * Centralized schema definition for all plugin database tables
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access prohibited');
}

/**
 * Define schema SQL as a constant for reusability
 * This SQL is used by both create-tables.php and the Installer class
 */
define('ZIPPICKS_VIBES_SCHEMA_SQL', '
-- Main vibes table
CREATE TABLE IF NOT EXISTS {prefix}zippicks_vibes (
    id int(11) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    description text,
    icon varchar(255) DEFAULT \'default\',
    color varchar(7) DEFAULT \'#000000\',
    order_position int(11) DEFAULT 0,
    is_active tinyint(1) DEFAULT 1,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY is_active (is_active),
    KEY order_position (order_position)
) {charset_collate};

-- Vibe categories table
CREATE TABLE IF NOT EXISTS {prefix}zippicks_vibe_categories (
    id int(11) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    slug varchar(255) NOT NULL,
    description text,
    parent_id int(11) DEFAULT 0,
    order_position int(11) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug),
    KEY parent_id (parent_id)
) {charset_collate};

-- Vibe category assignments
CREATE TABLE IF NOT EXISTS {prefix}zippicks_vibe_category_assignments (
    id int(11) NOT NULL AUTO_INCREMENT,
    vibe_id int(11) NOT NULL,
    category_id int(11) NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY vibe_category (vibe_id, category_id),
    KEY vibe_id (vibe_id),
    KEY category_id (category_id)
) {charset_collate};

-- Waitlist table
CREATE TABLE IF NOT EXISTS {prefix}zippicks_waitlist (
    id int(11) NOT NULL AUTO_INCREMENT,
    vibe_id int(11) NOT NULL,
    zip_code varchar(10) NOT NULL,
    user_id bigint(20) DEFAULT NULL,
    email varchar(255) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY vibe_zip (vibe_id, zip_code),
    KEY user_id (user_id),
    KEY created_at (created_at)
) {charset_collate};

-- Scrape log table
CREATE TABLE IF NOT EXISTS {prefix}zippicks_scrape_log (
    id int(11) NOT NULL AUTO_INCREMENT,
    ip_address varchar(45) NOT NULL,
    request_path varchar(255) NOT NULL,
    user_agent text,
    referrer varchar(255) DEFAULT NULL,
    session_id varchar(255) DEFAULT NULL,
    is_bot tinyint(1) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY ip_date (ip_address, created_at),
    KEY session_id (session_id),
    KEY created_at (created_at)
) {charset_collate};

-- Security log table
CREATE TABLE IF NOT EXISTS {prefix}zippicks_security_log (
    id int(11) NOT NULL AUTO_INCREMENT,
    event_type varchar(50) NOT NULL,
    ip_address varchar(45) NOT NULL,
    user_id bigint(20) DEFAULT NULL,
    details text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY event_date (event_type, created_at),
    KEY ip_address (ip_address),
    KEY user_id (user_id),
    KEY created_at (created_at)
) {charset_collate};

-- Rate limit log table
CREATE TABLE IF NOT EXISTS {prefix}zippicks_rate_limit_log (
    id int(11) NOT NULL AUTO_INCREMENT,
    identifier varchar(255) NOT NULL,
    endpoint varchar(255) NOT NULL,
    count int(11) DEFAULT 1,
    window_start datetime NOT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY identifier_endpoint (identifier, endpoint, window_start),
    KEY window_start (window_start),
    KEY updated_at (updated_at)
) {charset_collate};

-- Security events table
CREATE TABLE IF NOT EXISTS {prefix}zippicks_security_events (
    id int(11) NOT NULL AUTO_INCREMENT,
    event_type varchar(50) NOT NULL,
    severity varchar(20) NOT NULL,
    source varchar(100) NOT NULL,
    details text,
    ip_address varchar(45) DEFAULT NULL,
    user_id bigint(20) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY event_type (event_type),
    KEY severity (severity),
    KEY created_at (created_at),
    KEY ip_address (ip_address)
) {charset_collate};

-- Audit log table
CREATE TABLE IF NOT EXISTS {prefix}zippicks_audit_log (
    id int(11) NOT NULL AUTO_INCREMENT,
    action varchar(100) NOT NULL,
    object_type varchar(50) NOT NULL,
    object_id int(11) NOT NULL,
    user_id bigint(20) NOT NULL,
    old_value text,
    new_value text,
    ip_address varchar(45) NOT NULL,
    user_agent text,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY action (action),
    KEY object (object_type, object_id),
    KEY user_id (user_id),
    KEY created_at (created_at)
) {charset_collate};

-- Performance metrics table
CREATE TABLE IF NOT EXISTS {prefix}zippicks_performance_metrics (
    id int(11) NOT NULL AUTO_INCREMENT,
    metric_name varchar(100) NOT NULL,
    value float NOT NULL,
    context varchar(255) DEFAULT NULL,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY metric_name (metric_name),
    KEY created_at (created_at),
    KEY metric_date (metric_name, created_at)
) {charset_collate};
');

/**
 * Get table definitions array
 * 
 * @return array Associative array of table names and descriptions
 */
function get_table_definitions() {
    return [
        'zippicks_vibes' => 'Main vibes table storing mood-based categorizations',
        'zippicks_vibe_categories' => 'Vibe category hierarchy for organization',
        'zippicks_vibe_category_assignments' => 'Links vibes to categories (many-to-many)',
        'zippicks_waitlist' => 'Tracks user demand for vibes by ZIP code',
        'zippicks_scrape_log' => 'Anti-scraping protection and monitoring',
        'zippicks_security_log' => 'Security event tracking and analysis',
        'zippicks_rate_limit_log' => 'API rate limiting enforcement',
        'zippicks_security_events' => 'Enhanced security event tracking',
        'zippicks_audit_log' => 'Comprehensive audit trail for compliance',
        'zippicks_performance_metrics' => 'Performance monitoring and optimization'
    ];
}

/**
 * Get formatted schema SQL with proper prefix and charset
 * 
 * @param string $prefix Table prefix (optional)
 * @return string Formatted SQL ready for execution
 */
function get_formatted_schema_sql($prefix = null) {
    global $wpdb;
    
    if (!$prefix) {
        $prefix = $wpdb->get_blog_prefix();
    }
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Replace placeholders
    $sql = str_replace('{prefix}', $prefix, ZIPPICKS_VIBES_SCHEMA_SQL);
    $sql = str_replace('{charset_collate}', $charset_collate, $sql);
    
    return $sql;
}