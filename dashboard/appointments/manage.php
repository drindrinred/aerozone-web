<?php
require_once '../../config/database.php';
require_once '../../config/session.php';
require_once '../../includes/notification_helper.php';

requireRole('store_owner');

$user = getCurrentUser();
$db = getDB();

// Get store owner ID
$stmt = $db->prepare("SELECT store_owner_id FROM store_owners WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$store_owner = $stmt->fetch();
$store_owner_id = $store_owner['store_owner_id'];

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appointment_id = intval($_POST['appointment_id']);
    
    try {
        // Get appointment details
        $stmt = $db->prepare("
            SELECT ma.*, p.player_id, u.full_name as player_name, u.email as player_email
            FROM maintenance_appointments ma
            JOIN players p ON ma.player_id = p.player_id
            JOIN users u ON p.user_id = u.user_id
            WHERE ma.appointment_id = ? AND ma.store_owner_id = ?
        ");
        $stmt->execute([$appointment_id, $store_owner_id]);
        $appointment = $stmt->fetch();
        
        if ($appointment) {
            if ($_POST['action'] === 'confirm') {
                $stmt = $db->prepare("UPDATE maintenance_appointments SET status = 'confirmed' WHERE appointment_id = ? AND store_owner_id = ?");
                $stmt->execute([$appointment_id, $store_owner_id]);
                
                // Create notification
                createAppointmentConfirmation($appointment['player_id'], $appointment['appointment_date'], $appointment['service_type']);
                
                $message = 'Appointment confirmed successfully!';
                $message_type = 'success';
                
            } elseif ($_POST['action'] === 'start') {
                $stmt = $db->prepare("UPDATE maintenance_appointments SET status = 'in_progress' WHERE appointment_id = ? AND store_owner_id = ?");
                $stmt->execute([$appointment_id, $store_owner_id]);
                $message = 'Appointment marked as in progress!';
                $message_type = 'success';
                
            } elseif ($_POST['action'] === 'complete') {
                $actual_cost = $_POST['actual_cost'] ?? null;
                $notes = $_POST['notes'] ?? '';
                
                $stmt = $db->prepare("UPDATE maintenance_appointments SET status = 'completed', actual_cost = ?, notes = ? WHERE appointment_id = ? AND store_owner_id = ?");
                $stmt->execute([$actual_cost, $notes, $appointment_id, $store_owner_id]);
                
                // Create notification
                createSimpleNotification($appointment['player_id'], 'appointment_confirmed', 'Service Completed', 'Your maintenance service has been completed. Please check the details.');
                
                $message = 'Appointment completed successfully!';
                $message_type = 'success';
                
            } elseif ($_POST['action'] === 'cancel') {
                $cancellation_reason = $_POST['cancellation_reason'] ?? 'No reason provided';
                $stmt = $db->prepare("UPDATE maintenance_appointments SET status = 'cancelled', notes = ? WHERE appointment_id = ? AND store_owner_id = ?");
                $stmt->execute([$cancellation_reason, $appointment_id, $store_owner_id]);
                
                // Create notification
                createSimpleNotification($appointment['player_id'], 'system_announcement', 'Appointment Cancelled', 'Your maintenance appointment has been cancelled. Reason: ' . $cancellation_reason);
                
                $message = 'Appointment cancelled successfully!';
                $message_type = 'success';
            }
        }
        
    } catch (PDOException $e) {
        $message = 'Error processing request: ' . $e->getMessage();
        $message_type = 'danger';
    }
}

// Get active service and status from URL parameters
$active_service = $_GET['service'] ?? 'all';
$active_status = $_GET['status'] ?? 'pending';

// Get available service types
try {
    $stmt = $db->prepare("
        SELECT DISTINCT service_type 
        FROM maintenance_appointments 
        WHERE store_owner_id = ? 
        ORDER BY service_type
    ");
    $stmt->execute([$store_owner_id]);
    $service_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $service_types = [];
}

// Get appointments with filtering
try {
    $where_conditions = ["ma.store_owner_id = ?"];
    $params = [$store_owner_id];
    
    if ($active_service !== 'all') {
        $where_conditions[] = "ma.service_type = ?";
        $params[] = $active_service;
    }
    
    if ($active_status !== 'all') {
        $where_conditions[] = "ma.status = ?";
        $params[] = $active_status;
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $db->prepare("
        SELECT ma.*, 
               u.full_name as player_name, 
               u.email as player_email,
               u.phone_number as player_phone,
               pi.item_name,
               pi.brand
        FROM maintenance_appointments ma
        JOIN players p ON ma.player_id = p.player_id
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN player_inventory pi ON ma.inventory_id = pi.inventory_id
        WHERE {$where_clause}
        ORDER BY ma.appointment_date DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
    // Get status counts for current service
    $status_counts = [];
    $status_where = ["ma.store_owner_id = ?"];
    $status_params = [$store_owner_id];
    
    if ($active_service !== 'all') {
        $status_where[] = "ma.service_type = ?";
        $status_params[] = $active_service;
    }
    
    $status_where_clause = implode(' AND ', $status_where);
    
    $stmt = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM maintenance_appointments ma 
        WHERE {$status_where_clause} 
        GROUP BY status
    ");
    $stmt->execute($status_params);
    while ($row = $stmt->fetch()) {
        $status_counts[$row['status']] = $row['count'];
    }
    
} catch (PDOException $e) {
    $error_message = "Error fetching appointments: " . $e->getMessage();
    $appointments = [];
    $status_counts = [];
}

// Helper function to get status styling
function getStatusBadge($status) {
    $styles = [
        'pending' => 'warning',
        'confirmed' => 'info', 
        'in_progress' => 'primary',
        'completed' => 'success',
        'cancelled' => 'danger'
    ];
    return $styles[$status] ?? 'secondary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Service Requests - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        /* Remove badge animations */
        .badge {
            animation: none !important;
            transition: none !important;
        }
        
        /* Remove any hover effects on badges */
        .badge:hover {
            transform: none !important;
            transition: none !important;
        }
        
        /* Remove any pulse or other badge animations */
        .badge.bg-warning,
        .badge.bg-info,
        .badge.bg-primary,
        .badge.bg-success,
        .badge.bg-danger {
            animation: none !important;
            transition: none !important;
        }

        /* Fix content alignment */
        .main-content {
            padding: 20px;
            min-height: 100vh;
        }

        /* Improve table alignment */
        .table th {
            vertical-align: middle;
            font-weight: 600;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        .table td {
            vertical-align: middle;
            padding: 12px 8px;
        }

        /* Fix customer info alignment */
        .customer-info {
            line-height: 1.4;
        }

        .customer-info strong {
            display: block;
            margin-bottom: 4px;
        }

        .customer-info small {
            display: block;
            margin-bottom: 2px;
        }

        /* Fix service info alignment */
        .service-info {
            line-height: 1.4;
        }

        .service-info strong {
            display: block;
            margin-bottom: 4px;
        }

        /* Fix date alignment */
        .date-info {
            line-height: 1.4;
        }

        .date-info strong {
            display: block;
            margin-bottom: 4px;
        }

        /* Fix cost alignment */
        .cost-info {
            text-align: right;
        }

        /* Fix action buttons alignment */
        .action-buttons {
            text-align: center;
        }

        .btn-group {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        /* Improve tab alignment */
        .nav-tabs .nav-link {
            padding: 10px 20px;
            font-weight: 500;
        }

        .nav-pills .nav-link {
            padding: 8px 16px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Fix card header alignment */
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 15px 20px;
        }

        .card-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0;
        }

        /* Improve responsive layout */
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }

            .table-responsive {
                font-size: 14px;
            }

            .btn-group {
                flex-direction: column;
                gap: 3px;
            }

            .btn-group .btn {
                width: 100%;
                margin: 0;
            }

            .nav-tabs .nav-link {
                padding: 8px 12px;
                font-size: 14px;
            }

            .nav-pills .nav-link {
                padding: 6px 12px;
                font-size: 14px;
            }
        }

        /* Fix empty state alignment */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state i {
            margin-bottom: 15px;
        }

        /* Improve status badge alignment */
        .status-badge {
            display: inline-block;
            text-align: center;
            min-width: 80px;
        }

        /* Fix modal alignment */
        .modal-body {
            padding: 20px;
        }

        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #dee2e6;
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
                        <i class="fas fa-calendar-check me-2"></i>Service Requests
                    </h1>
                </div>

                <?php if (isset($message)): ?>
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

                <!-- Service Type Tabs -->
                <ul class="nav nav-tabs mb-3" id="serviceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_service === 'all' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?service=all&status=<?php echo $active_status; ?>'" type="button">
                            <i class="fas fa-list me-2"></i>All Services
                        </button>
                    </li>
                    <?php foreach ($service_types as $service_type): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $active_service === $service_type ? 'active' : ''; ?>" 
                                    onclick="window.location.href='?service=<?php echo urlencode($service_type); ?>&status=<?php echo $active_status; ?>'" type="button">
                                <i class="fas fa-tools me-2"></i><?php echo ucfirst(htmlspecialchars($service_type)); ?>
                            </button>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <!-- Status Tabs -->
                <ul class="nav nav-pills mb-4" id="statusTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_status === 'pending' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?service=<?php echo urlencode($active_service); ?>&status=pending'" type="button">
                            <i class="fas fa-clock me-1"></i>Pending
                            <span class="badge bg-warning ms-1"><?php echo $status_counts['pending'] ?? 0; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_status === 'confirmed' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?service=<?php echo urlencode($active_service); ?>&status=confirmed'" type="button">
                            <i class="fas fa-check me-1"></i>Confirmed
                            <span class="badge bg-info ms-1"><?php echo $status_counts['confirmed'] ?? 0; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_status === 'in_progress' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?service=<?php echo urlencode($active_service); ?>&status=in_progress'" type="button">
                            <i class="fas fa-tools me-1"></i>In Progress
                            <span class="badge bg-primary ms-1"><?php echo $status_counts['in_progress'] ?? 0; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_status === 'completed' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?service=<?php echo urlencode($active_service); ?>&status=completed'" type="button">
                            <i class="fas fa-check-circle me-1"></i>Completed
                            <span class="badge bg-success ms-1"><?php echo $status_counts['completed'] ?? 0; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_status === 'cancelled' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?service=<?php echo urlencode($active_service); ?>&status=cancelled'" type="button">
                            <i class="fas fa-times-circle me-1"></i>Cancelled
                            <span class="badge bg-danger ms-1"><?php echo $status_counts['cancelled'] ?? 0; ?></span>
                        </button>
                    </li>
                </ul>

                <!-- Appointments List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>
                            <?php if ($active_service !== 'all'): ?>
                                <?php echo ucfirst(htmlspecialchars($active_service)); ?> - 
                            <?php endif; ?>
                            <?php echo ucfirst(str_replace('_', ' ', $active_status)); ?> Requests
                            <span class="text-muted">(<?php echo count($appointments); ?> requests)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($appointments)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Customer</th>
                                            <th>Service</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Cost</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <tr>
                                                <td>
                                                    <div class="customer-info">
                                                        <strong><?php echo htmlspecialchars($appointment['player_name']); ?></strong>
                                                        <small class="text-muted">
                                                            <i class="fas fa-envelope me-1"></i>
                                                            <?php echo htmlspecialchars($appointment['player_email']); ?>
                                                        </small>
                                                        <?php if ($appointment['player_phone']): ?>
                                                            <small class="text-muted">
                                                                <i class="fas fa-phone me-1"></i>
                                                                <?php echo htmlspecialchars($appointment['player_phone']); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="service-info">
                                                        <strong><?php echo ucfirst(htmlspecialchars($appointment['service_type'])); ?></strong>
                                                        <?php if ($appointment['item_name']): ?>
                                                            <small class="text-muted">
                                                                <?php echo htmlspecialchars($appointment['item_name']); ?>
                                                                <?php if ($appointment['brand']): ?>
                                                                    (<?php echo htmlspecialchars($appointment['brand']); ?>)
                                                                <?php endif; ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="date-info">
                                                        <strong><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></strong>
                                                        <small class="text-muted">
                                                            <?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?>
                                                        </small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadge($appointment['status']); ?> status-badge">
                                                        <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="cost-info">
                                                    <?php if ($appointment['actual_cost']): ?>
                                                        <strong>₱<?php echo number_format($appointment['actual_cost'], 2); ?></strong>
                                                    <?php elseif ($appointment['estimated_cost']): ?>
                                                        <small class="text-muted">Est: ₱<?php echo number_format($appointment['estimated_cost'], 2); ?></small>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="action-buttons">
                                                    <div class="btn-group" role="group">
                                                        <?php if ($appointment['status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-sm btn-success" onclick="confirmAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                                                <i class="fas fa-check me-1"></i>Confirm
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" onclick="cancelAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                                                <i class="fas fa-times me-1"></i>Cancel
                                                            </button>
                                                        <?php elseif ($appointment['status'] === 'confirmed'): ?>
                                                            <button type="button" class="btn btn-sm btn-primary" onclick="startAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                                                <i class="fas fa-tools me-1"></i>Start
                                                            </button>
                                                        <?php elseif ($appointment['status'] === 'in_progress'): ?>
                                                            <button type="button" class="btn btn-sm btn-success" onclick="completeAppointment(<?php echo $appointment['appointment_id']; ?>)">
                                                                <i class="fas fa-check-circle me-1"></i>Complete
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
                            <div class="empty-state">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No <?php echo $active_status; ?> requests</h5>
                                <p class="text-muted">
                                    <?php if ($active_service !== 'all'): ?>
                                        You don't have any <?php echo $active_status; ?> <?php echo $active_service; ?> requests.
                                    <?php else: ?>
                                        You don't have any <?php echo $active_status; ?> service requests.
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Complete Appointment Modal -->
    <div class="modal fade" id="completeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Complete Service Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="complete">
                        <input type="hidden" name="appointment_id" id="completeAppointmentId">
                        
                        <div class="mb-3">
                            <label for="actual_cost" class="form-label">Actual Cost (₱)</label>
                            <input type="number" step="0.01" class="form-control" id="actual_cost" name="actual_cost" placeholder="Enter actual cost">
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Enter any notes about the service performed"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Complete Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Cancel Appointment Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Cancel Service Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cancel">
                        <input type="hidden" name="appointment_id" id="cancelAppointmentId">
                        
                        <div class="mb-3">
                            <label for="cancellation_reason" class="form-label">Cancellation Reason</label>
                            <textarea class="form-control" id="cancellation_reason" name="cancellation_reason" rows="3" placeholder="Please provide a reason for cancellation" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Cancel Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function confirmAppointment(appointmentId) {
            if (confirm('Are you sure you want to confirm this service request?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="confirm">
                    <input type="hidden" name="appointment_id" value="${appointmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function startAppointment(appointmentId) {
            if (confirm('Are you sure you want to start this service request?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="start">
                    <input type="hidden" name="appointment_id" value="${appointmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function completeAppointment(appointmentId) {
            document.getElementById('completeAppointmentId').value = appointmentId;
            new bootstrap.Modal(document.getElementById('completeModal')).show();
        }

        function cancelAppointment(appointmentId) {
            document.getElementById('cancelAppointmentId').value = appointmentId;
            new bootstrap.Modal(document.getElementById('cancelModal')).show();
        }
    </script>
</body>
</html> 