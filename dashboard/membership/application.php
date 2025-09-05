<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('player');

$user = getCurrentUser();
$db = getDB();

$message = '';
$message_type = '';

// Get current player info
try {
    $stmt = $db->prepare("
        SELECT p.*, u.full_name, u.email, u.phone_number, u.address, u.date_of_birth
        FROM players p
        JOIN users u ON p.user_id = u.user_id
        WHERE p.user_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $player_info = $stmt->fetch();
} catch (PDOException $e) {
    $error_message = "Error loading player information: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    
    // Validation
    $errors = [];
    if (empty($full_name)) $errors[] = "Full name is required";
    if (empty($phone_number)) $errors[] = "Phone number is required";
    if (empty($address)) $errors[] = "Address is required";
    if (empty($date_of_birth)) $errors[] = "Date of birth is required";
    if (empty($emergency_contact_name)) $errors[] = "Emergency contact name is required";
    if (empty($emergency_contact_phone)) $errors[] = "Emergency contact phone is required";
    
    // Check age (must be 18 or older)
    if ($date_of_birth) {
        $birth_date = new DateTime($date_of_birth);
        $today = new DateTime();
        $age = $today->diff($birth_date)->y;
        if ($age < 18) {
            $errors[] = "You must be at least 18 years old to apply for membership";
        }
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update user information
            $stmt = $db->prepare("
                UPDATE users 
                SET full_name = ?, phone_number = ?, address = ?, date_of_birth = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$full_name, $phone_number, $address, $date_of_birth, $user['user_id']]);
            
            // Update player information
            $stmt = $db->prepare("
                UPDATE players 
                SET emergency_contact_name = ?, emergency_contact_phone = ?, membership_status = 'pending'
                WHERE user_id = ?
            ");
            $stmt->execute([$emergency_contact_name, $emergency_contact_phone, $user['user_id']]);
            
            // Create notification for admins
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message)
                SELECT u.user_id, 'system_announcement', 'New Membership Application', 
                       CONCAT('New membership application from ', ?)
                FROM users u 
                WHERE u.role = 'admin'
            ");
            $stmt->execute([$full_name]);
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, table_affected, record_id)
                VALUES (?, 'Membership application submitted', 'players', ?)
            ");
            $stmt->execute([$user['user_id'], $player_info['player_id']]);
            
            $db->commit();
            
            $message = "Your membership application has been submitted successfully! You will be notified once it's reviewed.";
            $message_type = "success";
            
            // Refresh player info
            $stmt = $db->prepare("
                SELECT p.*, u.full_name, u.email, u.phone_number, u.address, u.date_of_birth
                FROM players p
                JOIN users u ON p.user_id = u.user_id
                WHERE p.user_id = ?
            ");
            $stmt->execute([$user['user_id']]);
            $player_info = $stmt->fetch();
            
        } catch (PDOException $e) {
            $db->rollBack();
            $message = "Error submitting application: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = implode(', ', $errors);
        $message_type = "danger";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Application - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-file-alt me-2"></i>Membership Application
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="policies.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Membership Info
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Current Status -->
                        <?php if ($player_info): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-info-circle me-2"></i>Current Status
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-<?php 
                                            switch($player_info['membership_status']) {
                                                case 'active': echo 'success'; break;
                                                case 'pending': echo 'warning'; break;
                                                case 'inactive': echo 'secondary'; break;
                                                case 'suspended': echo 'danger'; break;
                                                default: echo 'secondary'; break;
                                            }
                                        ?> fs-6 me-3">
                                            <?php echo ucfirst($player_info['membership_status']); ?>
                                        </span>
                                        
                                        <?php if ($player_info['membership_status'] === 'pending'): ?>
                                            <span class="text-muted">Your application is under review</span>
                                        <?php elseif ($player_info['membership_status'] === 'active'): ?>
                                            <span class="text-success">
                                                Active since <?php echo date('F j, Y', strtotime($player_info['membership_date'])); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">Complete your application below</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Application Form -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-edit me-2"></i>
                                    <?php echo ($player_info['membership_status'] === 'pending' || $player_info['membership_status'] === 'active') ? 'Update Information' : 'Application Form'; ?>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                                       value="<?php echo htmlspecialchars($player_info['full_name'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email Address</label>
                                                <input type="email" class="form-control" id="email" 
                                                       value="<?php echo htmlspecialchars($player_info['email'] ?? ''); ?>" readonly>
                                                <div class="form-text">Email cannot be changed here. Contact support if needed.</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="phone_number" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                                       value="<?php echo htmlspecialchars($player_info['phone_number'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="date_of_birth" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                                       value="<?php echo htmlspecialchars($player_info['date_of_birth'] ?? ''); ?>" required>
                                                <div class="form-text">You must be at least 18 years old.</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="address" class="form-label">Complete Address <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($player_info['address'] ?? ''); ?></textarea>
                                    </div>

                                    <hr class="my-4">
                                    <h6 class="mb-3">Emergency Contact Information</h6>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="emergency_contact_name" class="form-label">Emergency Contact Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                                       value="<?php echo htmlspecialchars($player_info['emergency_contact_name'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone <span class="text-danger">*</span></label>
                                                <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                                       value="<?php echo htmlspecialchars($player_info['emergency_contact_phone'] ?? ''); ?>" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="agree_terms" required>
                                            <label class="form-check-label" for="agree_terms">
                                                I agree to the <a href="policies.php" target="_blank">membership policies</a>, 
                                                <a href="policies.php" target="_blank">safety guidelines</a>, and 
                                                <a href="policies.php" target="_blank">community rules</a> <span class="text-danger">*</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i>
                                            <?php echo ($player_info['membership_status'] === 'pending' || $player_info['membership_status'] === 'active') ? 'Update Information' : 'Submit Application'; ?>
                                        </button>
                                        <a href="policies.php" class="btn btn-outline-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Application Requirements -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-clipboard-list me-2"></i>Requirements
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Must be 18 years or older
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Valid contact information
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Emergency contact details
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Agreement to community rules
                                    </li>
                                    <li class="mb-0">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Commitment to safety guidelines
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Application Process -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-route me-2"></i>Application Process
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="badge bg-primary rounded-pill">1</span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Submit Application</h6>
                                        <small class="text-muted">Complete and submit your membership application form</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start mb-3">
                                    <div class="flex-shrink-0">
                                        <span class="badge bg-warning rounded-pill">2</span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Review Process</h6>
                                        <small class="text-muted">Admin team reviews your application (1-3 business days)</small>
                                    </div>
                                </div>
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0">
                                        <span class="badge bg-success rounded-pill">3</span>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">Approval</h6>
                                        <small class="text-muted">Receive notification and gain full access to community features</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Help & Support -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-question-circle me-2"></i>Need Help?
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">
                                    Having trouble with your application? Our support team is here to help.
                                </p>
                                <div class="d-grid gap-2">
                                    <a href="../messages/compose.php" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-envelope me-1"></i>Contact Support
                                    </a>
                                    <a href="policies.php" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-info-circle me-1"></i>View Policies
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // Calculate age when date of birth changes
        document.getElementById('date_of_birth').addEventListener('change', function() {
            const birthDate = new Date(this.value);
            const today = new Date();
            const age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            if (age < 18) {
                showToast('You must be at least 18 years old to apply for membership', 'warning');
            }
        });
    </script>
</body>
</html>