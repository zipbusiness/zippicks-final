<?php
/**
 * ZipPicks Geo Service Configuration
 * 
 * Sets up proper API connection to ZipBusiness API
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the ZipBusiness API configuration
 * 
 * @return array API configuration
 */
function get_api_config() {
    // Production API endpoint
    $api_url = 'https://zipbusiness-api.onrender.com';
    
    // Check for environment-specific overrides
    if (defined('ZIPPICKS_API_URL')) {
        $api_url = ZIPPICKS_API_URL;
    } elseif (defined('WP_ENV') && WP_ENV === 'development') {
        // Local development override
        $api_url = 'http://localhost:8000';
    }
    
    // Get API key from various sources
    $api_key = '';
    if (defined('ZIPPICKS_API_KEY')) {
        $api_key = ZIPPICKS_API_KEY;
    } elseif (get_option('zippicks_api_key')) {
        $api_key = get_option('zippicks_api_key');
    }
    
    return [
        'api_url' => rtrim($api_url, '/'),
        'api_key' => $api_key,
        'timeout' => 30,
        'verify_ssl' => true,
    ];
}

/**
 * Make a request to the ZipBusiness API
 * 
 * @param string $endpoint The API endpoint
 * @param array $args Request arguments
 * @return array|WP_Error Response data or error
 */
function make_api_request($endpoint, $args = []) {
    $config = get_api_config();
    
    if (empty($config['api_key'])) {
        return new \WP_Error('no_api_key', 'ZipPicks API key not configured');
    }
    
    $url = $config['api_url'] . $endpoint;
    
    $defaults = [
        'timeout' => $config['timeout'],
        'headers' => [
            'Authorization' => 'Bearer ' . $config['api_key'],
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ],
        'sslverify' => $config['verify_ssl'],
    ];
    
    $args = wp_parse_args($args, $defaults);
    
    // Make the request
    $response = wp_remote_request($url, $args);
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new \WP_Error('invalid_json', 'Invalid JSON response from API');
    }
    
    return $data;
}