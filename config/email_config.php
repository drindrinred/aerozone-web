<?php
/**
 * Email Configuration for AEROZONE
 * Configure your SMTP settings here for actual email sending
 */

// Check if we're in Azure production environment
if (isset($_ENV['WEBSITE_SITE_NAME']) || (isset($_ENV['EMAIL_SMTP_USERNAME']) && !empty($_ENV['EMAIL_SMTP_USERNAME']))) {
    // Use Azure production configuration
    require_once 'email_config_azure.php';
    return;
}

require_once 'phpmailer_setup.php';

// Email Configuration
define('EMAIL_SMTP_HOST', 'smtp.gmail.com');  // Gmail SMTP server
define('EMAIL_SMTP_PORT', 587);               // TLS port
define('EMAIL_SMTP_USERNAME', 'noname234312@gmail.com');  // Your Gmail address
define('EMAIL_SMTP_PASSWORD', 'mrzaooyqhvrqetbx');     // Gmail App Password
define('EMAIL_FROM_NAME', 'AEROZONE');
define('EMAIL_FROM_ADDRESS', 'noname234312@gmail.com');

// Development Mode (set to false for production)
define('EMAIL_DEVELOPMENT_MODE', true);

// Alternative: Use a service like Mailtrap for testing
// define('EMAIL_SMTP_HOST', 'smtp.mailtrap.io');
// define('EMAIL_SMTP_PORT', 2525);
// define('EMAIL_SMTP_USERNAME', 'your-mailtrap-username');
// define('EMAIL_SMTP_PASSWORD', 'your-mailtrap-password');

/**
 * Get email configuration based on environment
 */
function getEmailConfig() {
    return [
        'smtp_host' => EMAIL_SMTP_HOST,
        'smtp_port' => EMAIL_SMTP_PORT,
        'smtp_username' => EMAIL_SMTP_USERNAME,
        'smtp_password' => EMAIL_SMTP_PASSWORD,
        'from_name' => EMAIL_FROM_NAME,
        'from_address' => EMAIL_FROM_ADDRESS,
        'development_mode' => EMAIL_DEVELOPMENT_MODE
    ];
}

/**
 * Check if email is properly configured
 */
function isEmailConfigured() {
    $config = getEmailConfig();
    return !empty($config['smtp_username']) && 
           !empty($config['smtp_password']) && 
           $config['smtp_username'] !== 'your-email@gmail.com';
}

/**
 * Send email using PHPMailer (if available) or fallback to mail()
 */
function sendEmail($to, $subject, $message, $headers = '') {
    $config = getEmailConfig();
    
    // Try PHPMailer first if available
    if (PHPMailer_AVAILABLE) {
        $result = sendEmailWithPHPMailer($to, $subject, $message, $config);
        if ($result) {
            return true;
        }
    }
    
    // Fallback to PHP mail() function
    return sendEmailWithMail($to, $subject, $message, $headers);
}

/**
 * Send email using PHP mail() function
 */
function sendEmailWithMail($to, $subject, $message, $headers) {
    return mail($to, $subject, $message, $headers);
}
?>
