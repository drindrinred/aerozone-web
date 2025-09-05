<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('player');

$user = getCurrentUser();
$db = getDB();

// Get player ID
$stmt = $db->prepare("SELECT player_id FROM players WHERE user_id = ?");
$stmt->execute([$user['user_id']]);
$player = $stmt->fetch();
$player_id = $player['player_id'];

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'request_appointment') {
                $stmt = $db->prepare("INSERT INTO maintenance_appointments (player_id, store_owner_id, inventory_id, appointment_date, service_type, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $player_id,
                    $_POST['store_owner_id'],
                    $_POST['inventory_id'] ?: null,
                    $_POST['appointment_date'],
                    $_POST['service_type'],
                    $_POST['description']
                ]);
                $message = 'Appointment request submitted successfully!';
                $message_type = 'success';
            } elseif ($_POST['action'] === 'cancel_appointment') {
                $stmt = $db->prepare("UPDATE maintenance_appointments SET status = 'cancelled' WHERE appointment_id = ? AND player_id = ?");
                $stmt->execute([$_POST['appointment_id'], $player_id]);
                $message = 'Appointment cancelled successfully!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get active tab from URL parameter
$active_tab = $_GET['tab'] ?? 'pending';

// Get player's appointments by status
$statuses = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
$appointments_by_status = [];

foreach ($statuses as $status) {
    $stmt = $db->prepare("
        SELECT ma.*, so.business_name, u.full_name as store_owner_name, pi.item_name
        FROM maintenance_appointments ma
        JOIN store_owners so ON ma.store_owner_id = so.store_owner_id
        JOIN users u ON so.user_id = u.user_id
        LEFT JOIN player_inventory pi ON ma.inventory_id = pi.inventory_id
        WHERE ma.player_id = ? AND ma.status = ?
        ORDER BY ma.appointment_date DESC
    ");
    $stmt->execute([$player_id, $status]);
    $appointments_by_status[$status] = $stmt->fetchAll();
}

// Get approved stores for dropdown
$stmt = $db->query("
    SELECT so.store_owner_id, so.business_name, u.full_name as owner_name
    FROM store_owners so
    JOIN users u ON so.user_id = u.user_id
    WHERE so.registration_status = 'approved'
    ORDER BY so.business_name
");
$stores = $stmt->fetchAll();

// Get player's inventory for dropdown
$stmt = $db->prepare("
    SELECT inventory_id, item_name, brand, model
    FROM player_inventory
    WHERE player_id = ? AND is_active = 1
    ORDER BY item_name
");
$stmt->execute([$player_id]);
$inventory_items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Maintenance - AEROZONE</title>
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
                        <i class="fas fa-calendar me-2"></i>Service Requests
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestAppointmentModal">
                            <i class="fas fa-plus me-1"></i>New Service Request
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Appointment Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-2">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo count($appointments_by_status['pending']); ?></h4>
                                        <p class="card-text">Pending</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo count($appointments_by_status['confirmed']); ?></h4>
                                        <p class="card-text">Confirmed</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo count($appointments_by_status['in_progress']); ?></h4>
                                        <p class="card-text">In Progress</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-tools fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo count($appointments_by_status['completed']); ?></h4>
                                        <p class="card-text">Completed</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo count($appointments_by_status['cancelled']); ?></h4>
                                        <p class="card-text">Cancelled</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-times-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-secondary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo array_sum(array_map('count', $appointments_by_status)); ?></h4>
                                        <p class="card-text">Total</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Tabs -->
                <ul class="nav nav-tabs mb-4" id="appointmentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'pending' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?tab=pending'" type="button">
                            <i class="fas fa-clock me-2"></i>Pending
                            <span class="badge bg-warning ms-2"><?php echo count($appointments_by_status['pending']); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'confirmed' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?tab=confirmed'" type="button">
                            <i class="fas fa-check me-2"></i>Confirmed
                            <span class="badge bg-info ms-2"><?php echo count($appointments_by_status['confirmed']); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'in_progress' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?tab=in_progress'" type="button">
                            <i class="fas fa-tools me-2"></i>In Progress
                            <span class="badge bg-primary ms-2"><?php echo count($appointments_by_status['in_progress']); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'completed' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?tab=completed'" type="button">
                            <i class="fas fa-check-circle me-2"></i>Completed
                            <span class="badge bg-success ms-2"><?php echo count($appointments_by_status['completed']); ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo $active_tab === 'cancelled' ? 'active' : ''; ?>" 
                                onclick="window.location.href='?tab=cancelled'" type="button">
                            <i class="fas fa-times-circle me-2"></i>Cancelled
                            <span class="badge bg-danger ms-2"><?php echo count($appointments_by_status['cancelled']); ?></span>
                        </button>
                    </li>
                </ul>

                <!-- Appointments List for Current Tab -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $active_tab)); ?> Service Requests
                            <span class="text-muted">(<?php echo count($appointments_by_status[$active_tab]); ?> requests)</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($appointments_by_status[$active_tab])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>Store</th>
                                            <th>Service Type</th>
                                            <th>Item</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments_by_status[$active_tab] as $appointment): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></strong><br>
                                                    <small class="text-muted"><?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($appointment['business_name']); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($appointment['store_owner_name']); ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">
                                                        <?php echo ucfirst(str_replace('_', ' ', $appointment['service_type'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($appointment['item_name']): ?>
                                                        <?php echo htmlspecialchars($appointment['item_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">General Service</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo $appointment['status'] === 'pending' ? 'warning' : 
                                                            ($appointment['status'] === 'confirmed' ? 'info' : 
                                                            ($appointment['status'] === 'in_progress' ? 'primary' :
                                                            ($appointment['status'] === 'completed' ? 'success' : 
                                                            ($appointment['status'] === 'cancelled' ? 'danger' : 'secondary')))); 
                                                    ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $appointment['status'])); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="viewAppointment(<?php echo htmlspecialchars(json_encode($appointment)); ?>)">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <?php if (in_array($appointment['status'], ['pending', 'confirmed'])): ?>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="cancelAppointment(<?php echo $appointment['appointment_id']; ?>)">
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
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No <?php echo $active_tab; ?> requests</h4>
                                <p class="text-muted">
                                    <?php if ($active_tab === 'pending'): ?>
                                        You don't have any pending service requests.
                                    <?php elseif ($active_tab === 'completed'): ?>
                                        You don't have any completed service requests yet.
                                    <?php elseif ($active_tab === 'cancelled'): ?>
                                        You don't have any cancelled service requests.
                                    <?php else: ?>
                                        You don't have any <?php echo $active_tab; ?> service requests.
                                    <?php endif; ?>
                                </p>
                                <?php if ($active_tab === 'pending'): ?>
                                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestAppointmentModal">
                                        <i class="fas fa-plus me-1"></i>Request Service
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Request Appointment Modal -->
    <div class="modal fade" id="requestAppointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Service</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="request_appointment">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="store_owner_id" class="form-label">Select Store *</label>
                                <select class="form-select" id="store_owner_id" name="store_owner_id" required>
                                    <option value="">Choose a store...</option>
                                    <?php foreach ($stores as $store): ?>
                                        <option value="<?php echo $store['store_owner_id']; ?>">
                                            <?php echo htmlspecialchars($store['business_name']); ?> 
                                            (<?php echo htmlspecialchars($store['owner_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="service_type" class="form-label">Service Type *</label>
                                <select class="form-select" id="service_type" name="service_type" required>
                                    <option value="">Select service...</option>
                                    <option value="maintenance">General Maintenance</option>
                                    <option value="cleaning">Cleaning</option>
                                    <option value="repair">Repair</option>
                                    <option value="inspection">Inspection</option>
                                    <option value="part_replacement">Part Replacement</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="inventory_id" class="form-label">Select Item (Optional)</label>
                                <select class="form-select" id="inventory_id" name="inventory_id">
                                    <option value="">General service (no specific item)</option>
                                    <?php foreach ($inventory_items as $item): ?>
                                        <option value="<?php echo $item['inventory_id']; ?>">
                                            <?php echo htmlspecialchars($item['item_name']); ?>
                                            <?php if ($item['brand'] || $item['model']): ?>
                                                (<?php echo htmlspecialchars(trim($item['brand'] . ' ' . $item['model'])); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="appointment_date" class="form-label">Preferred Date & Time *</label>
                                <input type="datetime-local" class="form-control" id="appointment_date" name="appointment_date" 
                                       min="<?php echo date('Y-m-d\TH:i'); ?>" required>
                            </div>
                            
                            <div class="col-12">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Describe the issue or service needed..."></textarea>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Note:</strong> This is a service request. The store owner will review and confirm your appointment. 
                            You will receive a notification once they respond.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Appointment Modal -->
    <div class="modal fade" id="viewAppointmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Service Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="appointmentDetails">
                    <!-- Content will be populated by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function viewAppointment(appointment) {
            const details = `
                <div class="row g-3">
                    <div class="col-12">
                        <h6>Store Information</h6>
                        <p><strong>${appointment.business_name}</strong><br>
                        Owner: ${appointment.store_owner_name}</p>
                    </div>
                    <div class="col-6">
                        <h6>Date & Time</h6>
                        <p>${new Date(appointment.appointment_date).toLocaleDateString('en-US', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        })}<br>
                        ${new Date(appointment.appointment_date).toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        })}</p>
                    </div>
                    <div class="col-6">
                        <h6>Service Type</h6>
                        <p>${appointment.service_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</p>
                    </div>
                    ${appointment.item_name ? `
                    <div class="col-12">
                        <h6>Item</h6>
                        <p>${appointment.item_name}</p>
                    </div>
                    ` : ''}
                    <div class="col-12">
                        <h6>Status</h6>
                        <span class="badge bg-${appointment.status === 'pending' ? 'warning' : 
                            (appointment.status === 'confirmed' ? 'info' : 
                            (appointment.status === 'in_progress' ? 'primary' :
                            (appointment.status === 'completed' ? 'success' : 
                            (appointment.status === 'cancelled' ? 'danger' : 'secondary'))))}">${appointment.status.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                    </div>
                    ${appointment.description ? `
                    <div class="col-12">
                        <h6>Description</h6>
                        <p>${appointment.description}</p>
                    </div>
                    ` : ''}
                    ${appointment.estimated_cost ? `
                    <div class="col-6">
                        <h6>Estimated Cost</h6>
                        <p>₱${parseFloat(appointment.estimated_cost).toFixed(2)}</p>
                    </div>
                    ` : ''}
                    ${appointment.actual_cost ? `
                    <div class="col-6">
                        <h6>Actual Cost</h6>
                        <p>₱${parseFloat(appointment.actual_cost).toFixed(2)}</p>
                    </div>
                    ` : ''}
                    ${appointment.notes ? `
                    <div class="col-12">
                        <h6>Notes</h6>
                        <p>${appointment.notes}</p>
                    </div>
                    ` : ''}
                </div>
            `;
            
            document.getElementById('appointmentDetails').innerHTML = details;
            new bootstrap.Modal(document.getElementById('viewAppointmentModal')).show();
        }

        function cancelAppointment(appointmentId) {
            if (confirm('Are you sure you want to cancel this service request?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel_appointment">
                    <input type="hidden" name="appointment_id" value="${appointmentId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Set minimum date to today
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            document.getElementById('appointment_date').min = now.toISOString().slice(0, 16);
        });
    </script>
</body>
</html>
