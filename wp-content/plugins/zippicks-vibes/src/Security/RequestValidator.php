<?php
/**
 * Request Validator
 *
 * Enterprise-grade validation and sanitization for REST API requests.
 * Implements anti-scraping measures and protects against XSS, SQL injection,
 * and other attack vectors.
 *
 * @package ZipPicksVibes
 * @since 2.0.0
 */

namespace ZipPicksVibes\Security;

use WP_REST_Request;

class RequestValidator {

    /**
     * Maximum allowed search query length
     */
    private const MAX_SEARCH_LENGTH = 100;

    /**
     * Maximum allowed string field length
     */
    private const MAX_STRING_LENGTH = 255;

    /**
     * Maximum allowed array size for filters
     */
    private const MAX_ARRAY_SIZE = 50;

    /**
     * Blocked user agents for anti-scraping
     */
    private const BLOCKED_USER_AGENTS = [
        'curl',
        'wget',
        'python-requests',
        'scrapy',
        'go-http-client',
        'java',
        'libwww-perl',
        'mechanize',
        'nutch',
        'httpclient'
    ];

    /**
     * XSS dangerous patterns to strip
     */
    private const XSS_PATTERNS = [
        '/<script[^>]*>.*?<\/script>/is',
        '/<iframe[^>]*>.*?<\/iframe>/is',
        '/<object[^>]*>.*?<\/object>/is',
        '/<embed[^>]*>/is',
        '/on\w+\s*=\s*["\']?[^"\'>\s]*/i',
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/<img[^>]+src[\\s]*=[\\s]*["\']javascript:/i'
    ];

    /**
     * Validate and sanitize a search query string with enhanced XSS protection.
     *
     * @param mixed $input
     * @return string|null
     */
    public function validateSearchQuery($input): ?string {
        if (!is_string($input)) {
            return null;
        }

        // Remove potential XSS vectors first
        $input = $this->stripXSS($input);
        
        // Standard WordPress sanitization
        $input = sanitize_text_field($input);
        $input = trim($input);

        // Length validation
        if ($input === '' || strlen($input) > self::MAX_SEARCH_LENGTH) {
            return null;
        }

        // Remove SQL injection attempts
        $input = $this->stripSQLPatterns($input);

        // Additional filtering for special characters that could be problematic
        $input = preg_replace('/[<>{}\\\\]/', '', $input);

        return $input;
    }

    /**
     * Validate and sanitize a numeric ID with strict bounds checking.
     *
     * @param mixed $input
     * @return int|null
     */
    public function validateId($input): ?int {
        if (!is_numeric($input)) {
            return null;
        }

        $id = (int) $input;
        
        // Prevent negative IDs and unreasonably large IDs
        if ($id <= 0 || $id > 2147483647) { // Max signed 32-bit integer
            return null;
        }

        return $id;
    }

    /**
     * Validate an array of IDs (e.g., vibe IDs for filtering).
     *
     * @param mixed $input
     * @return array|null
     */
    public function validateIdArray($input): ?array {
        if (!is_array($input)) {
            return null;
        }

        // Limit array size to prevent DoS
        if (count($input) > self::MAX_ARRAY_SIZE) {
            return null;
        }

        $validated = [];
        foreach ($input as $item) {
            $id = $this->validateId($item);
            if ($id !== null) {
                $validated[] = $id;
            }
        }

        return !empty($validated) ? array_unique($validated) : null;
    }

    /**
     * Validate ZIP code format (US 5-digit or ZIP+4).
     *
     * @param mixed $input
     * @return string|null
     */
    public function validateZipCode($input): ?string {
        if (!is_string($input)) {
            return null;
        }

        $zip = trim($input);
        
        // Match 5-digit ZIP or ZIP+4 format
        if (!preg_match('/^\d{5}(-\d{4})?$/', $zip)) {
            return null;
        }

        return $zip;
    }

    /**
     * Validate geographic coordinates.
     *
     * @param mixed $lat
     * @param mixed $lng
     * @return array|null Returns ['lat' => float, 'lng' => float] or null
     */
    public function validateCoordinates($lat, $lng): ?array {
        if (!is_numeric($lat) || !is_numeric($lng)) {
            return null;
        }

        $lat = (float) $lat;
        $lng = (float) $lng;

        // Validate coordinate ranges
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /**
     * Validate pagination parameters.
     *
     * @param mixed $page
     * @param mixed $perPage
     * @return array|null Returns ['page' => int, 'per_page' => int] or null
     */
    public function validatePagination($page, $perPage = 20): ?array {
        $page = $this->validateId($page);
        if ($page === null) {
            $page = 1;
        }

        if (!is_numeric($perPage)) {
            $perPage = 20;
        }

        $perPage = (int) $perPage;
        
        // Limit per_page to prevent resource exhaustion
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }

        return ['page' => $page, 'per_page' => $perPage];
    }

    /**
     * Validate sort parameters.
     *
     * @param mixed $orderBy
     * @param mixed $order
     * @param array $allowedFields
     * @return array|null
     */
    public function validateSort($orderBy, $order, array $allowedFields = []): ?array {
        if (empty($allowedFields)) {
            $allowedFields = ['id', 'name', 'date', 'score', 'popularity'];
        }

        $orderBy = $this->validateString($orderBy, 50);
        if (!$orderBy || !in_array($orderBy, $allowedFields, true)) {
            $orderBy = 'date';
        }

        $order = strtoupper($this->validateString($order, 4) ?? 'DESC');
        if (!in_array($order, ['ASC', 'DESC'], true)) {
            $order = 'DESC';
        }

        return ['orderby' => $orderBy, 'order' => $order];
    }

    /**
     * General validator for string input fields with enhanced security.
     *
     * @param mixed $input
     * @param int $max
     * @return string|null
     */
    public function validateString($input, int $max = 255): ?string {
        if (!is_string($input)) {
            return null;
        }

        // Strip XSS attempts first
        $input = $this->stripXSS($input);

        // WordPress sanitization
        $value = sanitize_text_field(trim($input));
        
        if ($value === '' || strlen($value) > $max) {
            return null;
        }

        return $value;
    }

    /**
     * Validate request headers for anti-scraping measures.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function validateRequestHeaders(WP_REST_Request $request): bool {
        $user_agent = $request->get_header('user-agent');
        
        // Block empty user agents
        if (empty($user_agent)) {
            return false;
        }

        // Block known scraping user agents
        $user_agent_lower = strtolower($user_agent);
        foreach (self::BLOCKED_USER_AGENTS as $blocked) {
            if (strpos($user_agent_lower, $blocked) !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate session/nonce for authenticated requests.
     *
     * @param string|null $nonce
     * @param string $action
     * @return bool
     */
    public function validateNonce(?string $nonce, string $action = 'wp_rest'): bool {
        if (empty($nonce)) {
            return false;
        }

        return wp_verify_nonce($nonce, $action) !== false;
    }

    /**
     * Check if a required parameter is present and non-empty.
     *
     * @param mixed $input
     * @return bool
     */
    public function isRequired($input): bool {
        return !empty($input) && is_scalar($input);
    }

    /**
     * Validate an email address.
     *
     * @param mixed $input
     * @return string|null
     */
    public function validateEmail($input): ?string {
        if (!is_string($input)) {
            return null;
        }

        $email = sanitize_email(trim($input));
        
        if (!is_email($email)) {
            return null;
        }

        return $email;
    }

    /**
     * Validate a URL.
     *
     * @param mixed $input
     * @return string|null
     */
    public function validateUrl($input): ?string {
        if (!is_string($input)) {
            return null;
        }

        $url = esc_url_raw(trim($input));
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }

    /**
     * Validate a phone number (basic US format).
     *
     * @param mixed $input
     * @return string|null
     */
    public function validatePhone($input): ?string {
        if (!is_string($input)) {
            return null;
        }

        // Remove common formatting characters
        $phone = preg_replace('/[^\d]/', '', $input);
        
        // Basic US phone validation (10 digits, optional 1 prefix)
        if (!preg_match('/^1?\d{10}$/', $phone)) {
            return null;
        }

        return $phone;
    }

    /**
     * Strip XSS attempts from input.
     *
     * @param string $input
     * @return string
     */
    private function stripXSS(string $input): string {
        foreach (self::XSS_PATTERNS as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }

        // Strip any remaining HTML tags
        $input = wp_strip_all_tags($input);

        // Decode HTML entities to prevent double encoding attacks
        $input = html_entity_decode($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $input;
    }

    /**
     * Strip common SQL injection patterns.
     *
     * @param string $input
     * @return string
     */
    private function stripSQLPatterns(string $input): string {
        // Remove common SQL keywords used in injection attempts
        $patterns = [
            '/\b(union|select|insert|update|delete|drop|create|alter|exec|execute|script|declare)\b/i',
            '/[\'";]--/',
            '/\/\*.*?\*\//',
            '/\\\\./',
            '/\x00|\x1a/'
        ];

        foreach ($patterns as $pattern) {
            $input = preg_replace($pattern, '', $input);
        }

        return $input;
    }

    /**
     * Validate a date string.
     *
     * @param mixed $input
     * @param string $format
     * @return string|null
     */
    public function validateDate($input, string $format = 'Y-m-d'): ?string {
        if (!is_string($input)) {
            return null;
        }

        $date = \DateTime::createFromFormat($format, $input);
        
        if (!$date || $date->format($format) !== $input) {
            return null;
        }

        return $input;
    }

    /**
     * Validate a boolean parameter.
     *
     * @param mixed $input
     * @return bool|null
     */
    public function validateBoolean($input): ?bool {
        if (is_bool($input)) {
            return $input;
        }

        if (is_string($input)) {
            $input = strtolower(trim($input));
            if (in_array($input, ['true', '1', 'yes', 'on'], true)) {
                return true;
            }
            if (in_array($input, ['false', '0', 'no', 'off'], true)) {
                return false;
            }
        }

        if (is_numeric($input)) {
            return (bool) $input;
        }

        return null;
    }

    /**
     * Validate a numeric range.
     *
     * @param mixed $input
     * @param float $min
     * @param float $max
     * @return float|null
     */
    public function validateRange($input, float $min, float $max): ?float {
        if (!is_numeric($input)) {
            return null;
        }

        $value = (float) $input;
        
        if ($value < $min || $value > $max) {
            return null;
        }

        return $value;
    }

    /**
     * Batch validate multiple parameters.
     *
     * @param array $rules Array of validation rules
     * @param array $data Input data to validate
     * @return array Validated data
     */
    public function validateBatch(array $rules, array $data): array {
        $validated = [];

        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            $method = $rule['method'] ?? 'validateString';
            $args = $rule['args'] ?? [];

            if (method_exists($this, $method)) {
                $result = $this->$method($value, ...$args);
                
                if ($result !== null) {
                    $validated[$field] = $result;
                } elseif (!empty($rule['required'])) {
                    // Required field is missing or invalid
                    throw new \InvalidArgumentException(
                        sprintf('Required field "%s" is missing or invalid', $field)
                    );
                }
            }
        }

        return $validated;
    }
}