<?php
/**
 * Create Load Testing Database Tables
 * 
 * Database migration for load testing and runbook execution tracking
 *
 * @package ZipPicks\Foundation\Database\Migrations
 */

function zippicks_create_load_testing_tables() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // Load testing results table
    $load_tests_table = $wpdb->prefix . 'zippicks_load_tests';
    $load_tests_sql = "CREATE TABLE $load_tests_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        test_id varchar(255) NOT NULL,
        test_name varchar(255) NOT NULL,
        scenario varchar(100) NOT NULL,
        start_time timestamp NOT NULL,
        end_time timestamp NULL,
        status varchar(50) NOT NULL,
        config longtext,
        results longtext,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY test_id (test_id),
        KEY idx_test_name (test_name),
        KEY idx_scenario (scenario),
        KEY idx_start_time (start_time),
        KEY idx_status (status)
    ) $charset_collate;";

    // Runbook executions table
    $runbook_executions_table = $wpdb->prefix . 'zippicks_runbook_executions';
    $runbook_executions_sql = "CREATE TABLE $runbook_executions_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        execution_id varchar(255) NOT NULL,
        runbook_id varchar(255) NOT NULL,
        executed_by varchar(100) NOT NULL,
        start_time timestamp NOT NULL,
        end_time timestamp NULL,
        status varchar(50) NOT NULL,
        context longtext,
        results longtext,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY execution_id (execution_id),
        KEY idx_runbook_id (runbook_id),
        KEY idx_executed_by (executed_by),
        KEY idx_start_time (start_time),
        KEY idx_status (status)
    ) $charset_collate;";

    // Performance metrics table for historical tracking
    $performance_metrics_table = $wpdb->prefix . 'zippicks_performance_metrics';
    $performance_metrics_sql = "CREATE TABLE $performance_metrics_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        metric_type varchar(100) NOT NULL,
        metric_name varchar(255) NOT NULL,
        metric_value decimal(15,4) NOT NULL,
        metric_unit varchar(50) DEFAULT NULL,
        context longtext,
        recorded_at timestamp NOT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_metric_type (metric_type),
        KEY idx_metric_name (metric_name),
        KEY idx_recorded_at (recorded_at)
    ) $charset_collate;";

    // Test scenarios configuration table
    $test_scenarios_table = $wpdb->prefix . 'zippicks_test_scenarios';
    $test_scenarios_sql = "CREATE TABLE $test_scenarios_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        scenario_id varchar(255) NOT NULL,
        scenario_name varchar(255) NOT NULL,
        category varchar(100) NOT NULL,
        description text,
        default_config longtext,
        thresholds longtext,
        enabled tinyint(1) DEFAULT 1,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY scenario_id (scenario_id),
        KEY idx_category (category),
        KEY idx_enabled (enabled)
    ) $charset_collate;";

    // Runbook definitions table
    $runbooks_table = $wpdb->prefix . 'zippicks_runbooks';
    $runbooks_sql = "CREATE TABLE $runbooks_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        runbook_id varchar(255) NOT NULL,
        name varchar(255) NOT NULL,
        category varchar(100) NOT NULL,
        criticality varchar(50) NOT NULL,
        description text,
        steps longtext,
        rollback_steps longtext,
        prerequisites longtext,
        estimated_duration int DEFAULT NULL,
        enabled tinyint(1) DEFAULT 1,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY runbook_id (runbook_id),
        KEY idx_category (category),
        KEY idx_criticality (criticality),
        KEY idx_enabled (enabled)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    // Create tables
    dbDelta($load_tests_sql);
    dbDelta($runbook_executions_sql);
    dbDelta($performance_metrics_sql);
    dbDelta($test_scenarios_sql);
    dbDelta($runbooks_sql);

    // Insert default test scenarios
    $default_scenarios = [
        [
            'scenario_id' => 'api_endpoints',
            'scenario_name' => 'API Endpoints Load Test',
            'category' => 'api',
            'description' => 'Comprehensive load testing of all major API endpoints',
            'default_config' => json_encode([
                'duration' => 300,
                'concurrent_users' => 100,
                'target_rps' => 1000,
                'ramp_up_time' => 60,
                'ramp_down_time' => 30
            ]),
            'thresholds' => json_encode([
                'response_time_p95' => 500,
                'error_rate' => 5,
                'requests_per_second' => 1000
            ])
        ],
        [
            'scenario_id' => 'database_stress',
            'scenario_name' => 'Database Stress Test',
            'category' => 'database',
            'description' => 'Database performance testing under high load',
            'default_config' => json_encode([
                'duration' => 300,
                'concurrent_connections' => 50,
                'queries_per_second' => 500
            ]),
            'thresholds' => json_encode([
                'query_time_p95' => 100,
                'connection_success_rate' => 99,
                'deadlock_rate' => 1
            ])
        ],
        [
            'scenario_id' => 'cache_performance',
            'scenario_name' => 'Cache Performance Test',
            'category' => 'cache',
            'description' => 'Redis cache performance and reliability testing',
            'default_config' => json_encode([
                'duration' => 300,
                'operations_per_second' => 5000,
                'hit_rate_target' => 95
            ]),
            'thresholds' => json_encode([
                'response_time_p95' => 2,
                'hit_rate' => 95,
                'error_rate' => 1
            ])
        ]
    ];

    foreach ($default_scenarios as $scenario) {
        $wpdb->replace($test_scenarios_table, $scenario);
    }

    // Insert default runbooks
    $default_runbooks = [
        [
            'runbook_id' => 'api_outage',
            'name' => 'API Outage Response',
            'category' => 'incident_response',
            'criticality' => 'critical',
            'description' => 'Comprehensive incident response procedure for API outages',
            'estimated_duration' => 15,
            'steps' => json_encode([]),
            'rollback_steps' => json_encode([]),
            'prerequisites' => json_encode(['sudo_access', 'wp_cli_available'])
        ],
        [
            'runbook_id' => 'database_issues',
            'name' => 'Database Issues Response',
            'category' => 'incident_response',
            'criticality' => 'critical',
            'description' => 'Database connectivity and performance issue resolution',
            'estimated_duration' => 20,
            'steps' => json_encode([]),
            'rollback_steps' => json_encode([]),
            'prerequisites' => json_encode(['database_access', 'monitoring_access'])
        ],
        [
            'runbook_id' => 'cache_failures',
            'name' => 'Cache System Failures',
            'category' => 'incident_response',
            'criticality' => 'high',
            'description' => 'Redis cache failure detection and recovery procedures',
            'estimated_duration' => 10,
            'steps' => json_encode([]),
            'rollback_steps' => json_encode([]),
            'prerequisites' => json_encode(['redis_access', 'cache_monitoring'])
        ],
        [
            'runbook_id' => 'deployment_rollback',
            'name' => 'Deployment Rollback',
            'category' => 'deployment',
            'criticality' => 'high',
            'description' => 'Automated rollback procedures for failed deployments',
            'estimated_duration' => 30,
            'steps' => json_encode([]),
            'rollback_steps' => json_encode([]),
            'prerequisites' => json_encode(['deployment_access', 'git_access'])
        ]
    ];

    foreach ($default_runbooks as $runbook) {
        $wpdb->replace($runbooks_table, $runbook);
    }

    // Update database version
    update_option('zippicks_load_testing_db_version', '1.0.0');
    
    return true;
}

// Hook into WordPress to run migration
add_action('init', function() {
    $current_version = get_option('zippicks_load_testing_db_version', '0.0.0');
    if (version_compare($current_version, '1.0.0', '<')) {
        zippicks_create_load_testing_tables();
    }
});