<?php
/**
 * Cron job script to clean up expired email verification tokens
 * Run this script daily to maintain database cleanliness
 * 
 * Usage: php cleanup_expired_tokens.php
 * Add to crontab: 0 2 * * * /usr/bin/php /path/to/aerozone/cron/cleanup_expired_tokens.php
 */

require_once '../config/database.php';
require_once '../config/email_helper.php';

// Set error reporting for cron execution
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "Starting cleanup of expired email verification tokens...\n";

try {
    $emailHelper = new EmailHelper();
    $result = $emailHelper->cleanupExpiredTokens();
    
    if ($result) {
        echo "Successfully cleaned up expired verification tokens.\n";
    } else {
        echo "Failed to clean up expired verification tokens.\n";
    }
} catch (Exception $e) {
    echo "Error during cleanup: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Cleanup completed.\n";
?>
