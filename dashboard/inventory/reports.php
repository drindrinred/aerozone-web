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

// Handle export requests
if (isset($_GET['export']) && isset($_GET['format'])) {
    $format = $_GET['format'];
    $report_type = $_GET['report_type'] ?? 'inventory';
    
    if ($report_type === 'inventory') {
        exportInventoryReport($db, $store_owner_id, $format);
    } elseif ($report_type === 'sales') {
        exportSalesReport($db, $store_owner_id, $format);
    }
    exit();
}

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build inventory query with filters
$inventory_query = "
    SELECT si.*, gc.category_name,
           CASE 
               WHEN si.quantity = 0 THEN 'Out of Stock'
               WHEN si.quantity <= 5 THEN 'Low Stock'
               ELSE 'In Stock'
           END as stock_status
    FROM store_inventory si
    LEFT JOIN gear_categories gc ON si.category_id = gc.category_id
    WHERE si.store_owner_id = ?
";

$params = [$store_owner_id];

if ($category_filter) {
    $inventory_query .= " AND si.category_id = ?";
    $params[] = $category_filter;
}

if ($status_filter) {
    switch ($status_filter) {
        case 'out_of_stock':
            $inventory_query .= " AND si.quantity = 0";
            break;
        case 'low_stock':
            $inventory_query .= " AND si.quantity <= 5 AND si.quantity > 0";
            break;
        case 'in_stock':
            $inventory_query .= " AND si.quantity > 5";
            break;
    }
}

$inventory_query .= " ORDER BY si.item_name";

$stmt = $db->prepare($inventory_query);
$stmt->execute($params);
$inventory_items = $stmt->fetchAll();

// Get categories for filter
$stmt = $db->query("SELECT * FROM gear_categories ORDER BY category_name");
$categories = $stmt->fetchAll();

// Get summary statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_items,
        SUM(quantity) as total_quantity,
        SUM(quantity * unit_price) as total_value,
        COUNT(CASE WHEN quantity = 0 THEN 1 END) as out_of_stock,
        COUNT(CASE WHEN quantity <= 5 AND quantity > 0 THEN 1 END) as low_stock
    FROM store_inventory 
    WHERE store_owner_id = ?
");
$stmt->execute([$store_owner_id]);
$summary = $stmt->fetch();

function exportInventoryReport($db, $store_owner_id, $format) {
    // Get inventory data
    $stmt = $db->prepare("
        SELECT 
            si.item_name,
            gc.category_name,
            si.brand,
            si.model,
            si.quantity,
            si.unit_price,
            si.quantity * si.unit_price as total_value,
            si.is_available,
            si.created_at,
            si.updated_at
        FROM store_inventory si
        LEFT JOIN gear_categories gc ON si.category_id = gc.category_id
        WHERE si.store_owner_id = ?
        ORDER BY si.item_name
    ");
    $stmt->execute([$store_owner_id]);
    $data = $stmt->fetchAll();
    
    if ($format === 'csv') {
        exportToCSV($data, 'inventory_report');
    } elseif ($format === 'xlsx') {
        exportToXLSX($data, 'inventory_report');
    } elseif ($format === 'pdf') {
        exportToPDF($data, 'inventory_report');
    }
}

function exportSalesReport($db, $store_owner_id, $format) {
    // Get sales data
    $stmt = $db->prepare("
        SELECT 
            st.sale_id,
            st.customer_name,
            st.customer_phone,
            st.customer_email,
            st.total_amount,
            st.cash_received,
            st.change_amount,
            st.sale_date,
            st.notes,
            GROUP_CONCAT(CONCAT(si.item_name, ' (', si.quantity, ')') SEPARATOR ', ') as items_sold
        FROM sales_transactions st
        LEFT JOIN sales_items si ON st.sale_id = si.sale_id
        WHERE st.store_owner_id = ?
        GROUP BY st.sale_id
        ORDER BY st.sale_date DESC
    ");
    $stmt->execute([$store_owner_id]);
    $data = $stmt->fetchAll();
    
    if ($format === 'csv') {
        exportToCSV($data, 'sales_report');
    } elseif ($format === 'xlsx') {
        exportToXLSX($data, 'sales_report');
    } elseif ($format === 'pdf') {
        exportToPDF($data, 'sales_report');
    }
}

function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

function exportToXLSX($data, $filename) {
    // For XLSX export, we'll use a simple HTML table that can be opened in Excel
    // In a production environment, you might want to use a library like PhpSpreadsheet
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.xls"');
    
    echo '<table border="1">';
    
    if (!empty($data)) {
        // Write headers
        echo '<tr>';
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
        
        // Write data
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . htmlspecialchars($value) . '</td>';
            }
            echo '</tr>';
        }
    }
    
    echo '</table>';
}

