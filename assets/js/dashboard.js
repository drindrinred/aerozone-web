// AEROZONE Dashboard JavaScript

// Global variables
let notificationCheckInterval;
let messageCheckInterval;

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    initializeDashboard();
});

function initializeDashboard() {
    // Load initial data
    loadNotifications();
    loadMessages();
    
    // Set up periodic checks
    notificationCheckInterval = setInterval(loadNotifications, 30000); // Check every 30 seconds
    messageCheckInterval = setInterval(loadMessages, 30000); // Check every 30 seconds
    
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

// Load notifications
async function loadNotifications() {
    try {
        const response = await fetch('/aerozone/api/get-notifications.php');
        const data = await response.json();
        
        if (data.success) {
            updateNotificationDropdown(data.notifications);
            updateNotificationCount(data.unread_count);
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
    }
}

// Update notification dropdown
function updateNotificationDropdown(notifications) {
    const notificationsList = document.getElementById('notificationsList');
    if (!notificationsList) return;

    if (notifications.length === 0) {
        notificationsList.innerHTML = `
            <li class="dropdown-item text-center text-muted py-4">
                <i class="fas fa-bell-slash fa-2x mb-3 text-muted"></i>
                <div class="fw-bold">No new notifications</div>
                <small>You're all caught up!</small>
            </li>
        `;
        return;
    }

    let html = '';
    notifications.slice(0, 5).forEach(notification => {
        const timeAgo = getTimeAgo(new Date(notification.created_at));
        const iconClass = getNotificationIcon(notification.type);
        const isUnread = !notification.is_read;
        
        html += `
            <li class="dropdown-item notification-item ${isUnread ? 'unread' : ''}" 
                onclick="markNotificationRead(${notification.notification_id})">
                <div class="d-flex align-items-start">
                    <div class="notification-icon ${isUnread ? 'bg-primary' : 'bg-light'} me-3">
                        <i class="fas ${iconClass} ${isUnread ? 'text-white' : 'text-muted'}"></i>
                    </div>
                    <div class="notification-content flex-grow-1">
                        <div class="notification-title ${isUnread ? 'fw-bold' : ''}">${notification.title}</div>
                        <div class="notification-message">${notification.message}</div>
                        <div class="notification-time">${timeAgo}</div>
                    </div>
                    ${isUnread ? '<span class="badge bg-primary rounded-pill ms-2">New</span>' : ''}
                </div>
            </li>
        `;
    });

    notificationsList.innerHTML = html;
}

// Get notification icon based on type
function getNotificationIcon(type) {
    const icons = {
        'maintenance_reminder': 'fa-tools',
        'appointment_confirmed': 'fa-calendar-check',
        'message_received': 'fa-envelope',
        'listing_update': 'fa-tag',
        'system_announcement': 'fa-bullhorn'
    };
    return icons[type] || 'fa-bell';
}

// Update notification count badge
function updateNotificationCount(count) {
    const notificationCount = document.getElementById('notificationCount');
    if (!notificationCount) return;

    if (count > 0) {
        notificationCount.textContent = count > 99 ? '99+' : count;
        notificationCount.style.display = 'block';
        
        // Animation removed per UX feedback (keep badge static)
    } else {
        notificationCount.style.display = 'none';
    }
}

// Mark notification as read
async function markNotificationRead(notificationId) {
    try {
        const response = await fetch('/aerozone/api/mark-notification-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notification_id: notificationId })
        });
        
        const data = await response.json();
        if (data.success) {
            // Reload notifications to update the UI
            loadNotifications();
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

// Mark all notifications as read
async function markAllNotificationsRead() {
    try {
        const response = await fetch('/aerozone/api/mark-notification-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mark_all: true })
        });
        
        const data = await response.json();
        if (data.success) {
            loadNotifications();
            showToast('All notifications marked as read', 'success');
        }
    } catch (error) {
        console.error('Error marking all notifications as read:', error);
        showToast('Error marking notifications as read', 'error');
    }
}

// Load messages
async function loadMessages() {
    try {
        const response = await fetch('/aerozone/api/get-messages.php');
        const data = await response.json();
        
        if (data.success) {
            updateMessageDropdown(data.messages);
            updateMessageCount(data.unread_count);
        }
    } catch (error) {
        console.error('Error loading messages:', error);
    }
}

// Update message dropdown
function updateMessageDropdown(messages) {
    const messagesList = document.getElementById('messagesList');
    if (!messagesList) return;

    if (messages.length === 0) {
        messagesList.innerHTML = `
            <li class="dropdown-item text-center text-muted py-4">
                <i class="fas fa-envelope-open fa-2x mb-3 text-muted"></i>
                <div class="fw-bold">No new messages</div>
                <small>Your inbox is empty</small>
            </li>
        `;
        return;
    }

    let html = '';
    messages.slice(0, 5).forEach(message => {
        const timeAgo = getTimeAgo(new Date(message.created_at));
        const isUnread = !message.is_read;
        
        html += `
            <li class="dropdown-item notification-item ${isUnread ? 'unread' : ''}" 
                onclick="window.location.href='/aerozone/dashboard/messages/view.php?id=${message.message_id}'">
                <div class="d-flex align-items-start">
                    <div class="notification-icon ${isUnread ? 'bg-primary' : 'bg-light'} me-3">
                        <i class="fas fa-envelope ${isUnread ? 'text-white' : 'text-muted'}"></i>
                    </div>
                    <div class="notification-content flex-grow-1">
                        <div class="notification-title ${isUnread ? 'fw-bold' : ''}">${message.sender_name}</div>
                        <div class="notification-message">${message.subject || 'No subject'}</div>
                        <div class="notification-time">${timeAgo}</div>
                    </div>
                    ${isUnread ? '<span class="badge bg-primary rounded-pill ms-2">New</span>' : ''}
                </div>
            </li>
        `;
    });

    messagesList.innerHTML = html;
}

// Update message count badge
function updateMessageCount(count) {
    const messageCount = document.getElementById('messageCount');
    if (!messageCount) return;

    if (count > 0) {
        messageCount.textContent = count > 99 ? '99+' : count;
        messageCount.style.display = 'block';
        
        // Animation removed per UX feedback (keep badge static)
    } else {
        messageCount.style.display = 'none';
    }
}

// Mark all messages as read
async function markAllMessagesRead() {
    try {
        const response = await fetch('/aerozone/api/mark-message-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ mark_all: true })
        });
        
        const data = await response.json();
        if (data.success) {
            loadMessages();
            showToast('All messages marked as read', 'success');
        }
    } catch (error) {
        console.error('Error marking all messages as read:', error);
        showToast('Error marking messages as read', 'error');
    }
}

// Get time ago string
function getTimeAgo(date) {
    const now = new Date();
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes}m ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours}h ago`;
    } else if (diffInSeconds < 2592000) {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days}d ago`;
    } else {
        return date.toLocaleDateString();
    }
}

// Show toast notification
function showToast(message, type = 'info') {
    // Create toast container if it doesn't exist
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }
    
    const toastId = 'toast-' + Date.now();
    const bgClass = type === 'success' ? 'bg-success' : 
                   type === 'error' ? 'bg-danger' : 
                   type === 'warning' ? 'bg-warning' : 'bg-info';
    
    const toastHtml = `
        <div id="${toastId}" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header ${bgClass} text-white">
                <i class="fas fa-info-circle me-2"></i>
                <strong class="me-auto">AEROZONE</strong>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body">
                ${message}
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, { delay: 5000 });
    toast.show();
    
    // Remove toast element after it's hidden
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (notificationCheckInterval) {
        clearInterval(notificationCheckInterval);
    }
    if (messageCheckInterval) {
        clearInterval(messageCheckInterval);
    }
});
