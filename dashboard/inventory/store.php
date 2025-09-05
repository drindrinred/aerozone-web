<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add_item') {
                $stmt = $db->prepare("INSERT INTO store_inventory (store_owner_id, item_name, category_id, brand, model, quantity, unit_price, reorder_level, critical_level, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $store_owner_id,
                    trim($_POST['item_name']),
                    $_POST['category_id'] ?: null,
                    trim($_POST['brand'] ?? ''),
                    trim($_POST['model'] ?? ''),
                    max(0, (int)($_POST['quantity'] ?? 0)),
                    $_POST['unit_price'] ? (float)$_POST['unit_price'] : null,
                    max(1, (int)($_POST['reorder_level'] ?? 5)),
                    max(0, (int)($_POST['critical_level'] ?? 2)),
                    trim($_POST['description'] ?? '')
                ]);
                $message = 'Item added successfully!';
                $message_type = 'success';
            } elseif ($_POST['action'] === 'update_item') {
                $stmt = $db->prepare("UPDATE store_inventory SET item_name = ?, category_id = ?, brand = ?, model = ?, quantity = ?, unit_price = ?, reorder_level = ?, critical_level = ?, description = ?, is_available = ? WHERE store_inventory_id = ? AND store_owner_id = ?");
                $stmt->execute([
                    trim($_POST['item_name']),
                    $_POST['category_id'] ?: null,
                    trim($_POST['brand'] ?? ''),
                    trim($_POST['model'] ?? ''),
                    max(0, (int)($_POST['quantity'] ?? 0)),
                    $_POST['unit_price'] ? (float)$_POST['unit_price'] : null,
                    max(1, (int)($_POST['reorder_level'] ?? 5)),
                    max(0, (int)($_POST['critical_level'] ?? 2)),
                    trim($_POST['description'] ?? ''),
                    isset($_POST['is_available']) ? 1 : 0,
                    $_POST['store_inventory_id'],
                    $store_owner_id
                ]);
                $message = 'Item updated successfully!';
                $message_type = 'success';
            } elseif ($_POST['action'] === 'delete_item') {
                $stmt = $db->prepare("UPDATE store_inventory SET is_available = 0 WHERE store_inventory_id = ? AND store_owner_id = ?");
                $stmt->execute([$_POST['store_inventory_id'], $store_owner_id]);
                $message = 'Item removed successfully!';
                $message_type = 'success';
            } elseif ($_POST['action'] === 'add_stock') {
                $amount = max(1, (int)($_POST['stock_quantity'] ?? 0));
                $stmt = $db->prepare("UPDATE store_inventory SET quantity = quantity + ? WHERE store_inventory_id = ? AND store_owner_id = ?");
                $stmt->execute([$amount, $_POST['store_inventory_id'], $store_owner_id]);
                $message = "Stock added successfully! Added {$amount} units to inventory.";
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';

// Build inventory query with filters
$inventory_query = "
    SELECT si.*, gc.category_name,
           CASE 
               WHEN si.quantity <= si.critical_level THEN 'critical'
               WHEN si.quantity <= si.reorder_level THEN 'reorder'
               ELSE 'normal'
           END as stock_status
    FROM store_inventory si 
    LEFT JOIN gear_categories gc ON si.category_id = gc.category_id 
    WHERE si.store_owner_id = ? AND si.is_available = 1
";

$params = [$store_owner_id];

if ($category_filter) {
    $inventory_query .= " AND si.category_id = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    switch ($status_filter) {
        case 'critical':
            $inventory_query .= " AND si.quantity <= si.critical_level";
            break;
        case 'reorder':
            $inventory_query .= " AND si.quantity <= si.reorder_level AND si.quantity > si.critical_level";
            break;
        case 'normal':
            $inventory_query .= " AND si.quantity > si.reorder_level";
            break;
        case 'out_of_stock':
            $inventory_query .= " AND si.quantity = 0";
            break;
    }
}

