<?php
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../includes/notification_helper.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

$message = '';
$message_type = '';

// Handle mark as read action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $notification_id = intval($_POST['notification_id'] ?? 0);
    
    try {
        if ($action === 'mark_read' && $notification_id) {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user['user_id']]);
            $message = 'Notification marked as read!';
            $message_type = 'success';
        } elseif ($action === 'mark_all_read') {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user['user_id']]);
            $message = 'All notifications marked as read!';
            $message_type = 'success';
        }
    } catch (PDOException $e) {
        $message = 'Error processing request: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

try {
    // Get all notifications for the user (simplified - no pagination)
    $stmt = $db->prepare("
        SELECT notification_id, type, title, message, is_read, created_at
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$user['user_id']]);
    $notifications = $stmt->fetchAll();
    
    // Get unread count using helper function
    $unread_count = getUnreadNotificationCount($user['user_id']);
    
} catch (PDOException $e) {
    $error_message = "Error fetching notifications: " . $e->getMessage();
    $notifications = [];
    $unread_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        /* Fix content alignment */
        .main-content {
            padding: 20px;
            min-height: 100vh;
        }

        /* Remove badge animations */
        .badge {
            animation: none !important;
            transition: none !important;
        }
        
        .badge:hover {
            transform: none !important;
            transition: none !important;
        }

        /* Notification item styling */
        .notification-item {
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-item.unread {
            background-color: #fff3cd;
            border-left-color: #ffc107;
        }
        
        .notification-item.unread:hover {
            background-color: #ffeaa7;
        }

        /* Notification icon styling */
        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            flex-shrink: 0;
        }
        
        .notification-type-maintenance_reminder { 
            background-color: #e3f2fd; 
            color: #1976d2; 
        }
        
        .notification-type-appointment_confirmed { 
            background-color: #e8f5e8; 
            color: #388e3c; 
        }
        
        .notification-type-message_received { 
            background-color: #fff3e0; 
            color: #f57c00; 
        }
        
        .notification-type-listing_update { 
            background-color: #f3e5f5; 
            color: #7b1fa2; 
        }
        
        .notification-type-system_announcement { 
            background-color: #ffebee; 
            color: #d32f2f; 
        }

        /* Notification content styling */
        .notification-content {
            flex-grow: 1;
            min-width: 0;
        }

        .notification-title {
            font-weight: 600;
            margin-bottom: 8px;
            line-height: 1.3;
        }

        .notification-message {
            color: #6c757d;
            line-height: 1.5;
            margin-bottom: 12px;
        }

        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .notification-time {
            color: #6c757d;
            font-size: 0.875rem;
        }

        /* Card styling */
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h5 {
            margin-bottom: 10px;
            color: #6c757d;
        }

        .empty-state p {
            color: #6c757d;
            margin: 0;
        }

        /* Button styling */
        .btn-mark-read {
            padding: 6px 12px;
            font-size: 0.875rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .notification-item {
                padding: 15px;
            }

            .notification-icon {
                width: 40px;
                height: 40px;
                font-size: 1.1rem;
            }

            .notification-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }

            .notification-time {
                order: -1;
            }
        }

        /* Loading state */
        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        /* Success animation */
        .notification-item.marked-read {
            animation: fadeOut 0.3s ease-out;
        }

        @keyframes fadeOut {
            to {
                opacity: 0.6;
                transform: translateX(10px);
            }
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
                        <i class="fas fa-bell me-2"></i>Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-warning text-dark ms-2"><?php echo $unread_count; ?> new</span>
                        <?php endif; ?>
                    </h1>
                    <?php if ($unread_count > 0): ?>
                        <button class="btn btn-outline-primary" onclick="markAllAsRead()">
                            <i class="fas fa-check-double me-1"></i>Mark All Read
                        </button>
                    <?php endif; ?>
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

                <!-- Notifications List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-list me-2"></i>Recent Notifications
                            <span class="text-muted">(<?php echo count($notifications); ?> notifications)</span>
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" id="notification-<?php echo $notification['notification_id']; ?>">
                                    <div class="d-flex align-items-start">
                                        <div class="notification-icon notification-type-<?php echo $notification['type']; ?> me-3">
                                            <?php
                                            $icon = 'fas fa-bell'; // default
                                            switch($notification['type']) {
                                                case 'maintenance_reminder':
                                                    $icon = 'fas fa-wrench';
                                                    break;
                                                case 'appointment_confirmed':
                                                    $icon = 'fas fa-calendar-check';
                                                    break;
                                                case 'message_received':
                                                    $icon = 'fas fa-envelope';
                                                    break;
                                                case 'listing_update':
                                                    $icon = 'fas fa-shopping-cart';
                                                    break;
                                                case 'system_announcement':
                                                    $icon = 'fas fa-bullhorn';
                                                    break;
                                            }
                                            ?>
                                            <i class="<?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="notification-content">
                                            <div class="notification-title <?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>">
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                            </div>
                                            <div class="notification-message">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </div>
                                            <div class="notification-meta">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="badge bg-light text-dark">
                                                        <?php echo ucfirst(str_replace('_', ' ', $notification['type'])); ?>
                                                    </span>
                                                    <?php if (!$notification['is_read']): ?>
                                                        <span class="badge bg-primary">New</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="notification-time">
                                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                    </span>
                                                    <?php if (!$notification['is_read']): ?>
                                                        <button class="btn btn-outline-primary btn-sm btn-mark-read" onclick="markAsRead(<?php echo $notification['notification_id']; ?>)">
                                                            <i class="fas fa-check me-1"></i>Mark Read
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-bell-slash fa-4x"></i>
                                <h5>No notifications</h5>
                                <p>You're all caught up! No notifications at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function markAsRead(notificationId) {
            const notificationElement = document.getElementById(`notification-${notificationId}`);
            if (notificationElement) {
                notificationElement.classList.add('loading');
            }
            performNotificationAction('mark_read', notificationId);
        }

        function markAllAsRead() {
            const markAllButton = event.target;
            markAllButton.classList.add('loading');
            markAllButton.disabled = true;
            performNotificationAction('mark_all_read');
        }

        function performNotificationAction(action, notificationId = null) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="${action}">
                ${notificationId ? `<input type="hidden" name="notification_id" value="${notificationId}">` : ''}
            `;
            document.body.appendChild(form);
            form.submit();
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>