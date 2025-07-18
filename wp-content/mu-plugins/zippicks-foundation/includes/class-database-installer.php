<?php
/**
 * Simple Database Installer for ZipPicks Foundation
 * 
 * Handles table creation and verification for all ZipPicks plugins.
 * Future-ready for migration system but simple for MVP.
 */

namespace ZipPicks\Foundation;

class Database_Installer {
    
    /**
     * Registered plugin schemas
     */
    private $schemas = [];
    
    /**
     * Track installation status
     */
    private $install_status = [];
    
    /**
     * Register a plugin's database schema
     */
    public function register_schema($plugin_name, $schema_callback, $version = '1.0.0') {
        $this->schemas[$plugin_name] = [
            'callback' => $schema_callback,
            'version' => $version,
            'tables' => []
        ];
    }
    
    /**
     * Install all registered schemas
     */
    public function install_all() {
        foreach ($this->schemas as $plugin => $schema) {
            $this->install_plugin_schema($plugin);
        }
        return $this->install_status;
    }
    
    /**
     * Install a specific plugin's schema
     */
    public function install_plugin_schema($plugin_name) {
        if (!isset($this->schemas[$plugin_name])) {
            return false;
        }
        
        $schema = $this->schemas[$plugin_name];
        $callback = $schema['callback'];
        
        // Get table definitions from plugin
        $tables = call_user_func($callback);
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $results = [];
        foreach ($tables as $table_name => $sql) {
            $result = dbDelta($sql);
            $results[$table_name] = $result;
        }
        
        // Store version for future migrations
        update_option("zippicks_db_version_{$plugin_name}", $schema['version']);
        
        $this->install_status[$plugin_name] = [
            'success' => true,
            'tables' => array_keys($tables),
            'results' => $results
        ];
        
        return true;
    }
    
    /**
     * Verify all tables exist
     */
    public function verify_all() {
        global $wpdb;
        $status = [];
        
        foreach ($this->schemas as $plugin => $schema) {
            $tables = call_user_func($schema['callback']);
            
            foreach ($tables as $table_name => $sql) {
                // Extract actual table name from SQL
                preg_match('/CREATE TABLE\s+(\S+)\s+/i', $sql, $matches);
                $full_table_name = $matches[1] ?? $table_name;
                
                $exists = $wpdb->get_var(
                    $wpdb->prepare("SHOW TABLES LIKE %s", $full_table_name)
                );
                
                $status[$plugin][$table_name] = [
                    'exists' => (bool) $exists,
                    'name' => $full_table_name
                ];
            }
        }
        
        return $status;
    }
    
    /**
     * Auto-install missing tables (safe for production)
     */
    public function ensure_tables_exist() {
        $verification = $this->verify_all();
        $installed = [];
        
        foreach ($verification as $plugin => $tables) {
            $needs_install = false;
            
            foreach ($tables as $table => $info) {
                if (!$info['exists']) {
                    $needs_install = true;
                    break;
                }
            }
            
            if ($needs_install) {
                $this->install_plugin_schema($plugin);
                $installed[] = $plugin;
            }
        }
        
        return $installed;
    }
    
    /**
     * Get simple status report
     */
    public function get_status() {
        $verification = $this->verify_all();
        $status = [
            'healthy' => true,
            'plugins' => []
        ];
        
        foreach ($verification as $plugin => $tables) {
            $plugin_healthy = true;
            $missing_tables = [];
            
            foreach ($tables as $table => $info) {
                if (!$info['exists']) {
                    $plugin_healthy = false;
                    $status['healthy'] = false;
                    $missing_tables[] = $table;
                }
            }
            
            $status['plugins'][$plugin] = [
                'healthy' => $plugin_healthy,
                'version' => get_option("zippicks_db_version_{$plugin}", 'unknown'),
                'missing_tables' => $missing_tables
            ];
        }
        
        return $status;
    }
}