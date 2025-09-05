<?php
require_once 'database.php';
require_once 'email_config.php';

class EmailHelper {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Generate a secure verification token
     */
    public function generateVerificationToken() {
        return bin2hex(random_bytes(32));
    }
    
    /**
     * Send verification email
     */
    public function sendVerificationEmail($email, $username, $token) {
        $verificationLink = $this->getVerificationLink($token);
        
        $subject = "Verify Your Email - AEROZONE";
        $message = $this->getVerificationEmailTemplate($username, $verificationLink);
        $headers = $this->getEmailHeaders();
        
        // Check if email is properly configured
        if (!isEmailConfigured()) {
            // Fallback to development mode
            error_log("=== EMAIL VERIFICATION (DEVELOPMENT) ===");
            error_log("To: " . $email);
            error_log("Subject: " . $subject);
            error_log("Verification Link: " . $verificationLink);
            error_log("========================================");
            return true; // Return true to simulate successful email sending
        }
        
        // Try to send actual email
        $result = sendEmail($email, $subject, $message, $headers);
        
        if (!$result) {
            // If email sending fails, log it for development
            error_log("=== EMAIL VERIFICATION (FAILED) ===");
            error_log("To: " . $email);
            error_log("Subject: " . $subject);
            error_log("Verification Link: " . $verificationLink);
            error_log("========================================");
        }
        
        return $result;
    }
    
    /**
     * Get verification link
     */
    private function getVerificationLink($token) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        
        // Include the project folder name in the path
        return $baseUrl . '/aerozone/auth/verify-email.php?token=' . $token;
    }
    
    /**
     * Get email verification template
     */
    private function getVerificationEmailTemplate($username, $verificationLink) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Verify Your Email - AEROZONE</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .button { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1><i class='fas fa-crosshairs'></i> AEROZONE</h1>
                </div>
                <div class='content'>
                    <h2>Welcome to AEROZONE!</h2>
                    <p>Hi <strong>{$username}</strong>,</p>
                    <p>Thank you for registering with AEROZONE! To complete your registration and activate your account, please verify your email address by clicking the button below:</p>
                    
                    <div style='text-align: center;'>
                        <a href='{$verificationLink}' class='button'>Verify Email Address</a>
                    </div>
                    
                    <p>If the button doesn't work, you can copy and paste this link into your browser:</p>
                    <p style='word-break: break-all; color: #007bff;'>{$verificationLink}</p>
                    
                    <p><strong>Important:</strong> This verification link will expire in 24 hours for security reasons.</p>
                    
                    <p>If you didn't create an account with AEROZONE, you can safely ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>This is an automated message from AEROZONE. Please do not reply to this email.</p>
                    <p>&copy; " . date('Y') . " AEROZONE. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get email headers
     */
    private function getEmailHeaders() {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "MIME-Version: 1.0\r\n" .
               "Content-Type: text/html; charset=UTF-8\r\n" .
               "From: AEROZONE <noreply@" . $host . ">\r\n" .
               "Reply-To: noreply@" . $host . "\r\n" .
               "X-Mailer: PHP/" . phpversion();
    }
    
    /**
     * Store verification token in database
     */
    public function storeVerificationToken($user_id, $token) {
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET email_verification_token = ?, email_verification_expires = ? 
            WHERE user_id = ?
        ");
        
        return $stmt->execute([$token, $expires, $user_id]);
    }
    
    /**
     * Verify email token
     */
    public function verifyEmailToken($token) {
        $stmt = $this->db->prepare("
            SELECT user_id, email_verification_expires 
            FROM users 
            WHERE email_verification_token = ? AND email_verified = FALSE
        ");
        
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired verification token.'];
        }
        
        // Check if token has expired
        if (strtotime($user['email_verification_expires']) < time()) {
            return ['success' => false, 'message' => 'Verification token has expired. Please request a new one.'];
        }
        
        // Mark email as verified
        $stmt = $this->db->prepare("
            UPDATE users 
            SET email_verified = TRUE, 
                email_verified_at = CURRENT_TIMESTAMP,
                email_verification_token = NULL,
                email_verification_expires = NULL
            WHERE user_id = ?
        ");
        
        if ($stmt->execute([$user['user_id']])) {
            return ['success' => true, 'message' => 'Email verified successfully!'];
        } else {
            return ['success' => false, 'message' => 'Failed to verify email. Please try again.'];
        }
    }
    
    /**
     * Resend verification email
     */
    public function resendVerificationEmail($email) {
        $stmt = $this->db->prepare("
            SELECT user_id, username, email, email_verified 
            FROM users 
            WHERE email = ?
        ");
        
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return ['success' => false, 'message' => 'Email address not found.'];
        }
        
        if ($user['email_verified']) {
            return ['success' => false, 'message' => 'Email is already verified.'];
        }
        
        // Generate new token
        $token = $this->generateVerificationToken();
        
        // Store new token
        if ($this->storeVerificationToken($user['user_id'], $token)) {
            // Send new verification email
            if ($this->sendVerificationEmail($user['email'], $user['username'], $token)) {
                return ['success' => true, 'message' => 'Verification email sent successfully!'];
            } else {
                return ['success' => false, 'message' => 'Failed to send verification email. Please try again.'];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to generate verification token. Please try again.'];
        }
    }
    
    /**
     * Clean up expired verification tokens
     */
    public function cleanupExpiredTokens() {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET email_verification_token = NULL, 
                email_verification_expires = NULL 
            WHERE email_verification_expires < CURRENT_TIMESTAMP
        ");
        
        return $stmt->execute();
    }
}
?>
