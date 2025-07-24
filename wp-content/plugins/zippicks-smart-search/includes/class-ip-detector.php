<?php
/**
 * IP Detector Utility
 * 
 * Provides secure and consistent IP detection across the plugin
 * with trusted proxy validation to prevent IP spoofing
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class IP_Detector {
    
    /**
     * Get client IP address with trusted proxy validation
     * 
     * @return string
     */
    public static function get_client_ip() {
        // Default to REMOTE_ADDR
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        
        // Get trusted proxy IPs from configuration
        $trusted_proxies = self::get_trusted_proxies();
        
        // Only check forwarded headers if request is from a trusted proxy
        if (!empty($trusted_proxies) && self::is_trusted_proxy($ip, $trusted_proxies)) {
            // Check headers in order of preference
            $forwarded_headers = [
                'HTTP_CF_CONNECTING_IP',     // Cloudflare
                'HTTP_X_REAL_IP',           // Nginx proxy
                'HTTP_X_FORWARDED_FOR',     // Standard proxy header
                'HTTP_CLIENT_IP',           // Some proxies
            ];
            
            foreach ($forwarded_headers as $header) {
                if (!empty($_SERVER[$header])) {
                    $forwarded_ip = $_SERVER[$header];
                    
                    // Handle comma-separated list (X-Forwarded-For can have multiple IPs)
                    if (strpos($forwarded_ip, ',') !== false) {
                        $ips = array_map('trim', explode(',', $forwarded_ip));
                        // Get the first IP (original client)
                        $forwarded_ip = $ips[0];
                    }
                    
                    // Validate IP format
                    if (filter_var($forwarded_ip, FILTER_VALIDATE_IP)) {
                        return sanitize_text_field($forwarded_ip);
                    }
                }
            }
        }
        
        return sanitize_text_field($ip);
    }
    
    /**
     * Get list of trusted proxy IPs
     * 
     * @return array
     */
    private static function get_trusted_proxies() {
        // Get from options with filter for customization
        $trusted_proxies = apply_filters('zippicks_trusted_proxy_ips', 
            get_option('zippicks_trusted_proxies', [])
        );
        
        // Ensure it's an array
        if (!is_array($trusted_proxies)) {
            $trusted_proxies = [];
        }
        
        // Add Cloudflare IPs if enabled
        if (get_option('zippicks_trust_cloudflare', false)) {
            // Get dynamically fetched and cached Cloudflare IP ranges
            $cloudflare_ips = Cloudflare_IP_Manager::get_ip_ranges();
            $trusted_proxies = array_merge($trusted_proxies, $cloudflare_ips);
        }
        
        // Add common local/private ranges if in development
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $local_ranges = [
                '127.0.0.1',
                '::1',
                '10.0.0.0/8',
                '172.16.0.0/12',
                '192.168.0.0/16'
            ];
            
            $trusted_proxies = array_merge($trusted_proxies, $local_ranges);
        }
        
        return array_unique($trusted_proxies);
    }
    
    /**
     * Check if an IP is from a trusted proxy
     * 
     * @param string $ip IP address to check
     * @param array $trusted_proxies List of trusted proxy IPs/ranges
     * @return bool
     */
    private static function is_trusted_proxy($ip, $trusted_proxies) {
        foreach ($trusted_proxies as $proxy) {
            if (strpos($proxy, '/') !== false) {
                // CIDR range
                if (self::ip_in_range($ip, $proxy)) {
                    return true;
                }
            } else {
                // Single IP
                if ($ip === $proxy) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Check if IP is in CIDR range
     * 
     * @param string $ip IP address to check
     * @param string $cidr CIDR range
     * @return bool
     */
    private static function ip_in_range($ip, $cidr) {
        list($subnet, $bits) = explode('/', $cidr);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && 
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnet_long &= $mask;
            
            return ($ip_long & $mask) == $subnet_long;
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && 
                  filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6
            $ip_bin = inet_pton($ip);
            $subnet_bin = inet_pton($subnet);
            
            $bytes_to_check = intval($bits / 8);
            $bits_to_check = $bits % 8;
            
            // Check full bytes
            for ($i = 0; $i < $bytes_to_check; $i++) {
                if ($ip_bin[$i] !== $subnet_bin[$i]) {
                    return false;
                }
            }
            
            // Check remaining bits
            if ($bits_to_check > 0) {
                $mask = 0xFF << (8 - $bits_to_check);
                return (ord($ip_bin[$bytes_to_check]) & $mask) === 
                       (ord($subnet_bin[$bytes_to_check]) & $mask);
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get anonymized IP for logging
     * 
     * @param string $ip IP address to anonymize
     * @return string
     */
    public static function anonymize_ip($ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4: Zero out last octet
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6: Zero out last 64 bits
            $packed = inet_pton($ip);
            for ($i = 8; $i < 16; $i++) {
                $packed[$i] = "\x00";
            }
            return inet_ntop($packed);
        }
        
        return '0.0.0.0';
    }
}