if ($search_query) {
    $inventory_query .= " AND (si.item_name LIKE ? OR si.brand LIKE ? OR si.model LIKE ?)";
    $search_term = "%$search_query%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$inventory_query .= " ORDER BY 
    CASE 
        WHEN si.quantity <= si.critical_level THEN 1
        WHEN si.quantity <= si.reorder_level THEN 2
        ELSE 3
    END,
    si.item_name ASC";

$stmt = $db->prepare($inventory_query);
$stmt->execute($params);
$inventory_items = $stmt->fetchAll();

// Get categories for filter
$stmt = $db->query("SELECT * FROM gear_categories ORDER BY category_name");
$categories = $stmt->fetchAll();

// Calculate inventory statistics
$total_items = count($inventory_items);
$total_value = array_sum(array_map(function($item) { 
    return $item['quantity'] * ($item['unit_price'] ?? 0); 
}, $inventory_items));
$critical_stock_items = count(array_filter($inventory_items, function($item) { 
    return $item['stock_status'] === 'critical'; 
}));
$reorder_stock_items = count(array_filter($inventory_items, function($item) { 
    return $item['stock_status'] === 'reorder'; 
}));
$out_of_stock_items = count(array_filter($inventory_items, function($item) { 
    return $item['quantity'] == 0; 
}));
$low_stock_items = $critical_stock_items + $reorder_stock_items;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Inventory - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .stock-critical {
            background-color: #f8d7da !important;
            border-left: 4px solid #dc3545;
        }
        .stock-reorder {
            background-color: #fff3cd !important;
            border-left: 4px solid #ffc107;
        }
        .stock-normal {
            background-color: #d1edff !important;
            border-left: 4px solid #0d6efd;
        }
        .stock-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .stock-indicator.critical { background-color: #dc3545; }
        .stock-indicator.reorder { background-color: #ffc107; }
        .stock-indicator.normal { background-color: #198754; }
        .search-highlight {
            background-color: #fff3cd;
            padding: 2px 4px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-boxes text-primary"></i> Store Inventory
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                        <a href="reports.php" class="btn btn-outline-secondary ms-2">
                            <i class="fas fa-chart-bar"></i> Reports
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Total Items</h5>
                                <h3 class="card-text"><?php echo $total_items; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Total Value</h5>
                                <h3 class="card-text">₱<?php echo number_format($total_value, 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock</h5>
                                <h3 class="card-text"><?php echo $low_stock_items; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h5 class="card-title">Critical</h5>
                                <h3 class="card-text"><?php echo $critical_stock_items; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">Out of Stock</h5>
                                <h3 class="card-text"><?php echo $out_of_stock_items; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stock Warnings -->
                <?php if ($critical_stock_items > 0 || $reorder_stock_items > 0): ?>
                    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                            <div class="flex-grow-1">
                                <h5 class="alert-heading mb-1">⚠️ Stock Level Warnings</h5>
                                <p class="mb-2">
                                    <?php if ($critical_stock_items > 0): ?>
                                        <span class="badge bg-danger me-2"><?php echo $critical_stock_items; ?> Critical Stock Items</span>
                                    <?php endif; ?>
                                    <?php if ($reorder_stock_items > 0): ?>
                                        <span class="badge bg-warning"><?php echo $reorder_stock_items; ?> Low Stock Items</span>
                                    <?php endif; ?>
                                </p>
                                <p class="mb-0 small">
                                    <strong>Critical:</strong> Stock at or below critical level | 
                                    <strong>Low:</strong> Stock below reorder level. Use the <i class="fas fa-plus text-success"></i> button to add stock.
                                </p>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search_query); ?>" 
                                       placeholder="Item name, brand, or model">
                            </div>
                            <div class="col-md-3">
                                <label for="category" class="form-label">Category</label>
                                <select name="category" id="category" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['category_id']; ?>" 
                                                <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Stock Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="critical" <?php echo $status_filter === 'critical' ? 'selected' : ''; ?>>Critical</option>
                                    <option value="reorder" <?php echo $status_filter === 'reorder' ? 'selected' : ''; ?>>Reorder</option>
                                    <option value="normal" <?php echo $status_filter === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="out_of_stock" <?php echo $status_filter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Apply Filters
                                    </button>
                                    <a href="store.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Inventory Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Inventory Items
                        </h5>
                        <div class="d-flex gap-2">
                            <span class="stock-indicator normal"></span><small>Normal</small>
                            <span class="stock-indicator reorder ms-2"></span><small>Reorder</small>
                            <span class="stock-indicator critical ms-2"></span><small>Critical</small>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($inventory_items)): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th>Category</th>
                                            <th>Brand/Model</th>
                                            <th>Stocks on Hand</th>
                                            <th>Reorder Level</th>
                                            <th>Critical Level</th>
                                            <th>Unit Price</th>
                                            <th>Total Value</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inventory_items as $item): ?>
                                            <tr class="stock-<?php echo $item['stock_status']; ?>">
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                    <?php if ($item['description']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?><?php echo strlen($item['description']) > 50 ? '...' : ''; ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($item['category_name']): ?>
                                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($item['brand'] || $item['model']): ?>
                                                        <?php echo htmlspecialchars(trim($item['brand'] . ' ' . $item['model'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $item['stock_status'] === 'critical' ? 'danger' : ($item['stock_status'] === 'reorder' ? 'warning' : 'success'); ?>">
                                                        <?php echo $item['quantity']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo $item['reorder_level']; ?></small>
                                                </td>
                                                <td>
                                                    <small class="text-muted"><?php echo $item['critical_level']; ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($item['unit_price']): ?>
                                                        ₱<?php echo number_format($item['unit_price'], 2); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($item['unit_price']): ?>
                                                        <strong>₱<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($item['stock_status'] === 'critical'): ?>
                                                        <span class="badge bg-danger">Critical</span>
                                                    <?php elseif ($item['stock_status'] === 'reorder'): ?>
                                                        <span class="badge bg-warning">Reorder</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Normal</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" 
                                                                onclick="editItem(<?php echo htmlspecialchars(json_encode($item)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-success" 
                                                                onclick="addStock(<?php echo $item['store_inventory_id']; ?>, '<?php echo htmlspecialchars($item['item_name']); ?>', <?php echo $item['quantity']; ?>, <?php echo $item['reorder_level']; ?>)">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-boxes fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No items in inventory</h4>
                                <p class="text-muted">Start by adding your first inventory item or adjust your filters.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                    <i class="fas fa-plus me-1"></i>Add Your First Item
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (empty($inventory_items)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No inventory items found</h4>
                        <p class="text-muted">Start by adding your first inventory item or adjust your filters.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                            <i class="fas fa-plus"></i> Add First Item
                        </button>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Add Item Modal -->
    <div class="modal fade" id="addItemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Inventory Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_item">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="item_name" class="form-label">Item Name *</label>
                                    <input type="text" class="form-control" id="item_name" name="item_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
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
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="brand" name="brand">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="model" class="form-label">Model</label>
                                    <input type="text" class="form-control" id="model" name="model">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="quantity" class="form-label">Initial Quantity</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" value="0" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="unit_price" class="form-label">Unit Price (₱)</label>
                                    <input type="number" class="form-control" id="unit_price" name="unit_price" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="reorder_level" class="form-label">Reorder Level</label>
                                    <input type="number" class="form-control" id="reorder_level" name="reorder_level" value="5" min="1">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="critical_level" class="form-label">Critical Level</label>
                                    <input type="number" class="form-control" id="critical_level" name="critical_level" value="2" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                                </div>
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
                    <h5 class="modal-title">Edit Inventory Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_item">
                        <input type="hidden" name="store_inventory_id" id="edit_store_inventory_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_item_name" class="form-label">Item Name *</label>
                                    <input type="text" class="form-control" id="edit_item_name" name="item_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
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
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_brand" class="form-label">Brand</label>
                                    <input type="text" class="form-control" id="edit_brand" name="brand">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_model" class="form-label">Model</label>
                                    <input type="text" class="form-control" id="edit_model" name="model">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_quantity" class="form-label">Current Quantity</label>
                                    <input type="number" class="form-control" id="edit_quantity" name="quantity" min="0" readonly>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_unit_price" class="form-label">Unit Price (₱)</label>
                                    <input type="number" class="form-control" id="edit_unit_price" name="unit_price" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_reorder_level" class="form-label">Reorder Level</label>
                                    <input type="number" class="form-control" id="edit_reorder_level" name="reorder_level" min="1">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_critical_level" class="form-label">Critical Level</label>
                                    <input type="number" class="form-control" id="edit_critical_level" name="critical_level" min="0">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_description" class="form-label">Description</label>
                                    <textarea class="form-control" id="edit_description" name="description" rows="2"></textarea>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_available" name="is_available">
                                <label class="form-check-label" for="edit_is_available">
                                    Item is available for sale
                                </label>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove <strong id="delete_item_name"></strong> from your inventory?</p>
                    <p class="text-muted">This action will mark the item as unavailable but won't permanently delete it.</p>
                </div>
                <div class="modal-footer">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="delete_item">
                        <input type="hidden" name="store_inventory_id" id="delete_item_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Remove Item</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Stock Modal -->
    <div class="modal fade" id="addStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus-circle text-success me-2"></i>Add Stock
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_stock">
                        <input type="hidden" name="store_inventory_id" id="add_stock_item_id">
                        
                        <!-- Item Information -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">Item Information</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <strong>Item:</strong><br>
                                        <span id="add_stock_item_name" class="text-primary"></span>
                                    </div>
                                    <div class="col-6">
                                        <strong>Current Stock:</strong><br>
                                        <span id="add_stock_current_qty" class="badge bg-secondary"></span>
                                    </div>
                                </div>
                                <div class="row mt-2">
                                    <div class="col-6">
                                        <strong>Reorder Level:</strong><br>
                                        <span id="add_stock_reorder_level" class="badge bg-warning"></span>
                                    </div>
                                    <div class="col-6">
                                        <strong>Status:</strong><br>
                                        <span id="add_stock_status" class="badge"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Stock Addition -->
                        <div class="mb-3">
                            <label for="stock_quantity" class="form-label">Quantity to Add *</label>
                            <input type="number" class="form-control" id="stock_quantity" name="stock_quantity" min="1" required>
                            <div class="form-text">Enter the quantity you want to add to current stock</div>
                        </div>

                        <!-- Stock Level Alert -->
                        <div id="stock_level_alert" class="alert d-none">
                            <i class="fas fa-info-circle"></i>
                            <span id="stock_alert_message"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    
    <script>
        function editItem(item) {
            document.getElementById('edit_store_inventory_id').value = item.store_inventory_id;
            document.getElementById('edit_item_name').value = item.item_name;
            document.getElementById('edit_category_id').value = item.category_id || '';
            document.getElementById('edit_brand').value = item.brand || '';
            document.getElementById('edit_model').value = item.model || '';
            document.getElementById('edit_quantity').value = item.quantity;
            document.getElementById('edit_unit_price').value = item.unit_price || '';
            document.getElementById('edit_reorder_level').value = item.reorder_level;
            document.getElementById('edit_critical_level').value = item.critical_level;
            document.getElementById('edit_description').value = item.description || '';
            document.getElementById('edit_is_available').checked = item.is_available == 1;
            
            new bootstrap.Modal(document.getElementById('editItemModal')).show();
        }

        function deleteItem(itemId, itemName) {
            document.getElementById('delete_item_id').value = itemId;
            document.getElementById('delete_item_name').textContent = itemName;
            
            new bootstrap.Modal(document.getElementById('deleteItemModal')).show();
        }

        function addStock(itemId, itemName, currentQty, reorderLevel) {
            // Set item information
            document.getElementById('add_stock_item_id').value = itemId;
            document.getElementById('add_stock_item_name').textContent = itemName;
            document.getElementById('add_stock_current_qty').textContent = currentQty;
            document.getElementById('add_stock_reorder_level').textContent = reorderLevel;
            
            // Determine stock status
            let statusElement = document.getElementById('add_stock_status');
            let alertElement = document.getElementById('stock_level_alert');
            let alertMessage = document.getElementById('stock_alert_message');
            
            if (currentQty <= 2) {
                statusElement.className = 'badge bg-danger';
                statusElement.textContent = 'Critical';
                alertElement.className = 'alert alert-danger';
                alertMessage.textContent = 'This item is at critical stock level! Immediate restocking is recommended.';
            } else if (currentQty <= reorderLevel) {
                statusElement.className = 'badge bg-warning';
                statusElement.textContent = 'Low Stock';
                alertElement.className = 'alert alert-warning';
                alertMessage.textContent = 'This item is below reorder level. Consider restocking soon.';
            } else {
                statusElement.className = 'badge bg-success';
                statusElement.textContent = 'Normal';
                alertElement.className = 'alert alert-info';
                alertMessage.textContent = 'Current stock level is healthy.';
            }
            
            alertElement.classList.remove('d-none');
            document.getElementById('stock_quantity').value = '';
            
            new bootstrap.Modal(document.getElementById('addStockModal')).show();
        }

        // Highlight search terms in results
        <?php if ($search_query): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const searchTerm = '<?php echo addslashes($search_query); ?>';
            const regex = new RegExp(`(${searchTerm})`, 'gi');
            
            document.querySelectorAll('.table td strong').forEach(function(element) {
                element.innerHTML = element.innerHTML.replace(regex, '<span class="search-highlight">$1</span>');
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>
