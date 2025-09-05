<?php
// Session management for Aerozone
session_start();

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Check if user has any of the specified roles
function hasAnyRole($roles) {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /aerozone/auth/login.php');
        exit();
    }
}

// Redirect if user doesn't have required role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        header('Location: /aerozone/dashboard/unauthorized.php');
        exit();
    }
}

// Redirect if user doesn't have any of the required roles
function requireAnyRole($roles) {
    requireLogin();
    if (!hasAnyRole($roles)) {
        header('Location: /aerozone/dashboard/unauthorized.php');
        exit();
    }
}

// Set user session
function setUserSession($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
    
    // Update last login
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
}

// Clear user session
function clearUserSession() {
    session_unset();
    session_destroy();
}

// Get current user info
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'full_name' => $_SESSION['full_name'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role']
    ];
}

// If a store owner is not approved, redirect them to the appropriate holding page
function redirectIfStoreOwnerNotApproved() {
    if (!isLoggedIn()) { return; }
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'store_owner') { return; }
    try {
        $db = getDB();
        $stmt = $db->prepare('SELECT registration_status FROM store_owners WHERE user_id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $owner = $stmt->fetch();
        if ($owner) {
            if ($owner['registration_status'] === 'pending') {
                header('Location: /aerozone/dashboard/pending.php');
                exit();
            } elseif ($owner['registration_status'] === 'rejected') {
                header('Location: /aerozone/dashboard/rejected.php');
                exit();
            }
        }
    } catch (Throwable $e) {
        // Fail open: if DB unavailable, allow access rather than locking user out
    }
}

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
?>
