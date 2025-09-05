<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

$message_id = intval($_GET['id'] ?? 0);

if (!$message_id) {
    header('Location: index.php');
    exit();
}

try {
    // Get message details
    $stmt = $db->prepare("
        SELECT dm.*, 
               sender.full_name as sender_name, sender.role as sender_role,
               receiver.full_name as receiver_name, receiver.role as receiver_role,
               ml.title as listing_title, ml.listing_id
        FROM direct_messages dm
        JOIN users sender ON dm.sender_id = sender.user_id
        JOIN users receiver ON dm.receiver_id = receiver.user_id
        LEFT JOIN marketplace_listings ml ON dm.listing_id = ml.listing_id
        WHERE dm.message_id = ? AND (dm.sender_id = ? OR dm.receiver_id = ?)
    ");
    $stmt->execute([$message_id, $user['user_id'], $user['user_id']]);
    $message = $stmt->fetch();
    
    if (!$message) {
        header('Location: index.php');
        exit();
    }
    
    // Mark as read if user is the receiver
    if ($message['receiver_id'] == $user['user_id'] && !$message['is_read']) {
        $stmt = $db->prepare("UPDATE direct_messages SET is_read = 1 WHERE message_id = ?");
        $stmt->execute([$message_id]);
        $message['is_read'] = 1;
    }
    
    // Get conversation thread (messages between same users)
    $stmt = $db->prepare("
        SELECT dm.*, 
               sender.full_name as sender_name, sender.role as sender_role
        FROM direct_messages dm
        JOIN users sender ON dm.sender_id = sender.user_id
        WHERE ((dm.sender_id = ? AND dm.receiver_id = ?) OR (dm.sender_id = ? AND dm.receiver_id = ?))
        AND dm.message_id != ?
        ORDER BY dm.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([
        $message['sender_id'], $message['receiver_id'],
        $message['receiver_id'], $message['sender_id'],
        $message_id
    ]);
    $conversation = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Error loading message: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($message['subject'] ?? 'Message'); ?> - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .message-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px 8px 0 0;
        }
        .message-content {
            line-height: 1.6;
            white-space: pre-wrap;
        }
        .conversation-message {
            border-left: 3px solid #dee2e6;
            padding-left: 1rem;
            margin-bottom: 1rem;
        }
        .conversation-message.from-me {
            border-left-color: #007bff;
            background-color: #f8f9fa;
        }
        .conversation-message.from-them {
            border-left-color: #28a745;
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
                        <i class="fas fa-envelope-open me-2"></i>Message Details
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="compose.php?reply_to=<?php echo $message_id; ?>" class="btn btn-primary">
                                <i class="fas fa-reply me-1"></i>Reply
                            </a>
                            <a href="compose.php?recipient_id=<?php echo $message['sender_id'] == $user['user_id'] ? $message['receiver_id'] : $message['sender_id']; ?>" class="btn btn-outline-primary">
                                <i class="fas fa-plus me-1"></i>New Message
                            </a>
                        </div>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Messages
                        </a>
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
                        <!-- Main Message -->
                        <div class="card mb-4">
                            <div class="message-header p-4">
                                <h3 class="mb-3"><?php echo htmlspecialchars($message['subject'] ?: 'No Subject'); ?></h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <strong>From:</strong> 
                                            <?php echo htmlspecialchars($message['sender_name']); ?>
                                            <span class="badge bg-light text-dark ms-2">
                                                <?php echo ucfirst($message['sender_role']); ?>
                                            </span>
                                        </p>
                                        <p class="mb-1">
                                            <strong>To:</strong> 
                                            <?php echo htmlspecialchars($message['receiver_name']); ?>
                                            <span class="badge bg-light text-dark ms-2">
                                                <?php echo ucfirst($message['receiver_role']); ?>
                                            </span>
                                        </p>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <p class="mb-1">
                                            <strong>Date:</strong> 
                                            <?php echo date('F j, Y', strtotime($message['created_at'])); ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Time:</strong> 
                                            <?php echo date('g:i A', strtotime($message['created_at'])); ?>
                                        </p>
                                        <?php if ($message['receiver_id'] == $user['user_id']): ?>
                                            <p class="mb-0">
                                                <span class="badge bg-<?php echo $message['is_read'] ? 'success' : 'warning'; ?>">
                                                    <?php echo $message['is_read'] ? 'Read' : 'Unread'; ?>
                                                </span>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($message['listing_title']): ?>
                                    <div class="mt-3 pt-3 border-top border-light">
                                        <p class="mb-0">
                                            <i class="fas fa-tag me-2"></i>
                                            <strong>Related to listing:</strong> 
                                            <a href="../marketplace/view-listing.php?id=<?php echo $message['listing_id']; ?>" 
                                               class="text-white text-decoration-underline">
                                                <?php echo htmlspecialchars($message['listing_title']); ?>
                                            </a>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="message-content">
                                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex gap-2">
                                    <a href="compose.php?reply_to=<?php echo $message_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-reply me-1"></i>Reply
                                    </a>
                                    <button class="btn btn-outline-secondary" onclick="forwardMessage()">
                                        <i class="fas fa-share me-1"></i>Forward
                                    </button>
                                    <button class="btn btn-outline-info" onclick="printMessage()">
                                        <i class="fas fa-print me-1"></i>Print
                                    </button>
                                    <?php if ($message['receiver_id'] == $user['user_id']): ?>
                                        <button class="btn btn-outline-danger" onclick="deleteMessage()">
                                            <i class="fas fa-trash me-1"></i>Delete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Conversation History -->
                        <?php if (!empty($conversation)): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-comments me-2"></i>Conversation History
                                        <span class="badge bg-secondary ms-2"><?php echo count($conversation); ?></span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($conversation as $conv_msg): ?>
                                        <div class="conversation-message <?php echo $conv_msg['sender_id'] == $user['user_id'] ? 'from-me' : 'from-them'; ?>">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($conv_msg['sender_name']); ?></strong>
                                                    <span class="badge bg-light text-dark ms-2">
                                                        <?php echo ucfirst($conv_msg['sender_role']); ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y g:i A', strtotime($conv_msg['created_at'])); ?>
                                                </small>
                                            </div>
                                            <?php if ($conv_msg['subject']): ?>
                                                <h6 class="mb-2"><?php echo htmlspecialchars($conv_msg['subject']); ?></h6>
                                            <?php endif; ?>
                                            <div class="message-content">
                                                <?php echo nl2br(htmlspecialchars(substr($conv_msg['message'], 0, 300))); ?>
                                                <?php if (strlen($conv_msg['message']) > 300): ?>
                                                    <span class="text-muted">... 
                                                        <a href="view.php?id=<?php echo $conv_msg['message_id']; ?>">Read more</a>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Message Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-cogs me-2"></i>Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="compose.php?reply_to=<?php echo $message_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-reply me-2"></i>Reply to Message
                                    </a>
                                    <a href="compose.php?recipient_id=<?php echo $message['sender_id'] == $user['user_id'] ? $message['receiver_id'] : $message['sender_id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-plus me-2"></i>New Message to Sender
                                    </a>
                                    <button class="btn btn-outline-secondary" onclick="forwardMessage()">
                                        <i class="fas fa-share me-2"></i>Forward Message
                                    </button>
                                    <button class="btn btn-outline-info" onclick="printMessage()">
                                        <i class="fas fa-print me-2"></i>Print Message
                                    </button>
                                    <?php if ($message['receiver_id'] == $user['user_id']): ?>
                                        <hr>
                                        <button class="btn btn-outline-warning" onclick="markAsUnread()">
                                            <i class="fas fa-envelope me-2"></i>Mark as Unread
                                        </button>
                                        <button class="btn btn-outline-danger" onclick="deleteMessage()">
                                            <i class="fas fa-trash me-2"></i>Delete Message
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sender Information -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user me-2"></i>
                                    <?php echo $message['sender_id'] == $user['user_id'] ? 'Recipient' : 'Sender'; ?> Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php 
                                $contact_user = $message['sender_id'] == $user['user_id'] ? 
                                    ['name' => $message['receiver_name'], 'role' => $message['receiver_role'], 'id' => $message['receiver_id']] :
                                    ['name' => $message['sender_name'], 'role' => $message['sender_role'], 'id' => $message['sender_id']];
                                ?>
                                <div class="text-center mb-3">
                                    <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" 
                                         style="width: 60px; height: 60px; font-size: 1.5rem;">
                                        <?php echo strtoupper(substr($contact_user['name'], 0, 1)); ?>
                                    </div>
                                </div>
                                <h6 class="text-center mb-2"><?php echo htmlspecialchars($contact_user['name']); ?></h6>
                                <p class="text-center text-muted mb-3">
                                    <span class="badge bg-light text-dark">
                                        <?php echo ucfirst($contact_user['role']); ?>
                                    </span>
                                </p>
                                <div class="d-grid gap-2">
                                    <a href="compose.php?recipient_id=<?php echo $contact_user['id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-envelope me-1"></i>Send Message
                                    </a>
                                    <button class="btn btn-outline-secondary btn-sm" onclick="viewProfile(<?php echo $contact_user['id']; ?>)">
                                        <i class="fas fa-user me-1"></i>View Profile
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Related Listing (if applicable) -->
                        <?php if ($message['listing_title']): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-tag me-2"></i>Related Listing
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <h6><?php echo htmlspecialchars($message['listing_title']); ?></h6>
                                    <div class="d-grid gap-2 mt-3">
                                        <a href="../marketplace/view-listing.php?id=<?php echo $message['listing_id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-eye me-1"></i>View Listing
                                        </a>
                                    </div>
                                </div>
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
        function forwardMessage() {
            showToast('Forward functionality will be implemented', 'info');
        }

        function printMessage() {
            window.print();
        }

        function markAsUnread() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'index.php';
            form.innerHTML = `
                <input type="hidden" name="action" value="mark_unread">
                <input type="hidden" name="message_id" value="<?php echo $message_id; ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function deleteMessage() {
            if (confirm('Are you sure you want to delete this message?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'index.php';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="message_id" value="<?php echo $message_id; ?>">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewProfile(userId) {
            showToast('User profiles feature coming soon!', 'info');
        }
    </script>
</body>
</html>