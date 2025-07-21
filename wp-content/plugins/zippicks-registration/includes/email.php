<?php
/**
 * ZipPicks Email Helper Functions - WordPress Integration
 * File: includes/email.php
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class ZipPicksEmail {
    
    public static function sendVerificationEmail($user, $verification_token) {
        if (!$user || !$verification_token) {
            return false;
        }
        
        // Generate verification URL
        $verification_url = home_url('/?zp_verify=' . $verification_token);
        
        // Load email template
        $email_content = self::getVerificationEmailTemplate($user, $verification_url);
        
        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ZipPicks <noreply@zippicks.com>'
        );
        
        // Send email using WordPress mail function
        $sent = wp_mail(
            $user->user_email,
            'Verify Your ZipPicks Account',
            $email_content,
            $headers
        );
        
        if (!$sent) {
            error_log('ZipPicks: Failed to send verification email to ' . $user->user_email);
        }
        
        return $sent;
    }
    
    public static function sendApprovalEmail($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        $subject = 'Welcome to ZipPicks - Account Approved!';
        $message = self::getApprovalEmailTemplate($user, $site_name, $site_url);
        
        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ZipPicks <noreply@zippicks.com>'
        );
        
        // Send email
        $sent = wp_mail(
            $user->user_email,
            $subject,
            $message,
            $headers
        );
        
        if (!$sent) {
            error_log('ZipPicks: Failed to send approval email to ' . $user->user_email);
        }
        
        return $sent;
    }
    
    private static function getVerificationEmailTemplate($user, $verification_url) {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        $user_type = get_user_meta($user->ID, 'zippicks_user_type', true);
        $zip = get_user_meta($user->ID, 'zippicks_zip', true);
        
        // Customize message based on user type
        $next_steps = '';
        switch ($user_type) {
            case 'zipper':
                $next_steps = '<p>Once verified, you\'ll be automatically logged in and can start exploring local businesses in your area!</p>';
                break;
            case 'critic':
                $next_steps = '<p>Once verified, our team will review your account and you\'ll receive approval notification within 24-48 hours. As a critic, you\'ll be able to write detailed reviews and rate local businesses.</p>';
                break;
            case 'business_owner':
                $next_steps = '<p>Once verified, our team will review your account and you\'ll receive approval notification within 24-48 hours. As a business owner, you\'ll be able to manage your business profile and respond to customer reviews.</p>';
                break;
        }
        
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Verify Your ZipPicks Account</title>
            <style>
                body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8f9fa; color: #333333; line-height: 1.6; }
                .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
                .email-header { background: linear-gradient(135deg, #194FAD 0%, #133d8a 100%); color: #ffffff; padding: 40px 30px; text-align: center; }
                .logo { font-size: 32px; font-weight: bold; margin-bottom: 10px; letter-spacing: -1px; }
                .tagline { font-size: 16px; opacity: 0.9; margin: 0; }
                .email-body { padding: 40px 30px; }
                .greeting { font-size: 24px; font-weight: 600; color: #333333; margin-bottom: 20px; }
                .message { font-size: 16px; color: #333333; margin-bottom: 30px; line-height: 1.7; }
                .message p { margin: 0 0 16px 0; font-size: 16px; color: #333333; line-height: 1.7; }
                .message ul { margin: 16px 0; padding-left: 20px; }
                .message li { margin-bottom: 8px; font-size: 16px; color: #333333; line-height: 1.6; }
                .verify-button { display: inline-block; background: linear-gradient(135deg, #194FAD 0%, #133d8a 100%); color: #ffffff !important; text-decoration: none; padding: 16px 32px; border-radius: 6px; font-size: 16px; font-weight: 600; text-align: center; margin: 20px 0; }
                .alternative-link { margin-top: 30px; padding: 20px; background-color: #f8f9fa; border-radius: 6px; border-left: 4px solid #194FAD; }
                .alternative-link p { margin: 0 0 10px 0; font-size: 14px; color: #666666; }
                .alternative-link a { color: #194FAD; word-break: break-all; text-decoration: none; }
                .email-footer { background-color: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
                .footer-text { font-size: 14px; color: #666666; margin: 0 0 15px 0; }
                .footer-links a { color: #194FAD; text-decoration: none; margin: 0 15px; font-size: 14px; }
                .security-notice { background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 15px; margin: 20px 0; }
                .security-notice p { margin: 0; font-size: 14px; color: #856404; }
                @media only screen and (max-width: 600px) {
                    .email-container { margin: 0; border-radius: 0; }
                    .email-header, .email-body, .email-footer { padding: 20px; }
                    .verify-button { display: block; width: 100%; box-sizing: border-box; }
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <div class="logo">ZipPicks</div>
                    <p class="tagline">Top-Rated Spots, Curated by Experts<br>  <em>Only the best. Always curated.</em></p>
                </div>
                
                <div class="email-body">
                    <h1 class="greeting">Welcome, ' . esc_html($user->first_name) . '! 👋</h1>
                    
                    <div class="message">
                        <p>Thank you for joining ZipPicks as a <strong>' . esc_html(ucfirst(str_replace('_', ' ', $user_type))) . '</strong>! We\'re excited to have you as part of our community in the <strong>' . esc_html($zip) . '</strong> area.</p>
                        
                        <p>To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
                    </div>
                    
                    <div style="text-align: center;">
                        <a href="' . esc_url($verification_url) . '" class="verify-button">
                            Verify Email Address
                        </a>
                    </div>
                    
                    <div class="security-notice">
                        <p><strong>Security Note:</strong> This verification link will expire in 24 hours for your protection. If you didn\'t create this account, please ignore this email.</p>
                    </div>
                    
                    <div class="alternative-link">
                        <p><strong>Having trouble with the button?</strong> Copy and paste this link into your browser:</p>
                        <a href="' . esc_url($verification_url) . '">' . esc_url($verification_url) . '</a>
                    </div>
                    
                    <div class="message" style="margin-top: 30px;">
                        ' . $next_steps . '
                        
                        <p><strong>What\'s waiting for you:</strong></p>
                        <ul>
                            <li>Discover top-rated local businesses in ' . esc_html($zip) . '</li>
                            <li>Read authentic reviews from your community</li>
                            <li>Get personalized recommendations</li>
                            <li>Connect with local business owners</li>
                        </ul>
                    </div>
                </div>
                
                <div class="email-footer">
                    <p class="footer-text">
                        Questions? Contact our support team at 
                        <a href="mailto:hello@zippicks.com">hello@zippicks.com</a>
                    </p>
                    
                    <div class="footer-links">
                        <a href="' . esc_url($site_url) . '">Visit ZipPicks</a>
                        <a href="' . esc_url($site_url . '/privacy') . '">Privacy Policy</a>
                        <a href="' . esc_url($site_url . '/terms') . '">Terms of Service</a>
                    </div>
                    
                    <p class="footer-text" style="margin-top: 20px; font-size: 12px;">
                        © ' . date('Y') . ' ZipPicks. All rights reserved.<br>
                        This email was sent to ' . esc_html($user->user_email) . ' because you created an account on ZipPicks.
                    </p>
                </div>
            </div>
        </body>
        </html>';
    }
    
    private static function getApprovalEmailTemplate($user, $site_name, $site_url) {
        $user_type = get_user_meta($user->ID, 'zippicks_user_type', true);
        
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Account Approved - Welcome to ZipPicks!</title>
            <style>
                body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background-color: #f8f9fa; color: #333333; line-height: 1.6; }
                .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); }
                .email-header { background: linear-gradient(135deg, #00a32a 0%, #007d20 100%); color: #ffffff; padding: 40px 30px; text-align: center; }
                .logo { font-size: 32px; font-weight: bold; margin-bottom: 10px; }
                .email-body { padding: 40px 30px; }
                .greeting { font-size: 24px; font-weight: 600; color: #333333; margin-bottom: 20px; }
                .message { font-size: 16px; color: #333333; margin-bottom: 30px; line-height: 1.7; }
                .message p { margin: 0 0 16px 0; font-size: 16px; color: #333333; line-height: 1.7; }
                .cta-button { display: inline-block; background: linear-gradient(135deg, #194FAD 0%, #133d8a 100%); color: #ffffff; text-decoration: none; padding: 16px 32px; border-radius: 6px; font-size: 16px; font-weight: 600; text-align: center; margin: 20px 0; }
                .email-footer { background-color: #f8f9fa; padding: 30px; text-align: center; border-top: 1px solid #e9ecef; }
                .footer-text { font-size: 14px; color: #666666; margin: 0; }
                @media only screen and (max-width: 600px) {
                    .email-container { margin: 0; border-radius: 0; }
                    .email-header, .email-body, .email-footer { padding: 20px; }
                    .cta-button { display: block; width: 100%; box-sizing: border-box; }
                }
            </style>
        </head>
        <body>
            <div class="email-container">
                <div class="email-header">
                    <div class="logo">ZipPicks</div>
                    <p style="margin: 0; opacity: 0.9;">Account Approved! 🎉</p>
                </div>
                
                <div class="email-body">
                    <h1 class="greeting">Congratulations, ' . esc_html($user->first_name) . '!</h1>
                    
                    <div class="message">
                        <p>Great news! Your ZipPicks account has been approved and you now have full access to our platform as a <strong>' . esc_html(ucfirst(str_replace('_', ' ', $user_type))) . '</strong>.</p>
                        
                        <p>You can now log in and enjoy all the features of ZipPicks!</p>
                    </div>
                    
                    <div style="text-align: center;">
                        <a href="' . esc_url(wp_login_url()) . '" class="cta-button">
                            Log In to ZipPicks
                        </a>
                    </div>
                    
                    <div class="message" style="margin-top: 30px;">
                        <p>Welcome to the ZipPicks community! We\'re excited to have you on board.</p>
                    </div>
                </div>
                
                <div class="email-footer">
                    <p class="footer-text">
                        Need help? Contact us at 
                        <a href="mailto:hello@zippicks.com" style="color: #194FAD;">hello@zippicks.com</a>
                    </p>
                    <p class="footer-text" style="margin-top: 15px;">
                        © ' . date('Y') . ' ZipPicks. All rights reserved.
                    </p>
                </div>
            </div>
        </body>
        </html>';
    }
}