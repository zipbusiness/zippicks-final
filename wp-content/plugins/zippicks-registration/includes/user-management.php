<?php
/**
 * ZipPicks User Management Helper Functions
 * File: includes/user-management.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZipPicksUserManagement {
    
    /**
     * Get ZipPicks users with filtering options
     */
    public static function getUsers($args = array()) {
        $defaults = array(
            'user_type' => null,
            'email_verified' => null,
            'approved' => null,
            'zip' => null,
            'number' => 20,
            'offset' => 0,
            'orderby' => 'registered',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Build meta query
        $meta_query = array();
        
        if ($args['user_type']) {
            $meta_query[] = array(
                'key' => 'zippicks_user_type',
                'value' => $args['user_type'],
                'compare' => '='
            );
        }
        
        if ($args['email_verified'] !== null) {
            $meta_query[] = array(
                'key' => 'zippicks_email_verified',
                'value' => $args['email_verified'],
                'compare' => '='
            );
        }
        
        if ($args['approved'] !== null) {
            $meta_query[] = array(
                'key' => 'zippicks_approved',
                'value' => $args['approved'],
                'compare' => '='
            );
        }
        
        if ($args['zip']) {
            $meta_query[] = array(
                'key' => 'zippicks_zip',
                'value' => $args['zip'],
                'compare' => '='
            );
        }
        
        // Ensure we only get ZipPicks users
        $meta_query[] = array(
            'key' => 'zippicks_user_type',
            'compare' => 'EXISTS'
        );
        
        $user_args = array(
            'meta_query' => $meta_query,
            'number' => $args['number'],
            'offset' => $args['offset'],
            'orderby' => $args['orderby'],
            'order' => $args['order']
        );
        
        return get_users($user_args);
    }
    
    /**
     * Get count of ZipPicks users
     */
    public static function getUserCount($filters = array()) {
        $args = array_merge($filters, array('number' => -1));
        $users = self::getUsers($args);
        return count($users);
    }
    
    /**
     * Get user statistics
     */
    public static function getUserStats() {
        // Get all ZipPicks users
        $all_users = self::getUsers(array('number' => -1));
        
        $stats = array(
            'total' => count($all_users),
            'by_type' => array(
                'zipper' => 0,
                'critic' => 0,
                'business_owner' => 0
            ),
            'by_status' => array(
                'verified' => 0,
                'unverified' => 0,
                'approved' => 0,
                'pending_approval' => 0
            )
        );
        
        foreach ($all_users as $user) {
            $user_type = get_user_meta($user->ID, 'zippicks_user_type', true);
            $email_verified = get_user_meta($user->ID, 'zippicks_email_verified', true);
            $approved = get_user_meta($user->ID, 'zippicks_approved', true);
            
            // Count by type
            if (isset($stats['by_type'][$user_type])) {
                $stats['by_type'][$user_type]++;
            }
            
            // Count by verification status
            if ($email_verified === '1') {
                $stats['by_status']['verified']++;
            } else {
                $stats['by_status']['unverified']++;
            }
            
            // Count by approval status
            if ($approved === '1') {
                $stats['by_status']['approved']++;
            } else {
                $stats['by_status']['pending_approval']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Approve a user
     */
    public static function approveUser($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        // Check if user is verified
        $email_verified = get_user_meta($user_id, 'zippicks_email_verified', true);
        if ($email_verified !== '1') {
            return new WP_Error('not_verified', 'User email must be verified before approval.');
        }
        
        // Approve user
        update_user_meta($user_id, 'zippicks_approved', '1');
        
        // Send approval email
        require_once ZIPPICKS_PLUGIN_DIR . 'includes/email.php';
        ZipPicksEmail::sendApprovalEmail($user_id);
        
        // Log action
        error_log('ZipPicks: User approved - ' . $user->user_email . ' (ID: ' . $user_id . ')');
        
        return true;
    }
    
    /**
     * Reject/remove a user
     */
    public static function rejectUser($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        // Log action before deletion
        error_log('ZipPicks: User rejected and removed - ' . $user->user_email . ' (ID: ' . $user_id . ')');
        
        // Remove user using WordPress function
        if (!function_exists('wp_delete_user')) {
            require_once(ABSPATH . 'wp-admin/includes/user.php');
        }
        
        return wp_delete_user($user_id);
    }
    
    /**
     * Clean up expired verification tokens
     */
    public static function cleanupExpiredTokens() {
        $users_with_tokens = get_users(array(
            'meta_key' => 'zippicks_email_code_expiry',
            'meta_compare' => 'EXISTS'
        ));
        
        $cleaned = 0;
        $current_time = time();
        
        foreach ($users_with_tokens as $user) {
            $expiry = get_user_meta($user->ID, 'zippicks_email_code_expiry', true);
            
            if ($expiry && $current_time > $expiry) {
                delete_user_meta($user->ID, 'zippicks_email_code');
                delete_user_meta($user->ID, 'zippicks_email_code_expiry');
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * Resend verification email
     */
    public static function resendVerificationEmail($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        // Check if already verified
        $email_verified = get_user_meta($user_id, 'zippicks_email_verified', true);
        if ($email_verified === '1') {
            return new WP_Error('already_verified', 'User email is already verified.');
        }
        
        // Generate new token
        $verification_token = wp_generate_password(64, false);
        update_user_meta($user_id, 'zippicks_email_code', $verification_token);
        
        // Set new expiry (24 hours)
        $token_expiry = time() + (24 * 60 * 60);
        update_user_meta($user_id, 'zippicks_email_code_expiry', $token_expiry);
        
        // Send email
        require_once ZIPPICKS_PLUGIN_DIR . 'includes/email.php';
        return ZipPicksEmail::sendVerificationEmail($user, $verification_token);
    }
    
    /**
     * Get user's ZipPicks profile data
     */
    public static function getUserProfile($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        return array(
            'user' => $user,
            'zip' => get_user_meta($user_id, 'zippicks_zip', true),
            'user_type' => get_user_meta($user_id, 'zippicks_user_type', true),
            'email_verified' => get_user_meta($user_id, 'zippicks_email_verified', true) === '1',
            'approved' => get_user_meta($user_id, 'zippicks_approved', true) === '1',
            'registration_date' => get_user_meta($user_id, 'zippicks_registration_date', true)
        );
    }
    
    /**
     * Check if user has ZipPicks profile
     */
    public static function isZipPicksUser($user_id) {
        $user_type = get_user_meta($user_id, 'zippicks_user_type', true);
        return !empty($user_type);
    }
    
    /**
     * Get users requiring approval
     */
    public static function getUsersAwaitingApproval() {
        return self::getUsers(array(
            'email_verified' => '1',
            'approved' => '0',
            'number' => -1
        ));
    }
}