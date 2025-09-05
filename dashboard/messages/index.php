<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

// Get conversation partner if specified
$partner_id = intval($_GET['partner_id'] ?? 0);
$conversation_id = intval($_GET['conversation_id'] ?? 0);

// Get all conversations for the current user
try {
    $stmt = $db->prepare("
        SELECT DISTINCT 
            CASE 
                WHEN dm.sender_id = ? THEN dm.receiver_id 
                ELSE dm.sender_id 
            END as partner_id,
            u.full_name as partner_name,
            u.role as partner_role,
            u.is_active as partner_active,
            (SELECT COUNT(*) FROM direct_messages 
             WHERE ((sender_id = ? AND receiver_id = partner_id) OR (sender_id = partner_id AND receiver_id = ?))
             AND is_read = 0 AND receiver_id = ?) as unread_count,
            (SELECT message FROM direct_messages 
             WHERE ((sender_id = ? AND receiver_id = partner_id) OR (sender_id = partner_id AND receiver_id = ?))
             ORDER BY created_at DESC LIMIT 1) as last_message,
            (SELECT created_at FROM direct_messages 
             WHERE ((sender_id = ? AND receiver_id = partner_id) OR (sender_id = partner_id AND receiver_id = ?))
             ORDER BY created_at DESC LIMIT 1) as last_message_time
        FROM direct_messages dm
        JOIN users u ON (CASE 
            WHEN dm.sender_id = ? THEN dm.receiver_id 
            ELSE dm.sender_id 
        END) = u.user_id
        WHERE dm.sender_id = ? OR dm.receiver_id = ?
        ORDER BY last_message_time DESC
    ");
    $stmt->execute([
        $user['user_id'], $user['user_id'], $user['user_id'], $user['user_id'],
        $user['user_id'], $user['user_id'], $user['user_id'], $user['user_id'],
        $user['user_id'], $user['user_id'], $user['user_id']
    ]);
    $conversations = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Error loading conversations: " . $e->getMessage();
    $conversations = [];
}



// Get messages for selected conversation
$messages = [];
$selected_partner = null;
if ($partner_id) {
    try {
        // Get partner info
        $stmt = $db->prepare("SELECT user_id, full_name, role, is_active FROM users WHERE user_id = ?");
        $stmt->execute([$partner_id]);
        $selected_partner = $stmt->fetch();
        
        // Get messages
        $stmt = $db->prepare("
            SELECT dm.*, 
                   sender.full_name as sender_name,
                   sender.role as sender_role
            FROM direct_messages dm
            JOIN users sender ON dm.sender_id = sender.user_id
            WHERE (dm.sender_id = ? AND dm.receiver_id = ?) 
               OR (dm.sender_id = ? AND dm.receiver_id = ?)
            ORDER BY dm.created_at ASC
        ");
        $stmt->execute([$user['user_id'], $partner_id, $partner_id, $user['user_id']]);
        $messages = $stmt->fetchAll();
        
        // Mark messages as read
        $stmt = $db->prepare("
            UPDATE direct_messages 
            SET is_read = 1 
            WHERE sender_id = ? AND receiver_id = ? AND is_read = 0
        ");
        $stmt->execute([$partner_id, $user['user_id']]);
        
    } catch (PDOException $e) {
        $error_message = "Error loading messages: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .chat-container {
            height: calc(100vh - 200px);
            display: flex;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .conversations-sidebar {
            width: 300px;
            border-right: 1px solid #dee2e6;
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
        }
        
        .conversation-list {
            flex: 1;
            overflow-y: auto;
        }
        
        .conversation-item {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .conversation-item:hover {
            background-color: #e9ecef;
        }
        
        .conversation-item.active {
            background-color: #007bff;
            color: white;
        }
        
        .conversation-item.active .text-muted {
            color: rgba(255, 255, 255, 0.8) !important;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            background: white;
        }
        
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            background: #f8f9fa;
        }
        
        .message {
            margin-bottom: 15px;
            display: flex;
        }
        
        .message.sent {
            justify-content: flex-end;
        }
        
        .message.received {
            justify-content: flex-start;
        }
        
        .message-content {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 15px;
            word-wrap: break-word;
        }
        
        .message.sent .message-content {
            background: #007bff;
            color: white;
            border-bottom-right-radius: 5px;
        }
        
        .message.received .message-content {
            background: white;
            border: 1px solid #dee2e6;
            border-bottom-left-radius: 5px;
        }
        
        .message-time {
            font-size: 0.75rem;
            opacity: 0.7;
            margin-top: 5px;
        }
        
        .chat-input {
            padding: 15px;
            border-top: 1px solid #dee2e6;
            background: white;
        }
        
        .unread-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.75rem;
            margin-left: 5px;
        }
        
        .user-status {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        
        .user-status.online {
            background: #28a745;
        }
        
        .user-status.offline {
            background: #6c757d;
        }
        

        
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
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
                        <i class="fas fa-comments me-2"></i>Messages
                    </h1>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="chat-container">
                    <!-- Conversations Sidebar -->
                    <div class="conversations-sidebar">
                        <div class="conversation-list">
                            <?php if (!empty($conversations)): ?>
                                <?php foreach ($conversations as $conv): ?>
                                    <div class="conversation-item <?php echo $partner_id == $conv['partner_id'] ? 'active' : ''; ?>" 
                                         onclick="loadConversation(<?php echo $conv['partner_id']; ?>)">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="fw-bold">
                                                    <span class="user-status <?php echo $conv['partner_active'] ? 'online' : 'offline'; ?>"></span>
                                                    <?php echo htmlspecialchars($conv['partner_name']); ?>
                                                    <?php if ($conv['unread_count'] > 0): ?>
                                                        <span class="unread-badge"><?php echo $conv['unread_count']; ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)); ?>
                                                    <?php echo strlen($conv['last_message']) > 50 ? '...' : ''; ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php echo date('M j, g:i A', strtotime($conv['last_message_time'])); ?>
                                                </div>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo ucfirst($conv['partner_role']); ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center p-4">
                                    <i class="fas fa-comments fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No conversations yet</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Chat Main Area -->
                    <div class="chat-main">
                        <?php if ($selected_partner): ?>
                            <!-- Chat Header -->
                            <div class="chat-header">
                                <div class="d-flex align-items-center">
                                    <span class="user-status <?php echo $selected_partner['is_active'] ? 'online' : 'offline'; ?>"></span>
                                    <h5 class="mb-0"><?php echo htmlspecialchars($selected_partner['full_name']); ?></h5>
                                    <span class="badge bg-secondary ms-2"><?php echo ucfirst($selected_partner['role']); ?></span>
                                    <span class="text-muted ms-2">
                                        <?php echo $selected_partner['is_active'] ? 'Online' : 'Offline'; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- Messages Area -->
                            <div class="chat-messages" id="messagesContainer">
                                <?php if (!empty($messages)): ?>
                                    <?php foreach ($messages as $message): ?>
                                        <div class="message <?php echo $message['sender_id'] == $user['user_id'] ? 'sent' : 'received'; ?>">
                                            <div class="message-content">
                                                <div><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                                <div class="message-time">
                                                    <?php echo date('M j, g:i A', strtotime($message['created_at'])); ?>
                                                    <?php if ($message['sender_id'] == $user['user_id']): ?>
                                                        <i class="fas fa-check-double ms-1 <?php echo $message['is_read'] ? 'text-primary' : 'text-muted'; ?>"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="fas fa-comment-dots"></i>
                                        <h5>No messages yet</h5>
                                        <p>Start the conversation by sending a message!</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Message Input -->
                            <div class="chat-input">
                                <form id="messageForm" onsubmit="sendMessage(event)">
                                    <div class="input-group">
                                        <textarea class="form-control" id="messageInput" rows="2" 
                                                  placeholder="Type your message..." required></textarea>
                                        <button class="btn btn-primary" type="submit">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <!-- Empty State -->
                            <div class="empty-state">
                                <i class="fas fa-comments"></i>
                                <h5>Select a conversation</h5>
                                <p>Choose a conversation from the sidebar or start a new one</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>



    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentPartnerId = <?php echo $partner_id ?: 'null'; ?>;
        let messageCheckInterval;

        function loadConversation(partnerId) {
            window.location.href = `index.php?partner_id=${partnerId}`;
        }

        function sendMessage(event) {
            event.preventDefault();
            
            const messageInput = document.getElementById('messageInput');
            const message = messageInput.value.trim();
            
            if (!message || !currentPartnerId) return;
            
            // Disable form during send
            const form = document.getElementById('messageForm');
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            
            fetch('send-message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    receiver_id: currentPartnerId,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    messageInput.value = '';
                    // Reload the page to show new message
                    location.reload();
                } else {
                    alert('Error sending message: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error sending message');
            })
            .finally(() => {
                submitBtn.disabled = false;
            });
        }



        // Auto-scroll to bottom of messages
        function scrollToBottom() {
            const messagesContainer = document.getElementById('messagesContainer');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }

        // Check for new messages periodically
        function startMessageCheck() {
            if (currentPartnerId) {
                messageCheckInterval = setInterval(() => {
                    fetch(`get-messages.php?partner_id=${currentPartnerId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.new_messages) {
                                location.reload();
                            }
                        })
                        .catch(error => console.error('Error checking messages:', error));
                }, 5000); // Check every 5 seconds
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            scrollToBottom();
            startMessageCheck();
            
            // Auto-resize textarea
            const messageInput = document.getElementById('messageInput');
            if (messageInput) {
                messageInput.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 100) + 'px';
                });
            }
        });

        // Clean up interval when leaving page
        window.addEventListener('beforeunload', function() {
            if (messageCheckInterval) {
                clearInterval(messageCheckInterval);
            }
        });
    </script>
</body>
</html>
