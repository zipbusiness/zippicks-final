<?php
/**
 * Enable debugging for ZipPicks
 * 
 * Add this line to wp-config.php:
 * require_once(ABSPATH . 'wp-content/zippicks-debug.php');
 */

// Enable WordPress debug mode
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Log all errors to a ZipPicks-specific file
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $message = sprintf(
        "[%s] ZipPicks ERROR: %s in %s on line %d\n",
        date('Y-m-d H:i:s'),
        $errstr,
        $errfile,
        $errline
    );
    error_log($message, 3, WP_CONTENT_DIR . '/zippicks-debug.log');

    // Continue normal WordPress handling
    return false;
});

// Log fatal (shutdown) errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $message = sprintf(
            "[%s] ZipPicks FATAL: %s in %s on line %d\n",
            date('Y-m-d H:i:s'),
            $error['message'],
            $error['file'],
            $error['line']
        );
        error_log($message, 3, WP_CONTENT_DIR . '/zippicks-debug.log');
    }
});