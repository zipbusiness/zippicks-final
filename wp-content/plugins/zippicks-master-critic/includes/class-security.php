<?php
/**
 * Security helper class for ZipPicks Master Critic
 *
 * @package ZipPicks_Master_Critic
 */

// Ensure plugin directory constant is defined
if (!defined('ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR')) {
    define('ZIPPICKS_MASTER_CRITIC_PLUGIN_DIR', plugin_dir_path(dirname(__FILE__)));
}

class ZipPicks_Master_Critic_Security {
    
    /**
     * Encryption method
     */
    const ENCRYPTION_METHOD = 'AES-256-CBC';
    
    /**
     * Get encryption key
     *
     * @return string
     */
    private static function get_encryption_key() {
        // Use a combination of salts for the key
        $key = wp_salt('auth') . wp_salt('secure_auth');
        return substr(hash('sha256', $key), 0, 32);
    }
    
    /**
     * Get initialization vector
     *
     * @return string
     */
    private static function get_iv() {
        $iv = wp_salt('logged_in') . wp_salt('nonce');
        return substr(hash('sha256', $iv), 0, 16);
    }
    
    /**
     * Encrypt data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    public static function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $iv = self::get_iv();
        
        $encrypted = openssl_encrypt($data, self::ENCRYPTION_METHOD, $key, 0, $iv);
        
        return base64_encode($encrypted);
    }
    
    /**
     * Decrypt data
     *
     * @param string $data Data to decrypt
     * @return string Decrypted data
     */
    public static function decrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        $key = self::get_encryption_key();
        $iv = self::get_iv();
        
        $encrypted = base64_decode($data);
        $decrypted = openssl_decrypt($encrypted, self::ENCRYPTION_METHOD, $key, 0, $iv);
        
        return $decrypted !== false ? $decrypted : '';
    }
    
    /**
     * Save encrypted option
     *
     * @param string $option_name Option name
     * @param string $value Value to encrypt and save
     * @return bool
     */
    public static function save_encrypted_option($option_name, $value) {
        $encrypted = self::encrypt($value);
        return update_option($option_name, $encrypted);
    }
    
    /**
     * Get decrypted option
     *
     * @param string $option_name Option name
     * @param string $default Default value
     * @return string
     */
    public static function get_encrypted_option($option_name, $default = '') {
        $encrypted = get_option($option_name, '');
        
        if (empty($encrypted)) {
            return $default;
        }
        
        $decrypted = self::decrypt($encrypted);
        
        return !empty($decrypted) ? $decrypted : $default;
    }
    
    /**
     * Sanitize error messages to prevent sensitive data leaks
     *
     * @param string $message Error message
     * @return string Sanitized message
     */
    public static function sanitize_error_message($message) {
        // Remove API keys
        $message = preg_replace('/[a-zA-Z0-9]{32,}/', '[REDACTED]', $message);
        
        // Remove URLs with potential credentials
        $message = preg_replace('/https?:\/\/[^@\s]+@[^\s]+/', '[REDACTED_URL]', $message);
        
        // Remove potential email addresses
        $message = preg_replace('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[REDACTED_EMAIL]', $message);
        
        return $message;
    }
    
    /**
     * Validate input data
     *
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @return array Validation result
     */
    public static function validate_input($data, $rules) {
        $errors = array();
        $validated = array();
        
        foreach ($rules as $field => $rule) {
            $value = isset($data[$field]) ? $data[$field] : '';
            
            // Required check
            if (!empty($rule['required']) && empty($value)) {
                $errors[$field] = sprintf('%s is required', $rule['label'] ?? $field);
                continue;
            }
            
            // Type check
            if (!empty($rule['type']) && !empty($value)) {
                switch ($rule['type']) {
                    case 'email':
                        if (!is_email($value)) {
                            $errors[$field] = sprintf('%s must be a valid email', $rule['label'] ?? $field);
                            continue 2;
                        }
                        break;
                        
                    case 'number':
                        if (!is_numeric($value)) {
                            $errors[$field] = sprintf('%s must be a number', $rule['label'] ?? $field);
                            continue 2;
                        }
                        break;
                        
                    case 'url':
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            $errors[$field] = sprintf('%s must be a valid URL', $rule['label'] ?? $field);
                            continue 2;
                        }
                        break;
                }
            }
            
            // Min/Max for numbers
            if (!empty($rule['min']) && is_numeric($value) && $value < $rule['min']) {
                $errors[$field] = sprintf('%s must be at least %s', $rule['label'] ?? $field, $rule['min']);
                continue;
            }
            
            if (!empty($rule['max']) && is_numeric($value) && $value > $rule['max']) {
                $errors[$field] = sprintf('%s must be at most %s', $rule['label'] ?? $field, $rule['max']);
                continue;
            }
            
            // Length validation
            if (!empty($rule['maxlength']) && strlen($value) > $rule['maxlength']) {
                $errors[$field] = sprintf('%s must be at most %d characters', $rule['label'] ?? $field, $rule['maxlength']);
                continue;
            }
            
            // Sanitization
            $sanitize = $rule['sanitize'] ?? 'text';
            switch ($sanitize) {
                case 'email':
                    $validated[$field] = sanitize_email($value);
                    break;
                    
                case 'url':
                    $validated[$field] = esc_url_raw($value);
                    break;
                    
                case 'textarea':
                    $validated[$field] = sanitize_textarea_field($value);
                    break;
                    
                case 'int':
                    $validated[$field] = intval($value);
                    break;
                    
                case 'float':
                    $validated[$field] = floatval($value);
                    break;
                    
                default:
                    $validated[$field] = sanitize_text_field($value);
            }
        }
        
        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => $validated
        );
    }
    
    /**
     * Generate secure random token
     *
     * @param int $length Token length
     * @return string
     */
    public static function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Verify request origin
     *
     * @return bool
     */
    public static function verify_request_origin() {
        // Check referrer
        if (!check_ajax_referer('zippicks_master_critic_nonce', 'nonce', false)) {
            return false;
        }
        
        // Check user capability
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        return true;
    }
}