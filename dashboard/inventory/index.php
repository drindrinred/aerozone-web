<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireAnyRole(['player', 'admin']);

$user = getCurrentUser();
$db = getDB();

// Get player ID
$player_id = null;
if ($user['role'] === 'player') {
    $stmt = $db->prepare("SELECT player_id FROM players WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $player = $stmt->fetch();
    $player_id = $player['player_id'];
} elseif ($user['role'] === 'admin' && isset($_GET['player_id'])) {
    $player_id = $_GET['player_id'];
}

if (!$player_id) {
    header('Location: ../index.php');
    exit();
}

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_item') {
                $stmt = $db->prepare("INSERT INTO player_inventory (player_id, item_name, category_id, brand, model, serial_number, purchase_date, purchase_price, condition_status, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $player_id,
                    $_POST['item_name'],
                    $_POST['category_id'] ?: null,
                    $_POST['brand'] ?: null,
                    $_POST['model'] ?: null,
                    $_POST['serial_number'] ?: null,
                    $_POST['purchase_date'] ?: null,
                    $_POST['purchase_price'] ?: null,
                    $_POST['condition_status'],
                    $_POST['notes'] ?: null
                ]);
                $message = 'Item added successfully!';
                $message_type = 'success';
            } elseif ($_POST['action'] === 'update_item') {
                $stmt = $db->prepare("UPDATE player_inventory SET item_name = ?, category_id = ?, brand = ?, model = ?, serial_number = ?, purchase_date = ?, purchase_price = ?, condition_status = ?, notes = ? WHERE inventory_id = ? AND player_id = ?");
                $stmt->execute([
                    $_POST['item_name'],
                    $_POST['category_id'] ?: null,
                    $_POST['brand'] ?: null,
                    $_POST['model'] ?: null,
                    $_POST['serial_number'] ?: null,
                    $_POST['purchase_date'] ?: null,
                    $_POST['purchase_price'] ?: null,
                    $_POST['condition_status'],
                    $_POST['notes'] ?: null,
                    $_POST['inventory_id'],
                    $player_id
                ]);
                $message = 'Item updated successfully!';
                $message_type = 'success';
            } elseif ($_POST['action'] === 'delete_item') {
                $stmt = $db->prepare("UPDATE player_inventory SET is_active = 0 WHERE inventory_id = ? AND player_id = ?");
                $stmt->execute([$_POST['inventory_id'], $player_id]);
                $message = 'Item removed successfully!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get inventory items
$stmt = $db->prepare("
    SELECT pi.*, gc.category_name 
    FROM player_inventory pi 
    LEFT JOIN gear_categories gc ON pi.category_id = gc.category_id 
    WHERE pi.player_id = ? AND pi.is_active = 1 
    ORDER BY pi.created_at DESC
");
$stmt->execute([$player_id]);
$inventory_items = $stmt->fetchAll();

// Get categories for dropdown
$stmt = $db->query("SELECT * FROM gear_categories ORDER BY category_name");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Inventory - AEROZONE</title>
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
                        <i class="fas fa-box me-2"></i>My Inventory
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                            <i class="fas fa-plus me-1"></i>Add Item
                        </button>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Inventory Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo count($inventory_items); ?></h4>
                                        <p class="card-text">Total Items</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-box fa-2x"></i>
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
                                        <h4 class="card-title"><?php echo count(array_filter($inventory_items, function($item) { return in_array($item['condition_status'], ['excellent', 'good']); })); ?></h4>
                                        <p class="card-text">Good Condition</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
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
                                        <h4 class="card-title"><?php echo count(array_filter($inventory_items, function($item) { return $item['condition_status'] === 'needs_repair'; })); ?></h4>
                                        <p class="card-text">Needs Repair</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-exclamation-triangle fa-2x"></i>
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
                                        <h4 class="card-title"><?php echo count(array_filter($inventory_items, function($item) { return $item['next_maintenance_due'] && strtotime($item['next_maintenance_due']) <= strtotime('+30 days'); })); ?></h4>
                                        <p class="card-text">Due Maintenance</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-calendar fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Inventory Grid -->
                <div class="inventory-grid">
                    <?php foreach ($inventory_items as $item): ?>
                        <div class="inventory-item">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-0"><?php echo htmlspecialchars($item['item_name']); ?></h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="#" onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                            <i class="fas fa-edit me-2"></i>Edit
                                        </a></li>
                                        <li><a class="dropdown-item" href="../appointments/index.php?item_id=<?php echo $item['inventory_id']; ?>">
                                            <i class="fas fa-calendar me-2"></i>Schedule Service
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteItem(<?php echo $item['inventory_id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>')">
                                            <i class="fas fa-trash me-2"></i>Remove
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <?php if ($item['category_name']): ?>
                                <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($item['category_name']); ?></span>
                            <?php endif; ?>
                            
                            <div class="row g-2 mb-2">
                                <?php if ($item['brand']): ?>
                                    <div class="col-6">
                                        <small class="text-muted">Brand:</small><br>
                                        <strong><?php echo htmlspecialchars($item['brand']); ?></strong>
                                    </div>
                                <?php endif; ?>
                                <?php if ($item['model']): ?>
                                    <div class="col-6">
                                        <small class="text-muted">Model:</small><br>
                                        <strong><?php echo htmlspecialchars($item['model']); ?></strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-2">
                                <small class="text-muted">Condition:</small><br>
                                <span class="badge bg-<?php 
                                    echo $item['condition_status'] === 'excellent' ? 'success' : 
                                        ($item['condition_status'] === 'good' ? 'primary' : 
                                        ($item['condition_status'] === 'fair' ? 'warning' : 'danger')); 
                                ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $item['condition_status'])); ?>
                                </span>
                            </div>
                            
                            <?php if ($item['purchase_date']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Purchased:</small><br>
                                    <?php echo date('M j, Y', strtotime($item['purchase_date'])); ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($item['next_maintenance_due']): ?>
                                <div class="mb-2">
                                    <small class="text-muted">Next Maintenance:</small><br>
                                    <span class="<?php echo strtotime($item['next_maintenance_due']) <= strtotime('+30 days') ? 'text-warning fw-bold' : ''; ?>">
                                        <?php echo date('M j, Y', strtotime($item['next_maintenance_due'])); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($item['notes']): ?>
                                <div class="mt-2">
                                    <small class="text-muted">Notes:</small><br>
                                    <small><?php echo htmlspecialchars($item['notes']); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($inventory_items)): ?>
                        <div class="col-12 text-center py-5">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No items in your inventory</h4>
                            <p class="text-muted">Start by adding your first airsoft gear item.</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                <i class="fas fa-plus me-1"></i>Add Your First Item
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_item">
                        
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="item_name" class="form-label">Item Name *</label>
                                <input type="text" class="form-control" id="item_name" name="item_name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="category_id" class="form-label">Category</label>
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>">
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="brand" class="form-label">Brand</label>
                                <input type="text" class="form-control" id="brand" name="brand">
                            </div>
                            <div class="col-md-6">
                                <label for="model" class="form-label">Model</label>
                                <input type="text" class="form-control" id="model" name="model">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="serial_number" class="form-label">Serial Number</label>
                                <input type="text" class="form-control" id="serial_number" name="serial_number">
                            </div>
                            <div class="col-md-6">
                                <label for="condition_status" class="form-label">Condition *</label>
                                <select class="form-select" id="condition_status" name="condition_status" required>
                                    <option value="excellent">Excellent</option>
                                    <option value="good" selected>Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                    <option value="needs_repair">Needs Repair</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="purchase_date" class="form-label">Purchase Date</label>
                                <input type="date" class="form-control" id="purchase_date" name="purchase_date">
                            </div>
                            <div class="col-md-6">
                                <label for="purchase_price" class="form-label">Purchase Price</label>
                                <input type="number" class="form-control" id="purchase_price" name="purchase_price" step="0.01">
                            </div>
                            
                            <div class="col-12">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="editItemForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_item">
                        <input type="hidden" name="inventory_id" id="edit_inventory_id">
                        
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label for="edit_item_name" class="form-label">Item Name *</label>
                                <input type="text" class="form-control" id="edit_item_name" name="item_name" required>
                            </div>
                            <div class="col-md-4">
                                <label for="edit_category_id" class="form-label">Category</label>
                                <select class="form-select" id="edit_category_id" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>">
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_brand" class="form-label">Brand</label>
                                <input type="text" class="form-control" id="edit_brand" name="brand">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_model" class="form-label">Model</label>
                                <input type="text" class="form-control" id="edit_model" name="model">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_serial_number" class="form-label">Serial Number</label>
                                <input type="text" class="form-control" id="edit_serial_number" name="serial_number">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_condition_status" class="form-label">Condition *</label>
                                <select class="form-select" id="edit_condition_status" name="condition_status" required>
                                    <option value="excellent">Excellent</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="poor">Poor</option>
                                    <option value="needs_repair">Needs Repair</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="edit_purchase_date" class="form-label">Purchase Date</label>
                                <input type="date" class="form-control" id="edit_purchase_date" name="purchase_date">
                            </div>
                            <div class="col-md-6">
                                <label for="edit_purchase_price" class="form-label">Purchase Price</label>
                                <input type="number" class="form-control" id="edit_purchase_price" name="purchase_price" step="0.01">
                            </div>
                            
                            <div class="col-12">
                                <label for="edit_notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="edit_notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editItem(item) {
            document.getElementById('edit_inventory_id').value = item.inventory_id;
            document.getElementById('edit_item_name').value = item.item_name;
            document.getElementById('edit_category_id').value = item.category_id || '';
            document.getElementById('edit_brand').value = item.brand || '';
            document.getElementById('edit_model').value = item.model || '';
            document.getElementById('edit_serial_number').value = item.serial_number || '';
            document.getElementById('edit_condition_status').value = item.condition_status;
            document.getElementById('edit_purchase_date').value = item.purchase_date || '';
            document.getElementById('edit_purchase_price').value = item.purchase_price || '';
            document.getElementById('edit_notes').value = item.notes || '';
            
            new bootstrap.Modal(document.getElementById('editItemModal')).show();
        }

        function deleteItem(inventoryId, itemName) {
            if (confirm(`Are you sure you want to remove "${itemName}" from your inventory?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_item">
                    <input type="hidden" name="inventory_id" value="${inventoryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
