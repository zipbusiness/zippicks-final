<?php
/**
 * JSON Render Strategy
 * 
 * Implements JSON rendering for vibes (API/headless mode)
 * 
 * @package ZipPicksVibes\Services
 * @since 2.0.0
 */

namespace ZipPicksVibes\Services;

use ZipPicksVibes\Models\Vibe;

class JsonRenderStrategy implements RenderStrategyInterface {
    
    /**
     * Whether to include secure/obfuscated data
     * 
     * @var bool
     */
    private bool $secure;
    
    /**
     * Constructor
     * 
     * @param bool $secure Whether to use secure/obfuscated output
     */
    public function __construct(bool $secure = true) {
        $this->secure = $secure;
    }
    
    /**
     * Render a list of vibes
     * 
     * @param Vibe[] $vibes Array of Vibe models
     * @param array $options Rendering options
     * @return string JSON string
     */
    public function renderList(array $vibes, array $options = []): string {
        $data = [
            'success' => true,
            'data' => [],
            'meta' => [
                'total' => count($vibes),
                'timestamp' => time(),
                'secure' => $this->secure
            ]
        ];
        
        foreach ($vibes as $vibe) {
            $data['data'][] = $this->secure 
                ? $vibe->toSecureApiArray() 
                : $vibe->toApiArray();
        }
        
        // Add pagination meta if provided
        if (isset($options['pagination'])) {
            $data['meta']['pagination'] = $options['pagination'];
        }
        
        // Add watermark/fingerprint
        if ($this->secure) {
            $data['meta']['fingerprint'] = $this->generateFingerprint();
        }
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Render a single vibe item
     * 
     * @param Vibe $vibe Vibe model
     * @param array $options Rendering options
     * @return string JSON string
     */
    public function renderItem(Vibe $vibe, array $options = []): string {
        $data = [
            'success' => true,
            'data' => $this->secure 
                ? $vibe->toSecureApiArray() 
                : $vibe->toApiArray(),
            'meta' => [
                'timestamp' => time(),
                'secure' => $this->secure
            ]
        ];
        
        // Add relationships if requested
        if (!empty($options['include'])) {
            $includes = is_array($options['include']) 
                ? $options['include'] 
                : explode(',', $options['include']);
                
            foreach ($includes as $include) {
                switch ($include) {
                    case 'categories':
                        if ($vibe->hasCategories()) {
                            $data['data']['relationships']['categories'] = $vibe->getCategories();
                        }
                        break;
                    case 'businesses':
                        // Would include business data when available
                        $data['data']['relationships']['businesses'] = [
                            'count' => $vibe->getBusinessCount()
                        ];
                        break;
                }
            }
        }
        
        // Add watermark/fingerprint
        if ($this->secure) {
            $data['meta']['fingerprint'] = $this->generateFingerprint();
        }
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Render empty state
     * 
     * @param string $message Empty state message
     * @param array $options Rendering options
     * @return string JSON string
     */
    public function renderEmptyState(string $message = '', array $options = []): string {
        if (empty($message)) {
            $message = 'No vibes found';
        }
        
        $data = [
            'success' => true,
            'data' => [],
            'meta' => [
                'total' => 0,
                'message' => $message,
                'timestamp' => time()
            ]
        ];
        
        // Add suggestions if provided
        if (!empty($options['suggestions'])) {
            $data['meta']['suggestions'] = $options['suggestions'];
        }
        
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    
    /**
     * Get content type for this strategy
     * 
     * @return string
     */
    public function getContentType(): string {
        return 'application/json';
    }
    
    /**
     * Generate fingerprint for tracking
     * 
     * @return string
     */
    private function generateFingerprint(): string {
        $data = [
            'timestamp' => time(),
            'user_id' => get_current_user_id(),
            'session' => session_id() ?: 'no-session',
            'ip' => $this->getUserIP()
        ];
        
        return 'ZP' . substr(hash('sha256', serialize($data) . wp_salt()), 0, 16);
    }
    
    /**
     * Get user IP address
     * 
     * @return string
     */
    private function getUserIP(): string {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = filter_var($_SERVER[$key], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                if ($ip !== false) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}