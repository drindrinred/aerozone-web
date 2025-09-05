<?php
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();
// Block pending or rejected store owners from accessing dashboard
redirectIfStoreOwnerNotApproved();

$user = getCurrentUser();
$db = getDB();

// Get role-specific dashboard data
$dashboardData = [];

try {
    // General user info
    $stmt = $db->prepare("SELECT last_login FROM users WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $dashboardData['last_login'] = ($stmt->fetch()['last_login'] ?? null);

    if ($user['role'] === 'admin') {
        // Admin dashboard data
        $stmt = $db->query("SELECT COUNT(*) as total_users FROM users WHERE is_active = 1");
        $dashboardData['total_users'] = $stmt->fetch()['total_users'];
        
        $stmt = $db->query("SELECT COUNT(*) as pending_stores FROM store_owners WHERE registration_status = 'pending'");
        $dashboardData['pending_stores'] = $stmt->fetch()['pending_stores'];
        
        $stmt = $db->query("SELECT COUNT(*) as active_listings FROM marketplace_listings WHERE status = 'active'");
        $dashboardData['active_listings'] = $stmt->fetch()['active_listings'];
        
    } elseif ($user['role'] === 'player') {
        // Player dashboard data
        $stmt = $db->prepare("SELECT COUNT(*) as inventory_count FROM player_inventory pi 
                             JOIN players p ON pi.player_id = p.player_id 
                             WHERE p.user_id = ? AND pi.is_active = 1");
        $stmt->execute([$user['user_id']]);
        $dashboardData['inventory_count'] = $stmt->fetch()['inventory_count'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as upcoming_appointments FROM maintenance_appointments ma 
                             JOIN players p ON ma.player_id = p.player_id 
                             WHERE p.user_id = ? AND ma.status IN ('pending', 'confirmed') AND ma.appointment_date > NOW()");
        $stmt->execute([$user['user_id']]);
        $dashboardData['upcoming_appointments'] = $stmt->fetch()['upcoming_appointments'];

        // Membership status
        $stmt = $db->prepare("SELECT membership_status FROM players WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $dashboardData['membership_status'] = ($stmt->fetch()['membership_status'] ?? 'pending');
        
    } elseif ($user['role'] === 'store_owner') {
        // Store owner dashboard data
        $stmt = $db->prepare("SELECT registration_status, business_type, business_name, business_address, bir_document_path, valid_id_path FROM store_owners WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);
        $store = $stmt->fetch();
        $dashboardData['registration_status'] = $store['registration_status'] ?? 'pending';
        $dashboardData['business_type'] = $store['business_type'] ?? null;
        $dashboardData['business_name'] = $store['business_name'] ?? null;
        $dashboardData['business_address'] = $store['business_address'] ?? null;
        $dashboardData['bir_uploaded'] = !empty($store['bir_document_path']);
        $dashboardData['valid_id_uploaded'] = !empty($store['valid_id_path']);
        
        $stmt = $db->prepare("SELECT COUNT(*) as pending_appointments FROM maintenance_appointments ma 
                             JOIN store_owners so ON ma.store_owner_id = so.store_owner_id 
                             WHERE so.user_id = ? AND ma.status = 'pending'");
        $stmt->execute([$user['user_id']]);
        $dashboardData['pending_appointments'] = $stmt->fetch()['pending_appointments'];
    }
    
    // Get recent notifications for all users
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 5");
    $stmt->execute([$user['user_id']]);
    $dashboardData['notifications'] = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $dashboardData = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-download me-1"></i>Export
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Welcome Message -->
                <div class="alert alert-primary" role="alert">
                    <i class="fas fa-user-circle me-2"></i>
                    Welcome back, <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>! 
                    You are logged in as a <span class="badge bg-secondary"><?php echo ucfirst($user['role']); ?></span>
                    <?php if (!empty($dashboardData['last_login'])): ?>
                        <span class="ms-3 small text-muted">Last login: <?php echo date('M j, Y g:i A', strtotime($dashboardData['last_login'])); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Dashboard Stats -->
                <div class="row g-4 mb-4">
                    <?php if ($user['role'] === 'admin'): ?>
                        <div class="col-md-3">
                            <div class="card text-white bg-primary">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="card-title"><?php echo $dashboardData['total_users'] ?? 0; ?></h4>
                                            <p class="card-text">Total Users</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-users fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-warning">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="card-title"><?php echo $dashboardData['pending_stores'] ?? 0; ?></h4>
                                            <p class="card-text">Pending Stores</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-store fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-success">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="card-title"><?php echo $dashboardData['active_listings'] ?? 0; ?></h4>
                                            <p class="card-text">Active Listings</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-shopping-cart fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($user['role'] === 'player'): ?>
                        <div class="col-md-3">
                            <div class="card text-white bg-primary">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="card-title"><?php echo $dashboardData['inventory_count'] ?? 0; ?></h4>
                                            <p class="card-text">My Gear</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-box fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-info">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="card-title"><?php echo $dashboardData['upcoming_appointments'] ?? 0; ?></h4>
                                            <p class="card-text">Appointments</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-calendar fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($user['role'] === 'store_owner'): ?>
                        <div class="col-md-3">
                            <div class="card text-white <?php echo $dashboardData['registration_status'] === 'approved' ? 'bg-success' : 'bg-warning'; ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title"><?php echo ucfirst($dashboardData['registration_status'] ?? 'pending'); ?></h6>
                                            <p class="card-text">Store Status</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-store fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-primary">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="card-title"><?php echo $dashboardData['pending_appointments'] ?? 0; ?></h4>
                                            <p class="card-text">Pending Requests</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-clock fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-info">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title"><?php echo htmlspecialchars($dashboardData['business_type'] ?? 'N/A'); ?></h6>
                                            <p class="card-text">Business Type</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-briefcase fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-secondary">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="card-title"><?php echo ($dashboardData['bir_uploaded'] && $dashboardData['valid_id_uploaded']) ? 'Complete' : 'Missing'; ?></h6>
                                            <p class="card-text">Docs Status</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="fas fa-file-alt fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Recent Activity & Notifications -->
                <div class="row g-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-line me-2"></i>Quick Actions & Insights
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <?php if ($user['role'] === 'admin'): ?>
                                        <div class="col-md-6">
                                            <a href="users/index.php" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-users me-2"></i>Manage Users
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="stores/pending.php" class="btn btn-outline-warning w-100">
                                                <i class="fas fa-store me-2"></i>Review Stores
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="content/index.php" class="btn btn-outline-info w-100">
                                                <i class="fas fa-edit me-2"></i>Manage Content
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="reports/index.php" class="btn btn-outline-success w-100">
                                                <i class="fas fa-chart-bar me-2"></i>View Reports
                                            </a>
                                        </div>
                                    <?php elseif ($user['role'] === 'player'): ?>
                                        <div class="col-md-6">
                                            <a href="inventory/index.php" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-box me-2"></i>My Inventory
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="appointments/index.php" class="btn btn-outline-info w-100">
                                                <i class="fas fa-calendar me-2"></i>Schedule Service
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="marketplace/index.php" class="btn btn-outline-success w-100">
                                                <i class="fas fa-shopping-cart me-2"></i>Marketplace
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="requirements/index.php" class="btn btn-outline-warning w-100">
                                                <i class="fas fa-list-check me-2"></i>Gear Requirements
                                            </a>
                                        </div>
                                        <div class="col-12">
                                            <div class="alert alert-light border">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Keep your gear list updated for faster appointments and better recommendations.
                                            </div>
                                        </div>
                                    <?php elseif ($user['role'] === 'store_owner'): ?>
                                        <div class="col-md-6">
                                            <a href="store/profile.php" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-store me-2"></i>Store Profile
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="appointments/manage.php" class="btn btn-outline-info w-100">
                                                <i class="fas fa-calendar-alt me-2"></i>Manage Appointments
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="inventory/store.php" class="btn btn-outline-success w-100">
                                                <i class="fas fa-boxes me-2"></i>Store Inventory
                                            </a>
                                        </div>
                                        <div class="col-md-6">
                                            <a href="employees/index.php" class="btn btn-outline-warning w-100">
                                                <i class="fas fa-users me-2"></i>Manage Staff
                                            </a>
                                        </div>
                                        <div class="col-12">
                                            <div class="alert alert-light border">
                                                <i class="fas fa-bullhorn me-2"></i>
                                                Tips: Keep your business profile complete and respond to appointment requests quickly to improve customer trust.
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-bell me-2"></i>Recent Notifications
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($dashboardData['notifications'])): ?>
                                    <?php foreach ($dashboardData['notifications'] as $notification): ?>
                                        <div class="notification-item unread mb-2">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                            <p class="mb-1 small"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="text-center mt-3">
                                        <a href="notifications/index.php" class="btn btn-sm btn-outline-primary">View All</a>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">No new notifications</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>
