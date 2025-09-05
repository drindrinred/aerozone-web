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
    exportSalesReport($db, $store_owner_id, $format);
    exit();
}

// Get filter parameters
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // Default to first day of current month
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // Default to today
$customer_filter = $_GET['customer'] ?? '';

// Build sales query with filters
$sales_query = "
    SELECT 
        st.*,
        GROUP_CONCAT(CONCAT(si.item_name, ' (', si.quantity, ')') SEPARATOR ', ') as items_sold,
        COUNT(si.sale_item_id) as total_items_sold
    FROM sales_transactions st
    LEFT JOIN sales_items si ON st.sale_id = si.sale_id
    WHERE st.store_owner_id = ? 
    AND DATE(st.sale_date) BETWEEN ? AND ?
";

$params = [$store_owner_id, $date_from, $date_to];

if ($customer_filter) {
    $sales_query .= " AND (st.customer_name LIKE ? OR st.customer_phone LIKE ? OR st.customer_email LIKE ?)";
    $search_term = "%$customer_filter%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$sales_query .= " GROUP BY st.sale_id ORDER BY st.sale_date DESC";

$stmt = $db->prepare($sales_query);
$stmt->execute($params);
$sales_transactions = $stmt->fetchAll();

// Get summary statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_sales,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as average_sale,
        COUNT(DISTINCT DATE(sale_date)) as days_with_sales,
        SUM(total_amount) / COUNT(DISTINCT DATE(sale_date)) as daily_average
    FROM sales_transactions 
    WHERE store_owner_id = ? 
    AND DATE(sale_date) BETWEEN ? AND ?
");
$stmt->execute([$store_owner_id, $date_from, $date_to]);
$summary = $stmt->fetch();

// Get top selling items
$stmt = $db->prepare("
    SELECT 
        si.item_name,
        SUM(si.quantity) as total_quantity_sold,
        SUM(si.total_price) as total_revenue,
        COUNT(DISTINCT st.sale_id) as number_of_sales
    FROM sales_items si
    JOIN sales_transactions st ON si.sale_id = st.sale_id
    WHERE st.store_owner_id = ? 
    AND DATE(st.sale_date) BETWEEN ? AND ?
    GROUP BY si.item_name
    ORDER BY total_quantity_sold DESC
    LIMIT 10
");
$stmt->execute([$store_owner_id, $date_from, $date_to]);
$top_items = $stmt->fetchAll();

// Get daily sales for chart
$stmt = $db->prepare("
    SELECT 
        DATE(sale_date) as sale_date,
        COUNT(*) as sales_count,
        SUM(total_amount) as daily_revenue
    FROM sales_transactions 
    WHERE store_owner_id = ? 
    AND DATE(sale_date) BETWEEN ? AND ?
    GROUP BY DATE(sale_date)
    ORDER BY sale_date
");
$stmt->execute([$store_owner_id, $date_from, $date_to]);
$daily_sales = $stmt->fetchAll();

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
    <title>Sales Reports - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-chart-line text-success"></i> Sales Reports
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="?export=csv&format=csv" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </a>
                            <a href="?export=xlsx&format=xlsx" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-excel"></i> Export XLSX
                            </a>
                            <a href="?export=pdf&format=pdf" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-file-pdf"></i> Export PDF
                            </a>
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
                                <label for="date_from" class="form-label">Date From</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="date_to" class="form-label">Date To</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="customer" class="form-label">Customer Search</label>
                                <input type="text" class="form-control" id="customer" name="customer" value="<?php echo htmlspecialchars($customer_filter); ?>" placeholder="Name, phone, or email">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Apply Filters
                                    </button>
                                    <a href="sales-reports.php" class="btn btn-secondary">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card text-white bg-primary">
                            <div class="card-body">
                                <h5 class="card-title">Total Sales</h5>
                                <h3 class="card-text"><?php echo $summary['total_sales']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Total Revenue</h5>
                                <h3 class="card-text">₱<?php echo number_format($summary['total_revenue'], 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-info">
                            <div class="card-body">
                                <h5 class="card-title">Average Sale</h5>
                                <h3 class="card-text">₱<?php echo number_format($summary['average_sale'], 2); ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Days with Sales</h5>
                                <h3 class="card-text"><?php echo $summary['days_with_sales']; ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h5 class="card-title">Daily Average</h5>
                                <h3 class="card-text">₱<?php echo number_format($summary['daily_average'], 2); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-area"></i> Daily Sales Trend</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="dailySalesChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Top Selling Items</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="topItemsChart" width="400" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Items Table -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-trophy"></i> Top Selling Items</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Quantity Sold</th>
                                        <th>Total Revenue</th>
                                        <th>Number of Sales</th>
                                        <th>Average per Sale</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                            <td>
                                                <span class="badge bg-primary"><?php echo $item['total_quantity_sold']; ?></span>
                                            </td>
                                            <td>₱<?php echo number_format($item['total_revenue'], 2); ?></td>
                                            <td><?php echo $item['number_of_sales']; ?></td>
                                            <td>₱<?php echo number_format($item['total_revenue'] / $item['number_of_sales'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Sales Transactions Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Sales Transactions</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Sale ID</th>
                                        <th>Customer</th>
                                        <th>Contact Info</th>
                                        <th>Items Sold</th>
                                        <th>Total Amount</th>
                                        <th>Cash Received</th>
                                        <th>Change</th>
                                        <th>Sale Date</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sales_transactions as $sale): ?>
                                        <tr>
                                            <td>#<?php echo $sale['sale_id']; ?></td>
                                            <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
                                            <td>
                                                <?php if ($sale['customer_phone']): ?>
                                                    <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($sale['customer_phone']); ?></div>
                                                <?php endif; ?>
                                                <?php if ($sale['customer_email']): ?>
                                                    <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($sale['customer_email']); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($sale['items_sold']); ?></small>
                                                <br><span class="badge bg-secondary"><?php echo $sale['total_items_sold']; ?> items</span>
                                            </td>
                                            <td><strong>₱<?php echo number_format($sale['total_amount'], 2); ?></strong></td>
                                            <td>₱<?php echo number_format($sale['cash_received'], 2); ?></td>
                                            <td>₱<?php echo number_format($sale['change_amount'], 2); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($sale['notes'] ?? ''); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($sales_transactions)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No sales transactions found for the selected period.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    
    <script>
        // Daily Sales Chart
        const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
        const dailySalesChart = new Chart(dailySalesCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($daily_sales, 'sale_date')); ?>,
                datasets: [{
                    label: 'Daily Revenue (₱)',
                    data: <?php echo json_encode(array_column($daily_sales, 'daily_revenue')); ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }, {
                    label: 'Number of Sales',
                    data: <?php echo json_encode(array_column($daily_sales, 'sales_count')); ?>,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'rgba(255, 99, 132, 0.2)',
                    tension: 0.1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (₱)'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Number of Sales'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Top Items Chart
        const topItemsCtx = document.getElementById('topItemsChart').getContext('2d');
        const topItemsChart = new Chart(topItemsCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($top_items, 'item_name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($top_items, 'total_revenue')); ?>,
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                        '#FF6384',
                        '#C9CBCF',
                        '#4BC0C0',
                        '#FF6384'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    </script>
</body>
</html>
