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

function getUploadErrorMessage($code) {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the allowed size. Please upload a smaller file.';
        case UPLOAD_ERR_PARTIAL:
            return 'The file was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder on the server.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write the uploaded file to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'A PHP extension stopped the file upload.';
        default:
            return 'Unknown upload error.';
    }
}

// Type selection routing (player, sole_proprietor, partnership, corporation)
$selectedType = $_GET['type'] ?? '';
$preselectRole = '';
$preselectBusinessType = '';
switch ($selectedType) {
    case 'player':
        $preselectRole = 'player';
        break;
    case 'sole_proprietor':
        $preselectRole = 'store_owner';
        $preselectBusinessType = 'sole_proprietor';
        break;
    case 'partnership':
        $preselectRole = 'store_owner';
        $preselectBusinessType = 'partnership';
        break;
    case 'corporation':
        $preselectRole = 'store_owner';
        $preselectBusinessType = 'corporation';
        break;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = $_POST['role'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    
    // Store owner specific fields
    $business_name = trim($_POST['business_name'] ?? '');
    $business_address = trim($_POST['business_address'] ?? '');
    $business_email = trim($_POST['business_email'] ?? '');
    $business_phone = trim($_POST['business_phone'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $corp_other_requirements = trim($_POST['corp_other_requirements'] ?? '');
    $business_type = $_POST['business_type'] ?? '';
    
    // File upload handling for store owners
    $bir_document_path = null;
    $valid_id_path = null;
    $dti_document_path = null;
    $sec_certificate_partnership_path = null;
    $articles_partnership_path = null;
    $sec_certificate_incorporation_path = null;
    $articles_bylaws_path = null;
    $board_resolution_path = null;
    
    // Validation
    if (empty($role) || empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill in all required fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($role === 'player' && empty($date_of_birth)) {
        $error = 'Date of birth is required for players.';
    } elseif ($role === 'store_owner' && (empty($business_name) || empty($business_address))) {
        $error = 'Business information is required for store owners.';
    } else {
        try {
            $db = getDB();
            
            // Check if username or email already exists
            $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
            if (!$stmt->execute([$username, $email])) {
                error_log('[REGISTER] Failed to execute duplicate check');
            }
            if ($stmt->fetch()) {
                $error = 'Username or email already exists.';
            } else {
                // Skip file uploads for store owners (reset processing). Document paths remain null.
                
                if (empty($error)) {
                    // Start transaction
                    $db->beginTransaction();
                
                // Insert user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, full_name, phone_number, address, date_of_birth) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash, $role, $full_name, $phone_number ?: null, $address ?: null, $date_of_birth ?: null]);
                
                $user_id = $db->lastInsertId();
                
                // Insert role-specific data
                if ($role === 'player') {
                    $stmt = $db->prepare("INSERT INTO players (user_id) VALUES (?)");
                    $stmt->execute([$user_id]);
                } elseif ($role === 'store_owner') {
                    $stmt = $db->prepare("INSERT INTO store_owners (
                        user_id, business_name, business_address, business_type, business_email, business_phone, website,
                        bir_document_path, valid_id_path,
                        dti_document_path, sec_certificate_partnership_path, articles_partnership_path,
                        sec_certificate_incorporation_path, articles_bylaws_path, board_resolution_path,
                        corp_other_requirements
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $user_id, $business_name, $business_address, $business_type ?: null, $business_email ?: null, $business_phone ?: null, $website ?: null,
                        $bir_document_path, $valid_id_path,
                        $dti_document_path, $sec_certificate_partnership_path, $articles_partnership_path,
                        $sec_certificate_incorporation_path, $articles_bylaws_path, $board_resolution_path,
                        $corp_other_requirements ?: null
                    ]);
                }
                
                // Try to implement email verification, but fallback if it fails
                try {
                    $emailHelper = new EmailHelper();
                    $verificationToken = $emailHelper->generateVerificationToken();
                    $emailHelper->storeVerificationToken($user_id, $verificationToken);
                    
                    // Send verification email
                    $emailSent = $emailHelper->sendVerificationEmail($email, $username, $verificationToken);
                    
                    $db->commit();
                    
                    if ($emailSent) {
                        $success = 'Registration successful! Please check your email and click the verification link to activate your account.';
                    } else {
                        $success = 'Registration successful! However, we could not send the verification email. Please contact support or try logging in to request a new verification email.';
                    }
                } catch (Exception $e) {
                    // Fallback: registration without email verification
                    $db->commit();
                    $success = 'Registration successful! Please log in to continue.';
                    error_log('Email verification failed: ' . $e->getMessage());
                }
                }
            }
        } catch (Throwable $e) {
            if (isset($db) && $db instanceof PDO) {
                try { if ($db->inTransaction()) { $db->rollBack(); } } catch (Throwable $ignored) {}
            }
            error_log('[REGISTER] Error: ' . $e->getMessage());
            $error = 'Registration failed. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
        <style>
        .account-select { display: flex; flex-direction: column; align-items: center; gap: 1.5rem; }
        .account-grid { display: grid; grid-template-columns: repeat(2, minmax(220px, 1fr)); gap: 1.25rem; width: 100%; }
        @media (max-width: 576px) { .account-grid { grid-template-columns: 1fr; } }
        .account-card { border-radius: 1.25rem; padding: 2rem 1.5rem; text-align: center; text-decoration: none; box-shadow: 0 8px 20px rgba(0,0,0,0.08); border: 1px solid rgba(0,0,0,0.05); transition: transform .15s ease, box-shadow .15s ease; background: #f8f9fa; }
        .account-card:hover { transform: translateY(-2px); box-shadow: 0 12px 28px rgba(0,0,0,0.12); }
        .account-card .icon { font-size: 2rem; margin-bottom: .75rem; }
        .account-card .title { font-weight: 600; display: block; color: #212529; }
        .account-card.player { background: #e9f7ef; border-color: #cfe9dc; }
        .account-card.owner { background: #fff1e6; border-color: #f9ddc8; }
        .account-hint { background: #ffffff; border-radius: .75rem; padding: .75rem 1rem; box-shadow: 0 6px 16px rgba(0,0,0,0.06); border: 1px solid rgba(0,0,0,0.05); }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="fw-bold text-primary-custom">
                                <i class="fas fa-crosshairs me-2"></i>AEROZONE
                            </h2>
                            <p class="text-muted">Join the airsoft community</p>
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
                                    <a href="login.php" class="btn btn-success btn-sm me-2">Login Now</a>
                                    <a href="resend-verification.php" class="btn btn-outline-primary btn-sm">Resend Verification Email</a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!$selectedType): ?>
                            <div class="account-select my-2">
                                <img src="../assets/img/logo.svg" alt="Aerozone" style="height:56px" onerror="this.style.display='none'">
                                <h5 class="mb-1 fw-semibold">What type of account do you want to create?</h5>
                                <div class="account-grid">
                                    <a href="register.php?type=player" class="account-card player">
                                        <div class="icon text-success"><i class="fas fa-user"></i></div>
                                        <span class="title">Player Account</span>
                                    </a>
                                    <a href="register.php?type=sole_proprietor" class="account-card owner">
                                        <div class="icon text-warning"><i class="fas fa-store"></i></div>
                                        <span class="title">Store Owner - Sole Proprietor</span>
                                    </a>
                                    <a href="register.php?type=partnership" class="account-card owner">
                                        <div class="icon text-warning"><i class="fas fa-handshake"></i></div>
                                        <span class="title">Store Owner - Partnership</span>
                                    </a>
                                    <a href="register.php?type=corporation" class="account-card owner">
                                        <div class="icon text-warning"><i class="fas fa-building"></i></div>
                                        <span class="title">Store Owner - Corporation</span>
                                    </a>
                                </div>
                                <div class="account-hint mt-2 text-muted small text-center">
                                    Create an account to join games, manage your store, and engage the community.
                                </div>
                            </div>
                        <?php else: ?>

                        <form method="POST" action="" id="registrationForm" enctype="multipart/form-data">
                            <!-- Role Selection -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Account Type *</label>
                                <?php $effectiveRole = $_POST['role'] ?? $preselectRole; ?>
                                <?php if ($effectiveRole): ?>
                                    <div class="alert alert-secondary py-2 mb-2">
                                        <i class="fas fa-id-badge me-2"></i>
                                        <?php echo $effectiveRole === 'player' ? 'Player' : 'Store Owner'; ?> selected
                                    </div>
                                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($effectiveRole); ?>">
                                <?php endif; ?>
                                <div class="row g-2" style="<?php echo $effectiveRole ? 'display:none;' : '';?>">
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="role" id="player" value="player" 
                                               <?php echo (($effectiveRole === 'player') || (isset($_POST['role']) && $_POST['role'] === 'player')) ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-primary w-100" for="player">
                                            <i class="fas fa-user me-2"></i>Player
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="role" id="store_owner" value="store_owner"
                                               <?php echo (($effectiveRole === 'store_owner') || (isset($_POST['role']) && $_POST['role'] === 'store_owner')) ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-success w-100" for="store_owner">
                                            <i class="fas fa-store me-2"></i>Store Owner
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Basic Information -->
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="username" class="form-label">Username *</label>
                                    <input type="text" class="form-control" id="username" name="username" 
                                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="password" class="form-label">Password *</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <div class="form-text">Minimum 8 characters</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name *</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
                            </div>

                            <div class="mb-3" id="dobField">
                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                       value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>">
                            </div>

                            <!-- Store Owner Specific Fields -->
                            <div id="storeOwnerFields" style="display: none;">
                                <hr>
                                <h5 class="text-success mb-3"><i class="fas fa-store me-2"></i>Business Information</h5>
                                
                                <?php $effectiveBizType = $_POST['business_type'] ?? $preselectBusinessType; ?>
                                <div class="mb-3" style="<?php echo $effectiveBizType ? 'display:none;' : '';?>">
                                    <label for="business_type" class="form-label">Business Type *</label>
                                    <select class="form-select" id="business_type" name="business_type">
                                        <option value="" disabled <?php echo empty($effectiveBizType) ? 'selected' : ''; ?>>Select business type</option>
                                        <option value="sole_proprietor" <?php echo ($effectiveBizType === 'sole_proprietor') ? 'selected' : ''; ?>>Sole Proprietor</option>
                                        <option value="partnership" <?php echo ($effectiveBizType === 'partnership') ? 'selected' : ''; ?>>Partnership</option>
                                        <option value="corporation" <?php echo ($effectiveBizType === 'corporation') ? 'selected' : ''; ?>>Corporation</option>
                                    </select>
                                </div>
                                <?php if ($effectiveBizType): ?>
                                    <input type="hidden" name="business_type" value="<?php echo htmlspecialchars($effectiveBizType); ?>">
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <label for="business_name" class="form-label">Business Name *</label>
                                    <input type="text" class="form-control" id="business_name" name="business_name" 
                                           value="<?php echo htmlspecialchars($_POST['business_name'] ?? ''); ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="business_address" class="form-label">Business Address *</label>
                                    <textarea class="form-control" id="business_address" name="business_address" rows="2"><?php echo htmlspecialchars($_POST['business_address'] ?? ''); ?></textarea>
                                </div>

                                

                                <!-- Required Documents -->
                                <hr>
                                <h6 class="text-danger mb-3"><i class="fas fa-file-upload me-2"></i>Required Documents</h6>
                                
                                <div class="alert alert-secondary" id="businessTypeRequirements" role="alert" style="display:none;"></div>

                                <!-- Sole Proprietor specific upload -->
                                <div class="mb-3" id="dtiUpload" style="display:none;">
                                    <label for="dti_document" class="form-label">DTI Business Name Registration * <span class="text-muted" id="dti_document_name"></span></label>
                                    <input type="file" class="form-control" id="dti_document" name="dti_document" accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="form-text">Upload your DTI Business Name Registration (PDF, JPG, PNG, max 5MB)</div>
                                </div>

                                <!-- Partnership specific uploads -->
                                <div id="partnershipUploads" style="display:none;">
                                    <div class="mb-3">
                                        <label for="sec_certificate_partnership" class="form-label">SEC Certificate of Partnership * <span class="text-muted" id="sec_certificate_partnership_name"></span></label>
                                        <input type="file" class="form-control" id="sec_certificate_partnership" name="sec_certificate_partnership" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                    <div class="mb-3">
                                        <label for="articles_partnership" class="form-label">Articles of Partnership * <span class="text-muted" id="articles_partnership_name"></span></label>
                                        <input type="file" class="form-control" id="articles_partnership" name="articles_partnership" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                </div>

                                <!-- Corporation specific uploads -->
                                <div id="corporationUploads" style="display:none;">
                                    <div class="mb-3">
                                        <label for="sec_certificate_incorporation" class="form-label">SEC Certificate of Incorporation * <span class="text-muted" id="sec_certificate_incorporation_name"></span></label>
                                        <input type="file" class="form-control" id="sec_certificate_incorporation" name="sec_certificate_incorporation" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                    <div class="mb-3">
                                        <label for="articles_bylaws" class="form-label">Articles/By-laws * <span class="text-muted" id="articles_bylaws_name"></span></label>
                                        <input type="file" class="form-control" id="articles_bylaws" name="articles_bylaws" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                    <div class="mb-3">
                                        <label for="board_resolution" class="form-label">Board Resolution/Secretary's Certificate * <span class="text-muted" id="board_resolution_name"></span></label>
                                        <input type="file" class="form-control" id="board_resolution" name="board_resolution" accept=".pdf,.jpg,.jpeg,.png">
                                    </div>
                                    <div class="mb-3">
                                        <label for="corp_other_requirements" class="form-label">Other Business Requirements (optional)</label>
                                        <textarea class="form-control" id="corp_other_requirements" name="corp_other_requirements" rows="2" placeholder="List any additional corporation requirements or notes..."></textarea>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="bir_document" class="form-label">BIR Document * <span class="text-muted" id="bir_document_name"></span></label>
                                    <input type="file" class="form-control" id="bir_document" name="bir_document" 
                                           accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="form-text">Upload your BIR registration or business permit (PDF, JPG, PNG, max 5MB)</div>
                                </div>

                                <div class="mb-3">
                                    <label for="valid_id" class="form-label">Valid Government ID * <span class="text-muted" id="valid_id_name"></span></label>
                                    <input type="file" class="form-control" id="valid_id" name="valid_id" 
                                           accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="form-text">Upload a valid government-issued ID (PDF, JPG, PNG, max 5MB)</div>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> Store owner accounts require admin approval. Your documents will be reviewed before approval.
                                </div>
                            </div>

                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="text-decoration-none">Terms of Service</a> and 
                                    <a href="#" class="text-decoration-none">Privacy Policy</a> *
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mb-3">
                                <i class="fas fa-user-plus me-2"></i>Create Account
                            </button>
                        </form>
                        <?php endif; ?>

                        <div class="text-center">
                            <p class="mb-0">
                                Already have an account? 
                                <a href="login.php" class="text-decoration-none fw-bold">Sign in here</a>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="text-center mt-3">
                    <a href="../index.php" class="text-decoration-none text-muted">
                        <i class="fas fa-arrow-left me-2"></i>Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide role-specific fields
        function toggleRoleFields() {
            const playerRadio = document.getElementById('player');
            const storeOwnerRadio = document.getElementById('store_owner');
            const storeOwnerFields = document.getElementById('storeOwnerFields');
            const dobField = document.getElementById('dobField');
            const dobInput = document.getElementById('date_of_birth');
            const businessNameInput = document.getElementById('business_name');
            const businessAddressInput = document.getElementById('business_address');
            const businessTypeSelect = document.getElementById('business_type');
            const dtiUpload = document.getElementById('dtiUpload');
            const partnershipUploads = document.getElementById('partnershipUploads');
            const corporationUploads = document.getElementById('corporationUploads');

            if (storeOwnerRadio.checked) {
                storeOwnerFields.style.display = 'block';
                dobField.querySelector('label').innerHTML = 'Date of Birth';
                dobInput.required = false;
                businessNameInput.required = true;
                businessAddressInput.required = true;
                if (businessTypeSelect) businessTypeSelect.required = false;
                const birEl = document.getElementById('bir_document'); if (birEl) birEl.required = false;
                const idEl = document.getElementById('valid_id'); if (idEl) idEl.required = false;
                updateBusinessRequirements();
                toggleBusinessTypeUploads();
            } else if (playerRadio.checked) {
                storeOwnerFields.style.display = 'none';
                dobField.querySelector('label').innerHTML = 'Date of Birth *';
                dobInput.required = true;
                businessNameInput.required = false;
                businessAddressInput.required = false;
                if (businessTypeSelect) businessTypeSelect.required = false;
                const birEl2 = document.getElementById('bir_document'); if (birEl2) birEl2.required = false;
                const idEl2 = document.getElementById('valid_id'); if (idEl2) idEl2.required = false;
                const reqBox = document.getElementById('businessTypeRequirements');
                if (reqBox) reqBox.style.display = 'none';
                if (dtiUpload) dtiUpload.style.display = 'none';
                if (partnershipUploads) partnershipUploads.style.display = 'none';
                if (corporationUploads) corporationUploads.style.display = 'none';
            }
        }

        function updateBusinessRequirements() {
            const reqBox = document.getElementById('businessTypeRequirements');
            const typeSelect = document.getElementById('business_type');
            if (!reqBox || !typeSelect) return;
            let html = '';
            switch (typeSelect.value) {
                case 'sole_proprietor':
                    html = '<strong>Sole Proprietor requirements:</strong><ul class="mb-0"><li>BIR Certificate of Registration or Mayor\'s/Business Permit</li><li>DTI Business Name Registration (if applicable)</li><li>Valid Government ID of the owner</li></ul>';
                    break;
                case 'partnership':
                    html = '<strong>Partnership requirements:</strong><ul class="mb-0"><li>SEC Certificate of Partnership and Articles of Partnership</li><li>BIR Certificate of Registration</li><li>Valid Government IDs of managing partner/authorized representative</li></ul>';
                    break;
                case 'corporation':
                    html = '<strong>Corporation requirements:</strong><ul class="mb-0"><li>SEC Certificate of Incorporation and Articles/By-laws</li><li>BIR Certificate of Registration</li><li>Board Resolution/Secretary\'s Certificate authorizing the representative</li><li>Valid Government ID of authorized representative</li></ul>';
                    break;
                default:
                    html = '<strong>Select a business type</strong> to view the specific requirements.';
            }
            reqBox.innerHTML = html;
            reqBox.style.display = 'block';
        }

        function toggleBusinessTypeUploads() {
            const typeSelect = document.getElementById('business_type');
            const dti = document.getElementById('dtiUpload');
            const part = document.getElementById('partnershipUploads');
            const corp = document.getElementById('corporationUploads');
            if (!typeSelect) return;
            if (dti) { dti.style.display = (typeSelect.value === 'sole_proprietor') ? 'block' : 'none'; }
            if (part) { part.style.display = (typeSelect.value === 'partnership') ? 'block' : 'none'; }
            if (corp) { corp.style.display = (typeSelect.value === 'corporation') ? 'block' : 'none'; }

            // ensure none of these uploads are required
            const ids = ['dti_document','sec_certificate_partnership','articles_partnership','sec_certificate_incorporation','articles_bylaws','board_resolution'];
            ids.forEach(id => { const el = document.getElementById(id); if (el) el.required = false; });
        }

        // Add event listeners
        document.getElementById('player').addEventListener('change', toggleRoleFields);
        document.getElementById('store_owner').addEventListener('change', toggleRoleFields);
        const businessTypeEl = document.getElementById('business_type');
        if (businessTypeEl) {
            businessTypeEl.addEventListener('change', updateBusinessRequirements);
            businessTypeEl.addEventListener('change', toggleBusinessTypeUploads);
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleRoleFields();
            updateBusinessRequirements();
            toggleBusinessTypeUploads();

            const bindFileLabel = (inputId, labelSpanId) => {
                const input = document.getElementById(inputId);
                const labelSpan = document.getElementById(labelSpanId);
                if (!input || !labelSpan) return;
                input.addEventListener('change', function() {
                    const fileName = this.files && this.files.length ? this.files[0].name : '';
                    labelSpan.textContent = fileName ? `(${fileName})` : '';
                });
            };

            bindFileLabel('dti_document', 'dti_document_name');
            bindFileLabel('sec_certificate_partnership', 'sec_certificate_partnership_name');
            bindFileLabel('articles_partnership', 'articles_partnership_name');
            bindFileLabel('sec_certificate_incorporation', 'sec_certificate_incorporation_name');
            bindFileLabel('articles_bylaws', 'articles_bylaws_name');
            bindFileLabel('board_resolution', 'board_resolution_name');
            bindFileLabel('bir_document', 'bir_document_name');
            bindFileLabel('valid_id', 'valid_id_name');
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
