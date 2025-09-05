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
            if ($_POST['action'] === 'process_sale') {
                $db->beginTransaction();
                
                // Validate inputs
                $customer_name = trim($_POST['customer_name']);
                $customer_phone = trim($_POST['customer_phone'] ?? '');
                $customer_email = trim($_POST['customer_email'] ?? '');
                $total_amount = floatval($_POST['total_amount']);
                $cash_received = floatval($_POST['cash_received']);
                $change_amount = $cash_received - $total_amount;
                $notes = trim($_POST['notes'] ?? '');
                
                if (empty($customer_name)) {
                    throw new Exception('Customer name is required');
                }
                
                if ($cash_received < $total_amount) {
                    throw new Exception('Cash received must be equal to or greater than total amount');
                }
                
                // Create sales transaction
                $stmt = $db->prepare("INSERT INTO sales_transactions (store_owner_id, customer_name, customer_phone, customer_email, total_amount, cash_received, change_amount, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $store_owner_id,
                    $customer_name,
                    $customer_phone,
                    $customer_email,
                    $total_amount,
                    $cash_received,
                    $change_amount,
                    $notes,
                    $user['user_id']
                ]);
                
                $sale_id = $db->lastInsertId();
                
                // Process each item in the sale
                $cart_items = json_decode($_POST['cart_items'], true);
                if (!is_array($cart_items)) {
                    throw new Exception('Invalid cart data');
                }
                
                foreach ($cart_items as $item) {
                    $inventory_id = intval($item['inventory_id']);
                    $quantity = intval($item['quantity']);
                    $unit_price = floatval($item['unit_price']);
                    $total_price = $quantity * $unit_price;
                    
                    // Check if item exists and has sufficient stock
                    $stmt = $db->prepare("SELECT item_name, quantity FROM store_inventory WHERE store_inventory_id = ? AND store_owner_id = ? AND is_available = 1");
                    $stmt->execute([$inventory_id, $store_owner_id]);
                    $inventory_item = $stmt->fetch();
                    
                    if (!$inventory_item) {
                        throw new Exception('Item not found or not available');
                    }
                    
                    if ($inventory_item['quantity'] < $quantity) {
                        throw new Exception('Insufficient stock for item: ' . $inventory_item['item_name']);
                    }
                    
                    // Add sale item
                    $stmt = $db->prepare("INSERT INTO sales_items (sale_id, store_inventory_id, item_name, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $sale_id,
                        $inventory_id,
                        $inventory_item['item_name'],
                        $quantity,
                        $unit_price,
                        $total_price
                    ]);
                    
                    // Update inventory quantity
                    $stmt = $db->prepare("UPDATE store_inventory SET quantity = quantity - ? WHERE store_inventory_id = ?");
                    $stmt->execute([$quantity, $inventory_id]);
                }
                
                $db->commit();
                $message = 'Sale processed successfully! Sale ID: ' . $sale_id;
                $message_type = 'success';
                
            } elseif ($_POST['action'] === 'get_item_info') {
                $inventory_id = intval($_POST['inventory_id']);
                
                $stmt = $db->prepare("SELECT store_inventory_id, item_name, brand, model, quantity, unit_price, description FROM store_inventory WHERE store_inventory_id = ? AND store_owner_id = ? AND is_available = 1");
                $stmt->execute([$inventory_id, $store_owner_id]);
                $item = $stmt->fetch();
                
                if ($item) {
                    header('Content-Type: application/json');
                    echo json_encode($item);
                    exit;
                } else {
                    http_response_code(404);
                    echo json_encode(['error' => 'Item not found']);
                    exit;
                }
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get available inventory items
$stmt = $db->prepare("
    SELECT si.*, gc.category_name
    FROM store_inventory si 
    LEFT JOIN gear_categories gc ON si.category_id = gc.category_id 
    WHERE si.store_owner_id = ? AND si.is_available = 1 AND si.quantity > 0
    ORDER BY si.item_name ASC
");
$stmt->execute([$store_owner_id]);
$inventory_items = $stmt->fetchAll();

// Get recent sales
$stmt = $db->prepare("
    SELECT st.*, COUNT(si.sale_item_id) as item_count
    FROM sales_transactions st
    LEFT JOIN sales_items si ON st.sale_id = si.sale_id
    WHERE st.store_owner_id = ?
    GROUP BY st.sale_id
    ORDER BY st.sale_date DESC
    LIMIT 10
");
$stmt->execute([$store_owner_id]);
$recent_sales = $stmt->fetchAll();

// Calculate today's sales
$stmt = $db->prepare("
    SELECT COUNT(*) as sale_count, COALESCE(SUM(total_amount), 0) as total_revenue
    FROM sales_transactions 
    WHERE store_owner_id = ? AND DATE(sale_date) = CURDATE()
");
$stmt->execute([$store_owner_id]);
$today_stats = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales - Aerozone</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        /* Sales-specific styles matching Aerozone design */
        .cart-item {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            transition: all 0.3s ease;
        }
        
        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .item-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: white;
        }
        
        .item-card:hover {
            border-color: var(--primary-color);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .item-card.selected {
            border-color: var(--success-color);
            background-color: #d1edff;
            border-left: 4px solid var(--success-color);
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: none;
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .quantity-btn:hover {
            background-color: var(--dark-color);
            transform: scale(1.1);
        }
        
        .quantity-btn:disabled {
            background-color: #6c757d;
            cursor: not-allowed;
            transform: none;
        }
        
        .total-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid #dee2e6;
        }
        
        .receipt-preview {
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        /* Sales statistics cards */
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }
        
        .stats-card.info {
            background: linear-gradient(135deg, var(--secondary-color) 0%, #5dade2 100%);
        }
        
        .stats-card.success {
            background: linear-gradient(135deg, var(--success-color) 0%, #58d68d 100%);
        }
        
        /* Inventory scroll area */
        .inventory-scroll {
            max-height: 600px;
            overflow-y: auto;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-color) #f1f1f1;
        }
        
        .inventory-scroll::-webkit-scrollbar {
            width: 6px;
        }
        
        .inventory-scroll::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .inventory-scroll::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 3px;
        }
        
        .inventory-scroll::-webkit-scrollbar-thumb:hover {
            background: var(--dark-color);
        }
        
        /* Price styling */
        .price-display {
            font-weight: bold;
            color: var(--success-color);
            font-size: 1.1rem;
        }
        
        .stock-display {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Category badge */
        .category-badge {
            background-color: var(--primary-color);
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 15px;
        }
        
        /* Process button */
        .process-btn {
            background: linear-gradient(135deg, var(--success-color) 0%, #58d68d 100%);
            border: none;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .process-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(39, 174, 96, 0.3);
        }
        
        .process-btn:disabled {
            background: #6c757d;
            transform: none;
            box-shadow: none;
        }
        
        /* Form controls */
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.25);
        }
        
        /* Recent sales table */
        .recent-sales-table {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .recent-sales-table .table th {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        .recent-sales-table .table td {
            border-color: #f8f9fa;
        }
        
        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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
                        <i class="fas fa-cash-register"></i> Sales
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="printReceipt()">
                                <i class="fas fa-print"></i> Print Receipt
                            </button>
                        </div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Today's Stats -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card stats-card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-chart-line me-2"></i>Today's Sales
                                </h5>
                                <div class="row">
                                    <div class="col-6">
                                        <h3><?php echo $today_stats['sale_count']; ?></h3>
                                        <p class="mb-0">Transactions</p>
                                    </div>
                                    <div class="col-6">
                                        <h3>₱<?php echo number_format($today_stats['total_revenue'], 2); ?></h3>
                                        <p class="mb-0">Revenue</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card stats-card info">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-boxes me-2"></i>Available Items
                                </h5>
                                <h3><?php echo count($inventory_items); ?></h3>
                                <p class="mb-0">Items in stock</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Inventory Items -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-boxes"></i> Available Inventory
                                </h5>
                            </div>
                            <div class="card-body inventory-scroll">
                                <?php if (empty($inventory_items)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-box-open"></i>
                                        <p>No items available for sale</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($inventory_items as $item): ?>
                                        <div class="item-card" onclick="addToCart(<?php echo $item['store_inventory_id']; ?>)">
                                            <div class="row">
                                                <div class="col-8">
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                                    <?php if ($item['brand']): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($item['brand']); ?></small>
                                                        <?php if ($item['model']): ?>
                                                            <small class="text-muted"> - <?php echo htmlspecialchars($item['model']); ?></small>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                    <?php if ($item['category_name']): ?>
                                                        <br><span class="category-badge"><?php echo htmlspecialchars($item['category_name']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-4 text-end">
                                                    <div class="price-display">₱<?php echo number_format($item['unit_price'], 2); ?></div>
                                                    <div class="stock-display">Stock: <?php echo $item['quantity']; ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Sales Cart -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-shopping-cart"></i> Sales Cart
                                </h5>
                            </div>
                            <div class="card-body">
                                <div id="cart-items">
                                    <div class="empty-state">
                                        <i class="fas fa-shopping-cart"></i>
                                        <p>No items in cart</p>
                                    </div>
                                </div>

                                <div class="total-section">
                                    <div class="row">
                                        <div class="col-6">
                                            <label for="total_amount" class="form-label">Total Amount:</label>
                                            <input type="number" class="form-control" id="total_amount" name="total_amount" readonly value="0.00" step="0.01">
                                        </div>
                                        <div class="col-6">
                                            <label for="cash_received" class="form-label">Cash Received:</label>
                                            <input type="number" class="form-control" id="cash_received" name="cash_received" value="0.00" step="0.01" onchange="calculateChange()">
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <label for="change_amount" class="form-label">Change:</label>
                                            <input type="number" class="form-control" id="change_amount" name="change_amount" readonly value="0.00" step="0.01">
                                        </div>
                                        <div class="col-6">
                                            <label for="customer_name" class="form-label">Customer Name:</label>
                                            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-6">
                                            <label for="customer_phone" class="form-label">Phone (Optional):</label>
                                            <input type="tel" class="form-control" id="customer_phone" name="customer_phone">
                                        </div>
                                        <div class="col-6">
                                            <label for="customer_email" class="form-label">Email (Optional):</label>
                                            <input type="email" class="form-control" id="customer_email" name="customer_email">
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <label for="notes" class="form-label">Notes (Optional):</label>
                                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <button type="button" class="btn btn-lg w-100 process-btn" onclick="processSale()" id="process-btn" disabled>
                                                <i class="fas fa-check me-2"></i> Process Sale
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Sales -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-history"></i> Recent Sales
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_sales)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-history"></i>
                                        <p>No recent sales</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive recent-sales-table">
                                        <table class="table table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Sale ID</th>
                                                    <th>Customer</th>
                                                    <th>Items</th>
                                                    <th>Total</th>
                                                    <th>Cash Received</th>
                                                    <th>Change</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_sales as $sale): ?>
                                                    <tr>
                                                        <td>#<?php echo $sale['sale_id']; ?></td>
                                                        <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                                        <td><?php echo $sale['item_count']; ?> items</td>
                                                        <td>₱<?php echo number_format($sale['total_amount'], 2); ?></td>
                                                        <td>₱<?php echo number_format($sale['cash_received'], 2); ?></td>
                                                        <td>₱<?php echo number_format($sale['change_amount'], 2); ?></td>
                                                        <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="receipt-content" class="receipt-preview"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="printReceipt()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let cart = [];
        let inventoryData = <?php echo json_encode($inventory_items); ?>;

        function addToCart(inventoryId) {
            const item = inventoryData.find(i => i.store_inventory_id == inventoryId);
            if (!item) return;

            const existingItem = cart.find(i => i.inventory_id == inventoryId);
            if (existingItem) {
                if (existingItem.quantity < item.quantity) {
                    existingItem.quantity++;
                    updateCartDisplay();
                } else {
                    alert('Cannot add more items. Stock limit reached.');
                }
            } else {
                cart.push({
                    inventory_id: inventoryId,
                    item_name: item.item_name,
                    unit_price: parseFloat(item.unit_price),
                    quantity: 1,
                    max_quantity: item.quantity
                });
                updateCartDisplay();
            }
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            updateCartDisplay();
        }

        function updateQuantity(index, change) {
            const item = cart[index];
            const newQuantity = item.quantity + change;
            
            if (newQuantity > 0 && newQuantity <= item.max_quantity) {
                item.quantity = newQuantity;
                updateCartDisplay();
            }
        }

        function updateCartDisplay() {
            const cartContainer = document.getElementById('cart-items');
            
            if (cart.length === 0) {
                cartContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <p>No items in cart</p>
                    </div>
                `;
                document.getElementById('total_amount').value = '0.00';
                document.getElementById('process-btn').disabled = true;
                return;
            }

            let cartHtml = '';
            let total = 0;

            cart.forEach((item, index) => {
                const itemTotal = item.quantity * item.unit_price;
                total += itemTotal;

                cartHtml += `
                    <div class="cart-item">
                        <div class="row">
                            <div class="col-8">
                                <h6 class="mb-1">${item.item_name}</h6>
                                <small class="text-muted">₱${item.unit_price.toFixed(2)} each</small>
                            </div>
                            <div class="col-4 text-end">
                                <div class="quantity-control">
                                    <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, -1)" ${item.quantity <= 1 ? 'disabled' : ''}>-</button>
                                    <span class="fw-bold">${item.quantity}</span>
                                    <button type="button" class="quantity-btn" onclick="updateQuantity(${index}, 1)" ${item.quantity >= item.max_quantity ? 'disabled' : ''}>+</button>
                                </div>
                                <div class="mt-2">
                                    <strong>₱${itemTotal.toFixed(2)}</strong>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeFromCart(${index})">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });

            cartContainer.innerHTML = cartHtml;
            document.getElementById('total_amount').value = total.toFixed(2);
            document.getElementById('process-btn').disabled = false;
            calculateChange();
        }

        function calculateChange() {
            const total = parseFloat(document.getElementById('total_amount').value) || 0;
            const cashReceived = parseFloat(document.getElementById('cash_received').value) || 0;
            const change = cashReceived - total;
            
            document.getElementById('change_amount').value = change.toFixed(2);
            
            if (change < 0) {
                document.getElementById('change_amount').classList.add('is-invalid');
            } else {
                document.getElementById('change_amount').classList.remove('is-invalid');
            }
        }

        function processSale() {
            if (cart.length === 0) {
                alert('Please add items to cart');
                return;
            }

            const customerName = document.getElementById('customer_name').value.trim();
            if (!customerName) {
                alert('Please enter customer name');
                return;
            }

            const total = parseFloat(document.getElementById('total_amount').value);
            const cashReceived = parseFloat(document.getElementById('cash_received').value);
            
            if (cashReceived < total) {
                alert('Cash received must be equal to or greater than total amount');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'process_sale');
            formData.append('customer_name', customerName);
            formData.append('customer_phone', document.getElementById('customer_phone').value);
            formData.append('customer_email', document.getElementById('customer_email').value);
            formData.append('total_amount', total);
            formData.append('cash_received', cashReceived);
            formData.append('notes', document.getElementById('notes').value);
            formData.append('cart_items', JSON.stringify(cart));

            fetch('sales.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                if (data.includes('Sale processed successfully')) {
                    // Show receipt
                    showReceipt();
                    // Reset form
                    cart = [];
                    updateCartDisplay();
                    document.getElementById('customer_name').value = '';
                    document.getElementById('customer_phone').value = '';
                    document.getElementById('customer_email').value = '';
                    document.getElementById('cash_received').value = '0.00';
                    document.getElementById('notes').value = '';
                    calculateChange();
                    // Reload page to refresh recent sales
                    setTimeout(() => location.reload(), 2000);
                } else {
                    alert('Error processing sale. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error processing sale. Please try again.');
            });
        }

        function showReceipt() {
            const customerName = document.getElementById('customer_name').value;
            const customerPhone = document.getElementById('customer_phone').value;
            const total = document.getElementById('total_amount').value;
            const cashReceived = document.getElementById('cash_received').value;
            const change = document.getElementById('change_amount').value;
            const notes = document.getElementById('notes').value;

            let receiptHtml = `
                <div style="text-align: center; margin-bottom: 20px;">
                    <h4>AEROZONE</h4>
                    <p>Airsoft Community Store</p>
                    <p>${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}</p>
                </div>
                <hr>
                <div style="margin-bottom: 20px;">
                    <p><strong>Customer:</strong> ${customerName}</p>
                    ${customerPhone ? `<p><strong>Phone:</strong> ${customerPhone}</p>` : ''}
                </div>
                <hr>
                <div style="margin-bottom: 20px;">
            `;

            cart.forEach(item => {
                const itemTotal = (item.quantity * item.unit_price).toFixed(2);
                receiptHtml += `
                    <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                        <span>${item.item_name} x${item.quantity}</span>
                        <span>₱${itemTotal}</span>
                    </div>
                `;
            });

            receiptHtml += `
                </div>
                <hr>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <strong>Total:</strong>
                    <strong>₱${total}</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span>Cash Received:</span>
                    <span>₱${cashReceived}</span>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <span>Change:</span>
                    <span>₱${change}</span>
                </div>
                ${notes ? `<hr><p><strong>Notes:</strong> ${notes}</p>` : ''}
                <hr>
                <div style="text-align: center; margin-top: 20px;">
                    <p>Thank you for your purchase!</p>
                    <p>Please keep this receipt for your records.</p>
                </div>
            `;

            document.getElementById('receipt-content').innerHTML = receiptHtml;
            new bootstrap.Modal(document.getElementById('receiptModal')).show();
        }

        function printReceipt() {
            const receiptContent = document.getElementById('receipt-content').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Receipt</title>
                        <style>
                            body { font-family: 'Courier New', monospace; font-size: 14px; }
                            @media print { body { margin: 0; } }
                        </style>
                    </head>
                    <body>${receiptContent}</body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            calculateChange();
        });
    </script>
</body>
</html>
