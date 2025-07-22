<?php
/**
 * IP Geolocation Class
 * 
 * Handles IP-based location detection using MaxMind GeoLite2
 * or fallback services
 * 
 * @package ZipPicks_Geo_Service
 */

namespace ZipPicks\Geo;

class IP_Geolocation {
    
    /**
     * GeoIP2 reader instance
     * @var \GeoIp2\Database\Reader|null
     */
    private $reader;
    
    /**
     * Cache TTL in seconds
     */
    private $cache_ttl = 3600; // 1 hour
    
    /**
     * Fallback IP geolocation API endpoints
     */
    private $fallback_apis = [
        'ipapi' => 'http://ip-api.com/json/%s',
        'ipinfo' => 'https://ipinfo.io/%s/json',
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_maxmind();
    }
    
    /**
     * Initialize MaxMind GeoLite2 database
     */
    private function init_maxmind() {
        $db_path = ZIPPICKS_GEO_PLUGIN_DIR . 'geoip/GeoLite2-City.mmdb';
        
        // Check if database file exists
        if (!file_exists($db_path)) {
            // Log missing database
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->warning('MaxMind GeoLite2 database not found', [
                    'path' => $db_path,
                ]);
            }
            return;
        }
        
        // Check if GeoIP2 library is available
        if (!class_exists('\GeoIp2\Database\Reader')) {
            // Try to load via Composer autoload if available
            $autoload = ZIPPICKS_GEO_PLUGIN_DIR . 'vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }
        }
        
        // Initialize reader if class is available
        if (class_exists('\GeoIp2\Database\Reader')) {
            try {
                $this->reader = new \GeoIp2\Database\Reader($db_path);
            } catch (\Exception $e) {
                if (function_exists('zippicks') && zippicks()->has('logger')) {
                    $logger = zippicks()->get('logger');
                    $logger->error('Failed to initialize MaxMind reader', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }
    
    /**
     * Get location by IP address
     * 
     * @param string|null $ip_address
     * @return array|null
     */
    public function get_location_by_ip($ip_address = null) {
        // Get IP address if not provided
        if (!$ip_address) {
            $ip_address = $this->get_client_ip();
        }
        
        // Skip private/local IPs
        if ($this->is_private_ip($ip_address)) {
            return null;
        }
        
        // Check cache first
        $cache_key = 'geo_ip_' . md5($ip_address);
        $cached = wp_cache_get($cache_key, 'zippicks_geo');
        if ($cached !== false) {
            return $cached;
        }
        
        // Try MaxMind first
        if ($this->reader) {
            $location = $this->get_maxmind_location($ip_address);
            if ($location) {
                wp_cache_set($cache_key, $location, 'zippicks_geo', $this->cache_ttl);
                return $location;
            }
        }
        
        // Try fallback APIs
        $location = $this->get_fallback_location($ip_address);
        if ($location) {
            wp_cache_set($cache_key, $location, 'zippicks_geo', $this->cache_ttl);
            return $location;
        }
        
        return null;
    }
    
    /**
     * Get location using MaxMind database
     * 
     * @param string $ip_address
     * @return array|null
     */
    private function get_maxmind_location($ip_address) {
        try {
            $record = $this->reader->city($ip_address);
            
            return [
                'latitude' => $record->location->latitude,
                'longitude' => $record->location->longitude,
                'city' => $record->city->name,
                'state' => $record->mostSpecificSubdivision->isoCode,
                'zip_code' => $record->postal->code,
                'country' => $record->country->isoCode,
                'accuracy' => 'city',
                'accuracy_meters' => ($record->location->accuracyRadius ?? 50) * 1000,
                'source' => 'ip',
            ];
        } catch (\GeoIp2\Exception\AddressNotFoundException $e) {
            // IP not found in database
            return null;
        } catch (\Exception $e) {
            // Log other errors
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->error('MaxMind lookup failed', [
                    'ip' => $ip_address,
                    'error' => $e->getMessage(),
                ]);
            }
            return null;
        }
    }
    
    /**
     * Get location using fallback API
     * 
     * @param string $ip_address
     * @return array|null
     */
    private function get_fallback_location($ip_address) {
        // Try ip-api.com first (free, no key required)
        $url = sprintf($this->fallback_apis['ipapi'], $ip_address);
        
        $response = wp_remote_get($url, [
            'timeout' => 3,
            'headers' => [
                'User-Agent' => 'ZipPicks Geo Service/1.0',
            ],
        ]);
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || $data['status'] !== 'success') {
            return null;
        }
        
        return [
            'latitude' => $data['lat'],
            'longitude' => $data['lon'],
            'city' => $data['city'],
            'state' => $data['region'],
            'zip_code' => $data['zip'] ?? null,
            'country' => $data['countryCode'],
            'accuracy' => 'city',
            'accuracy_meters' => 10000, // Approximate
            'source' => 'ip',
        ];
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function get_client_ip() {
        // CloudFlare
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ips = explode(',', $_SERVER['HTTP_CF_CONNECTING_IP']);
            return trim($ips[0]);
        }
        
        // Load balancer/proxy headers
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Direct connection
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Check if IP is private/local
     * 
     * @param string $ip
     * @return bool
     */
    private function is_private_ip($ip) {
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
    
    /**
     * Download/update MaxMind database
     * 
     * @param string $license_key MaxMind license key
     * @return bool
     */
    public function update_database($license_key = '') {
        if (empty($license_key)) {
            $license_key = get_option('zippicks_geo_maxmind_key', '');
        }
        
        if (empty($license_key)) {
            return false;
        }
        
        $url = 'https://download.maxmind.com/app/geoip_download';
        $params = [
            'edition_id' => 'GeoLite2-City',
            'license_key' => $license_key,
            'suffix' => 'tar.gz',
        ];
        
        $download_url = add_query_arg($params, $url);
        $temp_file = download_url($download_url);
        
        if (is_wp_error($temp_file)) {
            return false;
        }
        
        // Extract and move database file
        try {
            $archive = new \PharData($temp_file);
            $db_dir = ZIPPICKS_GEO_PLUGIN_DIR . 'geoip/';
            
            // Create directory if not exists
            if (!file_exists($db_dir)) {
                wp_mkdir_p($db_dir);
            }
            
            // Extract to temp directory
            $temp_dir = sys_get_temp_dir() . '/maxmind_' . uniqid();
            $archive->extractTo($temp_dir);
            
            // Find .mmdb file
            $files = glob($temp_dir . '/*/*.mmdb');
            if (!empty($files)) {
                // Move to plugin directory
                $target = $db_dir . 'GeoLite2-City.mmdb';
                rename($files[0], $target);
                
                // Clean up
                $this->cleanup_directory($temp_dir);
                unlink($temp_file);
                
                // Reinitialize reader
                $this->init_maxmind();
                
                return true;
            }
        } catch (\Exception $e) {
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->error('Failed to extract MaxMind database', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        return false;
    }
    
    /**
     * Recursively remove directory
     * 
     * @param string $dir
     */
    private function cleanup_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->cleanup_directory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
    
    /**
     * Get database info
     * 
     * @return array
     */
    public function get_database_info() {
        $info = [
            'available' => false,
            'path' => ZIPPICKS_GEO_PLUGIN_DIR . 'geoip/GeoLite2-City.mmdb',
            'size' => 0,
            'modified' => null,
        ];
        
        if (file_exists($info['path'])) {
            $info['available'] = true;
            $info['size'] = filesize($info['path']);
            $info['modified'] = filemtime($info['path']);
        }
        
        return $info;
    }
}