<?php
/**
 * AI Service Wrapper
 * 
 * Provides namespaced access to the legacy AI service
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
 * AIService class
 * 
 * Acts as a bridge between the modern namespaced code and the legacy AI service
 */
class AIService {
    
    /**
     * Legacy AI service instance
     * 
     * @var \ZipPicks_Master_Critic_AI_Service
     */
    private $legacy_ai_service;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load the legacy AI service if not already loaded
        if (!class_exists('ZipPicks_Master_Critic_AI_Service')) {
            $ai_file = ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR . 'includes/class-ai-service.php';
            if (file_exists($ai_file)) {
                require_once $ai_file;
            } else {
                throw new \RuntimeException('AI Service class file not found: ' . $ai_file);
            }
        }
        
        // Instantiate the legacy AI service
        $this->legacy_ai_service = new \ZipPicks_Master_Critic_AI_Service();
    }
    
    /**
     * Proxy method calls to the legacy AI service
     * 
     * @param string $method Method name
     * @param array $args Method arguments
     * @return mixed
     */
    public function __call($method, $args) {
        if (!method_exists($this->legacy_ai_service, $method)) {
            throw new \BadMethodCallException("Method {$method} does not exist on AI Service");
        }
        
        return call_user_func_array([$this->legacy_ai_service, $method], $args);
    }
    
    /**
     * Get the legacy AI service instance (for direct access if needed)
     * 
     * @return \ZipPicks_Master_Critic_AI_Service
     */
    public function get_legacy_instance() {
        return $this->legacy_ai_service;
    }
}