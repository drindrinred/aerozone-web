<?php
/**
 * Simple Notification Helper Functions
 * Provides easy-to-use functions for creating and managing notifications
 */

/**
 * Create a simple notification
 * 
 * @param int $user_id The user ID to send notification to
 * @param string $type The notification type (maintenance_reminder, appointment_confirmed, message_received, listing_update, system_announcement)
 * @param string $title The notification title
 * @param string $message The notification message
 * @return bool Success status
 */
function createSimpleNotification($user_id, $type, $title, $message) {
    global $db;
    
    if (!$db) {
        $db = getDB();
    }
    
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications (user_id, type, title, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$user_id, $type, $title, $message]);
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Create a maintenance reminder notification
 * 
 * @param int $user_id The user ID
 * @param string $item_name The name of the item needing maintenance
 * @param string $due_date The due date for maintenance
 * @return bool Success status
 */
function createMaintenanceReminder($user_id, $item_name, $due_date) {
    $title = "Maintenance Reminder";
    $message = "Your {$item_name} is due for maintenance on {$due_date}. Please schedule an appointment.";
    return createSimpleNotification($user_id, 'maintenance_reminder', $title, $message);
}

/**
 * Create an appointment confirmation notification
 * 
 * @param int $user_id The user ID
 * @param string $appointment_date The appointment date
 * @param string $service_type The type of service
 * @return bool Success status
 */
function createAppointmentConfirmation($user_id, $appointment_date, $service_type) {
    $title = "Appointment Confirmed";
    $message = "Your {$service_type} appointment has been confirmed for {$appointment_date}.";
    return createSimpleNotification($user_id, 'appointment_confirmed', $title, $message);
}

/**
 * Create a message received notification
 * 
 * @param int $user_id The user ID
 * @param string $sender_name The name of the message sender
 * @return bool Success status
 */
function createMessageNotification($user_id, $sender_name) {
    $title = "New Message";
    $message = "You have received a new message from {$sender_name}.";
    return createSimpleNotification($user_id, 'message_received', $title, $message);
}

/**
 * Create a marketplace listing update notification
 * 
 * @param int $user_id The user ID
 * @param string $listing_title The title of the listing
 * @param string $update_type The type of update (price_change, status_change, etc.)
 * @return bool Success status
 */
function createListingUpdateNotification($user_id, $listing_title, $update_type) {
    $title = "Listing Update";
    $message = "Your listing '{$listing_title}' has been updated: {$update_type}.";
    return createSimpleNotification($user_id, 'listing_update', $title, $message);
}

/**
 * Create a system announcement notification
 * 
 * @param int $user_id The user ID
 * @param string $title The announcement title
 * @param string $message The announcement message
 * @return bool Success status
 */
function createSystemAnnouncement($user_id, $title, $message) {
    return createSimpleNotification($user_id, 'system_announcement', $title, $message);
}

/**
 * Get unread notification count for a user
 * 
 * @param int $user_id The user ID
 * @return int Number of unread notifications
 */
function getUnreadNotificationCount($user_id) {
    global $db;
    
    if (!$db) {
        $db = getDB();
    }
    
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetch()['count'];
    } catch (PDOException $e) {
        error_log("Error getting unread count: " . $e->getMessage());
        return 0;
    }
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $user_id The user ID
 * @return bool Success status
 */
function markAllNotificationsAsRead($user_id) {
    global $db;
    
    if (!$db) {
        $db = getDB();
    }
    
    try {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([$user_id]);
    } catch (PDOException $e) {
        error_log("Error marking notifications as read: " . $e->getMessage());
        return false;
    }
}
?>
