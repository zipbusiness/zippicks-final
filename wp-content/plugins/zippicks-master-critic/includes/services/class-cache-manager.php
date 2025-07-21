<?php
/**
 * Cache Manager Service Wrapper
 * 
 * Provides namespaced access to the legacy cache manager
 * 
 * @package ZipPicks_Master_Critic
 * @subpackage Services
 */

namespace ZipPicks\MasterCritic\Services;

// Prevent direct access
if (!defined('WPINC')) {
    die;
}

/**
 * CacheManager service class
 * 
 * Acts as a bridge between the modern namespaced code and the legacy cache manager
 */
class CacheManager {
    
    /**
     * Legacy cache manager instance
     * 
     * @var \ZipPicks_Master_Critic_Cache_Manager
     */
    private $legacy_cache;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load the legacy cache manager if not already loaded
        if (!class_exists('ZipPicks_Master_Critic_Cache_Manager')) {
            $cache_file = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-cache-manager.php';
            if (file_exists($cache_file)) {
                require_once $cache_file;
            } else {
                throw new \RuntimeException('Cache Manager class file not found: ' . $cache_file);
            }
        }
        
        // Instantiate the legacy cache manager
        $this->legacy_cache = new \ZipPicks_Master_Critic_Cache_Manager();
    }
    
    /**
     * Proxy method calls to the legacy cache manager
     * 
     * @param string $method Method name
     * @param array $args Method arguments
     * @return mixed
     */
    public function __call($method, $args) {
        if (!method_exists($this->legacy_cache, $method)) {
            throw new \BadMethodCallException("Method {$method} does not exist on Cache Manager");
        }
        
        return call_user_func_array([$this->legacy_cache, $method], $args);
    }
    
    /**
     * Get the legacy cache instance (for direct access if needed)
     * 
     * @return \ZipPicks_Master_Critic_Cache_Manager
     */
    public function get_legacy_instance() {
        return $this->legacy_cache;
    }
}