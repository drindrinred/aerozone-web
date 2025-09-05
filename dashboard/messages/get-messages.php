<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user = getCurrentUser();
$db = getDB();

// Get parameters
$partner_id = intval($_GET['partner_id'] ?? 0);
$since = $_GET['since'] ?? '';

if (!$partner_id) {
    echo json_encode(['success' => false, 'error' => 'Partner ID is required']);
    exit();
}

try {
    // Verify partner exists
    $stmt = $db->prepare("SELECT user_id FROM users WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$partner_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Invalid partner']);
        exit();
    }
    
    // Build query for new messages
    $where_conditions = [
        "((dm.sender_id = ? AND dm.receiver_id = ?) OR (dm.sender_id = ? AND dm.receiver_id = ?))",
        "dm.receiver_id = ?" // Only get messages sent to current user
    ];
    $params = [$user['user_id'], $partner_id, $partner_id, $user['user_id'], $user['user_id']];
    
    // Add time filter if since parameter is provided
    if (!empty($since)) {
        $where_conditions[] = "dm.created_at > ?";
        $params[] = $since;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    // Get new messages
    $stmt = $db->prepare("
        SELECT dm.*, sender.full_name as sender_name
        FROM direct_messages dm
        JOIN users sender ON dm.sender_id = sender.user_id
        WHERE {$where_clause}
        ORDER BY dm.created_at ASC
    ");
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
    
    // Mark messages as read
    if (!empty($messages)) {
        $stmt = $db->prepare("
            UPDATE direct_messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->execute([$partner_id, $user['user_id']]);
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $messages
    ]);
    
} catch (PDOException $e) {
    error_log("Error getting messages: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to get messages']);
}
?>
