<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

$message = '';
$message_type = '';

// Get parameters
$recipient_id = intval($_GET['recipient_id'] ?? 0);
$listing_id = intval($_GET['listing_id'] ?? 0);
$reply_to = intval($_GET['reply_to'] ?? 0);

// Get recipient info if specified
$recipient_info = null;
if ($recipient_id) {
    try {
        $stmt = $db->prepare("SELECT user_id, full_name, role FROM users WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$recipient_id]);
        $recipient_info = $stmt->fetch();
    } catch (PDOException $e) {
        $error_message = "Error loading recipient information.";
    }
}

// Get listing info if specified
$listing_info = null;
if ($listing_id) {
    try {
        $stmt = $db->prepare("SELECT listing_id, title, seller_id FROM marketplace_listings WHERE listing_id = ? AND status = 'active'");
        $stmt->execute([$listing_id]);
        $listing_info = $stmt->fetch();
        
        // If no recipient specified but listing exists, set recipient to seller
        if ($listing_info && !$recipient_id) {
            $recipient_id = $listing_info['seller_id'];
            $stmt = $db->prepare("SELECT user_id, full_name, role FROM users WHERE user_id = ?");
            $stmt->execute([$recipient_id]);
            $recipient_info = $stmt->fetch();
        }
    } catch (PDOException $e) {
        $error_message = "Error loading listing information.";
    }
}

// Get original message if replying
$original_message = null;
if ($reply_to) {
    try {
        $stmt = $db->prepare("
            SELECT dm.*, u.full_name as sender_name
            FROM direct_messages dm
            JOIN users u ON dm.sender_id = u.user_id
            WHERE dm.message_id = ? AND dm.receiver_id = ?
        ");
        $stmt->execute([$reply_to, $user['user_id']]);
        $original_message = $stmt->fetch();
        
        if ($original_message && !$recipient_id) {
            $recipient_id = $original_message['sender_id'];
            $stmt = $db->prepare("SELECT user_id, full_name, role FROM users WHERE user_id = ?");
            $stmt->execute([$recipient_id]);
            $recipient_info = $stmt->fetch();
        }
    } catch (PDOException $e) {
        $error_message = "Error loading original message.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_user_id = intval($_POST['recipient_id'] ?? 0);
    $subject = trim($_POST['subject'] ?? '');
    $message_content = trim($_POST['message'] ?? '');
    $listing_ref = intval($_POST['listing_id'] ?? 0);
    
    // Validation
    $errors = [];
    if (!$to_user_id) $errors[] = "Recipient is required";
    if (empty($subject)) $errors[] = "Subject is required";
    if (empty($message_content)) $errors[] = "Message content is required";
    if ($to_user_id === $user['user_id']) $errors[] = "You cannot send a message to yourself";
    
    // Verify recipient exists
    if ($to_user_id) {
        try {
            $stmt = $db->prepare("SELECT user_id FROM users WHERE user_id = ? AND is_active = 1");
            $stmt->execute([$to_user_id]);
            if (!$stmt->fetch()) {
                $errors[] = "Invalid recipient";
            }
        } catch (PDOException $e) {
            $errors[] = "Error validating recipient";
        }
    }
    
    if (empty($errors)) {
        try {
            // Insert message
            $stmt = $db->prepare("
                INSERT INTO direct_messages (sender_id, receiver_id, listing_id, subject, message)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user['user_id'], 
                $to_user_id, 
                $listing_ref ?: null, 
                $subject, 
                $message_content
            ]);
            
            // Create notification for recipient
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message)
                VALUES (?, 'message_received', 'New Message', CONCAT('You have received a new message from ', ?))
            ");
            $stmt->execute([$to_user_id, $user['full_name']]);
            
            // Log activity
            $stmt = $db->prepare("
                INSERT INTO activity_logs (user_id, action, table_affected, record_id)
                VALUES (?, 'Message sent', 'direct_messages', ?)
            ");
            $stmt->execute([$user['user_id'], $db->lastInsertId()]);
            
            $message = "Message sent successfully!";
            $message_type = "success";
            
            // Redirect to messages list after a delay
            header("refresh:2;url=index.php");
            
        } catch (PDOException $e) {
            $message = "Error sending message: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = implode(', ', $errors);
        $message_type = "danger";
    }
}

// Get all users for recipient selection (excluding current user)
try {
    $stmt = $db->prepare("
        SELECT user_id, full_name, role 
        FROM users 
        WHERE user_id != ? AND is_active = 1 
        ORDER BY full_name ASC
    ");
    $stmt->execute([$user['user_id']]);
    $all_users = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_users = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compose Message - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-edit me-2"></i>
                        <?php echo $reply_to ? 'Reply to Message' : 'Compose Message'; ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Messages
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
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

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-envelope me-2"></i>New Message
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="recipient_id" class="form-label">To <span class="text-danger">*</span></label>
                                        <?php if ($recipient_info): ?>
                                            <div class="input-group">
                                                <input type="text" class="form-control" 
                                                       value="<?php echo htmlspecialchars($recipient_info['full_name'] . ' (' . ucfirst($recipient_info['role']) . ')'); ?>" 
                                                       readonly>
                                                <button class="btn btn-outline-secondary" type="button" onclick="changeRecipient()">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                            <input type="hidden" name="recipient_id" value="<?php echo $recipient_info['user_id']; ?>">
                                        <?php else: ?>
                                            <select class="form-select" id="recipient_id" name="recipient_id" required>
                                                <option value="">Select recipient...</option>
                                                <?php foreach ($all_users as $user_option): ?>
                                                    <option value="<?php echo $user_option['user_id']; ?>">
                                                        <?php echo htmlspecialchars($user_option['full_name'] . ' (' . ucfirst($user_option['role']) . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mb-3">
                                        <label for="subject" class="form-label">Subject <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="subject" name="subject" 
                                               value="<?php 
                                                   if ($original_message) {
                                                       echo htmlspecialchars('Re: ' . $original_message['subject']);
                                                   } elseif ($listing_info) {
                                                       echo htmlspecialchars('Inquiry about: ' . $listing_info['title']);
                                                   } else {
                                                       echo htmlspecialchars($_POST['subject'] ?? '');
                                                   }
                                               ?>" 
                                               placeholder="Enter message subject" required>
                                    </div>

                                    <?php if ($listing_info): ?>
                                        <div class="mb-3">
                                            <label class="form-label">Related Listing</label>
                                            <div class="alert alert-info">
                                                <i class="fas fa-tag me-2"></i>
                                                <strong><?php echo htmlspecialchars($listing_info['title']); ?></strong>
                                            </div>
                                            <input type="hidden" name="listing_id" value="<?php echo $listing_info['listing_id']; ?>">
                                        </div>
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label for="message" class="form-label">Message <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="message" name="message" rows="8" 
                                                  placeholder="Type your message here..." required><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-paper-plane me-1"></i>Send Message
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="saveDraft()">
                                            <i class="fas fa-save me-1"></i>Save Draft
                                        </button>
                                        <a href="index.php" class="btn btn-outline-danger">
                                            <i class="fas fa-times me-1"></i>Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Original Message (if replying) -->
                        <?php if ($original_message): ?>
                            <div class="card mt-4">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-reply me-2"></i>Original Message
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="border-start border-3 border-secondary ps-3">
                                        <div class="mb-2">
                                            <strong>From:</strong> <?php echo htmlspecialchars($original_message['sender_name']); ?>
                                            <br>
                                            <strong>Date:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($original_message['created_at'])); ?>
                                            <br>
                                            <strong>Subject:</strong> <?php echo htmlspecialchars($original_message['subject']); ?>
                                        </div>
                                        <div class="text-muted">
                                            <?php echo nl2br(htmlspecialchars($original_message['message'])); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Quick Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-bolt me-2"></i>Quick Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary" onclick="insertTemplate('greeting')">
                                        <i class="fas fa-hand-wave me-2"></i>Insert Greeting
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="insertTemplate('inquiry')">
                                        <i class="fas fa-question me-2"></i>Product Inquiry
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="insertTemplate('appointment')">
                                        <i class="fas fa-calendar me-2"></i>Appointment Request
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="insertTemplate('closing')">
                                        <i class="fas fa-handshake me-2"></i>Professional Closing
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Message Tips -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-lightbulb me-2"></i>Message Tips
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled small">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Be clear and concise
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Use a descriptive subject line
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Be polite and professional
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Include relevant details
                                    </li>
                                    <li class="mb-0">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Proofread before sending
                                    </li>
                                </ul>
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
        function changeRecipient() {
            // Convert readonly input back to select
            const recipientDiv = document.querySelector('[for="recipient_id"]').parentNode;
            recipientDiv.innerHTML = `
                <label for="recipient_id" class="form-label">To <span class="text-danger">*</span></label>
                <select class="form-select" id="recipient_id" name="recipient_id" required>
                    <option value="">Select recipient...</option>
                    <?php foreach ($all_users as $user_option): ?>
                        <option value="<?php echo $user_option['user_id']; ?>">
                            <?php echo htmlspecialchars($user_option['full_name'] . ' (' . ucfirst($user_option['role']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            `;
        }

        function insertTemplate(type) {
            const messageTextarea = document.getElementById('message');
            let template = '';
            
            switch(type) {
                case 'greeting':
                    template = 'Hello,\n\nI hope this message finds you well.\n\n';
                    break;
                case 'inquiry':
                    template = 'Hi,\n\nI am interested in your listing and would like to know more details about:\n\n- Condition\n- Availability\n- Price negotiability\n\nThank you for your time.\n\n';
                    break;
                case 'appointment':
                    template = 'Hello,\n\nI would like to schedule an appointment for equipment maintenance/service. Please let me know your available times.\n\nThank you.\n\n';
                    break;
                case 'closing':
                    template = '\n\nBest regards,\n<?php echo htmlspecialchars($user['full_name']); ?>';
                    break;
            }
            
            const currentValue = messageTextarea.value;
            const cursorPos = messageTextarea.selectionStart;
            const newValue = currentValue.substring(0, cursorPos) + template + currentValue.substring(cursorPos);
            
            messageTextarea.value = newValue;
            messageTextarea.focus();
            messageTextarea.setSelectionRange(cursorPos + template.length, cursorPos + template.length);
        }

        function saveDraft() {
            showToast('Draft save functionality will be implemented', 'info');
        }

        // Auto-resize textarea
        document.getElementById('message').addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</body>
</html>