<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

$store_id = intval($_GET['id'] ?? 0);

if (!$store_id) {
    echo '<div class="alert alert-danger">Invalid store ID.</div>';
    exit;
}

try {
    // Get store details
    $stmt = $db->prepare("
        SELECT so.*, u.full_name, u.email, u.phone_number, u.created_at as user_created_at
        FROM store_owners so
        JOIN users u ON so.user_id = u.user_id
        WHERE so.store_owner_id = ? AND so.registration_status = 'approved'
    ");
    $stmt->execute([$store_id]);
    $store = $stmt->fetch();
    
    if (!$store) {
        echo '<div class="alert alert-danger">Store not found or not approved.</div>';
        exit;
    }
    
    // Get store inventory
    $stmt = $db->prepare("
        SELECT si.*, gc.category_name
        FROM store_inventory si
        LEFT JOIN gear_categories gc ON si.category_id = gc.category_id
        WHERE si.store_owner_id = ? AND si.is_available = 1
        ORDER BY si.item_name ASC
        LIMIT 10
    ");
    $stmt->execute([$store_id]);
    $inventory = $stmt->fetchAll();
    
    // Get store employees
    $stmt = $db->prepare("
        SELECT employee_name, position, contact_info
        FROM store_employees
        WHERE store_owner_id = ? AND is_active = 1
        ORDER BY employee_name ASC
    ");
    $stmt->execute([$store_id]);
    $employees = $stmt->fetchAll();
    
    // Get recent appointments count
    $stmt = $db->prepare("
        SELECT COUNT(*) as total_appointments,
               SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_appointments
        FROM maintenance_appointments
        WHERE store_owner_id = ?
    ");
    $stmt->execute([$store_id]);
    $appointment_stats = $stmt->fetch();
    
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error loading store details.</div>';
    exit;
}
?>

<div class="row">
    <div class="col-md-8">
        <!-- Store Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-store me-2"></i><?php echo htmlspecialchars($store['business_name']); ?>
                    <span class="badge bg-success ms-2">Verified</span>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Business Information</h6>
                        <p class="mb-2">
                            <strong>Owner:</strong> <?php echo htmlspecialchars($store['full_name']); ?>
                        </p>
                        <p class="mb-2">
                            <strong>Address:</strong><br>
                            <?php echo nl2br(htmlspecialchars($store['business_address'])); ?>
                        </p>
                        <?php if ($store['business_phone']): ?>
                            <p class="mb-2">
                                <strong>Phone:</strong> 
                                <a href="tel:<?php echo htmlspecialchars($store['business_phone']); ?>">
                                    <?php echo htmlspecialchars($store['business_phone']); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        <?php if ($store['business_email']): ?>
                            <p class="mb-2">
                                <strong>Email:</strong> 
                                <a href="mailto:<?php echo htmlspecialchars($store['business_email']); ?>">
                                    <?php echo htmlspecialchars($store['business_email']); ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        <?php if ($store['website']): ?>
                            <p class="mb-2">
                                <strong>Website:</strong> 
                                <a href="<?php echo htmlspecialchars($store['website']); ?>" target="_blank">
                                    <?php echo htmlspecialchars($store['website']); ?>
                                    <i class="fas fa-external-link-alt ms-1"></i>
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h6>Service Statistics</h6>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="border-end">
                                    <h4 class="text-primary mb-0"><?php echo $appointment_stats['total_appointments']; ?></h4>
                                    <small class="text-muted">Total Services</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <h4 class="text-success mb-0"><?php echo $appointment_stats['completed_appointments']; ?></h4>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <h6>Member Since</h6>
                            <p class="text-muted"><?php echo date('F Y', strtotime($store['registration_date'])); ?></p>
                        </div>
                        
                        <!-- Rating placeholder -->
                        <div class="mt-3">
                            <h6>Customer Rating</h6>
                            <div class="text-warning">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="far fa-star"></i>
                                <span class="text-muted ms-2">4.0 (Coming Soon)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Store Inventory -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-boxes me-2"></i>Available Items
                    <span class="badge bg-primary ms-2"><?php echo count($inventory); ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($inventory)): ?>
                    <div class="row">
                        <?php foreach ($inventory as $item): ?>
                            <div class="col-md-6 mb-3">
                                <div class="border rounded p-3">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                    <?php if ($item['category_name']): ?>
                                        <span class="badge bg-light text-dark mb-2"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                    <?php endif; ?>
                                    <?php if ($item['brand']): ?>
                                        <p class="mb-1"><small><strong>Brand:</strong> <?php echo htmlspecialchars($item['brand']); ?></small></p>
                                    <?php endif; ?>
                                    <?php if ($item['model']): ?>
                                        <p class="mb-1"><small><strong>Model:</strong> <?php echo htmlspecialchars($item['model']); ?></small></p>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-muted">Qty: <?php echo $item['quantity']; ?></span>
                                        <?php if ($item['unit_price']): ?>
                                            <span class="fw-bold text-success">₱<?php echo number_format($item['unit_price'], 2); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center">
                        <button class="btn btn-outline-primary" onclick="viewFullInventory(<?php echo $store_id; ?>)">
                            <i class="fas fa-eye me-1"></i>View Full Inventory
                        </button>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-3">
                        <i class="fas fa-box-open fa-2x mb-2"></i>
                        <p>No inventory items available at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" onclick="bookAppointment(<?php echo $store_id; ?>)">
                        <i class="fas fa-calendar me-2"></i>Book Appointment
                    </button>
                    <button class="btn btn-outline-primary" onclick="contactStore(<?php echo $store['user_id']; ?>)">
                        <i class="fas fa-envelope me-2"></i>Send Message
                    </button>
                    <button class="btn btn-outline-secondary" onclick="getDirections()">
                        <i class="fas fa-map-marker-alt me-2"></i>Get Directions
                    </button>
                </div>
            </div>
        </div>

        <!-- Store Staff -->
        <?php if (!empty($employees)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-users me-2"></i>Store Staff
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($employees as $employee): ?>
                        <div class="d-flex align-items-center mb-2">
                            <div class="flex-grow-1">
                                <h6 class="mb-0"><?php echo htmlspecialchars($employee['employee_name']); ?></h6>
                                <?php if ($employee['position']): ?>
                                    <small class="text-muted"><?php echo htmlspecialchars($employee['position']); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($employee !== end($employees)): ?>
                            <hr class="my-2">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Services Offered -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-tools me-2"></i>Services Offered
                </h5>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2">
                        <i class="fas fa-wrench text-primary me-2"></i>
                        Equipment Maintenance
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-tools text-primary me-2"></i>
                        Repair Services
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-spray-can text-primary me-2"></i>
                        Cleaning & Care
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-search text-primary me-2"></i>
                        Equipment Inspection
                    </li>
                    <li class="mb-0">
                        <i class="fas fa-cog text-primary me-2"></i>
                        Part Replacement
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
function bookAppointment(storeId) {
    window.parent.location.href = `../appointments/index.php?store_id=${storeId}`;
}

function contactStore(userId) {
    window.parent.location.href = `../messages/compose.php?recipient_id=${userId}`;
}

function getDirections() {
    const address = "<?php echo addslashes($store['business_address']); ?>";
    const encodedAddress = encodeURIComponent(address);
    window.open(`https://www.google.com/maps/search/?api=1&query=${encodedAddress}`, '_blank');
}

function viewFullInventory(storeId) {
    window.parent.location.href = `../inventory/store-inventory.php?store_id=${storeId}`;
}
</script>
