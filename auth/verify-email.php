<?php
require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/email_helper.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ../dashboard/index.php');
    exit();
}

$error = '';
$success = '';
$showResendForm = false;

// Handle email verification
if (isset($_GET['token'])) {
    $token = trim($_GET['token']);
    
    if (empty($token)) {
        $error = 'Invalid verification link.';
    } else {
        $emailHelper = new EmailHelper();
        $result = $emailHelper->verifyEmailToken($token);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
            $showResendForm = true;
        }
    }
}

// Handle resend verification email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_email'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $emailHelper = new EmailHelper();
        $result = $emailHelper->resendVerificationEmail($email);
        
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary-custom">
                                <i class="fas fa-crosshairs me-2"></i>AEROZONE
                            </h2>
                            <p class="text-muted">Email Verification</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                                <div class="mt-3">
                                    <a href="login.php" class="btn btn-success">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login Now
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($showResendForm): ?>
                            <div class="text-center mb-4">
                                <i class="fas fa-envelope-open-text text-muted" style="font-size: 3rem;"></i>
                                <h5 class="mt-3">Verification Link Expired</h5>
                                <p class="text-muted">Your verification link has expired or is invalid. Please request a new one.</p>
                            </div>

                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                                <button type="submit" name="resend_email" class="btn btn-primary w-100 mb-3">
                                    <i class="fas fa-paper-plane me-2"></i>Resend Verification Email
                                </button>
                            </form>
                        <?php endif; ?>

                        <div class="text-center">
                            <p class="mb-0">
                                <a href="login.php" class="text-decoration-none">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Login
                                </a>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="../index.php" class="text-decoration-none text-muted">
                        <i class="fas fa-home me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
