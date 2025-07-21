<?php
/**
 * Enterprise Validation System for ZipPicks Business
 * 
 * Comprehensive validation and security checks for production environments.
 * This file ensures the plugin meets all enterprise requirements before execution.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enterprise Validation Class
 *
 * Performs comprehensive system checks and validations for production readiness.
 */
class ZipPicks_Business_Enterprise_Validator {
    
    /**
     * Validation results
     */
    private static $validation_results = array();
    private static $critical_errors = array();
    private static $warnings = array();
    
    /**
     * Run all enterprise validation checks
     *
     * @return bool True if all critical checks pass
     */
    public static function validate() {
        self::$validation_results = array();
        self::$critical_errors = array();
        self::$warnings = array();
        
        // System requirements
        self::check_php_version();
        self::check_wordpress_version();
        self::check_memory_limit();
        self::check_execution_time();
        
        // Security checks
        self::check_ssl_status();
        self::check_file_permissions();
        self::check_database_security();
        self::check_wp_debug_status();
        
        // Plugin integrity
        self::check_critical_files();
        self::check_database_tables();
        self::check_dependencies();
        
        // Performance checks
        self::check_cache_availability();
        self::check_opcache_status();
        
        // Log results if in debug mode
        if (ZIPPICKS_BUSINESS_DEBUG) {
            self::log_validation_results();
        }
        
        return empty(self::$critical_errors);
    }
    
    /**
     * Check PHP version
     */
    private static function check_php_version() {
        $required = '8.0.0';
        $current = PHP_VERSION;
        $passed = version_compare($current, $required, '>=');
        
        self::$validation_results['php_version'] = array(
            'check' => 'PHP Version',
            'required' => $required,
            'current' => $current,
            'passed' => $passed,
            'critical' => true
        );
        
        if (!$passed) {
            self::$critical_errors[] = sprintf(
                'PHP %s or higher is required. Current version: %s',
                $required,
                $current
            );
        }
    }
    
    /**
     * Check WordPress version
     */
    private static function check_wordpress_version() {
        global $wp_version;
        $required = '6.0';
        $passed = version_compare($wp_version, $required, '>=');
        
        self::$validation_results['wp_version'] = array(
            'check' => 'WordPress Version',
            'required' => $required,
            'current' => $wp_version,
            'passed' => $passed,
            'critical' => true
        );
        
        if (!$passed) {
            self::$critical_errors[] = sprintf(
                'WordPress %s or higher is required. Current version: %s',
                $required,
                $wp_version
            );
        }
    }
    
    /**
     * Check memory limit
     */
    private static function check_memory_limit() {
        $memory_limit = ini_get('memory_limit');
        $memory_bytes = wp_convert_hr_to_bytes($memory_limit);
        $required_bytes = wp_convert_hr_to_bytes('256M');
        $recommended_bytes = wp_convert_hr_to_bytes('512M');
        
        $passed = $memory_bytes >= $required_bytes;
        $optimal = $memory_bytes >= $recommended_bytes;
        
        self::$validation_results['memory_limit'] = array(
            'check' => 'PHP Memory Limit',
            'required' => '256M',
            'recommended' => '512M',
            'current' => $memory_limit,
            'passed' => $passed,
            'optimal' => $optimal,
            'critical' => true
        );
        
        if (!$passed) {
            self::$critical_errors[] = sprintf(
                'PHP memory limit of at least 256M is required. Current: %s',
                $memory_limit
            );
        } elseif (!$optimal) {
            self::$warnings[] = sprintf(
                'PHP memory limit of 512M is recommended for optimal performance. Current: %s',
                $memory_limit
            );
        }
    }
    
    /**
     * Check max execution time
     */
    private static function check_execution_time() {
        $max_execution_time = ini_get('max_execution_time');
        $required = 60;
        $recommended = 300;
        
        $passed = $max_execution_time == 0 || $max_execution_time >= $required;
        $optimal = $max_execution_time == 0 || $max_execution_time >= $recommended;
        
        self::$validation_results['execution_time'] = array(
            'check' => 'Max Execution Time',
            'required' => $required . 's',
            'recommended' => $recommended . 's',
            'current' => $max_execution_time == 0 ? 'Unlimited' : $max_execution_time . 's',
            'passed' => $passed,
            'optimal' => $optimal,
            'critical' => false
        );
        
        if (!$optimal && ZIPPICKS_BUSINESS_ENV === 'production') {
            self::$warnings[] = sprintf(
                'Max execution time of %ds is recommended. Current: %s',
                $recommended,
                $max_execution_time == 0 ? 'Unlimited' : $max_execution_time . 's'
            );
        }
    }
    
    /**
     * Check SSL status
     */
    private static function check_ssl_status() {
        $is_ssl = is_ssl();
        
        self::$validation_results['ssl_status'] = array(
            'check' => 'SSL Certificate',
            'required' => 'Required in production',
            'current' => $is_ssl ? 'Enabled' : 'Disabled',
            'passed' => $is_ssl || ZIPPICKS_BUSINESS_ENV !== 'production',
            'critical' => ZIPPICKS_BUSINESS_ENV === 'production'
        );
        
        if (!$is_ssl && ZIPPICKS_BUSINESS_ENV === 'production') {
            self::$critical_errors[] = 'SSL is required for production environments';
        }
    }
    
