<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('store_owner');

$user = getCurrentUser();
$db = getDB();

// Get store owner details
$stmt = $db->prepare("SELECT * FROM store_owners WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$store_owner = $stmt->fetch();

if (!$store_owner || $store_owner['registration_status'] !== 'approved') {
    header('Location: ../store/profile.php');
    exit;
}

// Handle employee actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_employee') {
            $stmt = $db->prepare("
                INSERT INTO store_employees (store_owner_id, employee_name, position, email, phone, 
                                           hire_date, salary, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $store_owner['store_owner_id'],
                $_POST['employee_name'],
                $_POST['position'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['hire_date'],
                $_POST['salary'] ?: null,
                $_POST['notes']
            ]);
            
            $success_message = "Employee added successfully.";
            
        } elseif ($action === 'edit_employee') {
            $employee_id = intval($_POST['employee_id']);
            $stmt = $db->prepare("
                UPDATE store_employees 
                SET employee_name = ?, position = ?, email = ?, phone = ?, 
                    hire_date = ?, salary = ?, notes = ?
                WHERE employee_id = ? AND store_owner_id = ?
            ");
            $stmt->execute([
                $_POST['employee_name'],
                $_POST['position'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['hire_date'],
                $_POST['salary'] ?: null,
                $_POST['notes'],
                $employee_id,
                $store_owner['store_owner_id']
            ]);
            
            $success_message = "Employee updated successfully.";
            
        } elseif ($action === 'delete_employee') {
            $employee_id = intval($_POST['employee_id']);
            $stmt = $db->prepare("DELETE FROM store_employees WHERE employee_id = ? AND store_owner_id = ?");
            $stmt->execute([$employee_id, $store_owner['store_owner_id']]);
            
            $success_message = "Employee removed successfully.";
        }
        
        // Log activity
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, table_affected, record_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$user['user_id'], "Employee {$action}", 'store_employees', $employee_id ?? null]);
        
    } catch (PDOException $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get employees
$search = $_GET['search'] ?? '';
$position_filter = $_GET['position'] ?? '';

$where_conditions = ["store_owner_id = ?"];
$params = [$store_owner['store_owner_id']];

if (!empty($search)) {
    $where_conditions[] = "(employee_name LIKE ? OR email LIKE ? OR position LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($position_filter)) {
    $where_conditions[] = "position = ?";
    $params[] = $position_filter;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    $stmt = $db->prepare("
        SELECT * FROM store_employees 
        {$where_clause}
        ORDER BY hire_date DESC
    ");
    $stmt->execute($params);
    $employees = $stmt->fetchAll();
    
    // Get unique positions for filter
    $stmt = $db->prepare("SELECT DISTINCT position FROM store_employees WHERE store_owner_id = ? ORDER BY position");
    $stmt->execute([$store_owner['store_owner_id']]);
    $positions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error_message = "Error fetching employees: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - AEROZONE</title>
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
                        <i class="fas fa-users me-2"></i>Employee Management
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                            <i class="fas fa-plus me-1"></i>Add Employee
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

                <!-- Employee Statistics -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo count($employees); ?></h4>
                                        <p class="card-text">Total Employees</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-users fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo count(array_unique(array_column($employees, 'position'))); ?></h4>
                                        <p class="card-text">Positions</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-briefcase fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo count(array_filter($employees, fn($e) => strtotime($e['hire_date']) > strtotime('-30 days'))); ?></h4>
                                        <p class="card-text">New Hires (30d)</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-user-plus fa-2x"></i>
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
                            <div class="col-md-4">
                                <label for="position" class="form-label">Position</label>
                                <select class="form-select" id="position" name="position">
                                    <option value="">All Positions</option>
                                    <?php foreach ($positions as $position): ?>
                                        <option value="<?php echo htmlspecialchars($position); ?>" 
                                                <?php echo $position_filter === $position ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($position); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by employee name, email, or position..." 
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

                <!-- Employees List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Employees
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($employees)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Employee</th>
                                            <th>Position</th>
                                            <th>Contact</th>
                                            <th>Hire Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $employee): ?>
                                            <tr>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($employee['employee_name']); ?></strong>
                                                        <?php if ($employee['salary']): ?>
                                                            <br>
                                                            <small class="text-muted">₱<?php echo number_format($employee['salary'], 2); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                                <td>
                                                    <div>
                                                        <small>
                                                            <i class="fas fa-envelope me-1"></i>
                                                            <?php echo htmlspecialchars($employee['email']); ?>
                                                        </small>
                                                        <?php if ($employee['phone']): ?>
                                                            <br>
                                                            <small>
                                                                <i class="fas fa-phone me-1"></i>
                                                                <?php echo htmlspecialchars($employee['phone']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <small><?php echo date('M j, Y', strtotime($employee['hire_date'])); ?></small>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php
                                                        $days = floor((time() - strtotime($employee['hire_date'])) / (60 * 60 * 24));
                                                        echo $days . ' days ago';
                                                        ?>
                                                    </small>
                                                </td>

                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                onclick="editEmployee(<?php echo htmlspecialchars(json_encode($employee)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteEmployee(<?php echo $employee['employee_id']; ?>, '<?php echo htmlspecialchars($employee['employee_name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No employees found</h5>
                                <p class="text-muted">
                                    <?php if (!empty($search) || !empty($position_filter)): ?>
                                        Try adjusting your search criteria.
                                    <?php else: ?>
                                        Start by adding your first employee.
                                    <?php endif; ?>
                                </p>
                                <?php if (empty($search) && empty($position_filter)): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                        <i class="fas fa-plus me-1"></i>Add Employee
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_employee">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="employee_name" class="form-label">Employee Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="employee_name" name="employee_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="position" class="form-label">Position <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="position" name="position" required
                                       placeholder="e.g., Technician, Sales Associate">
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label for="hire_date" class="form-label">Hire Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="hire_date" name="hire_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="salary" class="form-label">Salary (Optional)</label>
                                <input type="number" class="form-control" id="salary" name="salary" step="0.01" min="0">
                            </div>

                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"
                                          placeholder="Additional notes about the employee..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Add Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Employee</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_employee">
                        <input type="hidden" name="employee_id" id="edit_employee_id">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="edit_employee_name" class="form-label">Employee Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_employee_name" name="employee_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_position" class="form-label">Position <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_position" name="position" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_email" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_phone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_hire_date" class="form-label">Hire Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="edit_hire_date" name="hire_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="edit_salary" class="form-label">Salary (Optional)</label>
                                <input type="number" class="form-control" id="edit_salary" name="salary" step="0.01" min="0">
                            </div>

                            <div class="col-12">
                                <label for="edit_notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Update Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_employee">
                        <input type="hidden" name="employee_id" id="delete_employee_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Are you sure you want to remove <strong id="delete_employee_name"></strong> from your employee list?
                        </div>
                        
                        <p class="text-muted">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>Remove Employee
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function editEmployee(employee) {
            document.getElementById('edit_employee_id').value = employee.employee_id;
            document.getElementById('edit_employee_name').value = employee.employee_name;
            document.getElementById('edit_position').value = employee.position;
            document.getElementById('edit_email').value = employee.email;
            document.getElementById('edit_phone').value = employee.phone || '';
            document.getElementById('edit_hire_date').value = employee.hire_date;
            document.getElementById('edit_salary').value = employee.salary || '';
            document.getElementById('edit_notes').value = employee.notes || '';
            
            const modal = new bootstrap.Modal(document.getElementById('editEmployeeModal'));
            modal.show();
        }

        function deleteEmployee(employeeId, employeeName) {
            document.getElementById('delete_employee_id').value = employeeId;
            document.getElementById('delete_employee_name').textContent = employeeName;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteEmployeeModal'));
            modal.show();
        }

        // Set default hire date to today
        document.getElementById('hire_date').value = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
