<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireAnyRole(['player', 'admin']);

$user = getCurrentUser();
$db = getDB();

try {
    // Get membership policies content
    $stmt = $db->prepare("SELECT * FROM content_pages WHERE page_key = 'membership_policies' AND is_active = 1");
    $stmt->execute();
    $policies_page = $stmt->fetch();
    
    // Get safety guidelines
    $stmt = $db->prepare("SELECT * FROM content_pages WHERE page_key = 'safety_guidelines' AND is_active = 1");
    $stmt->execute();
    $safety_page = $stmt->fetch();
    
    // Get community rules
    $stmt = $db->prepare("SELECT * FROM content_pages WHERE page_key = 'community_rules' AND is_active = 1");
    $stmt->execute();
    $rules_page = $stmt->fetch();
    
    // Get user's membership status if they're a player
    $membership_info = null;
    if ($user['role'] === 'player') {
        $stmt = $db->prepare("
            SELECT p.*, u.full_name, u.email, u.phone_number, u.created_at as user_created_at
            FROM players p
            JOIN users u ON p.user_id = u.user_id
            WHERE p.user_id = ?
        ");
        $stmt->execute([$user['user_id']]);
        $membership_info = $stmt->fetch();
    }
    
} catch (PDOException $e) {
    $error_message = "Error loading membership information: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Membership Information - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .membership-status {
            border-left: 4px solid;
            padding-left: 1rem;
        }
        .status-active { border-color: #28a745; }
        .status-pending { border-color: #ffc107; }
        .status-inactive { border-color: #6c757d; }
        .status-suspended { border-color: #dc3545; }
        
        .content-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-id-card me-2"></i>Membership Information
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?php if ($user['role'] === 'player'): ?>
                            <a href="application.php" class="btn btn-outline-primary">
                                <i class="fas fa-file-alt me-1"></i>Membership Application
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- Membership Status (for players) -->
                        <?php if ($user['role'] === 'player' && $membership_info): ?>
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user-check me-2"></i>Your Membership Status
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="membership-status status-<?php echo $membership_info['membership_status']; ?>">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6>Current Status</h6>
                                                <span class="badge bg-<?php 
                                                    switch($membership_info['membership_status']) {
                                                        case 'active': echo 'success'; break;
                                                        case 'pending': echo 'warning'; break;
                                                        case 'inactive': echo 'secondary'; break;
                                                        case 'suspended': echo 'danger'; break;
                                                        default: echo 'secondary'; break;
                                                    }
                                                ?> fs-6">
                                                    <?php echo ucfirst($membership_info['membership_status']); ?>
                                                </span>
                                                
                                                <?php if ($membership_info['membership_date']): ?>
                                                    <p class="mt-2 mb-0">
                                                        <strong>Member Since:</strong> 
                                                        <?php echo date('F j, Y', strtotime($membership_info['membership_date'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-6">
                                                <h6>Emergency Contact</h6>
                                                <?php if ($membership_info['emergency_contact_name']): ?>
                                                    <p class="mb-1">
                                                        <strong>Name:</strong> <?php echo htmlspecialchars($membership_info['emergency_contact_name']); ?>
                                                    </p>
                                                    <p class="mb-0">
                                                        <strong>Phone:</strong> <?php echo htmlspecialchars($membership_info['emergency_contact_phone']); ?>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="text-muted mb-0">Not provided</p>
                                                    <a href="application.php" class="btn btn-sm btn-outline-primary mt-2">
                                                        Update Information
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if ($membership_info['membership_status'] === 'pending'): ?>
                                            <div class="alert alert-warning mt-3 mb-0">
                                                <i class="fas fa-clock me-2"></i>
                                                Your membership application is under review. You will be notified once it's processed.
                                            </div>
                                        <?php elseif ($membership_info['membership_status'] === 'suspended'): ?>
                                            <div class="alert alert-danger mt-3 mb-0">
                                                <i class="fas fa-exclamation-triangle me-2"></i>
                                                Your membership has been suspended. Please contact an administrator for more information.
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Membership Policies -->    
                        <?php if ($policies_page): ?>
                            <div class="content-section">
                                <h4 class="mb-3">
                                    <i class="fas fa-file-contract me-2"></i>Membership Policies
                                </h4>
                                <div class="content">
                                    <?php echo nl2br(htmlspecialchars($policies_page['content'])); ?>
                                </div>
                                <small class="text-muted">
                                    Last updated: <?php echo date('F j, Y', strtotime($policies_page['updated_at'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>

                        <!-- Safety Guidelines -->
                        <?php if ($safety_page): ?>
                            <div class="content-section">
                                <h4 class="mb-3">
                                    <i class="fas fa-shield-alt me-2"></i>Safety Guidelines
                                </h4>
                                <div class="content">
                                    <?php echo nl2br(htmlspecialchars($safety_page['content'])); ?>
                                </div>
                                <small class="text-muted">
                                    Last updated: <?php echo date('F j, Y', strtotime($safety_page['updated_at'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>

                        <!-- Community Rules -->
                        <?php if ($rules_page): ?>
                            <div class="content-section">
                                <h4 class="mb-3">
                                    <i class="fas fa-users me-2"></i>Community Rules
                                </h4>
                                <div class="content">
                                    <?php echo nl2br(htmlspecialchars($rules_page['content'])); ?>
                                </div>
                                <small class="text-muted">
                                    Last updated: <?php echo date('F j, Y', strtotime($rules_page['updated_at'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Quick Links -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-link me-2"></i>Quick Links
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <?php if ($user['role'] === 'player'): ?>
                                        <a href="application.php" class="btn btn-outline-primary">
                                            <i class="fas fa-file-alt me-2"></i>Membership Application
                                        </a>
                                        <a href="../requirements/index.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-list-check me-2"></i>Gear Requirements
                                        </a>
                                        <a href="../inventory/index.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-box me-2"></i>My Inventory
                                        </a>
                                    <?php endif; ?>
                                    <a href="../stores/browse.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-store me-2"></i>Browse Stores
                                    </a>
                                    <a href="../marketplace/index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-shopping-cart me-2"></i>Marketplace
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Membership Benefits -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-star me-2"></i>Membership Benefits
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Access to exclusive events
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Equipment maintenance services
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Community marketplace access
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Safety training programs
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Gear requirement assistance
                                    </li>
                                    <li class="mb-0">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Community support network
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-phone me-2"></i>Need Help?
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-2">
                                    <strong>Questions about membership?</strong>
                                </p>
                                <p class="mb-3">
                                    Contact our membership team for assistance with applications, policies, or general inquiries.
                                </p>
                                <div class="d-grid gap-2">
                                    <a href="../messages/compose.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-envelope me-1"></i>Send Message
                                    </a>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="showContactInfo()">
                                        <i class="fas fa-info-circle me-1"></i>Contact Info
                                    </button>
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
        function showContactInfo() {
            showToast('Email: membership@aerozone.com | Phone: +63-XXX-XXXX-XXX', 'info');
        }
    </script>
</body>
</html>