<?php
require_once __DIR__ . '/notification_helper.php';
$user = getCurrentUser();
$unread_notifications = getUnreadNotificationCount($user['user_id']);

// Get recent notifications for dropdown
$db = getDB();
$recent_notifications = [];
try {
    $stmt = $db->prepare("
        SELECT notification_id, type, title, message, is_read, created_at
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$user['user_id']]);
    $recent_notifications = $stmt->fetchAll();
} catch (PDOException $e) {
    // Silently handle error
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="/aerozone/dashboard/index.php">
            <i class="fas fa-crosshairs me-2"></i>AEROZONE
        </a>
        
        <button class="navbar-toggler d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="navbar-nav ms-auto align-items-center">
            <!-- Notifications Dropdown -->
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle position-relative d-flex align-items-center" href="#" id="notificationsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-bell me-1"></i>
                    <?php if ($unread_notifications > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 0.25rem 0.4rem;">
                            <?php echo $unread_notifications; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="width: 300px; max-height: 350px; overflow-y: auto;">
                    <li class="dropdown-header d-flex justify-content-between align-items-center py-1">
                        <span class="fw-bold" style="font-size: 0.9rem;">Notifications</span>
                        <?php if ($unread_notifications > 0): ?>
                            <button class="btn btn-sm btn-outline-primary" onclick="markAllNotificationsRead()" style="font-size: 0.7rem; padding: 0.2rem 0.4rem;">
                                <i class="fas fa-check-double"></i>
                            </button>
                        <?php endif; ?>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <div id="notificationsList">
                        <?php if (!empty($recent_notifications)): ?>
                            <?php foreach ($recent_notifications as $notification): ?>
                                <li class="dropdown-item p-1 <?php echo !$notification['is_read'] ? 'bg-light' : ''; ?>" style="border-bottom: 1px solid #f0f0f0;">
                                    <div class="d-flex align-items-center">
                                        <div class="notification-icon-small me-2" style="width: 24px; height: 24px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; background-color: <?php echo !$notification['is_read'] ? '#fff3cd' : '#f8f9fa'; ?>; color: #6c757d; flex-shrink: 0;">
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
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="<?php echo !$notification['is_read'] ? 'fw-bold' : ''; ?>" style="font-size: 0.75rem; line-height: 1.1;">
                                                    <?php echo htmlspecialchars(substr($notification['title'], 0, 40)) . (strlen($notification['title']) > 40 ? '...' : ''); ?>
                                                </span>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-primary ms-1" style="font-size: 0.5rem; padding: 0.15rem 0.3rem;">New</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted" style="font-size: 0.6rem;">
                                                <?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="dropdown-item text-center text-muted py-2">
                                <i class="fas fa-bell-slash" style="font-size: 1rem;"></i>
                                <div style="font-size: 0.7rem;">No notifications</div>
                            </li>
                        <?php endif; ?>
                    </div>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <a class="dropdown-item text-center py-1" href="/aerozone/dashboard/notifications/index.php" style="font-size: 0.7rem;">
                            <i class="fas fa-eye me-1"></i>View All
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Messages Dropdown -->
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle position-relative d-flex align-items-center" href="#" id="messagesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-envelope me-1"></i>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-primary" id="messageCount" style="display: none; font-size: 0.65rem; padding: 0.25rem 0.4rem;">
                        0
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="width: 280px; max-height: 300px; overflow-y: auto;">
                    <li class="dropdown-header d-flex justify-content-between align-items-center">
                        <span class="fw-bold">Messages</span>
                        <button class="btn btn-sm btn-outline-primary" onclick="markAllMessagesRead()">
                            <i class="fas fa-check-double"></i>
                        </button>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <div id="messagesList">
                        <li class="dropdown-item text-center text-muted py-3">
                            <i class="fas fa-envelope-open mb-2"></i>
                            <div>No new messages</div>
                        </li>
                    </div>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-center" href="/aerozone/dashboard/messages/index.php">
                            <i class="fas fa-eye me-1"></i>View All
                        </a>
                    </li>
                </ul>
            </div>

            <!-- User Profile Dropdown -->
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle me-2"></i>
                    <span class="d-none d-sm-inline"><?php echo htmlspecialchars($user['full_name']); ?></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="dropdown-header">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-circle fa-2x text-primary"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                <small class="text-muted"><?php echo ucfirst($user['role']); ?></small>
                            </div>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="/aerozone/dashboard/profile/index.php">
                            <i class="fas fa-user me-2"></i>My Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="/aerozone/dashboard/settings/index.php">
                            <i class="fas fa-cog me-2"></i>Settings
                        </a>
                    </li>
                    <?php if ($user['role'] === 'player'): ?>
                    <li>
                        <a class="dropdown-item" href="/aerozone/dashboard/inventory/index.php">
                            <i class="fas fa-box me-2"></i>My Inventory
                        </a>
                    </li>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="/aerozone/auth/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<script>
function markAllNotificationsRead() {
    // Create a form and submit it to mark all notifications as read
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/aerozone/dashboard/notifications/index.php';
    form.innerHTML = `
        <input type="hidden" name="action" value="mark_all_read">
    `;
    document.body.appendChild(form);
    form.submit();
}
</script>
