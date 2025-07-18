<?php
/**
 * Database operations for ZipPicks Business plugin.
 *
 * Handles table creation, schema management, and database queries.
 */
class ZipPicks_Business_Database {
    
    /**
     * Table name constants
     */
    const TABLE_ANALYTICS = 'zippicks_business_analytics';
    const TABLE_MONETIZATION = 'zippicks_business_monetization';
    const TABLE_VERIFICATION = 'zippicks_business_verification';
    const TABLE_SCRAPE_LOG = 'zippicks_scrape_log';
    const TABLE_VIBES = 'zippicks_business_vibes';
    
    /**
     * Get analytics table name with prefix
     */
    public static function get_analytics_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_ANALYTICS;
    }
    
    /**
     * Get monetization table name with prefix
     */
    public static function get_monetization_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_MONETIZATION;
    }
    
    /**
     * Get verification table name with prefix
     */
    public static function get_verification_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_VERIFICATION;
    }
    
    /**
     * Get scrape log table name with prefix
     */
    public static function get_scrape_log_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SCRAPE_LOG;
    }
    
    /**
     * Get vibes table name with prefix
     */
    public static function get_vibes_table() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_VIBES;
    }
    
    /**
     * Get database schema SQL
     */
    public static function get_schema_sql() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "
        CREATE TABLE " . self::get_analytics_table() . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            business_id bigint(20) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_value varchar(255),
            user_id bigint(20),
            ip_address varchar(45),
            user_agent text,
            referrer text,
            session_id varchar(32),
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY business_id (business_id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY user_id (user_id),
            KEY session_id (session_id)
        ) $charset_collate;
        
        CREATE TABLE " . self::get_monetization_table() . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            business_id bigint(20) NOT NULL,
            tier varchar(20) NOT NULL DEFAULT 'basic',
            subscription_id varchar(100),
            subscription_status varchar(20),
            payment_method varchar(50),
            features text,
            amount decimal(10,2),
            currency varchar(3) DEFAULT 'USD',
            started_at datetime,
            expires_at datetime,
            last_payment_at datetime,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY business_id (business_id),
            KEY tier (tier),
            KEY expires_at (expires_at),
            KEY subscription_status (subscription_status)
        ) $charset_collate;
        
        CREATE TABLE " . self::get_verification_table() . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            business_id bigint(20) NOT NULL,
            verification_type varchar(50) NOT NULL,
            verification_status varchar(20) NOT NULL,
            verification_data text,
            verification_notes text,
            verified_by bigint(20),
            verified_at datetime,
            expires_at datetime,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY business_id (business_id),
            KEY verification_status (verification_status),
            KEY verification_type (verification_type),
            KEY expires_at (expires_at)
        ) $charset_collate;
        
        CREATE TABLE " . self::get_scrape_log_table() . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            request_path varchar(255) NOT NULL,
            user_agent text,
            referrer text,
            request_count int(11) DEFAULT 1,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY ip_address (ip_address),
            KEY request_path (request_path),
            KEY timestamp (timestamp)
        ) $charset_collate;
        
        CREATE TABLE " . self::get_vibes_table() . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            business_id bigint(20) NOT NULL,
            vibe_slug varchar(100) NOT NULL,
            confidence_score float NOT NULL,
            category varchar(50),
            source varchar(20) DEFAULT 'api',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_business_vibe (business_id, vibe_slug),
            KEY idx_business (business_id),
            KEY idx_vibe (vibe_slug),
            KEY idx_confidence (confidence_score)
        ) $charset_collate;";
        
        return $sql;
    }
    
    /**
     * Create database tables
     */
    public static function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $sql = self::get_schema_sql();
        $result = dbDelta($sql);
        
        // Log results if logger is available
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('ZipPicks Business tables created', array(
                'result' => $result
            ));
        }
        
        return $result;
    }
    
    /**
     * Verify tables exist
     */
    public static function verify_tables() {
        global $wpdb;
        
        $tables = array(
            self::get_analytics_table(),
            self::get_monetization_table(),
            self::get_verification_table(),
            self::get_scrape_log_table(),
            self::get_vibes_table()
        );
        
        foreach ($tables as $table) {
            $result = $wpdb->get_var("SHOW TABLES LIKE '$table'");
            if ($result !== $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Alternative table creation method using direct SQL
     */
    public static function create_tables_direct() {
        global $wpdb;
        
        $queries = explode(';', self::get_schema_sql());
        
        foreach ($queries as $query) {
            $query = trim($query);
            if (!empty($query)) {
                $wpdb->query($query);
            }
        }
        
        return self::verify_tables();
    }
    
    /**
     * Track analytics event
     */
    public static function track_event($business_id, $event_type, $event_value = null, $user_id = null) {
        global $wpdb;
        
        $data = array(
            'business_id' => $business_id,
            'event_type' => $event_type,
            'event_value' => $event_value,
            'user_id' => $user_id ?: get_current_user_id(),
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
            'session_id' => self::get_session_id(),
            'created_at' => current_time('mysql')
        );
        
        return $wpdb->insert(
            self::get_analytics_table(),
            $data,
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Get client IP address
     */
    private static function get_client_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                $ip = $_SERVER[$key];
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * Get or create session ID
     * 
     * Enterprise-grade session handling with security improvements
     */
    private static function get_session_id() {
        // Use WordPress transients for session tracking instead of PHP sessions
        $session_cookie_name = 'zippicks_business_session';
        $session_id = null;
        
        // Check for existing session cookie
        if (isset($_COOKIE[$session_cookie_name])) {
            $session_id = sanitize_key($_COOKIE[$session_cookie_name]);
            
            // Validate session format (32 character alphanumeric)
            if (!preg_match('/^[a-z0-9]{32}$/', $session_id)) {
                $session_id = null; // Invalid session, regenerate
            }
        }
        
        // Generate new session if needed
        if (empty($session_id)) {
            $session_id = wp_generate_password(32, false, false);
            
            // Set secure cookie
            $secure = is_ssl();
            $httponly = true;
            $samesite = 'Strict';
            
            // Use WordPress cookie setting for consistency
            if (!headers_sent()) {
                setcookie(
                    $session_cookie_name,
                    $session_id,
                    time() + DAY_IN_SECONDS,
                    COOKIEPATH,
                    COOKIE_DOMAIN,
                    $secure,
                    $httponly
                );
                
                // For PHP 7.3+ add SameSite
                if (version_compare(PHP_VERSION, '7.3.0', '>=')) {
                    setcookie(
                        $session_cookie_name,
                        $session_id,
                        array(
                            'expires' => time() + DAY_IN_SECONDS,
                            'path' => COOKIEPATH,
                            'domain' => COOKIE_DOMAIN,
                            'secure' => $secure,
                            'httponly' => $httponly,
                            'samesite' => $samesite
                        )
                    );
                }
            }
        }
        
        return substr($session_id, 0, 32);
    }
    
    /**
     * Get individual table SQL for Foundation registration
     */
    public static function get_analytics_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE " . self::get_analytics_table() . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            business_id bigint(20) NOT NULL,
            event_type varchar(50) NOT NULL,
            event_value varchar(255),
            user_id bigint(20),
            ip_address varchar(45),
            user_agent text,
            referrer text,
            session_id varchar(32),
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY business_id (business_id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY user_id (user_id),
            KEY session_id (session_id)
        ) $charset_collate";
    }
    
    public static function get_monetization_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE " . self::get_monetization_table() . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            business_id bigint(20) NOT NULL,
            tier varchar(20) NOT NULL DEFAULT 'basic',
            subscription_id varchar(100),
            subscription_status varchar(20),
            payment_method varchar(50),
            features text,
            amount decimal(10,2),
            currency varchar(3) DEFAULT 'USD',
            started_at datetime,
            expires_at datetime,
            last_payment_at datetime,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY business_id (business_id),
            KEY tier (tier),
            KEY expires_at (expires_at),
            KEY subscription_status (subscription_status)
        ) $charset_collate";
    }
    
    public static function get_verification_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE " . self::get_verification_table() . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            business_id bigint(20) NOT NULL,
            verification_type varchar(50) NOT NULL,
            verification_status varchar(20) NOT NULL,
            verification_data text,
            verification_notes text,
            verified_by bigint(20),
            verified_at datetime,
            expires_at datetime,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY business_id (business_id),
            KEY verification_status (verification_status),
            KEY verification_type (verification_type),
            KEY expires_at (expires_at)
        ) $charset_collate";
    }
    
    public static function get_scrape_log_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE " . self::get_scrape_log_table() . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            request_path varchar(255) NOT NULL,
            user_agent text,
            referrer text,
            request_count int(11) DEFAULT 1,
            timestamp datetime NOT NULL,
            PRIMARY KEY (id),
            KEY ip_address (ip_address),
            KEY request_path (request_path),
            KEY timestamp (timestamp)
        ) $charset_collate";
    }
    
    public static function get_vibes_table_sql() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        return "CREATE TABLE " . self::get_vibes_table() . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            business_id bigint(20) NOT NULL,
            vibe_slug varchar(100) NOT NULL,
            confidence_score float NOT NULL,
            category varchar(50),
            source varchar(20) DEFAULT 'api',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_business_vibe (business_id, vibe_slug),
            KEY idx_business (business_id),
            KEY idx_vibe (vibe_slug),
            KEY idx_confidence (confidence_score)
        ) $charset_collate";
    }
}