function exportToPDF($data, $filename) {
    // For PDF export, we'll use a simple HTML that can be converted to PDF
    // In a production environment, you might want to use a library like TCPDF or mPDF
    header('Content-Type: text/html');
    header('Content-Disposition: attachment; filename="' . $filename . '_' . date('Y-m-d') . '.html"');
    
    echo '<!DOCTYPE html>';
    echo '<html><head><title>' . ucfirst($filename) . ' Report</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }';
    echo 'th { background-color: #f2f2f2; }';
    echo 'h1 { color: #333; }';
    echo '</style></head><body>';
    
    echo '<h1>' . ucfirst($filename) . ' Report</h1>';
    echo '<p>Generated on: ' . date('Y-m-d H:i:s') . '</p>';
    
    echo '<table>';
    
    if (!empty($data)) {
        // Write headers
        echo '<tr>';
        foreach (array_keys($data[0]) as $header) {
            echo '<th>' . htmlspecialchars($header) . '</th>';
        }
        echo '</tr>';
        
        // Write data
        foreach ($data as $row) {
            echo '<tr>';
            foreach ($row as $value) {
                echo '<td>' . htmlspecialchars($value) . '</td>';
            }
            echo '</tr>';
        }
    }
    
    echo '</table>';
    echo '</body></html>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Reports - AEROZONE</title>
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
                        <i class="fas fa-chart-bar text-primary"></i> Inventory Reports
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="?export=csv&report_type=inventory" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </a>
                            <a href="?export=xlsx&report_type=inventory" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-excel"></i> Export XLSX
                            </a>
                            <a href="?export=pdf&report_type=inventory" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Total Items</h5>
                                <h3 class="card-text"><?php echo $summary['total_items']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Total Quantity</h5>
                                <h3 class="card-text"><?php echo $summary['total_quantity']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">Total Value</h5>
                                <h3 class="card-text">₱<?php echo number_format($summary['total_value'], 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Low Stock</h5>
                                <h3 class="card-text"><?php echo $summary['low_stock']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h5 class="card-title">Out of Stock</h5>
                                <h3 class="card-text"><?php echo $summary['out_of_stock']; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-filter"></i> Filters</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
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
                                    <option value="in_stock" <?php echo $status_filter === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                                    <option value="low_stock" <?php echo $status_filter === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                                    <option value="out_of_stock" <?php echo $status_filter === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                                <a href="reports.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Inventory Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Inventory Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Brand</th>
                                        <th>Model</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total Value</th>
                                        <th>Stock Status</th>
                                        <th>Last Updated</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($inventory_items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($item['brand'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($item['model'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $item['quantity'] == 0 ? 'bg-danger' : 
                                                        ($item['quantity'] <= 5 ? 'bg-warning' : 'bg-success'); 
                                                ?>">
                                                    <?php echo $item['quantity']; ?>
                                                </span>
                                            </td>
                                            <td>₱<?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td>₱<?php echo number_format($item['quantity'] * $item['unit_price'], 2); ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $item['stock_status'] === 'Out of Stock' ? 'bg-danger' : 
                                                        ($item['stock_status'] === 'Low Stock' ? 'bg-warning' : 'bg-success'); 
                                                ?>">
                                                    <?php echo $item['stock_status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($item['updated_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($inventory_items)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No inventory items found matching your criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>
