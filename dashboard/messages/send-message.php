<?php
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../includes/notification_helper.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user = getCurrentUser();
$db = getDB();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit();
}

$receiver_id = intval($input['receiver_id'] ?? 0);
$message_content = trim($input['message'] ?? '');

// Validation
if (!$receiver_id) {
    echo json_encode(['success' => false, 'error' => 'Receiver ID is required']);
    exit();
}

if (empty($message_content)) {
    echo json_encode(['success' => false, 'error' => 'Message content is required']);
    exit();
}

if ($receiver_id === $user['user_id']) {
    echo json_encode(['success' => false, 'error' => 'You cannot send a message to yourself']);
    exit();
}

try {
    // Verify receiver exists and is active
    $stmt = $db->prepare("SELECT user_id, full_name FROM users WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$receiver_id]);
    $receiver = $stmt->fetch();
    
    if (!$receiver) {
        echo json_encode(['success' => false, 'error' => 'Invalid receiver']);
        exit();
    }
    
    // Insert message
    $stmt = $db->prepare("
        INSERT INTO direct_messages (sender_id, receiver_id, message)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['user_id'], $receiver_id, $message_content]);
    
    $message_id = $db->lastInsertId();
    
    // Get the inserted message with sender info
    $stmt = $db->prepare("
        SELECT dm.*, sender.full_name as sender_name
        FROM direct_messages dm
        JOIN users sender ON dm.sender_id = sender.user_id
        WHERE dm.message_id = ?
    ");
    $stmt->execute([$message_id]);
    $message = $stmt->fetch();
    
    // Create notification for recipient using helper function
    createMessageNotification($receiver_id, $user['full_name']);
    
    // Log activity
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, table_affected, record_id)
        VALUES (?, 'Message sent', 'direct_messages', ?)
    ");
    $stmt->execute([$user['user_id'], $message_id]);
    
    echo json_encode([
        'success' => true,
        'message' => [
            'message_id' => $message['message_id'],
            'sender_id' => $message['sender_id'],
            'receiver_id' => $message['receiver_id'],
            'message' => $message['message'],
            'created_at' => $message['created_at'],
            'sender_name' => $message['sender_name']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error sending message: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
}
?>
