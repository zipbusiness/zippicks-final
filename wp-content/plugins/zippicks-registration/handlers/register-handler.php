<?php
/**
 * ZipPicks Registration Handler - WordPress Core Integration
 * File: handlers/register-handler.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZipPicksRegisterHandler {
    
    private $errors = array();
    
    public function process() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['zippicks_nonce'], 'zippicks_register_nonce')) {
            wp_die('Security check failed');
        }
        
        // Validate and sanitize input
        $data = $this->validateInput();
        
        if (!empty($this->errors)) {
            wp_send_json_error(array(
                'message' => 'Please correct the following errors:',
                'errors' => $this->errors
            ));
        }
        
        // Check if email already exists in WordPress
        if (email_exists($data['email'])) {
            wp_send_json_error(array(
                'message' => 'An account with this email address already exists.',
                'errors' => array('email' => 'Email already registered')
            ));
        }
        
        // Verify reCAPTCHA
        if (!$this->verifyRecaptcha()) {
            wp_send_json_error(array(
                'message' => 'Please complete the reCAPTCHA verification.',
                'errors' => array('recaptcha' => 'reCAPTCHA verification failed')
            ));
        }
        
        // Create WordPress user
        $user_id = $this->createWordPressUser($data);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array(
                'message' => 'Failed to create account: ' . $user_id->get_error_message(),
                'errors' => array()
            ));
        }
        
        if ($user_id) {
            // Store additional metadata
            $this->storeUserMetadata($user_id, $data);
            
            // Send verification email
            $this->sendVerificationEmail($user_id);
            
            wp_send_json_success(array(
                'message' => 'Account created successfully!',
                'redirect' => home_url('/registration-success/')
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to create account. Please try again.',
                'errors' => array()
            ));
        }
    }
    
    private function validateInput() {
        $data = array();
        
        // First Name
        $first_name = sanitize_text_field($_POST['first_name'] ?? '');
        if (empty($first_name)) {
            $this->errors['first_name'] = 'First name is required';
        } elseif (strlen($first_name) > 100) {
            $this->errors['first_name'] = 'First name must be less than 100 characters';
        } else {
            $data['first_name'] = $first_name;
        }
        
        // Last Name
        $last_name = sanitize_text_field($_POST['last_name'] ?? '');
        if (empty($last_name)) {
            $this->errors['last_name'] = 'Last name is required';
        } elseif (strlen($last_name) > 100) {
            $this->errors['last_name'] = 'Last name must be less than 100 characters';
        } else {
            $data['last_name'] = $last_name;
        }
        
        // Username
        $username = sanitize_user($_POST['username'] ?? '');
        if (empty($username)) {
            $this->errors['username'] = 'Username is required';
        } elseif (strlen($username) < 3) {
            $this->errors['username'] = 'Username must be at least 3 characters';
        } elseif (strlen($username) > 20) {
            $this->errors['username'] = 'Username must be less than 20 characters';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $username)) {
            $this->errors['username'] = 'Username can only contain lowercase letters, numbers, and underscores';
        } elseif (username_exists($username)) {
            $this->errors['username'] = 'Username already exists. Please choose another.';
        } else {
            $data['username'] = $username;
        }
        
        // Email
        $email = sanitize_email($_POST['email'] ?? '');
        if (empty($email)) {
            $this->errors['email'] = 'Email address is required';
        } elseif (!is_email($email)) {
            $this->errors['email'] = 'Please enter a valid email address';
        } else {
            $data['email'] = $email;
        }
        
        // ZIP Code
        $zip = sanitize_text_field($_POST['zip'] ?? '');
        if (empty($zip)) {
            $this->errors['zip'] = 'ZIP code is required';
        } elseif (!preg_match('/^\d{5}(-\d{4})?$/', $zip)) {
            $this->errors['zip'] = 'Please enter a valid ZIP code (e.g., 12345 or 12345-6789)';
        } else {
            $data['zip'] = $zip;
        }
        
        // Password
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($password)) {
            $this->errors['password'] = 'Password is required';
        } elseif (strlen($password) < 8) {
            $this->errors['password'] = 'Password must be at least 8 characters long';
        } elseif ($password !== $confirm_password) {
            $this->errors['confirm_password'] = 'Passwords do not match';
        } else {
            // Validate password strength
            if (!$this->validatePasswordStrength($password)) {
                $this->errors['password'] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number';
            } else {
                $data['password'] = $password;
            }
        }
        
        // User Type
        $user_type = sanitize_text_field($_POST['user_type'] ?? 'zipper');
        $allowed_types = array('zipper', 'critic', 'business_owner');
        if (!in_array($user_type, $allowed_types)) {
            $user_type = 'zipper';
        }
        $data['user_type'] = $user_type;
        
        return $data;
    }
    
    private function validatePasswordStrength($password) {
        // Check for at least one uppercase, one lowercase, and one number
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password);
    }
    
    private function verifyRecaptcha() {
        $recaptcha_response = $_POST['g-recaptcha-response'] ?? '';
        
        if (empty($recaptcha_response)) {
            return false;
        }
        
        $response = wp_remote_post('https://www.google.com/recaptcha/api/siteverify', array(
            'body' => array(
                'secret' => ZIPPICKS_RECAPTCHA_SECRET_KEY,
                'response' => $recaptcha_response,
                'remoteip' => $_SERVER['REMOTE_ADDR']
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $response_body = wp_remote_retrieve_body($response);
        $result = json_decode($response_body, true);
        
        return isset($result['success']) && $result['success'] === true;
    }
    
    private function createWordPressUser($data) {
        // Use the validated username from form
        $username = $data['username'];
        
        // Create user using WordPress core function
        $user_id = wp_create_user(
            $username,
            $data['password'],
            $data['email']
        );
        
        if (!is_wp_error($user_id)) {
            // Update user's display name
            wp_update_user(array(
                'ID' => $user_id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'display_name' => $data['first_name'] . ' ' . $data['last_name']
            ));
            
            // Assign appropriate WordPress role
            $user = get_user_by('id', $user_id);
            if ($user) {
                $this->assignUserRole($user, $data['user_type']);
            }
        }
        
        return $user_id;
    }
    
    private function assignUserRole($user, $user_type) {
        // Remove default role
        $user->remove_role('subscriber');
        
        // Assign appropriate role based on user type
        switch ($user_type) {
            case 'zipper':
                $user->add_role('subscriber');
                break;
            case 'critic':
                $user->add_role('zippicks_critic');
                break;
            case 'business_owner':
                $user->add_role('zippicks_business_owner');
                break;
            default:
                $user->add_role('subscriber');
        }
    }
    
    private function storeUserMetadata($user_id, $data) {
        // Store ZIP code
        update_user_meta($user_id, 'zippicks_zip', $data['zip']);
        
        // Store user type
        update_user_meta($user_id, 'zippicks_user_type', $data['user_type']);
        
        // Store username in ACF field
        update_field('username', $data['username'], 'user_' . $user_id);
        
        // Set email verification status (not verified initially)
        update_user_meta($user_id, 'zippicks_email_verified', '0');
        
        // Generate and store email verification token
        $verification_token = wp_generate_password(64, false);
        update_user_meta($user_id, 'zippicks_email_code', $verification_token);
        
        // Set token expiry (24 hours)
        $token_expiry = time() + (24 * 60 * 60);
        update_user_meta($user_id, 'zippicks_email_code_expiry', $token_expiry);
        
        // Set approval status (critics and business owners need approval)
        if (in_array($data['user_type'], ['critic', 'business_owner'])) {
            update_user_meta($user_id, 'zippicks_approved', '0');
        } else {
            update_user_meta($user_id, 'zippicks_approved', '1'); // Auto-approve zippers
        }
        
        // Store registration timestamp
        update_user_meta($user_id, 'zippicks_registration_date', current_time('mysql'));
    }
    
    private function sendVerificationEmail($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $verification_token = get_user_meta($user_id, 'zippicks_email_code', true);
        if (!$verification_token) {
            return false;
        }
        
        // Use WordPress email system
        require_once ZIPPICKS_PLUGIN_DIR . 'includes/email.php';
        return ZipPicksEmail::sendVerificationEmail($user, $verification_token);
    }
}