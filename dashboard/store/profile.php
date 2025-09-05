<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('store_owner');

$user = getCurrentUser();
$db = getDB();

$message = '';
$message_type = '';

// Get store owner information
$stmt = $db->prepare("
    SELECT so.*, u.full_name, u.email, u.phone_number, u.address
    FROM store_owners so
    JOIN users u ON so.user_id = u.user_id
    WHERE so.user_id = ?
");
$stmt->execute([$user['user_id']]);
$store = $stmt->fetch();

if (!$store) {
    header('Location: ../index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'update_profile') {
                // Update user information (only required fields)
                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
                $stmt->execute([
                    $_POST['full_name'],
                    $_POST['email'],
                    $user['user_id']
                ]);
                
                // Update store information (only required fields)
                $stmt = $db->prepare("UPDATE store_owners SET business_name = ?, business_address = ?, business_type = ? WHERE user_id = ?");
                $stmt->execute([
                    $_POST['business_name'],
                    $_POST['business_address'],
                    $_POST['business_type'],
                    $user['user_id']
                ]);
                
                $message = 'Profile updated successfully!';
                $message_type = 'success';
                
                // Refresh store data
                $stmt = $db->prepare("
                    SELECT so.*, u.full_name, u.email, u.phone_number, u.address
                    FROM store_owners so
                    JOIN users u ON so.user_id = u.user_id
                    WHERE so.user_id = ?
                ");
                $stmt->execute([$user['user_id']]);
                $store = $stmt->fetch();
                
            } elseif ($_POST['action'] === 'upload_documents') {
                $upload_dir = '../../uploads/documents/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $bir_document = null;
                $valid_id = null;
                
                // Handle BIR document upload
                if (isset($_FILES['bir_document']) && $_FILES['bir_document']['error'] === UPLOAD_ERR_OK) {
                    $file_extension = pathinfo($_FILES['bir_document']['name'], PATHINFO_EXTENSION);
                    $bir_document = 'bir_' . $user['user_id'] . '_' . time() . '.' . $file_extension;
                    move_uploaded_file($_FILES['bir_document']['tmp_name'], $upload_dir . $bir_document);
                }
                
                // Handle Valid ID upload
                if (isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] === UPLOAD_ERR_OK) {
                    $file_extension = pathinfo($_FILES['valid_id']['name'], PATHINFO_EXTENSION);
                    $valid_id = 'id_' . $user['user_id'] . '_' . time() . '.' . $file_extension;
                    move_uploaded_file($_FILES['valid_id']['tmp_name'], $upload_dir . $valid_id);
                }
                
                // Update database
                $update_fields = [];
                $update_params = [];
                
                if ($bir_document) {
                    $update_fields[] = "bir_document_path = ?";
                    $update_params[] = $bir_document;
                }
                
                if ($valid_id) {
                    $update_fields[] = "valid_id_path = ?";
                    $update_params[] = $valid_id;
                }
                
                if (!empty($update_fields)) {
                    $update_params[] = $user['user_id'];
                    $stmt = $db->prepare("UPDATE store_owners SET " . implode(', ', $update_fields) . " WHERE user_id = ?");
                    $stmt->execute($update_params);
                    
                    $message = 'Documents uploaded successfully! Your registration is now pending admin approval.';
                    $message_type = 'success';
                    
                    // Refresh store data
                    $stmt = $db->prepare("
                        SELECT so.*, u.full_name, u.email, u.phone_number, u.address
                        FROM store_owners so
                        JOIN users u ON so.user_id = u.user_id
                        WHERE so.user_id = ?
                    ");
                    $stmt->execute([$user['user_id']]);
                    $store = $stmt->fetch();
                }
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Profile - AEROZONE</title>
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
                        <i class="fas fa-store me-2"></i>Store Profile
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="badge bg-<?php 
                            echo $store['registration_status'] === 'approved' ? 'success' : 
                                ($store['registration_status'] === 'pending' ? 'warning' : 
                                ($store['registration_status'] === 'rejected' ? 'danger' : 'secondary')); 
                        ?> fs-6">
                            <?php echo ucfirst($store['registration_status']); ?>
                        </span>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Registration Status Alert -->
                <?php if ($store['registration_status'] === 'pending'): ?>
                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-clock me-2"></i>
                        <strong>Registration Pending:</strong> Your store registration is currently under review by our administrators.
                    </div>
                <?php elseif ($store['registration_status'] === 'rejected'): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-times-circle me-2"></i>
                        <strong>Registration Rejected:</strong> Your store registration has been rejected. Please contact support for more information or resubmit your documents.
                    </div>
                <?php elseif ($store['registration_status'] === 'approved'): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Registration Approved:</strong> Your store is now active on the platform! You can start managing appointments and services.
                    </div>
                <?php endif; ?>

                <div class="row g-4">
                    <!-- Store Information -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Store Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="business_name" class="form-label">Business Name *</label>
                                            <input type="text" class="form-control" id="business_name" name="business_name" 
                                                   value="<?php echo htmlspecialchars($store['business_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="full_name" class="form-label">Owner Name *</label>
                                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                                   value="<?php echo htmlspecialchars($store['full_name']); ?>" required>
                                        </div>
                                        
                                        <div class="col-12">
                                            <label for="business_address" class="form-label">Business Address *</label>
                                            <textarea class="form-control" id="business_address" name="business_address" rows="2" required><?php echo htmlspecialchars($store['business_address']); ?></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="business_type" class="form-label">Business Type *</n+label>
                                            <select class="form-select" id="business_type" name="business_type" required>
                                                <option value="" disabled <?php echo empty($store['business_type']) ? 'selected' : ''; ?>>Select business type</option>
                                                <option value="sole_proprietor" <?php echo ($store['business_type'] === 'sole_proprietor') ? 'selected' : ''; ?>>Sole Proprietor</option>
                                                <option value="partnership" <?php echo ($store['business_type'] === 'partnership') ? 'selected' : ''; ?>>Partnership</option>
                                                <option value="corporation" <?php echo ($store['business_type'] === 'corporation') ? 'selected' : ''; ?>>Corporation</option>
                                            </select>
                                        </div>

                                        
                                        <div class="col-md-6">
                                            <label for="email" class="form-label">Personal Email *</label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($store['email']); ?>" required>
                                        </div>
                                        
                                        
                                        
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Update Profile
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <!-- Registration Timeline -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-timeline me-2"></i>Registration Timeline
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <div class="timeline-item completed">
                                        <div class="timeline-marker bg-success"></div>
                                        <div class="timeline-content">
                                            <h6>Account Created</h6>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($store['registration_date'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-item <?php echo ($store['bir_document_path'] && $store['valid_id_path']) ? 'completed' : 'pending'; ?>">
                                        <div class="timeline-marker bg-<?php echo ($store['bir_document_path'] && $store['valid_id_path']) ? 'success' : 'warning'; ?>"></div>
                                        <div class="timeline-content">
                                            <h6>Documents Submitted</h6>
                                            <small class="text-muted">
                                                <?php if ($store['bir_document_path'] && $store['valid_id_path']): ?>
                                                    Completed
                                                <?php else: ?>
                                                    Pending
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="timeline-item <?php echo $store['registration_status'] === 'approved' ? 'completed' : 'pending'; ?>">
                                        <div class="timeline-marker bg-<?php echo $store['registration_status'] === 'approved' ? 'success' : 'secondary'; ?>"></div>
                                        <div class="timeline-content">
                                            <h6>Admin Approval</h6>
                                            <small class="text-muted">
                                                <?php if ($store['approved_at'] ?? null): ?>
                                                    <?php echo date('M j, Y', strtotime($store['approved_at'])); ?>
                                                <?php else: ?>
                                                    Pending
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
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
    <style>
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-marker {
            position: absolute;
            left: -22px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .timeline-content h6 {
            margin-bottom: 2px;
            font-size: 0.9rem;
        }
    </style>
</body>
</html>
