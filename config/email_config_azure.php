<?php
/**
 * Azure Email Configuration for AEROZONE
 * Uses Azure App Service environment variables and SendGrid integration
 */

require_once 'phpmailer_setup.php';

// Email Configuration from Azure App Service Environment Variables
define('EMAIL_SMTP_HOST', $_ENV['EMAIL_SMTP_HOST'] ?? 'smtp.sendgrid.net');
define('EMAIL_SMTP_PORT', (int)($_ENV['EMAIL_SMTP_PORT'] ?? 587));
define('EMAIL_SMTP_USERNAME', $_ENV['SENDGRID_USERNAME'] ?? $_ENV['EMAIL_SMTP_USERNAME'] ?? '');
define('EMAIL_SMTP_PASSWORD', $_ENV['SENDGRID_PASSWORD'] ?? $_ENV['EMAIL_SMTP_PASSWORD'] ?? '');
define('EMAIL_FROM_NAME', $_ENV['EMAIL_FROM_NAME'] ?? 'AEROZONE');
define('EMAIL_FROM_ADDRESS', $_ENV['EMAIL_FROM_ADDRESS'] ?? '');

// Azure App Service environment
define('EMAIL_DEVELOPMENT_MODE', $_ENV['APP_ENV'] === 'development');

/**
 * Get email configuration based on Azure environment
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
           !empty($config['from_address']);
}

/**
 * Send email using PHPMailer with Azure/SendGrid configuration
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

/**
 * Azure-specific email helper for SendGrid
 */
function sendEmailWithSendGrid($to, $subject, $message) {
    $apiKey = $_ENV['SENDGRID_API_KEY'] ?? '';
    
    if (empty($apiKey)) {
        return false;
    }
    
    $data = [
        'personalizations' => [
            [
                'to' => [['email' => $to]]
            ]
        ],
        'from' => [
            'email' => EMAIL_FROM_ADDRESS,
            'name' => EMAIL_FROM_NAME
        ],
        'subject' => $subject,
        'content' => [
            [
                'type' => 'text/html',
                'value' => $message
            ]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode >= 200 && $httpCode < 300;
}
?>
