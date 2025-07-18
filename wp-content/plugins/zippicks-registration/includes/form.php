<?php
/**
 * ZipPicks Registration Form Template - Enhanced Design
 * File: includes/form.php
 * PREVENTS GLOBAL DISPLAY
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// EMERGENCY: ONLY SHOW ON SPECIFIC PAGES
$allowed_pages = array('register', 'sign-up', 'join', 'signup');
$current_page = get_post_field('post_name', get_queried_object_id());

// Don't show on homepage
if (is_front_page() || is_home()) {
    return '';
}

// Don't show on admin pages
if (is_admin() || strpos($_SERVER['REQUEST_URI'], 'wp-admin') !== false) {
    return '';
}

// Don't show if user is logged in - CLEAN (no banner message)
if (is_user_logged_in()) {
    return '';
}

// Only show on specific pages
if (!in_array($current_page, $allowed_pages) && !is_page($allowed_pages)) {
    return '<!-- ZipPicks Registration Form: Not displayed on this page -->';
}
?>

<style>
.zip-form {
    max-width: 600px;
    margin: 0 auto;
    padding: 20px;
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(25, 79, 173, 0.08);
}

/* Override zip-follow-button for form context */
.zip-form .zip-follow-button {
    width: 100%;
    border-radius: 8px;
    padding: 16px 24px;
    font-size: 16px;
}

.zip-form .zip-follow-button:hover {
    color: #ffffff !important;
    background-color: #133d8a !important;
    border: 1px solid #133d8a !important;
}

.zip-form h1 {
    color: #194FAD;
    font-size: 32px;
    font-weight: 700;
    text-align: center;
    margin-bottom: 8px;
    letter-spacing: -0.5px;
}

.zip-hero-copy {
    text-align: center;
    color: #666;
    font-size: 16px;
    line-height: 1.5;
    margin-bottom: 32px;
    padding: 0 20px;
}

.zip-form-row {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
}

.zip-form-half {
    flex: 1;
}

.zip-form label {
    display: block;
    font-weight: 600;
    color: #333;
    margin-bottom: 6px;
    font-size: 14px;
}

.zip-form input,
.zip-form select {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.2s ease;
    background: #fff;
    box-sizing: border-box;
}

.zip-form input:focus,
.zip-form select:focus {
    outline: none;
    border-color: #194FAD;
    box-shadow: 0 0 0 3px rgba(25, 79, 173, 0.1);
}

.zip-form input::placeholder {
    color: #999;
}

.zip-form-group {
    margin-bottom: 20px;
}

.zip-field-help {
    font-size: 13px;
    color: #666;
    margin-top: 4px;
    font-style: italic;
}

.zip-password-help {
    font-size: 13px;
    color: #666;
    margin-top: 4px;
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 6px;
    border-left: 3px solid #194FAD;
}

.zip-help-text {
    background: #f8f9fa;
    padding: 12px 16px;
    border-radius: 8px;
    font-size: 13px;
    color: #666;
    margin-top: 8px;
    line-height: 1.4;
}

.zip-help-text strong {
    color: #333;
}

.zip-error {
    color: #dc3545;
    font-size: 13px;
    margin-top: 4px;
    display: block;
}

.g-recaptcha {
    margin: 24px 0;
    display: flex;
    justify-content: center;
}

.zip-submit-btn {
    width: 100%;
    padding: 16px 24px;
    font-size: 16px;
    margin: 24px 0;
    border: none;
    cursor: pointer;
    /* Inherits from zip-follow-button class */
}

.zip-form-footer {
    text-align: center;
    margin-top: 24px;
}

.zip-form-footer p {
    color: #666;
    font-size: 14px;
    margin: 8px 0;
}

.zip-form-footer a {
    color: #194FAD;
    text-decoration: none;
    font-weight: 500;
}

.zip-form-footer a:hover {
    text-decoration: underline;
}

.zip-terms-label {
    font-size: 12px !important;
    color: #999 !important;
    line-height: 1.4;
}

/* Mobile Optimizations */
@media (max-width: 768px) {
    .zip-form {
        margin: 10px 5px !important;
        padding: 20px 8px !important;
        max-width: none !important;
        width: calc(100% - 10px) !important;
        box-sizing: border-box;
    }
    
    /* Override any parent container constraints */
    .zip-form * {
        max-width: none !important;
    }
    
    /* Ensure parent containers don't constrain */
    .content, .container, .entry-content, .post-content, 
    .page-content, .site-content, .main-content {
        max-width: none !important;
        width: 100% !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-left: 0 !important;
        margin-right: 0 !important;
    }
}
    
    .zip-form h1 {
        font-size: 28px;
        margin-bottom: 6px;
    }
    
    .zip-hero-copy {
        font-size: 15px;
        padding: 0 10px;
        margin-bottom: 28px;
    }
    
    .zip-form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .zip-form-half {
        margin-bottom: 20px;
    }
    
    .zip-form input,
    .zip-form select {
        font-size: 16px; /* Prevents zoom on iOS */
        padding: 16px;
    }
    
    .zip-submit-btn {
        padding: 18px 24px;
        font-size: 17px;
        width: 100%;
    }
}

/* Desktop - constrain width for better UX */
@media (min-width: 769px) {
    .zip-form {
        max-width: 600px;
    }
}
.zip-form input:focus,
.zip-form select:focus {
    border-color: #194FAD;
    box-shadow: 0 0 0 3px rgba(25, 79, 173, 0.1);
}

/* Loading state */
.zip-spinner {
    display: inline-block;
    width: 16px;
    height: 16px;
    border: 2px solid #ffffff;
    border-radius: 50%;
    border-top-color: transparent;
    animation: zip-spin 1s ease-in-out infinite;
    margin-left: 8px;
}