    /**
     * Check file permissions
     */
    private static function check_file_permissions() {
        $upload_dir = wp_upload_dir();
        $paths_to_check = array(
            $upload_dir['basedir'] => 'Uploads directory',
            ZIPPICKS_BUSINESS_PLUGIN_DIR . 'logs' => 'Plugin logs directory'
        );
        
        $permission_issues = array();
        
        foreach ($paths_to_check as $path => $label) {
            if (!file_exists($path)) {
                // Try to create directory
                if (!wp_mkdir_p($path)) {
                    $permission_issues[] = sprintf('%s cannot be created', $label);
                }
            } elseif (!is_writable($path)) {
                $permission_issues[] = sprintf('%s is not writable', $label);
            }
        }
        
        self::$validation_results['file_permissions'] = array(
            'check' => 'File Permissions',
            'required' => 'Writable directories',
            'current' => empty($permission_issues) ? 'All directories writable' : implode(', ', $permission_issues),
            'passed' => empty($permission_issues),
            'critical' => false
        );
        
        if (!empty($permission_issues)) {
            foreach ($permission_issues as $issue) {
                self::$warnings[] = 'File permission issue: ' . $issue;
            }
        }
    }
    
    /**
     * Check database security
     */
    private static function check_database_security() {
        global $wpdb;
        
        // Check if we can create tables
        $can_create_tables = true;
        $test_table = $wpdb->prefix . 'zippicks_validator_test_' . time();
        
        $wpdb->hide_errors();
        $wpdb->query("CREATE TABLE IF NOT EXISTS $test_table (id INT PRIMARY KEY)");
        
        if ($wpdb->last_error) {
            $can_create_tables = false;
        } else {
            $wpdb->query("DROP TABLE IF EXISTS $test_table");
        }
        
        self::$validation_results['database_permissions'] = array(
            'check' => 'Database Permissions',
            'required' => 'CREATE TABLE permission',
            'current' => $can_create_tables ? 'Full permissions' : 'Limited permissions',
            'passed' => $can_create_tables,
            'critical' => true
        );
        
        if (!$can_create_tables) {
            self::$critical_errors[] = 'Database CREATE TABLE permission is required';
        }
    }
    
    /**
     * Check WP_DEBUG status in production
     */
    private static function check_wp_debug_status() {
        $debug_enabled = defined('WP_DEBUG') && WP_DEBUG;
        $debug_display = defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY;
        
        $issues = array();
        if ($debug_enabled && ZIPPICKS_BUSINESS_ENV === 'production') {
            $issues[] = 'WP_DEBUG is enabled';
        }
        if ($debug_display && ZIPPICKS_BUSINESS_ENV === 'production') {
            $issues[] = 'WP_DEBUG_DISPLAY is enabled';
        }
        
        self::$validation_results['debug_status'] = array(
            'check' => 'Debug Mode',
            'required' => 'Disabled in production',
            'current' => empty($issues) ? 'Properly configured' : implode(', ', $issues),
            'passed' => empty($issues),
            'critical' => false
        );
        
        if (!empty($issues) && ZIPPICKS_BUSINESS_ENV === 'production') {
            foreach ($issues as $issue) {
                self::$warnings[] = 'Security warning: ' . $issue . ' in production';
            }
        }
    }
    
    /**
     * Check critical plugin files
     */
    private static function check_critical_files() {
        $required_files = array(
            'includes/class-activator.php' => 'Activator class',
            'includes/class-business.php' => 'Main plugin class',
            'includes/class-database.php' => 'Database handler',
            'includes/class-installer.php' => 'Installer class',
            'includes/class-post-types.php' => 'Post types handler',
            'includes/class-business-manager.php' => 'Business manager',
            'admin/class-admin.php' => 'Admin interface'
        );
        
        $missing_files = array();
        
        foreach ($required_files as $file => $description) {
            $path = ZIPPICKS_BUSINESS_PLUGIN_DIR . $file;
            if (!file_exists($path)) {
                $missing_files[] = $description . ' (' . $file . ')';
            }
        }
        
        self::$validation_results['critical_files'] = array(
            'check' => 'Critical Files',
            'required' => 'All plugin files present',
            'current' => empty($missing_files) ? 'All files present' : count($missing_files) . ' files missing',
            'passed' => empty($missing_files),
            'critical' => true
        );
        
        if (!empty($missing_files)) {
            self::$critical_errors[] = 'Missing critical files: ' . implode(', ', $missing_files);
        }
    }
    
