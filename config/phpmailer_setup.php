<?php
/**
 * PHPMailer Setup for AEROZONE
 * This file sets up PHPMailer for email sending
 */

// Check if PHPMailer is available via Composer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    define('PHPMailer_AVAILABLE', true);
} else {
    define('PHPMailer_AVAILABLE', false);
}

/**
 * Send email using PHPMailer if available
 */
function sendEmailWithPHPMailer($to, $subject, $message, $config) {
    if (!PHPMailer_AVAILABLE) {
        return false;
    }
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['smtp_port'];
        
        // Enable debug output (optional)
        // $mail->SMTPDebug = 2;
        
        // Recipients
        $mail->setFrom($config['from_address'], $config['from_name']);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $e->getMessage());
        return false;
    }
}
?>