@keyframes zip-spin {
    to { transform: rotate(360deg); }
}

/* Success/Error message styling */
#zippicks-messages {
    margin-bottom: 20px;
}

#zippicks-messages .success {
    background: #d4edda;
    color: #155724;
    padding: 12px 16px;
    border-radius: 8px;
    border-left: 4px solid #28a745;
}

#zippicks-messages .error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px 16px;
    border-radius: 8px;
    border-left: 4px solid #dc3545;
}
</style>

<div class="zip-form">
    <h1>Join ZipPicks</h1>
    
    <p class="zip-hero-copy">
        Join ZipPicks and discover top-rated restaurants, bars, and hidden gems curated by experts and tastemakers nationwide.
    </p>
    
    <div id="zippicks-messages"></div>
    
    <form id="zippicks-registration-form" method="post" novalidate>
        <?php wp_nonce_field('zippicks_register_nonce', 'zippicks_nonce'); ?>
        
        <div class="zip-form-row">
            <div class="zip-form-half">
                <label for="zp_first_name">First Name *</label>
                <input type="text" id="zp_first_name" name="first_name" required 
                       maxlength="100" placeholder="Enter your first name">
                <span class="zip-error" id="zp_first_name_error"></span>
            </div>
            
            <div class="zip-form-half">
                <label for="zp_last_name">Last Name *</label>
                <input type="text" id="zp_last_name" name="last_name" required 
                       maxlength="100" placeholder="Enter your last name">
                <span class="zip-error" id="zp_last_name_error"></span>
            </div>
        </div>
        
        <div class="zip-form-group">
            <label for="zp_username">Username *</label>
            <input type="text" id="zp_username" name="username" required 
                   maxlength="20" minlength="3" pattern="[a-z0-9_]+" 
                   placeholder="Choose a username">
            <span class="zip-error" id="zp_username_error"></span>
        </div>
        
        <div class="zip-form-group">
            <label for="zp_email">Email Address *</label>
            <input type="email" id="zp_email" name="email" required 
                   maxlength="255" placeholder="Enter your email address">
            <span class="zip-error" id="zp_email_error"></span>
        </div>
        
        <div class="zip-form-group">
            <label for="zp_zip">ZIP Code *</label>
            <input type="text" id="zp_zip" name="zip" required 
                   maxlength="10" pattern="[0-9]{5}(-[0-9]{4})?" 
                   placeholder="Enter your ZIP code (e.g., 90210)">
            <div class="zip-field-help">Used to personalize your local experience</div>
            <span class="zip-error" id="zp_zip_error"></span>
        </div>
        
        <div class="zip-form-row">
            <div class="zip-form-half">
                <label for="zp_password">Password *</label>
                <input type="password" id="zp_password" name="password" required 
                       minlength="8" placeholder="Create a secure password">
                <div class="zip-password-help">
                    Use at least 8 characters with uppercase, lowercase, numbers, and symbols
                </div>
                <span class="zip-error" id="zp_password_error"></span>
            </div>
            
            <div class="zip-form-half">
                <label for="zp_confirm_password">Confirm Password *</label>
                <input type="password" id="zp_confirm_password" name="confirm_password" required 
                       minlength="8" placeholder="Confirm your password">
                <span class="zip-error" id="zp_confirm_password_error"></span>
            </div>
        </div>
        
        <!-- Hidden fields to satisfy the mu-plugin -->
        <input type="hidden" id="pass1" name="pass1" />
        <input type="hidden" id="pass2" name="pass2" />
        
        <div class="zip-form-group">
            <label for="zp_user_type">How will you use ZipPicks?</label>
            <select id="zp_user_type" name="user_type" required>
                <option value="zipper">Zipper - Local Explorer (auto-approved)</option>
                <option value="critic">Critic - Professional Reviewer (requires approval)</option>
                <option value="business_owner">Business Owner - Manage My Business (requires approval)</option>
            </select>
            <div class="zip-help-text">
                • <strong>Zippers</strong> can discover and review local businesses immediately<br>
                • <strong>Critics</strong> can write detailed professional reviews after approval<br>
                • <strong>Business Owners</strong> can manage business profiles and respond to reviews after approval
            </div>
        </div>
        
        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(ZIPPICKS_RECAPTCHA_SITE_KEY); ?>"></div>
        <span class="zip-error" id="zp_recaptcha_error"></span>
        
        <button type="submit" id="zp-submit-btn" class="zip-follow-button zip-submit-btn">
            <span class="zip-btn-text">Create Account</span>
            <span class="zip-spinner" style="display: none;"></span>
        </button>
        
        <div class="zip-form-footer">
         <p>Already have an account? <a href="<?php echo home_url('/login'); ?>">Sign In</a></p>
            <p class="zip-terms-label">By creating an account, you agree to our 
               <a href="/terms" target="_blank">Terms of Service</a> and 
               <a href="/privacy" target="_blank">Privacy Policy</a>.</p>
        </div>
    </form>
</div>

<script>
// Sync password values to hidden fields for mu-plugin compatibility
document.addEventListener('DOMContentLoaded', function() {
    var passwordField = document.getElementById('zp_password');
    var confirmPasswordField = document.getElementById('zp_confirm_password');
    var pass1Field = document.getElementById('pass1');
    var pass2Field = document.getElementById('pass2');
    
    if (passwordField && pass1Field) {
        passwordField.addEventListener('input', function() {
            pass1Field.value = this.value;
        });
    }
    
    if (confirmPasswordField && pass2Field) {
        confirmPasswordField.addEventListener('input', function() {
            pass2Field.value = this.value;
        });
    }
});
</script>