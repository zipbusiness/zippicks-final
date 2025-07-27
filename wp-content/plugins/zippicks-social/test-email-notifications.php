<?php
/**
 * Test Email Notifications
 * 
 * Run this from the command line to test the email notification system:
 * wp eval-file wp-content/plugins/zippicks-social/test-email-notifications.php
 */

// Load WordPress
if (!defined('ABSPATH')) {
    require_once dirname(__FILE__) . '/../../../wp-load.php';
}

// Test configuration
$test_user_1 = 1; // Follower user ID
$test_user_2 = 2; // Followed user ID

echo "=== Testing ZipPicks Social Email Notifications ===\n\n";

// Check if plugin is active
if (!class_exists('ZipPicks_Social_Email_Notifications')) {
    die("Error: ZipPicks Social plugin is not active.\n");
}

// Check if WP Mail SMTP is configured
if (!function_exists('wp_mail_smtp')) {
    echo "Warning: WP Mail SMTP plugin is not active. Emails will use default WordPress mail.\n\n";
}

// Get email notifications instance
$email_notifications = new ZipPicks_Social_Email_Notifications();

echo "1. Testing New Follower Notification\n";
echo "   From User ID: $test_user_1\n";
echo "   To User ID: $test_user_2\n\n";

// Trigger new follower notification
do_action('zippicks_social_after_follow', $test_user_1, $test_user_2, 'user');

echo "   ✓ New follower notification triggered\n\n";

echo "2. Testing Milestone Notification\n";
echo "   User ID: $test_user_2\n";
echo "   Milestone: 100 followers\n\n";

// Trigger milestone notification
do_action('zippicks_social_milestone_reached', $test_user_2, 'followers_100', 100);

echo "   ✓ Milestone notification triggered\n\n";

echo "3. Testing Email Queue Processing\n";

// Process email queue
do_action('zippicks_social_process_email_queue');

echo "   ✓ Email queue processed\n\n";

echo "4. Testing Direct Email Send\n";

// Get test user
$user = get_user_by('id', $test_user_2);
if ($user) {
    $test_email_sent = wp_mail(
        $user->user_email,
        'ZipPicks Social - Test Email',
        '<h1>Test Email</h1><p>This is a test email from ZipPicks Social plugin.</p>',
        ['Content-Type: text/html; charset=UTF-8']
    );
    
    if ($test_email_sent) {
        echo "   ✓ Test email sent successfully to {$user->user_email}\n\n";
    } else {
        echo "   ✗ Failed to send test email\n\n";
    }
} else {
    echo "   ✗ Test user not found\n\n";
}

echo "=== Email Notification Test Complete ===\n";
echo "\nPlease check:\n";
echo "1. Email inbox for test notifications\n";
echo "2. WordPress error logs for any issues\n";
echo "3. API notification queue for pending items\n";

// Show email configuration
echo "\nCurrent Email Configuration:\n";
$mailer = get_option('wp_mail_smtp');
if ($mailer && isset($mailer['mail']['mailer'])) {
    echo "Mailer: " . $mailer['mail']['mailer'] . "\n";
    if ($mailer['mail']['mailer'] === 'smtp') {
        echo "SMTP Host: " . ($mailer['smtp']['host'] ?? 'Not set') . "\n";
        echo "SMTP Port: " . ($mailer['smtp']['port'] ?? 'Not set') . "\n";
        echo "Encryption: " . ($mailer['smtp']['encryption'] ?? 'None') . "\n";
    }
} else {
    echo "Using default WordPress mail configuration\n";
}