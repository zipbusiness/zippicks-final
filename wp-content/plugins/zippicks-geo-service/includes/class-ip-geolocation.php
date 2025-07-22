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
     * 
     * Note: ip-api.com requires paid plan for HTTPS access
     * Using ipinfo.io as primary fallback for security
     */
    private $fallback_apis = [
        'ipinfo' => 'https://ipinfo.io/%s/json',
        'ipapi' => 'http://ip-api.com/json/%s', // WARNING: Insecure HTTP - use only as last resort
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
        // Try ipinfo.io first (HTTPS, more secure)
        $url = sprintf($this->fallback_apis['ipinfo'], $ip_address);
        
        $response = wp_remote_get($url, [
            'timeout' => 3,
            'headers' => [
                'User-Agent' => 'ZipPicks Geo Service/1.0',
            ],
        ]);
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data && isset($data['loc'])) {
                // Parse ipinfo.io response format
                list($latitude, $longitude) = explode(',', $data['loc']);
                return [
                    'latitude' => floatval($latitude),
                    'longitude' => floatval($longitude),
                    'city' => $data['city'] ?? null,
                    'state' => $data['region'] ?? null,
                    'zip_code' => $data['postal'] ?? null,
                    'country' => $data['country'] ?? null,
                    'accuracy' => 'city',
                    'accuracy_meters' => 10000, // Approximate
                    'source' => 'ip',
                ];
            }
        }
        
        // Only fall back to HTTP ip-api.com if HTTPS fails and log warning
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->warning('Falling back to insecure HTTP API for IP geolocation', [
                'ip' => $ip_address,
                'reason' => 'HTTPS ipinfo.io request failed'
            ]);
        }
        
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
        $remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // If remote address is not from a trusted proxy, return it directly
        if (!$this->is_trusted_proxy($remote_addr)) {
            return $remote_addr;
        }
        
        // Remote address is from trusted proxy, check forwarded headers
        // Priority order: CloudFlare, X-Forwarded-For, X-Real-IP, Client-IP
        
        // CloudFlare (only if remote is CloudFlare IP)
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ips = explode(',', $_SERVER['HTTP_CF_CONNECTING_IP']);
            $ip = trim($ips[0]);
            
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                if (function_exists('zippicks') && zippicks()->has('logger')) {
                    $logger = zippicks()->get('logger');
                    $logger->debug('Using CloudFlare connecting IP', [
                        'ip' => $ip,
                        'proxy' => $remote_addr
                    ]);
                }
                return $ip;
            }
        }
        
        // Other proxy headers
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For can contain multiple IPs
                $ips = explode(',', $_SERVER[$header]);
                
                // Get the first non-private IP
                foreach ($ips as $ip) {
                    $ip = trim($ip);
                    
                    // Validate IP and ensure it's not private/reserved
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        if (function_exists('zippicks') && zippicks()->has('logger')) {
                            $logger = zippicks()->get('logger');
                            $logger->debug('Using forwarded IP from trusted proxy', [
                                'ip' => $ip,
                                'header' => $header,
                                'proxy' => $remote_addr
                            ]);
                        }
                        return $ip;
                    }
                }
            }
        }
        
        // No valid forwarded IP found, return the proxy IP
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->warning('Trusted proxy did not provide valid forwarded IP', [
                'proxy' => $remote_addr
            ]);
        }
        
        return $remote_addr;
    }
    
    /**
     * Check if an IP is from a trusted proxy
     * 
     * @param string $ip IP address to check
     * @return bool
     */
    private function is_trusted_proxy($ip) {
        // Get trusted proxy configuration
        $trusted_proxies = get_option('zippicks_geo_trusted_proxies', $this->get_default_trusted_proxies());
        
        if (empty($trusted_proxies)) {
            return false;
        }
        
        // Check each trusted proxy/range
        foreach ($trusted_proxies as $proxy) {
            if ($this->ip_in_range($ip, $proxy)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is within a range (supports CIDR notation)
     * 
     * @param string $ip IP address to check
     * @param string $range IP or CIDR range
     * @return bool
     */
    private function ip_in_range($ip, $range) {
        // Direct IP match
        if ($ip === $range) {
            return true;
        }
        
        // CIDR notation check
        if (strpos($range, '/') !== false) {
            list($subnet, $mask) = explode('/', $range);
            
            // Check if IPv6
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && 
                filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                return $this->ipv6_in_range($ip, $subnet, $mask);
            }
            
            // IPv4 handling
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            
            if ($ip_long === false || $subnet_long === false) {
                return false;
            }
            
            $mask = (int)$mask;
            $ip_binary = sprintf('%032b', $ip_long);
            $subnet_binary = sprintf('%032b', $subnet_long);
            
            // Compare network portions
            return substr($ip_binary, 0, $mask) === substr($subnet_binary, 0, $mask);
        }
        
        return false;
    }
    
    /**
     * Check if IPv6 is within a range
     * 
     * @param string $ip IPv6 address
     * @param string $subnet IPv6 subnet
     * @param int $mask Subnet mask
     * @return bool
     */
    private function ipv6_in_range($ip, $subnet, $mask) {
        $ip_bin = inet_pton($ip);
        $subnet_bin = inet_pton($subnet);
        
        if ($ip_bin === false || $subnet_bin === false) {
            return false;
        }
        
        $mask = (int)$mask;
        
        // Compare bit by bit
        for ($i = 0; $i < $mask; $i++) {
            $byte_index = intval($i / 8);
            $bit_index = $i % 8;
            
            $ip_bit = (ord($ip_bin[$byte_index]) >> (7 - $bit_index)) & 1;
            $subnet_bit = (ord($subnet_bin[$byte_index]) >> (7 - $bit_index)) & 1;
            
            if ($ip_bit !== $subnet_bit) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Get default trusted proxy ranges
     * 
     * @return array
     */
    public function get_default_trusted_proxies() {
        return [
            // Cloudflare IPv4 ranges (as of 2024)
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
            
            // Cloudflare IPv6 ranges
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32',
        ];
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
        
        // First, download the checksum file
        $checksum_params = $params;
        $checksum_params['suffix'] = 'tar.gz.sha256';
        $checksum_url = add_query_arg($checksum_params, $url);
        
        $checksum_response = wp_remote_get($checksum_url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'ZipPicks Geo Service/1.0',
            ],
        ]);
        
        if (is_wp_error($checksum_response)) {
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->error('Failed to download MaxMind checksum', [
                    'error' => $checksum_response->get_error_message(),
                ]);
            }
            return false;
        }
        
        $expected_checksum = trim(wp_remote_retrieve_body($checksum_response));
        if (empty($expected_checksum)) {
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->error('MaxMind checksum file is empty');
            }
            return false;
        }
        
        // Extract just the hash from the checksum file (format: "hash  filename")
        $checksum_parts = explode(' ', $expected_checksum);
        $expected_hash = $checksum_parts[0];
        
        // Download the database file
        $temp_file = download_url($download_url);
        
        if (is_wp_error($temp_file)) {
            return false;
        }
        
        // Verify checksum
        $actual_hash = hash_file('sha256', $temp_file);
        
        if ($actual_hash !== $expected_hash) {
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->error('MaxMind database checksum verification failed', [
                    'expected' => $expected_hash,
                    'actual' => $actual_hash,
                    'code' => ZIPPICKS_GEO_ERRORS['GEO006'],
                ]);
            }
            
            // Delete potentially compromised file
            unlink($temp_file);
            return false;
        }
        
        // Log successful verification
        if (function_exists('zippicks') && zippicks()->has('logger')) {
            $logger = zippicks()->get('logger');
            $logger->info('MaxMind database checksum verified successfully', [
                'checksum' => $expected_hash,
            ]);
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
                
                // Clean up with explicit base directory for safety
                $this->cleanup_directory($temp_dir, sys_get_temp_dir());
                unlink($temp_file);
                
                // Update last update timestamp
                update_option('zippicks_geo_maxmind_last_update', current_time('timestamp'));
                
                // Reinitialize reader
                $this->init_maxmind();
                
                if (function_exists('zippicks') && zippicks()->has('logger')) {
                    $logger = zippicks()->get('logger');
                    $logger->info('MaxMind database updated successfully', [
                        'file' => $target,
                        'checksum' => $expected_hash,
                    ]);
                }
                
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
     * Recursively remove directory with path validation
     * 
     * @param string $dir Directory to remove
     * @param string $allowed_base Base directory that $dir must be within (optional)
     * @return bool True if cleaned up, false if validation failed
     */
    private function cleanup_directory($dir, $allowed_base = null) {
        // If no base provided, use system temp directory
        if ($allowed_base === null) {
            $allowed_base = sys_get_temp_dir();
        }
        
        // Normalize paths for comparison
        $real_dir = realpath($dir);
        $real_base = realpath($allowed_base);
        
        // Validate paths exist
        if ($real_dir === false || $real_base === false) {
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->warning('Invalid path provided to cleanup_directory', [
                    'dir' => $dir,
                    'allowed_base' => $allowed_base,
                ]);
            }
            return false;
        }
        
        // Ensure directory is within allowed base
        if (strpos($real_dir, $real_base) !== 0) {
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->error('Attempted to clean directory outside allowed path', [
                    'dir' => $real_dir,
                    'allowed_base' => $real_base,
                    'code' => ZIPPICKS_GEO_ERRORS['GEO007'],
                ]);
            }
            return false;
        }
        
        // Additional safety check - ensure it's a MaxMind temp directory
        if (strpos($real_dir, '/maxmind_') === false && strpos($real_dir, '\\maxmind_') === false) {
            if (function_exists('zippicks') && zippicks()->has('logger')) {
                $logger = zippicks()->get('logger');
                $logger->error('Attempted to clean non-MaxMind directory', [
                    'dir' => $real_dir,
                ]);
            }
            return false;
        }
        
        if (!is_dir($dir)) {
            return true;
        }
        
        // Proceed with recursive deletion
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                // Recursive call maintains the same allowed_base
                $this->cleanup_directory($path, $allowed_base);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
        return true;
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