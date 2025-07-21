<?php
/**
 * Security Health Check
 * 
 * Checks security configuration and potential vulnerabilities
 * 
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\HealthCheck\Checks;

use ZipPicksVibes\HealthCheck\HealthCheckInterface;
use ZipPicksVibes\HealthCheck\HealthCheckResult;

/**
 * Class SecurityCheck
 * 
 * Enhanced security health check with standardized status reporting,
 * detailed diagnostics, and fallback guidance
 */
class SecurityCheck implements HealthCheckInterface {
    
    /**
     * Logger instance
     * 
     * @var mixed
     */
    private $logger;
    
    /**
     * Constructor
     * 
     * @param mixed $logger
     */
    public function __construct($logger = null) {
        $this->logger = $logger;
    }
    
    /**
     * Execute health check (legacy method)
     * 
     * @return HealthCheckResult
     */
    public function check(): HealthCheckResult {
        return $this->run();
    }
    
    /**
     * Execute enhanced security health check
     * 
     * @return HealthCheckResult
     */
    public function run(): HealthCheckResult {
        $startTime = microtime(true);
        $issues = [];
        $warnings = [];
        
        try {
            // Check SSL
            if (!is_ssl()) {
                $warnings[] = 'Site is not using SSL/HTTPS';
            }
            
            // Check debug mode
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $warnings[] = 'WordPress debug mode is enabled';
            }
            
            // Check file permissions
            $fileChecks = $this->checkFilePermissions();
            if (!empty($fileChecks['issues'])) {
                $issues = array_merge($issues, $fileChecks['issues']);
            }
            if (!empty($fileChecks['warnings'])) {
                $warnings = array_merge($warnings, $fileChecks['warnings']);
            }
            
            // Check security headers
            $headerChecks = $this->checkSecurityHeaders();
            if (!empty($headerChecks)) {
                $warnings = array_merge($warnings, $headerChecks);
            }
            
            // Check for recent security incidents
            $incidents = $this->checkSecurityIncidents();
            if ($incidents['critical'] > 0) {
                $issues[] = sprintf('Found %d critical security incidents in the last 24 hours', $incidents['critical']);
            }
            
            // Check API key security
            $apiKeyIssues = $this->checkApiKeySecurity();
            if (!empty($apiKeyIssues)) {
                $issues = array_merge($issues, $apiKeyIssues);
            }
            
            // Determine overall status
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            // Enhanced status determination with detailed diagnostics
            if (!empty($issues)) {
                $result = HealthCheckResult::fail(
                    $this->getName(),
                    'Critical security vulnerabilities detected',
                    [
                        'status' => HealthCheckResult::FAIL,
                        'issues' => $issues,
                        'warnings' => $warnings,
                        'incidents' => $incidents,
                        'recommendations' => $this->getSecurityRecommendations($issues, $warnings),
                        'fallback_guidance' => 'Immediate action required: Review and fix security issues',
                        'severity_level' => 'critical',
                        'check_category' => 'security',
                        'audit_details' => $this->getAuditDetails()
                    ],
                    $executionTime
                );
                
                // Enhanced logging for critical security failures
                if ($this->logger) {
                    $this->logger->critical('Critical security issues detected', [
                        'check_id' => $result->getCheckId(),
                        'issues_count' => count($issues),
                        'issues' => $issues,
                        'incidents' => $incidents,
                        'timestamp' => date('Y-m-d H:i:s')
                    ]);
                }
                
                return $result;
            } elseif (!empty($warnings)) {
                return HealthCheckResult::warn(
                    $this->getName(),
                    'Security configuration needs attention',
                    [
                        'status' => HealthCheckResult::WARN,
                        'warnings' => $warnings,
                        'incidents' => $incidents,
                        'recommendations' => $this->getSecurityRecommendations([], $warnings),
                        'fallback_guidance' => 'Review warnings and implement security best practices',
                        'severity_level' => 'medium',
                        'check_category' => 'security',
                        'audit_details' => $this->getAuditDetails()
                    ],
                    $executionTime
                );
            }
            
            return HealthCheckResult::pass(
                $this->getName(),
                'Security configuration is optimal',
                [
                    'status' => HealthCheckResult::PASS,
                    'ssl_enabled' => is_ssl(),
                    'debug_mode' => defined('WP_DEBUG') ? WP_DEBUG : false,
                    'incidents' => $incidents,
                    'security_score' => $this->calculateSecurityScore(),
                    'last_audit' => date('Y-m-d H:i:s'),
                    'check_category' => 'security',
                    'audit_details' => $this->getAuditDetails()
                ],
                $executionTime
            );
            
        } catch (\Exception $e) {
            $result = HealthCheckResult::fail(
                $this->getName(),
                'Security check failed with exception: ' . $e->getMessage(),
                [
                    'status' => HealthCheckResult::FAIL,
                    'exception' => get_class($e),
                    'error_message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'fallback_guidance' => 'Check server logs and ensure security monitoring is functioning',
                    'check_category' => 'security'
                ],
                (microtime(true) - $startTime) * 1000
            );
            
            // Enhanced exception logging
            if ($this->logger) {
                $this->logger->error('Security health check exception', [
                    'check_id' => $result->getCheckId(),
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
            
            return $result;
        }
    }
    
    /**
     * Get check name
     * 
     * @return string
     */
    public function getName(): string {
        return 'security';
    }
    
    /**
     * Get check description
     * 
     * @return string
     */
    public function getDescription(): string {
        return 'Checks security configuration and potential vulnerabilities';
    }
    
    /**
     * Get check priority
     * 
     * @return int
     */
    public function getPriority(): int {
        return 95;
    }
    
    /**
     * Whether this check is critical
     * 
     * @return bool
     */
    public function isCritical(): bool {
        return true;
    }
    
    /**
     * Get check category for aggregation
     * 
     * @return string
     */
    public function getCategory(): string {
        return 'security';
    }
    
    /**
     * Get estimated execution duration
     * 
     * @return int
     */
    public function getEstimatedDuration(): int {
        return 500; // 500ms estimated
    }
    
    /**
     * Check if monitoring is enabled
     * 
     * @return bool
     */
    public function isMonitoringEnabled(): bool {
        return true;
    }
    
    /**
     * Check file permissions
     * 
     * @return array
     */
    private function checkFilePermissions(): array {
        $issues = [];
        $warnings = [];
        
        // Check wp-config.php
        $wpConfigPath = ABSPATH . 'wp-config.php';
        if (!file_exists($wpConfigPath)) {
            $wpConfigPath = dirname(ABSPATH) . '/wp-config.php';
        }
        
        if (file_exists($wpConfigPath)) {
            $perms = fileperms($wpConfigPath);
            $octal = substr(sprintf('%o', $perms), -3);
            if ($octal !== '644' && $octal !== '640') {
                $warnings[] = sprintf('wp-config.php has insecure permissions: %s (should be 644 or 640)', $octal);
            }
        }
        
        // Check plugin directory
        $pluginDir = dirname(dirname(dirname(__FILE__)));
        if (is_writable($pluginDir)) {
            $dirPerms = fileperms($pluginDir);
            $dirOctal = substr(sprintf('%o', $dirPerms), -3);
            if ($dirOctal === '777') {
                $issues[] = 'Plugin directory has world-writable permissions (777)';
            }
        }
        
        return [
            'issues' => $issues,
            'warnings' => $warnings
        ];
    }
    
    /**
     * Check security headers
     * 
     * @return array
     */
    private function checkSecurityHeaders(): array {
        $warnings = [];
        $headers = headers_list();
        
        $requiredHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => ['DENY', 'SAMEORIGIN'],
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => ['no-referrer', 'strict-origin-when-cross-origin']
        ];
        
        foreach ($requiredHeaders as $header => $expectedValues) {
            $found = false;
            foreach ($headers as $sentHeader) {
                if (stripos($sentHeader, $header . ':') === 0) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $warnings[] = sprintf('Missing security header: %s', $header);
            }
        }
        
        return $warnings;
    }
    
    /**
     * Check security incidents
     * 
     * @return array
     */
    private function checkSecurityIncidents(): array {
        global $wpdb;
        
        $stats = [
            'total' => 0,
            'critical' => 0,
            'high' => 0,
            'medium' => 0,
            'low' => 0
        ];
        
        try {
            $table = $wpdb->prefix . 'zippicks_security_log';
            
            // Check if table exists
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                $incidents = $wpdb->get_results($wpdb->prepare(
                    "SELECT severity, COUNT(*) as count 
                     FROM $table 
                     WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     GROUP BY severity"
                ));
                
                foreach ($incidents as $incident) {
                    $stats['total'] += $incident->count;
                    if (isset($stats[$incident->severity])) {
                        $stats[$incident->severity] = (int) $incident->count;
                    }
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail the health check
            if ($this->logger) {
                $this->logger->warning('Failed to check security incidents', [
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        return $stats;
    }
    
    /**
     * Check API key security
     * 
     * @return array
     */
    private function checkApiKeySecurity(): array {
        $issues = [];
        
        // Check for exposed API keys in options
        $sensitiveOptions = [
            'zippicks_openai_api_key',
            'zippicks_stripe_secret_key',
            'zippicks_aws_secret_key'
        ];
        
        foreach ($sensitiveOptions as $option) {
            $value = get_option($option);
            if ($value && strlen($value) > 10) {
                // Check if it looks like a real key (not placeholder)
                if (!preg_match('/^(sk_test_|pk_test_|xxx|placeholder|your[-_]?key[-_]?here)/i', $value)) {
                    // Check if it's properly encrypted
                    if (strpos($value, 'enc:') !== 0) {
                        $issues[] = sprintf('API key %s appears to be stored unencrypted', $option);
                    }
                }
            }
        }
        
        return $issues;
    }
    
    /**
     * Get security recommendations based on issues
     * 
     * @param array $issues
     * @param array $warnings
     * @return array
     */
    private function getSecurityRecommendations(array $issues, array $warnings): array {
        $recommendations = [];
        
        if (!empty($issues)) {
            $recommendations['critical'] = [
                'Fix file permissions immediately',
                'Review and rotate compromised API keys',
                'Enable SSL/HTTPS if not already active',
                'Implement security headers',
                'Review recent security incidents'
            ];
        }
        
        if (!empty($warnings)) {
            $recommendations['improvements'] = [
                'Enable SSL/HTTPS for better security',
                'Disable debug mode in production',
                'Implement missing security headers',
                'Review file permissions',
                'Set up security monitoring'
            ];
        }
        
        $recommendations['general'] = [
            'Regular security audits',
            'Keep WordPress and plugins updated',
            'Use strong passwords and 2FA',
            'Implement Web Application Firewall',
            'Regular backup verification'
        ];
        
        return $recommendations;
    }
    
    /**
     * Calculate overall security score
     * 
     * @return int
     */
    private function calculateSecurityScore(): int {
        $score = 100;
        
        // Deduct for issues
        if (!is_ssl()) $score -= 20;
        if (defined('WP_DEBUG') && WP_DEBUG) $score -= 10;
        
        // Check headers
        $headers = headers_list();
        $requiredHeaders = ['X-Content-Type-Options', 'X-Frame-Options', 'X-XSS-Protection'];
        foreach ($requiredHeaders as $header) {
            $found = false;
            foreach ($headers as $sentHeader) {
                if (stripos($sentHeader, $header . ':') === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) $score -= 5;
        }
        
        return max(0, min(100, $score));
    }
    
    /**
     * Get detailed audit information
     * 
     * @return array
     */
    private function getAuditDetails(): array {
        return [
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'ssl_enabled' => is_ssl(),
            'debug_mode' => defined('WP_DEBUG') ? WP_DEBUG : false,
            'admin_ssl' => defined('FORCE_SSL_ADMIN') ? FORCE_SSL_ADMIN : false,
            'file_editing' => defined('DISALLOW_FILE_EDIT') ? DISALLOW_FILE_EDIT : false,
            'user_count' => count_users()['total_users'],
            'active_plugins' => count(get_option('active_plugins', [])),
            'audit_timestamp' => time()
        ];
    }
}