    /**
     * Check database tables
     */
    private static function check_database_tables() {
        global $wpdb;
        
        $required_tables = array(
            'zippicks_business_analytics',
            'zippicks_business_monetization',
            'zippicks_business_verification',
            'zippicks_scrape_log'
        );
        
        $missing_tables = array();
        
        foreach ($required_tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            if (!$exists) {
                $missing_tables[] = $table;
            }
        }
        
        self::$validation_results['database_tables'] = array(
            'check' => 'Database Tables',
            'required' => 'All tables created',
            'current' => empty($missing_tables) ? 'All tables exist' : count($missing_tables) . ' tables missing',
            'passed' => true, // Not critical - tables will be created on activation
            'critical' => false
        );
        
        if (!empty($missing_tables)) {
            self::$warnings[] = 'Database tables will be created on activation: ' . implode(', ', $missing_tables);
        }
    }
    
    /**
     * Check plugin dependencies
     */
    private static function check_dependencies() {
        $dependencies = array(
            'zippicks_foundation' => array(
                'check' => function_exists('zippicks'),
                'label' => 'ZipPicks Foundation',
                'required' => false
            )
        );
        
        $missing_optional = array();
        
        foreach ($dependencies as $key => $dep) {
            if (!$dep['check'] && !$dep['required']) {
                $missing_optional[] = $dep['label'];
            }
        }
        
        self::$validation_results['dependencies'] = array(
            'check' => 'Plugin Dependencies',
            'required' => 'None required',
            'current' => empty($missing_optional) ? 'All recommended plugins active' : 'Some optional plugins missing',
            'passed' => true,
            'critical' => false
        );
        
        if (!empty($missing_optional)) {
            self::$warnings[] = 'Recommended plugins not active: ' . implode(', ', $missing_optional);
        }
    }
    
    /**
     * Check cache availability
     */
    private static function check_cache_availability() {
        $cache_methods = array(
            'Redis' => extension_loaded('redis'),
            'Memcached' => extension_loaded('memcached'),
            'APCu' => function_exists('apcu_fetch')
        );
        
        $available_caches = array_filter($cache_methods);
        
        self::$validation_results['cache_availability'] = array(
            'check' => 'Object Cache',
            'required' => 'Recommended for production',
            'current' => empty($available_caches) ? 'No object cache available' : implode(', ', array_keys($available_caches)),
            'passed' => !empty($available_caches) || ZIPPICKS_BUSINESS_ENV !== 'production',
            'critical' => false
        );
        
        if (empty($available_caches) && ZIPPICKS_BUSINESS_ENV === 'production') {
            self::$warnings[] = 'Object cache (Redis/Memcached) is recommended for production performance';
        }
    }
    
    /**
     * Check OPcache status
     */
    private static function check_opcache_status() {
        $opcache_enabled = function_exists('opcache_get_status') && opcache_get_status() !== false;
        
        self::$validation_results['opcache_status'] = array(
            'check' => 'OPcache',
            'required' => 'Recommended for production',
            'current' => $opcache_enabled ? 'Enabled' : 'Disabled',
            'passed' => $opcache_enabled || ZIPPICKS_BUSINESS_ENV !== 'production',
            'critical' => false
        );
        
        if (!$opcache_enabled && ZIPPICKS_BUSINESS_ENV === 'production') {
            self::$warnings[] = 'OPcache is recommended for production performance';
        }
    }
    
    /**
     * Log validation results
     */
    private static function log_validation_results() {
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            
            $logger->info('ZipPicks Business enterprise validation completed', array(
                'environment' => ZIPPICKS_BUSINESS_ENV,
                'critical_errors' => count(self::$critical_errors),
                'warnings' => count(self::$warnings),
                'results' => self::$validation_results
            ));
            
            if (!empty(self::$critical_errors)) {
                foreach (self::$critical_errors as $error) {
                    $logger->error('Enterprise validation critical error: ' . $error);
                }
            }
            
            if (!empty(self::$warnings)) {
                foreach (self::$warnings as $warning) {
                    $logger->warning('Enterprise validation warning: ' . $warning);
                }
            }
        }
    }
    
    /**
     * Get validation results
     */
    public static function get_results() {
        return array(
            'passed' => empty(self::$critical_errors),
            'results' => self::$validation_results,
            'critical_errors' => self::$critical_errors,
            'warnings' => self::$warnings,
            'environment' => ZIPPICKS_BUSINESS_ENV
        );
    }
    
    /**
     * Display validation report in admin
     */
    public static function display_admin_report() {
        $results = self::get_results();
        
        if (!$results['passed']) {
            ?>
            <div class="notice notice-error">
                <h3><?php _e('ZipPicks Business: Enterprise Validation Failed', 'zippicks-business'); ?></h3>
                <p><?php _e('Critical errors must be resolved before the plugin can operate:', 'zippicks-business'); ?></p>
                <ul style="list-style: disc; margin-left: 20px;">
                    <?php foreach ($results['critical_errors'] as $error) : ?>
                        <li><?php echo esc_html($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
        
        if (!empty($results['warnings']) && ZIPPICKS_BUSINESS_DEBUG) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <h3><?php _e('ZipPicks Business: Optimization Recommendations', 'zippicks-business'); ?></h3>
                <ul style="list-style: disc; margin-left: 20px;">
                    <?php foreach ($results['warnings'] as $warning) : ?>
                        <li><?php echo esc_html($warning); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }
    }
}