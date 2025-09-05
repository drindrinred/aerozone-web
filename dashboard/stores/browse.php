<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireAnyRole(['player', 'admin']);

$user = getCurrentUser();
$db = getDB();

// Handle search and filters
$search = $_GET['search'] ?? '';
$location_filter = $_GET['location'] ?? '';
$service_filter = $_GET['service'] ?? '';

// Build query for approved stores only
$where_conditions = ["so.registration_status = 'approved'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(so.business_name LIKE ? OR u.full_name LIKE ? OR so.business_address LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($location_filter)) {
    $where_conditions[] = "so.business_address LIKE ?";
    $params[] = "%{$location_filter}%";
}

$where_clause = implode(' AND ', $where_conditions);

try {
    // Get approved stores with pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $per_page = 12;
    $offset = ($page - 1) * $per_page;
    
    // Count total stores
    $count_sql = "SELECT COUNT(*) FROM store_owners so 
                  JOIN users u ON so.user_id = u.user_id 
                  WHERE {$where_clause}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_stores = $stmt->fetchColumn();
    $total_pages = ceil($total_stores / $per_page);
    
    // Get stores with their inventory count
    $sql = "SELECT so.*, u.full_name, u.email, u.phone_number,
                   COUNT(si.store_inventory_id) as inventory_count,
                   COUNT(DISTINCT ma.appointment_id) as total_appointments
            FROM store_owners so
            JOIN users u ON so.user_id = u.user_id
            LEFT JOIN store_inventory si ON so.store_owner_id = si.store_owner_id AND si.is_available = 1
            LEFT JOIN maintenance_appointments ma ON so.store_owner_id = ma.store_owner_id
            WHERE {$where_clause}
            GROUP BY so.store_owner_id
            ORDER BY so.business_name ASC
            LIMIT {$per_page} OFFSET {$offset}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $stores = $stmt->fetchAll();
    
    // Get unique locations for filter
    $stmt = $db->query("SELECT DISTINCT SUBSTRING_INDEX(business_address, ',', -1) as city 
                       FROM store_owners 
                       WHERE registration_status = 'approved' 
                       ORDER BY city");
    $locations = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Error fetching stores: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Stores - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .store-card {
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .store-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .store-rating {
            color: #ffc107;
        }
        .store-status {
            position: absolute;
            top: 10px;
            right: 10px;
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
                        <i class="fas fa-store me-2"></i>Browse Stores
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <button type="button" class="btn btn-outline-primary" onclick="toggleMapView()">
                                <i class="fas fa-map me-1"></i>Map View
                            </button>
                        </div>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" action="" class="row g-3">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Search stores, services, or location..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="location">
                                    <option value="">All Locations</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo htmlspecialchars(trim($location['city'])); ?>" 
                                                <?php echo $location_filter === trim($location['city']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(trim($location['city'])); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="service">
                                    <option value="">All Services</option>
                                    <option value="maintenance" <?php echo $service_filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                    <option value="repair" <?php echo $service_filter === 'repair' ? 'selected' : ''; ?>>Repair</option>
                                    <option value="cleaning" <?php echo $service_filter === 'cleaning' ? 'selected' : ''; ?>>Cleaning</option>
                                    <option value="parts" <?php echo $service_filter === 'parts' ? 'selected' : ''; ?>>Parts & Accessories</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <a href="browse.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times"></i>
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Results Summary -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <p class="mb-0 text-muted">
                        <?php echo $total_stores; ?> store<?php echo $total_stores !== 1 ? 's' : ''; ?> found
                        <?php if (!empty($search) || !empty($location_filter)): ?>
                            <span class="badge bg-primary ms-2">Filtered</span>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Stores Grid -->
                <?php if (!empty($stores)): ?>
                    <div class="row g-4">
                        <?php foreach ($stores as $store): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card store-card h-100" onclick="viewStoreDetails(<?php echo $store['store_owner_id']; ?>)">
                                    <div class="card-body position-relative">
                                        <div class="store-status">
                                            <span class="badge bg-success">
                                                <i class="fas fa-check-circle me-1"></i>Verified
                                            </span>
                                        </div>
                                        
                                        <h5 class="card-title mb-2">
                                            <?php echo htmlspecialchars($store['business_name']); ?>
                                        </h5>
                                        
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-user me-1"></i>
                                            <?php echo htmlspecialchars($store['full_name']); ?>
                                        </p>
                                        
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-map-marker-alt me-1"></i>
                                            <?php echo htmlspecialchars(substr($store['business_address'], 0, 60)); ?>
                                            <?php if (strlen($store['business_address']) > 60) echo '...'; ?>
                                        </p>
                                        
                                        <?php if ($store['business_phone']): ?>
                                            <p class="text-muted mb-2">
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($store['business_phone']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($store['website']): ?>
                                            <p class="text-muted mb-3">
                                                <i class="fas fa-globe me-1"></i>
                                                <a href="<?php echo htmlspecialchars($store['website']); ?>" 
                                                   target="_blank" onclick="event.stopPropagation();">
                                                    Visit Website
                                                </a>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <!-- Store Stats -->
                                        <div class="row text-center mb-3">
                                            <div class="col-6">
                                                <div class="border-end">
                                                    <h6 class="mb-0"><?php echo $store['inventory_count']; ?></h6>
                                                    <small class="text-muted">Items</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <h6 class="mb-0"><?php echo $store['total_appointments']; ?></h6>
                                                <small class="text-muted">Services</small>
                                            </div>
                                        </div>
                                        
                                        <!-- Store Rating (placeholder for future implementation) -->
                                        <div class="mb-3">
                                            <div class="store-rating">
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="fas fa-star"></i>
                                                <i class="far fa-star"></i>
                                                <small class="text-muted ms-1">(4.0)</small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-footer bg-transparent">
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-primary btn-sm flex-fill" 
                                                    onclick="event.stopPropagation(); viewStoreDetails(<?php echo $store['store_owner_id']; ?>)">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </button>
                                            <button class="btn btn-outline-success btn-sm" 
                                                    onclick="event.stopPropagation(); bookAppointment(<?php echo $store['store_owner_id']; ?>)">
                                                <i class="fas fa-calendar me-1"></i>Book
                                            </button>
                                            <button class="btn btn-outline-primary btn-sm" 
                                                    onclick="event.stopPropagation(); contactStore(<?php echo $store['user_id']; ?>)">
                                                <i class="fas fa-envelope"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Store pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location_filter); ?>&service=<?php echo urlencode($service_filter); ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location_filter); ?>&service=<?php echo urlencode($service_filter); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&location=<?php echo urlencode($location_filter); ?>&service=<?php echo urlencode($service_filter); ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-store fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No stores found</h4>
                        <p class="text-muted">
                            <?php if (!empty($search) || !empty($location_filter)): ?>
                                Try adjusting your search criteria or filters.
                            <?php else: ?>
                                No approved stores are currently available.
                            <?php endif; ?>
                        </p>
                        <?php if (!empty($search) || !empty($location_filter)): ?>
                            <a href="browse.php" class="btn btn-outline-primary">Browse All Stores</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Store Details Modal -->
    <div class="modal fade" id="storeDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Store Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="storeDetailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function viewStoreDetails(storeOwnerId) {
            const modal = new bootstrap.Modal(document.getElementById('storeDetailsModal'));
            const content = document.getElementById('storeDetailsContent');
            
            // Show loading spinner
            content.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            `;
            
            modal.show();
            
            // Fetch store details
            fetch(`store-details.php?id=${storeOwnerId}`)
                .then(response => response.text())
                .then(html => {
                    content.innerHTML = html;
                })
                .catch(error => {
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Error loading store details. Please try again.
                        </div>
                    `;
                });
        }

        function bookAppointment(storeOwnerId) {
            window.location.href = `../appointments/index.php?store_id=${storeOwnerId}`;
        }

        function contactStore(userId) {
            window.location.href = `../messages/compose.php?recipient_id=${userId}`;
        }

        function toggleMapView() {
            showToast('Map view feature coming soon!', 'info');
        }

        // Initialize tooltips
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>