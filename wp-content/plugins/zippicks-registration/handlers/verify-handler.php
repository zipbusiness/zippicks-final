<?php
/**
 * ZipPicks Email Verification Handler - WordPress Integration
 * File: handlers/verify-handler.php
 * Fixes 503 error and infinite recursion with limit-login-attempts plugin
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZipPicksVerifyHandler {
    
    public function process() {
        // Get verification token from URL parameter
        $verification_token = sanitize_text_field($_GET['zp_verify'] ?? '');
        
        if (empty($verification_token)) {
            $this->showError('Invalid verification link. Please check your email and try again.');
            return;
        }
        
        // Find user by verification token - using direct database query to avoid hooks
        global $wpdb;
        
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} 
             WHERE meta_key = 'zippicks_email_code' 
             AND meta_value = %s",
            $verification_token
        ));
        
        if (!$user_id) {
            $this->showError('Invalid or expired verification token. Please request a new verification email.');
            return;
        }
        
        // Check if token is expired
        $token_expiry = get_user_meta($user_id, 'zippicks_email_code_expiry', true);
        if ($token_expiry && time() > $token_expiry) {
            // Clean up expired token
            delete_user_meta($user_id, 'zippicks_email_code');
            delete_user_meta($user_id, 'zippicks_email_code_expiry');
            
            $this->showError('Verification link has expired. Please request a new verification email.');
            return;
        }
        
        // Get user data directly from database to avoid authentication hooks
        $user_data = $wpdb->get_row($wpdb->prepare(
            "SELECT ID, user_login, user_email, display_name 
             FROM {$wpdb->users} 
             WHERE ID = %d",
            $user_id
        ));
        
        if (!$user_data) {
            $this->showError('User account not found.');
            return;
        }
        
        // Check if already verified
        $email_verified = get_user_meta($user_id, 'zippicks_email_verified', true);
        if ($email_verified === '1') {
            // Already verified, redirect to profile
            $this->redirectToProfile($user_data->user_login);
            return;
        }
        
        // Verify the user account
        $success = $this->verifyUserAccount($user_id);
        
        if ($success) {
            // Clean up verification token
            delete_user_meta($user_id, 'zippicks_email_code');
            delete_user_meta($user_id, 'zippicks_email_code_expiry');
            
            // Log successful verification
            error_log('ZipPicks: Email verified successfully for user ID: ' . $user_id);
            
            // Show success message and redirect
            $this->showSuccessAndRedirect($user_data);
        } else {
            $this->showError('Failed to verify your account. Please try again or contact support.');
        }
    }
    
    private function verifyUserAccount($user_id) {
        // Update verification status
        $verified = update_user_meta($user_id, 'zippicks_email_verified', '1');
        
        if ($verified !== false) {
            // Store verification timestamp
            update_user_meta($user_id, 'zippicks_email_verified_date', current_time('mysql'));
            return true;
        }
        
        return false;
    }
    
    private function showSuccessAndRedirect($user_data) {
        // Get user type to determine proper redirect
        $user_type = get_user_meta($user_data->ID, 'zippicks_user_type', true);
        
        // Determine redirect URL based on ZipPicks user flow
        $redirect_url = $this->getProperRedirectUrl($user_data->ID, $user_type);
        $redirect_message = $this->getRedirectMessage($user_type);
        
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email Verified - ZipPicks</title>
            <meta name="robots" content="noindex,nofollow">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, #194FAD 0%, #133d8a 100%);
                    margin: 0;
                    padding: 0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #333;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 500px;
                    margin: 20px;
                }
                .success-icon {
                    font-size: 64px;
                    color: #00a32a;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #194FAD;
                    margin-bottom: 15px;
                    font-size: 28px;
                }
                p {
                    color: #666;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
                .redirect-info {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    margin: 20px 0;
                }
                .manual-link {
                    display: inline-block;
                    background: #194FAD;
                    color: white;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: 600;
                    margin-top: 15px;
                }
                .manual-link:hover {
                    background: #133d8a;
                }
                .loading {
                    display: inline-block;
                    width: 20px;
                    height: 20px;
                    border: 2px solid #194FAD;
                    border-radius: 50%;
                    border-top-color: transparent;
                    animation: spin 1s ease-in-out infinite;
                    margin-left: 10px;
                }
                @keyframes spin {
                    to { transform: rotate(360deg); }
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="success-icon">✅</div>
                <h1>Email Verified Successfully!</h1>
                <p>Welcome to ZipPicks, <strong><?php echo esc_html($user_data->display_name); ?></strong>!</p>
                <p><?php echo esc_html($redirect_message); ?></p>
                
                <div class="redirect-info">
                    <p><strong>Redirecting you now...</strong> <span class="loading"></span></p>
                    <p><small>You'll be automatically redirected in a few seconds.</small></p>
                </div>
                
                <a href="<?php echo esc_url($redirect_url); ?>" class="manual-link">
                    Continue to ZipPicks
                </a>
            </div>
            
            <script>
                // Redirect after showing success message
                setTimeout(function() {
                    window.location.href = '<?php echo esc_url($redirect_url); ?>';
                }, 3000);
                
                // Also allow immediate redirect on click
                document.addEventListener('click', function() {
                    window.location.href = '<?php echo esc_url($redirect_url); ?>';
                });
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    private function getProperRedirectUrl($user_id, $user_type) {
        // Zippers go directly to profile after email verification
        if ($user_type === 'zipper') {
            $username = get_userdata($user_id)->user_login;
            return home_url("/author/{$username}/");
        }
        
        // Critics and Business Owners need admin approval, so they go to review page
        if (in_array($user_type, ['critic', 'business_owner'])) {
            return home_url('/account-under-review/');
        }
        
        // Fallback to profile for unknown user types
        $username = get_userdata($user_id)->user_login;
        return home_url("/author/{$username}/");
    }
    
    private function getRedirectMessage($user_type) {
        switch ($user_type) {
            case 'zipper':
                return "Your account is now active! You can start exploring and discovering local businesses in your area.";
            case 'critic':
                return "Your email is verified! Your critic application is now under review by our team. You'll receive an email once approved.";
            case 'business_owner':
                return "Your email is verified! Your business owner application is now under review by our team. You'll receive an email once approved.";
            default:
                return "Your account has been activated and you're ready to start exploring!";
        }
    }
    
    private function redirectToProfile($username) {
        $profile_url = home_url("/author/{$username}/");
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Already Verified - ZipPicks</title>
            <meta name="robots" content="noindex,nofollow">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, #194FAD 0%, #133d8a 100%);
                    margin: 0;
                    padding: 0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #333;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 500px;
                    margin: 20px;
                }
                .info-icon {
                    font-size: 64px;
                    color: #0073aa;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #194FAD;
                    margin-bottom: 15px;
                }
                p {
                    color: #666;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="info-icon">ℹ️</div>
                <h1>Account Already Verified</h1>
                <p>Your email address has already been verified. Redirecting to your profile...</p>
            </div>
            
            <script>
                setTimeout(function() {
                    window.location.href = '<?php echo esc_url($profile_url); ?>';
                }, 2000);
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    private function showError($message) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Verification Error - ZipPicks</title>
            <meta name="robots" content="noindex,nofollow">
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, #194FAD 0%, #133d8a 100%);
                    margin: 0;
                    padding: 0;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #333;
                }
                .container {
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 500px;
                    margin: 20px;
                }
                .error-icon {
                    font-size: 64px;
                    color: #d63638;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #d63638;
                    margin-bottom: 15px;
                }
                p {
                    color: #666;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
                .action-buttons {
                    display: flex;
                    gap: 15px;
                    justify-content: center;
                    flex-wrap: wrap;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 24px;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: 600;
                    text-align: center;
                }
                .btn-primary {
                    background: #194FAD;
                    color: white;
                }
                .btn-secondary {
                    background: #f0f0f1;
                    color: #333;
                    border: 1px solid #ccd0d4;
                }
                .btn:hover {
                    opacity: 0.9;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="error-icon">❌</div>
                <h1>Verification Failed</h1>
                <p><?php echo esc_html($message); ?></p>
                
                <div class="action-buttons">
                    <a href="<?php echo home_url('/register/'); ?>" class="btn btn-secondary">
                        Register Again
                    </a>
                    <a href="<?php echo home_url('/support/'); ?>" class="btn btn-primary">
                        Contact Support
                    </a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}