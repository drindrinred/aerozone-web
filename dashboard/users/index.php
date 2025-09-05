<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$user = getCurrentUser();
$db = getDB();

$message = '';
$message_type = '';

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'toggle_status') {
                $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE user_id = ?");
                $stmt->execute([$_POST['user_id']]);
                $message = 'User status updated successfully!';
                $message_type = 'success';
            } elseif ($_POST['action'] === 'delete_user') {
                // Soft delete by deactivating
                $stmt = $db->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
                $stmt->execute([$_POST['user_id']]);
                $message = 'User deactivated successfully!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get filters
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = ["1=1"];
$params = [];

if ($role_filter) {
    $where_conditions[] = "u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter) {
    if ($status_filter === 'active') {
        $where_conditions[] = "u.is_active = 1";
    } elseif ($status_filter === 'inactive') {
        $where_conditions[] = "u.is_active = 0";
    }
}

if ($search) {
    $where_conditions[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = implode(' AND ', $where_conditions);

// Get users with role-specific information
$stmt = $db->prepare("
    SELECT u.*, 
           CASE 
               WHEN u.role = 'player' THEN p.membership_status
               WHEN u.role = 'store_owner' THEN so.registration_status
               ELSE NULL
           END as role_status,
           CASE 
               WHEN u.role = 'store_owner' THEN so.business_name
               ELSE NULL
           END as business_name
    FROM users u
    LEFT JOIN players p ON u.user_id = p.user_id AND u.role = 'player'
    LEFT JOIN store_owners so ON u.user_id = so.user_id AND u.role = 'store_owner'
    WHERE $where_clause
    ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user statistics
$stmt = $db->query("
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count,
        SUM(CASE WHEN role = 'player' THEN 1 ELSE 0 END) as player_count,
        SUM(CASE WHEN role = 'store_owner' THEN 1 ELSE 0 END) as store_owner_count,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_count
    FROM users
");
$stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - AEROZONE</title>
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
                        <i class="fas fa-users me-2"></i>User Management
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                            <i class="fas fa-user-plus me-1"></i>Create User
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- User Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-2">
                        <div class="card text-white bg-primary">
                            <div class="card-body text-center">
                                <h4 class="card-title"><?php echo $stats['total_users']; ?></h4>
                                <p class="card-text">Total Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-warning">
                            <div class="card-body text-center">
                                <h4 class="card-title"><?php echo $stats['admin_count']; ?></h4>
                                <p class="card-text">Admins</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-info">
                            <div class="card-body text-center">
                                <h4 class="card-title"><?php echo $stats['player_count']; ?></h4>
                                <p class="card-text">Players</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-success">
                            <div class="card-body text-center">
                                <h4 class="card-title"><?php echo $stats['store_owner_count']; ?></h4>
                                <p class="card-text">Store Owners</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-success">
                            <div class="card-body text-center">
                                <h4 class="card-title"><?php echo $stats['active_count']; ?></h4>
                                <p class="card-text">Active</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-danger">
                            <div class="card-body text-center">
                                <h4 class="card-title"><?php echo $stats['inactive_count']; ?></h4>
                                <p class="card-text">Inactive</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="role">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="player" <?php echo $role_filter === 'player' ? 'selected' : ''; ?>>Player</option>
                                    <option value="store_owner" <?php echo $role_filter === 'store_owner' ? 'selected' : ''; ?>>Store Owner</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <a href="index.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Users (<?php echo count($users); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($users)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Last Login</th>
                                            <th>Registered</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user_row): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($user_row['full_name']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            @<?php echo htmlspecialchars($user_row['username']); ?> • 
                                                            <?php echo htmlspecialchars($user_row['email']); ?>
                                                        </small>
                                                        <?php if ($user_row['business_name']): ?>
                                                            <br>
                                                            <small class="text-info">
                                                                <i class="fas fa-store me-1"></i>
                                                                <?php echo htmlspecialchars($user_row['business_name']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $user_row['role'] === 'admin' ? 'warning' : 
                                                            ($user_row['role'] === 'player' ? 'info' : 'success'); 
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $user_row['role'])); ?>
                                                    </span>
                                                    <?php if ($user_row['role_status']): ?>
                                                        <br>
                                                        <small class="badge bg-secondary mt-1">
                                                            <?php echo ucfirst($user_row['role_status']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $user_row['is_active'] ? 'success' : 'danger'; ?>">
                                                        <?php echo $user_row['is_active'] ? 'Active' : 'Inactive'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($user_row['last_login']): ?>
                                                        <?php echo date('M j, Y g:i A', strtotime($user_row['last_login'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Never</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M j, Y', strtotime($user_row['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="viewUser(<?php echo htmlspecialchars(json_encode($user_row)); ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-warning" 
                                                                onclick="editUser(<?php echo $user_row['user_id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($user_row['user_id'] != $user['user_id']): ?>
                                                            <button type="button" class="btn btn-outline-<?php echo $user_row['is_active'] ? 'secondary' : 'success'; ?>" 
                                                                    onclick="toggleUserStatus(<?php echo $user_row['user_id']; ?>, '<?php echo $user_row['is_active'] ? 'deactivate' : 'activate'; ?>')">
                                                                <i class="fas fa-<?php echo $user_row['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No users found</h4>
                                <p class="text-muted">Try adjusting your search criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetails">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewUser(user) {
            const details = `
                <div class="row g-3">
                    <div class="col-md-6">
                        <h6>Basic Information</h6>
                        <p><strong>Full Name:</strong> ${user.full_name}<br>
                        <strong>Username:</strong> @${user.username}<br>
                        <strong>Email:</strong> ${user.email}<br>
                        <strong>Phone:</strong> ${user.phone_number || 'Not provided'}<br>
                        <strong>Role:</strong> ${user.role.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Account Status</h6>
                        <p><strong>Status:</strong> <span class="badge bg-${user.is_active == 1 ? 'success' : 'danger'}">${user.is_active == 1 ? 'Active' : 'Inactive'}</span><br>
                        <strong>Registered:</strong> ${new Date(user.created_at).toLocaleDateString()}<br>
                        <strong>Last Login:</strong> ${user.last_login ? new Date(user.last_login).toLocaleDateString() : 'Never'}</p>
                    </div>
                    ${user.address ? `
                    <div class="col-12">
                        <h6>Address</h6>
                        <p>${user.address}</p>
                    </div>
                    ` : ''}
                    ${user.business_name ? `
                    <div class="col-12">
                        <h6>Business Information</h6>
                        <p><strong>Business Name:</strong> ${user.business_name}<br>
                        <strong>Registration Status:</strong> <span class="badge bg-secondary">${user.role_status}</span></p>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('userDetails').innerHTML = details;
            new bootstrap.Modal(document.getElementById('viewUserModal')).show();
        }

        function toggleUserStatus(userId, action) {
            if (confirm(`Are you sure you want to ${action} this user?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="toggle_status">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function editUser(userId) {
            window.location.href = `edit.php?id=${userId}`;
        }
    </script>
</body>
</html>

