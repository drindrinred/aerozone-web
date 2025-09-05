<?php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../config/session.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user = getCurrentUser();
$db = getDB();

try {
    // Get recent notifications (simplified - no complex filtering)
    $stmt = $db->prepare("
        SELECT notification_id, type, title, message, is_read, created_at
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$user['user_id']]);
    $notifications = $stmt->fetchAll();

    // Get unread count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user['user_id']]);
    $unread_count = $stmt->fetch()['count'];

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => (int)$unread_count
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
}
?>
