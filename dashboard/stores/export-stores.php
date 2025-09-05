<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$db = getDB();

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "so.registration_status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $where_conditions[] = "(u.full_name LIKE ? OR so.business_name LIKE ? OR u.email LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get stores for export
    $sql = "SELECT so.*, u.full_name, u.email, u.phone_number, u.created_at as user_created_at,
                   approver.full_name as approved_by_name,
                   rejector.full_name as rejected_by_name
            FROM store_owners so
            JOIN users u ON so.user_id = u.user_id
            LEFT JOIN users approver ON so.approved_by = approver.user_id
            LEFT JOIN users rejector ON so.rejected_by = rejector.user_id
            {$where_clause}
            ORDER BY so.registration_date DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $stores = $stmt->fetchAll();
    
    // Set headers for CSV download
    $filename = 'aerozone_stores_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // Create file pointer
    $output = fopen('php://output', 'w');
    
    // Add CSV headers
    fputcsv($output, [
        'Store Owner ID',
        'Business Name',
        'Owner Name',
        'Email',
        'Phone',
        'Business Address',
        'Business Phone',
        'Business Email',
        'Website',
        'Registration Status',
        'Registration Date',
        'Approved By',
        'Approved Date',
        'Rejected By',
        'Rejected Date',
        'Rejection Reason',
        'User Created Date'
    ]);
    
    // Add data rows
    foreach ($stores as $store) {
        fputcsv($output, [
            $store['store_owner_id'],
            $store['business_name'],
            $store['full_name'],
            $store['email'],
            $store['phone_number'] ?? '',
            $store['business_address'],
            $store['business_phone'] ?? '',
            $store['business_email'] ?? '',
            $store['website'] ?? '',
            ucfirst($store['registration_status']),
            date('Y-m-d H:i:s', strtotime($store['registration_date'])),
            $store['approved_by_name'] ?? '',
            $store['approved_at'] ? date('Y-m-d H:i:s', strtotime($store['approved_at'])) : '',
            $store['rejected_by_name'] ?? '',
            $store['rejected_at'] ? date('Y-m-d H:i:s', strtotime($store['rejected_at'])) : '',
            $store['rejection_reason'] ?? '',
            date('Y-m-d H:i:s', strtotime($store['user_created_at']))
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    // If there's an error, redirect back with error message
    header('Location: index.php?error=' . urlencode('Export failed: ' . $e->getMessage()));
    exit;
}
?>
