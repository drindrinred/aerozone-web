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

// Get quick statistics for dashboard
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_inventory_items,
        SUM(quantity) as total_quantity,
        SUM(quantity * unit_price) as total_inventory_value,
        COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock,
        COUNT(CASE WHEN quantity <= 5 AND quantity > 0 THEN 1 END) as low_stock
    FROM store_inventory 
    WHERE store_owner_id = ?
");
$stmt->execute([$store_owner_id]);
$inventory_summary = $stmt->fetch();

$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_sales,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as average_sale,
        COUNT(DISTINCT DATE(sale_date)) as days_with_sales
    FROM sales_transactions 
    WHERE store_owner_id = ? 
    AND DATE(sale_date) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([$store_owner_id]);
$sales_summary = $stmt->fetch();

// Get recent sales for quick view
$stmt = $db->prepare("
    SELECT 
        st.sale_id,
        st.customer_name,
        st.total_amount,
        st.sale_date,
        COUNT(si.sale_item_id) as items_count
    FROM sales_transactions st
    LEFT JOIN sales_items si ON st.sale_id = si.sale_id
    WHERE st.store_owner_id = ?
    GROUP BY st.sale_id
    ORDER BY st.sale_date DESC
    LIMIT 5
");
$stmt->execute([$store_owner_id]);
$recent_sales = $stmt->fetchAll();

// Get low stock alerts
$stmt = $db->prepare("
    SELECT 
        item_name,
        quantity,
        unit_price,
        category_id
    FROM store_inventory 
    WHERE store_owner_id = ? 
    AND quantity <= 5 
    AND quantity > 0
    ORDER BY quantity ASC
    LIMIT 5
");
$stmt->execute([$store_owner_id]);
$low_stock_alerts = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Dashboard - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chart-dashboard text-primary"></i> Reports Dashboard
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="../inventory/reports.php" class="btn btn-primary">
                                <i class="fas fa-boxes"></i> Inventory Reports
                            </a>
                            <a href="../inventory/sales-reports.php" class="btn btn-success">
                                <i class="fas fa-chart-line"></i> Sales Reports
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Stats Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h5 class="card-title">Total Items</h5>
                                        <h3 class="card-text"><?php echo $inventory_summary['total_inventory_items']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-boxes fa-2x"></i>
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
                                        <h5 class="card-title">Total Revenue</h5>
                                        <h3 class="card-text">₱<?php echo number_format($sales_summary['total_revenue'], 2); ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-money-bill-wave fa-2x"></i>
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
                                        <h5 class="card-title">Low Stock Items</h5>
                                        <h3 class="card-text"><?php echo $inventory_summary['low_stock']; ?></h3>
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
                                        <h5 class="card-title">Monthly Sales</h5>
                                        <h3 class="card-text"><?php echo $sales_summary['total_sales']; ?></h3>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-chart-line fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Report Cards Row -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-boxes"></i> Inventory Reports
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text">Generate comprehensive reports for your store inventory including stock levels, values, and low stock alerts.</p>
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <i class="fas fa-file-csv fa-2x text-success mb-2"></i>
                                            <div class="small">CSV Export</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <i class="fas fa-file-excel fa-2x text-primary mb-2"></i>
                                            <div class="small">Excel Export</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <i class="fas fa-file-pdf fa-2x text-danger mb-2"></i>
                                            <div class="small">PDF Export</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <a href="../inventory/reports.php" class="btn btn-primary">
                                        <i class="fas fa-arrow-right"></i> View Inventory Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line"></i> Sales Reports
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="card-text">Analyze your sales performance with detailed reports, charts, and analytics including customer insights.</p>
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <i class="fas fa-chart-area fa-2x text-info mb-2"></i>
                                            <div class="small">Sales Trends</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <i class="fas fa-chart-pie fa-2x text-warning mb-2"></i>
                                            <div class="small">Top Items</div>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                                            <div class="small">Customer Data</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-grid">
                                    <a href="../inventory/sales-reports.php" class="btn btn-success">
                                        <i class="fas fa-arrow-right"></i> View Sales Reports
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alerts and Quick Actions Row -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0">
                                    <i class="fas fa-exclamation-triangle"></i> Low Stock Alerts
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($low_stock_alerts)): ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                        <p class="text-muted mb-0">All items are well stocked!</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($low_stock_alerts as $item): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">Quantity: <?php echo $item['quantity']; ?></small>
                                                </div>
                                                <span class="badge bg-warning text-dark">₱<?php echo number_format($item['unit_price'], 2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3">
                                        <a href="../inventory/reports.php" class="btn btn-warning btn-sm">
                                            <i class="fas fa-eye"></i> View All Inventory
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-clock"></i> Recent Sales Activity
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_sales)): ?>
                                    <div class="text-center py-3">
                                        <i class="fas fa-chart-line fa-2x text-muted mb-2"></i>
                                        <p class="text-muted mb-0">No recent sales activity</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_sales as $sale): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($sale['customer_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?>
                                                        • <?php echo $sale['items_count']; ?> items
                                                    </small>
                                                </div>
                                                <span class="badge bg-success">₱<?php echo number_format($sale['total_amount'], 2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="mt-3">
                                        <a href="../inventory/sales-reports.php" class="btn btn-info btn-sm">
                                            <i class="fas fa-eye"></i> View All Sales
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Export Section -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0">
                                    <i class="fas fa-download"></i> Quick Export Options
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3 text-center mb-3">
                                        <a href="../inventory/reports.php?export=csv&report_type=inventory" class="btn btn-outline-success w-100">
                                            <i class="fas fa-file-csv fa-2x mb-2"></i>
                                            <br>Inventory CSV
                                        </a>
                                    </div>
                                    <div class="col-md-3 text-center mb-3">
                                        <a href="../inventory/reports.php?export=xlsx&report_type=inventory" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-file-excel fa-2x mb-2"></i>
                                            <br>Inventory Excel
                                        </a>
                                    </div>
                                    <div class="col-md-3 text-center mb-3">
                                        <a href="../inventory/sales-reports.php?export=csv&format=csv" class="btn btn-outline-success w-100">
                                            <i class="fas fa-file-csv fa-2x mb-2"></i>
                                            <br>Sales CSV
                                        </a>
                                    </div>
                                    <div class="col-md-3 text-center mb-3">
                                        <a href="../inventory/sales-reports.php?export=xlsx&format=xlsx" class="btn btn-outline-primary w-100">
                                            <i class="fas fa-file-excel fa-2x mb-2"></i>
                                            <br>Sales Excel
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>
