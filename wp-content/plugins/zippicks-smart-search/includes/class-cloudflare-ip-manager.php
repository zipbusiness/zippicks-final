<?php
/**
 * Cloudflare IP Manager
 * 
 * Fetches and caches Cloudflare IP ranges dynamically
 * 
 * @package ZipPicks_Smart_Search
 */

namespace ZipPicks\SmartSearch;

class Cloudflare_IP_Manager {
    
    /**
     * Option name for cached IP ranges
     */
    const OPTION_NAME = 'zippicks_cloudflare_ips';
    
    /**
     * Option name for last update timestamp
     */
    const OPTION_TIMESTAMP = 'zippicks_cloudflare_ips_updated';
    
    /**
     * Cloudflare IPv4 ranges URL
     */
    const IPV4_URL = 'https://www.cloudflare.com/ips-v4';
    
    /**
     * Cloudflare IPv6 ranges URL
     */
    const IPV6_URL = 'https://www.cloudflare.com/ips-v6';
    
    /**
     * Cache duration in seconds (24 hours)
     */
    const CACHE_DURATION = 86400;
    
    /**
     * WP-Cron hook name
     */
    const CRON_HOOK = 'zippicks_update_cloudflare_ips';
    
    /**
     * Get Cloudflare IP ranges
     * 
     * @return array
     */
    public static function get_ip_ranges() {
        $cached_ips = get_option(self::OPTION_NAME, []);
        $last_updated = get_option(self::OPTION_TIMESTAMP, 0);
        
        // Check if cache is still valid
        if (!empty($cached_ips) && (time() - $last_updated) < self::CACHE_DURATION) {
            return $cached_ips;
        }
        
        // Fetch fresh IP ranges
        $fresh_ips = self::fetch_ip_ranges();
        
        // If fetch was successful, update cache
        if (!empty($fresh_ips)) {
            update_option(self::OPTION_NAME, $fresh_ips, false);
            update_option(self::OPTION_TIMESTAMP, time(), false);
            return $fresh_ips;
        }
        
        // If fetch failed but we have cached data, use it
        if (!empty($cached_ips)) {
            return $cached_ips;
        }
        
        // Fallback to hardcoded IPs if everything else fails
        return self::get_fallback_ips();
    }
    
    /**
     * Fetch IP ranges from Cloudflare
     * 
     * @return array
     */
    private static function fetch_ip_ranges() {
        $ip_ranges = [];
        
        // Fetch IPv4 ranges
        $ipv4_response = wp_remote_get(self::IPV4_URL, [
            'timeout' => 10,
            'sslverify' => true,
            'user-agent' => 'ZipPicks Smart Search/' . ZIPPICKS_SEARCH_VERSION
        ]);
        
        if (!is_wp_error($ipv4_response) && wp_remote_retrieve_response_code($ipv4_response) === 200) {
            $ipv4_body = wp_remote_retrieve_body($ipv4_response);
            $ipv4_ranges = array_filter(array_map('trim', explode("\n", $ipv4_body)));
            
            // Validate each IP range
            foreach ($ipv4_ranges as $range) {
                if (self::validate_cidr($range)) {
                    $ip_ranges[] = $range;
                }
            }
        }
        
        // Fetch IPv6 ranges
        $ipv6_response = wp_remote_get(self::IPV6_URL, [
            'timeout' => 10,
            'sslverify' => true,
            'user-agent' => 'ZipPicks Smart Search/' . ZIPPICKS_SEARCH_VERSION
        ]);
        
        if (!is_wp_error($ipv6_response) && wp_remote_retrieve_response_code($ipv6_response) === 200) {
            $ipv6_body = wp_remote_retrieve_body($ipv6_response);
            $ipv6_ranges = array_filter(array_map('trim', explode("\n", $ipv6_body)));
            
            // Validate each IP range
            foreach ($ipv6_ranges as $range) {
                if (self::validate_cidr($range, true)) {
                    $ip_ranges[] = $range;
                }
            }
        }
        
        // Log the update
        if (!empty($ip_ranges)) {
            error_log(sprintf(
                '[ZipPicks] Successfully fetched %d Cloudflare IP ranges',
                count($ip_ranges)
            ));
        } else {
            error_log('[ZipPicks] Failed to fetch Cloudflare IP ranges');
        }
        
        return $ip_ranges;
    }
    
    /**
     * Validate CIDR notation
     * 
     * @param string $cidr
     * @param bool $ipv6
     * @return bool
     */
    private static function validate_cidr($cidr, $ipv6 = false) {
        $parts = explode('/', $cidr);
        
        if (count($parts) !== 2) {
            return false;
        }
        
        list($ip, $bits) = $parts;
        
        // Validate IP address
        $flag = $ipv6 ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;
        if (!filter_var($ip, FILTER_VALIDATE_IP, $flag)) {
            return false;
        }
        
        // Validate subnet bits
        $max_bits = $ipv6 ? 128 : 32;
        $bits = intval($bits);
        
        return $bits >= 0 && $bits <= $max_bits;
    }
    
    /**
     * Get fallback IP ranges
     * 
     * @return array
     */
    private static function get_fallback_ips() {
        // Last known Cloudflare IP ranges as fallback
        return [
            // IPv4 ranges
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
            // IPv6 ranges
            '2400:cb00::/32',
            '2606:4700::/32',
            '2803:f800::/32',
            '2405:b500::/32',
            '2405:8100::/32',
            '2a06:98c0::/29',
            '2c0f:f248::/32'
        ];
    }
    
    /**
     * Update IP ranges (called by WP-Cron)
     */
    public static function update_ip_ranges() {
        $fresh_ips = self::fetch_ip_ranges();
        
        if (!empty($fresh_ips)) {
            update_option(self::OPTION_NAME, $fresh_ips, false);
            update_option(self::OPTION_TIMESTAMP, time(), false);
            
            // Also update the last cron run time
            update_option('zippicks_cloudflare_ips_last_cron', time(), false);
        }
    }
    
    /**
     * Schedule cron job
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CRON_HOOK);
        }
    }
    
    /**
     * Unschedule cron job
     */
    public static function unschedule_cron() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }
    
    /**
     * Force update IP ranges (admin action)
     * 
     * @return bool
     */
    public static function force_update() {
        $fresh_ips = self::fetch_ip_ranges();
        
        if (!empty($fresh_ips)) {
            update_option(self::OPTION_NAME, $fresh_ips, false);
            update_option(self::OPTION_TIMESTAMP, time(), false);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get status information
     * 
     * @return array
     */
    public static function get_status() {
        $cached_ips = get_option(self::OPTION_NAME, []);
        $last_updated = get_option(self::OPTION_TIMESTAMP, 0);
        $last_cron = get_option('zippicks_cloudflare_ips_last_cron', 0);
        $next_cron = wp_next_scheduled(self::CRON_HOOK);
        
        return [
            'ip_count' => count($cached_ips),
            'last_updated' => $last_updated,
            'last_updated_human' => $last_updated ? human_time_diff($last_updated) . ' ago' : 'Never',
            'cache_valid' => !empty($cached_ips) && (time() - $last_updated) < self::CACHE_DURATION,
            'last_cron_run' => $last_cron,
            'last_cron_human' => $last_cron ? human_time_diff($last_cron) . ' ago' : 'Never',
            'next_cron' => $next_cron,
            'next_cron_human' => $next_cron ? human_time_diff(time(), $next_cron) . ' from now' : 'Not scheduled'
        ];
    }
}