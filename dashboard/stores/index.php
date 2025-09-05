<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$user = getCurrentUser();
$db = getDB();

// Handle store approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $store_owner_id = intval($_POST['store_owner_id']);
    $action = $_POST['action'];
    
    try {
        if ($action === 'approve') {
            $stmt = $db->prepare("UPDATE store_owners SET registration_status = 'approved', approved_by = ?, approved_at = NOW() WHERE store_owner_id = ?");
            $stmt->execute([$user['user_id'], $store_owner_id]);
            
            // Create notification for store owner
            $stmt = $db->prepare("SELECT user_id FROM store_owners WHERE store_owner_id = ?");
            $stmt->execute([$store_owner_id]);
            $store_user = $stmt->fetch();
            
            if ($store_user) {
                $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $store_user['user_id'],
                    'Store Registration Approved',
                    'Congratulations! Your store registration has been approved. You can now start managing appointments and services.',
                    'success'
                ]);
            }
            
            $success_message = "Store registration approved successfully.";
            
        } elseif ($action === 'reject') {
            $rejection_reason = $_POST['rejection_reason'] ?? 'No reason provided';
            $stmt = $db->prepare("UPDATE store_owners SET registration_status = 'rejected', rejected_by = ?, rejected_at = NOW(), rejection_reason = ? WHERE store_owner_id = ?");
            $stmt->execute([$user['user_id'], $rejection_reason, $store_owner_id]);
            
            // Create notification for store owner
            $stmt = $db->prepare("SELECT user_id FROM store_owners WHERE store_owner_id = ?");
            $stmt->execute([$store_owner_id]);
            $store_user = $stmt->fetch();
            
            if ($store_user) {
                $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $store_user['user_id'],
                    'Store Registration Rejected',
                    'Your store registration has been rejected. Reason: ' . $rejection_reason,
                    'error'
                ]);
            }
            
            $success_message = "Store registration rejected.";
        }
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_affected, record_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['user_id'], "Store {$action}d", 'store_owners', $store_owner_id]);
        
    } catch (PDOException $e) {
        $error_message = "Error processing request: " . $e->getMessage();
    }
}

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
    // Get stores with pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    // Count total stores
    $count_sql = "SELECT COUNT(*) FROM store_owners so 
                  JOIN users u ON so.user_id = u.user_id 
                  {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_stores = $stmt->fetchColumn();
    $total_pages = ceil($total_stores / $per_page);
    
    // Get stores
    $sql = "SELECT so.*, u.full_name, u.email, u.phone_number, u.created_at as user_created_at,
                   approver.full_name as approved_by_name,
                   rejector.full_name as rejected_by_name
            FROM store_owners so
            JOIN users u ON so.user_id = u.user_id
            LEFT JOIN users approver ON so.approved_by = approver.user_id
            LEFT JOIN users rejector ON so.rejected_by = rejector.user_id
            {$where_clause}
            ORDER BY 
                CASE so.registration_status 
                    WHEN 'pending' THEN 1 
                    WHEN 'approved' THEN 2 
                    WHEN 'rejected' THEN 3 
                END,
                so.registration_date DESC
            LIMIT {$per_page} OFFSET {$offset}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $stores = $stmt->fetchAll();
    
    // Get status counts
    $stmt = $db->query("SELECT registration_status, COUNT(*) as count FROM store_owners GROUP BY registration_status");
    $status_counts = [
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'suspended' => 0
    ];
    while ($row = $stmt->fetch()) {
        $status_counts[$row['registration_status']] = $row['count'];
    }
    
} catch (PDOException $e) {
    $error_message = "Error fetching stores: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Management - AEROZONE</title>
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
                        <i class="fas fa-store me-2"></i>Store Management
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-outline-primary" onclick="exportStoreList()">
                            <i class="fas fa-download me-1"></i>Export List
                        </button>
                    </div>
                </div>

                <?php if (isset($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Status Overview Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $status_counts['pending'] ?? 0; ?></h4>
                                        <p class="card-text">Pending Review</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $status_counts['approved'] ?? 0; ?></h4>
                                        <p class="card-text">Approved</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $status_counts['rejected'] ?? 0; ?></h4>
                                        <p class="card-text">Rejected</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-times-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo array_sum($status_counts); ?></h4>
                                        <p class="card-text">Total Stores</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-store fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3 align-items-end">
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by name, business, or email..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-search me-1"></i>Filter
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Stores List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Store Registrations
                            <?php if ($total_stores > 0): ?>
                                <span class="text-muted">(<?php echo $total_stores; ?> total)</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($stores)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Business Info</th>
                                            <th>Owner Details</th>
                                            <th>Registration Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($stores as $store): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($store['business_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-map-marker-alt me-1"></i>
                                                            <?php echo htmlspecialchars(substr($store['business_address'], 0, 50)); ?>
                                                            <?php if (strlen($store['business_address']) > 50) echo '...'; ?>
                                                        </small>
                                                        <?php if ($store['business_phone']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-phone me-1"></i>
                                                                <?php echo htmlspecialchars($store['business_phone']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($store['full_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            <i class="fas fa-envelope me-1"></i>
                                                            <?php echo htmlspecialchars($store['email']); ?>
                                                        </small>
                                                        <?php if ($store['phone_number']): ?>
                                                            <br>
                                                            <small class="text-muted">
                                                                <i class="fas fa-phone me-1"></i>
                                                                <?php echo htmlspecialchars($store['phone_number']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M j, Y', strtotime($store['registration_date'])); ?></small>
                                                    <br>
                                                    <small class="text-muted">
                                                        User since: <?php echo date('M j, Y', strtotime($store['user_created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = [
                                                        'pending' => 'warning',
                                                        'approved' => 'success',
                                                        'rejected' => 'danger'
                                                    ];
                                                    ?>
                                                    <span class="badge bg-<?php echo $status_class[$store['registration_status']]; ?>">
                                                        <?php echo ucfirst($store['registration_status']); ?>
                                                    </span>
                                                    <?php if ($store['registration_status'] === 'approved' && $store['approved_by_name']): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            by <?php echo htmlspecialchars($store['approved_by_name']); ?>
                                                            <br>
                                                            <?php echo date('M j, Y', strtotime($store['approved_at'])); ?>
                                                        </small>
                                                    <?php elseif ($store['registration_status'] === 'rejected' && $store['rejected_by_name']): ?>
                                                        <br>
                                                        <small class="text-muted">
                                                            by <?php echo htmlspecialchars($store['rejected_by_name']); ?>
                                                            <br>
                                                            <?php echo date('M j, Y', strtotime($store['rejected_at'])); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="viewStoreDetails(<?php echo $store['store_owner_id']; ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if ($store['registration_status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-sm btn-success" 
                                                                    onclick="approveStore(<?php echo $store['store_owner_id']; ?>)">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" 
                                                                    onclick="rejectStore(<?php echo $store['store_owner_id']; ?>)">
                                                                <i class="fas fa-times"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Store pagination" class="mt-3">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-store fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No stores found</h5>
                                <p class="text-muted">
                                    <?php if (!empty($search) || $status_filter !== 'all'): ?>
                                        Try adjusting your search criteria or filters.
                                    <?php else: ?>
                                        No store registrations have been submitted yet.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Store Details Modal -->
    <div class="modal fade" id="storeDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Store Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="storeDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Store Registration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="store_owner_id" id="rejectStoreId">
                        
                        <div class="mb-3">
                            <label for="rejection_reason" class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required
                                      placeholder="Please provide a clear reason for rejecting this registration..."></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            The store owner will be notified of this rejection and the reason provided.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-times me-1"></i>Reject Registration
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function viewStoreDetails(storeOwnerId) {
            const modal = new bootstrap.Modal(document.getElementById('storeDetailsModal'));
            const content = document.getElementById('storeDetailsContent');
            
            // Show loading spinner
            content.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            // Fetch store details
            fetch(`store-details.php?id=${storeOwnerId}`)
                .then(response => response.text())
                .then(html => {
                    content.innerHTML = html;
                })
                .catch(error => {
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading store details. Please try again.
                        </div>
                    `;
                });
        }

        function approveStore(storeOwnerId) {
            if (confirm('Are you sure you want to approve this store registration?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="store_owner_id" value="${storeOwnerId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function rejectStore(storeOwnerId) {
            document.getElementById('rejectStoreId').value = storeOwnerId;
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        }

        function exportStoreList() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'export-stores.php?' + params.toString();
        }
    </script>
</body>
</html>
