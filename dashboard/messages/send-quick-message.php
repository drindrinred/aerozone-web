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
$listing_id = intval($input['listing_id'] ?? 0);
$subject = trim($input['subject'] ?? '');
$message_content = trim($input['message'] ?? '');

// Validation
if (!$receiver_id) {
    echo json_encode(['success' => false, 'error' => 'Receiver ID is required']);
    exit();
}

if (!$listing_id) {
    echo json_encode(['success' => false, 'error' => 'Listing ID is required']);
    exit();
}

if (empty($subject)) {
    echo json_encode(['success' => false, 'error' => 'Subject is required']);
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
    
    // Verify listing exists and is active
    $stmt = $db->prepare("SELECT listing_id, title, seller_id FROM marketplace_listings WHERE listing_id = ? AND status = 'active'");
    $stmt->execute([$listing_id]);
    $listing = $stmt->fetch();
    
    if (!$listing) {
        echo json_encode(['success' => false, 'error' => 'Invalid listing']);
        exit();
    }
    
    // Verify the receiver is the seller of the listing
    if ($listing['seller_id'] != $receiver_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid receiver for this listing']);
        exit();
    }
    
    // Insert message
    $stmt = $db->prepare("
        INSERT INTO direct_messages (sender_id, receiver_id, listing_id, subject, message)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user['user_id'], $receiver_id, $listing_id, $subject, $message_content]);
    
    $message_id = $db->lastInsertId();
    
    // Create notification for recipient
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, type, title, message)
        VALUES (?, 'message_received', 'New Message', CONCAT('You have received a new message from ', ?, ' about your listing: ', ?))
    ");
    $stmt->execute([$receiver_id, $user['full_name'], $listing['title']]);
    
    // Log activity
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, table_affected, record_id)
        VALUES (?, 'Quick message sent', 'direct_messages', ?)
    ");
    $stmt->execute([$user['user_id'], $message_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully!',
        'data' => [
            'message_id' => $message_id,
            'sender_id' => $user['user_id'],
            'receiver_id' => $receiver_id,
            'listing_id' => $listing_id,
            'subject' => $subject,
            'message' => $message_content
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Error sending quick message: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Failed to send message']);
}
?>

