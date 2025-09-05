<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

$message = '';
$message_type = '';

// Get user's role-specific information
$user_data = null;
$role_specific_data = null;

try {
    // Get basic user information
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $user_data = $stmt->fetch();

    // Get role-specific information
    if ($user['role'] === 'admin') {
        $stmt = $db->prepare("SELECT * FROM admins WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $role_specific_data = $stmt->fetch();
    } elseif ($user['role'] === 'player') {
        $stmt = $db->prepare("SELECT * FROM players WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $role_specific_data = $stmt->fetch();
    } elseif ($user['role'] === 'store_owner') {
        $stmt = $db->prepare("SELECT * FROM store_owners WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $role_specific_data = $stmt->fetch();
    }

    // Get user statistics
    $stats = [];
    
    if ($user['role'] === 'player') {
        // Player statistics
        $stmt = $db->prepare("SELECT COUNT(*) as inventory_count FROM player_inventory WHERE player_id = ? AND is_active = 1");
        $stmt->execute([$role_specific_data['player_id']]);
        $stats['inventory_count'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) as appointments_count FROM maintenance_appointments WHERE player_id = ?");
        $stmt->execute([$role_specific_data['player_id']]);
        $stats['appointments_count'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) as marketplace_listings FROM marketplace_listings WHERE seller_id = ? AND status = 'active'");
        $stmt->execute([$user['user_id']]);
        $stats['marketplace_listings'] = $stmt->fetchColumn();
        
    } elseif ($user['role'] === 'store_owner') {
        // Store owner statistics
        $stmt = $db->prepare("SELECT COUNT(*) as appointments_count FROM maintenance_appointments WHERE store_owner_id = ?");
        $stmt->execute([$role_specific_data['store_owner_id']]);
        $stats['appointments_count'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) as inventory_count FROM store_inventory WHERE store_owner_id = ? AND is_available = 1");
        $stmt->execute([$role_specific_data['store_owner_id']]);
        $stats['inventory_count'] = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT COUNT(*) as employees_count FROM store_employees WHERE store_owner_id = ? AND is_active = 1");
        $stmt->execute([$role_specific_data['store_owner_id']]);
        $stats['employees_count'] = $stmt->fetchColumn();
        
    } elseif ($user['role'] === 'admin') {
        // Admin statistics
        $stmt = $db->query("SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'");
        $stats['total_users'] = $stmt->fetchColumn();
        
        $stmt = $db->query("SELECT COUNT(*) as pending_stores FROM store_owners WHERE registration_status = 'pending'");
        $stats['pending_stores'] = $stmt->fetchColumn();
        
        $stmt = $db->query("SELECT COUNT(*) as total_appointments FROM maintenance_appointments");
        $stats['total_appointments'] = $stmt->fetchColumn();
    }

} catch (PDOException $e) {
    $error_message = "Error fetching profile data: " . $e->getMessage();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'update_profile') {
                // Update basic user information
                $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone_number = ?, address = ?, date_of_birth = ? WHERE user_id = ?");
                $stmt->execute([
                    $_POST['full_name'],
                    $_POST['email'],
                    $_POST['phone_number'],
                    $_POST['address'],
                    $_POST['date_of_birth'] ?: null,
                    $user['user_id']
                ]);
                
                // Update role-specific information
                if ($user['role'] === 'player' && $role_specific_data) {
                    $stmt = $db->prepare("UPDATE players SET emergency_contact_name = ?, emergency_contact_phone = ? WHERE player_id = ?");
                    $stmt->execute([
                        $_POST['emergency_contact_name'],
                        $_POST['emergency_contact_phone'],
                        $role_specific_data['player_id']
                    ]);
                } elseif ($user['role'] === 'store_owner' && $role_specific_data) {
                    $stmt = $db->prepare("UPDATE store_owners SET business_name = ?, business_address = ?, business_email = ?, business_phone = ?, website = ? WHERE store_owner_id = ?");
                    $stmt->execute([
                        $_POST['business_name'],
                        $_POST['business_address'],
                        $_POST['business_email'],
                        $_POST['business_phone'],
                        $_POST['website'],
                        $role_specific_data['store_owner_id']
                    ]);
                }
                
                $message = 'Profile updated successfully!';
                $message_type = 'success';
                
                // Refresh user data
                $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
                $stmt->execute([$user['user_id']]);
                $user_data = $stmt->fetch();
                
            } elseif ($_POST['action'] === 'change_password') {
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                // Verify current password
                if (!password_verify($current_password, $user_data['password_hash'])) {
                    $message = 'Current password is incorrect.';
                    $message_type = 'danger';
                } elseif ($new_password !== $confirm_password) {
                    $message = 'New passwords do not match.';
                    $message_type = 'danger';
                } elseif (strlen($new_password) < 8) {
                    $message = 'New password must be at least 8 characters long.';
                    $message_type = 'danger';
                } else {
                    // Update password
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $stmt->execute([$new_password_hash, $user['user_id']]);
                    
                    $message = 'Password changed successfully!';
                    $message_type = 'success';
                }
            }
            
            // Log activity
            $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_affected, record_id) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user['user_id'], "Profile " . $_POST['action'] . "ed", 'users', $user['user_id']]);
            
        } catch (PDOException $e) {
            $message = 'Error processing request: ' . $e->getMessage();
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
    <title>Profile - AEROZONE</title>
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
                        <i class="fas fa-user me-2"></i>Profile
                    </h1>
                </div>

                <?php if (isset($message)): ?>
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
                    <!-- Profile Information -->
                    <div class="col-md-8">
                        <!-- Basic Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-user-circle me-2"></i>Basic Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="full_name" class="form-label">Full Name *</label>
                                                <input type="text" class="form-control" id="full_name" name="full_name" 
                                                       value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Email *</label>
                                                <input type="email" class="form-control" id="email" name="email" 
                                                       value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="phone_number" class="form-label">Phone Number</label>
                                                <input type="tel" class="form-control" id="phone_number" name="phone_number" 
                                                       value="<?php echo htmlspecialchars($user_data['phone_number'] ?? ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="date_of_birth" class="form-label">Date of Birth</label>
                                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                                       value="<?php echo $user_data['date_of_birth'] ?? ''; ?>">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address" class="form-label">Address</label>
                                        <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($user_data['address'] ?? ''); ?></textarea>
                                    </div>

                                    <?php if ($user['role'] === 'player' && $role_specific_data): ?>
                                        <!-- Player-specific fields -->
                                        <hr>
                                        <h6 class="mb-3">Emergency Contact Information</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="emergency_contact_name" class="form-label">Emergency Contact Name</label>
                                                    <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" 
                                                           value="<?php echo htmlspecialchars($role_specific_data['emergency_contact_name'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="emergency_contact_phone" class="form-label">Emergency Contact Phone</label>
                                                    <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" 
                                                           value="<?php echo htmlspecialchars($role_specific_data['emergency_contact_phone'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($user['role'] === 'store_owner' && $role_specific_data): ?>
                                        <!-- Store owner-specific fields -->
                                        <hr>
                                        <h6 class="mb-3">Business Information</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="business_name" class="form-label">Business Name *</label>
                                                    <input type="text" class="form-control" id="business_name" name="business_name" 
                                                           value="<?php echo htmlspecialchars($role_specific_data['business_name']); ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="business_email" class="form-label">Business Email</label>
                                                    <input type="email" class="form-control" id="business_email" name="business_email" 
                                                           value="<?php echo htmlspecialchars($role_specific_data['business_email'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="business_phone" class="form-label">Business Phone</label>
                                                    <input type="tel" class="form-control" id="business_phone" name="business_phone" 
                                                           value="<?php echo htmlspecialchars($role_specific_data['business_phone'] ?? ''); ?>">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="website" class="form-label">Website</label>
                                                    <input type="url" class="form-control" id="website" name="website" 
                                                           value="<?php echo htmlspecialchars($role_specific_data['website'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="business_address" class="form-label">Business Address</label>
                                            <textarea class="form-control" id="business_address" name="business_address" rows="3"><?php echo htmlspecialchars($role_specific_data['business_address'] ?? ''); ?></textarea>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-1"></i>Update Profile
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-lock me-2"></i>Change Password
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Current Password *</label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">New Password *</label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-key me-1"></i>Change Password
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Sidebar -->
                    <div class="col-md-4">
                        <!-- Profile Summary -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Profile Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <div class="avatar-placeholder bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px; font-size: 2rem;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <h5 class="mt-2 mb-1"><?php echo htmlspecialchars($user_data['full_name']); ?></h5>
                                    <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'store_owner' ? 'success' : 'primary'); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                                    </span>
                                </div>
                                
                                <hr>
                                
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h6 class="text-muted">Member Since</h6>
                                        <p class="mb-0"><?php echo date('M Y', strtotime($user_data['created_at'])); ?></p>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="text-muted">Last Login</h6>
                                        <p class="mb-0"><?php echo $user_data['last_login'] ? date('M j, Y', strtotime($user_data['last_login'])) : 'Never'; ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Statistics -->
                        <?php if (!empty($stats)): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-chart-bar me-2"></i>Statistics
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if ($user['role'] === 'player'): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Inventory Items</span>
                                            <span class="badge bg-primary"><?php echo $stats['inventory_count']; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Appointments</span>
                                            <span class="badge bg-info"><?php echo $stats['appointments_count']; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Marketplace Listings</span>
                                            <span class="badge bg-success"><?php echo $stats['marketplace_listings']; ?></span>
                                        </div>
                                    <?php elseif ($user['role'] === 'store_owner'): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Total Appointments</span>
                                            <span class="badge bg-primary"><?php echo $stats['appointments_count']; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Inventory Items</span>
                                            <span class="badge bg-info"><?php echo $stats['inventory_count']; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Employees</span>
                                            <span class="badge bg-success"><?php echo $stats['employees_count']; ?></span>
                                        </div>
                                    <?php elseif ($user['role'] === 'admin'): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Total Users</span>
                                            <span class="badge bg-primary"><?php echo $stats['total_users']; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Pending Stores</span>
                                            <span class="badge bg-warning"><?php echo $stats['pending_stores']; ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span>Total Appointments</span>
                                            <span class="badge bg-info"><?php echo $stats['total_appointments']; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Quick Actions -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bolt me-2"></i>Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if ($user['role'] === 'player'): ?>
                                        <a href="../inventory/index.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-box me-1"></i>Manage Inventory
                                        </a>
                                        <a href="../appointments/index.php" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-calendar me-1"></i>Schedule Maintenance
                                        </a>
                                        <a href="../marketplace/index.php" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-store me-1"></i>Marketplace
                                        </a>
                                    <?php elseif ($user['role'] === 'store_owner'): ?>
                                        <a href="../appointments/manage.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-calendar-check me-1"></i>Manage Appointments
                                        </a>
                                        <a href="../inventory/store.php" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-box me-1"></i>Store Inventory
                                        </a>
                                        <a href="../services/index.php" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-tools me-1"></i>Manage Services
                                        </a>
                                    <?php elseif ($user['role'] === 'admin'): ?>
                                        <a href="../stores/index.php" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-store me-1"></i>Store Management
                                        </a>
                                        <a href="../users/index.php" class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-users me-1"></i>User Management
                                        </a>
                                        <a href="../reports/index.php" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-chart-line me-1"></i>Reports
                                        </a>
                                    <?php endif; ?>
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
</body>
</html> 