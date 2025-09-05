<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

$message = '';
$message_type = '';

// Handle search and filters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';
$condition_filter = $_GET['condition'] ?? '';
$type_filter = $_GET['type'] ?? '';
$price_min = $_GET['price_min'] ?? '';
$price_max = $_GET['price_max'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;
// simplified: no personal-only toggle

// Build query
$where_conditions = ["ml.status = 'active'"];
$params = [];

if ($search) {
    $where_conditions[] = "(ml.title LIKE ? OR ml.description LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category_filter) {
    $where_conditions[] = "ml.category_id = ?";
    $params[] = (int)$category_filter;
}

if ($condition_filter) {
    $where_conditions[] = "ml.condition_status = ?";
    $params[] = $condition_filter;
}

if ($type_filter) {
    $where_conditions[] = "ml.listing_type = ?";
    $params[] = $type_filter;
}

if ($price_min !== '') {
    $where_conditions[] = "ml.price >= ?";
    $params[] = $price_min;
}

if ($price_max !== '') {
    $where_conditions[] = "ml.price <= ?";
    $params[] = $price_max;
}

// no user-only filter here

$where_clause = implode(' AND ', $where_conditions);

// Sort options
switch($sort) {
    case 'price_low':
        $order_clause = 'ml.price ASC';
        break;
    case 'price_high':
        $order_clause = 'ml.price DESC';
        break;
    case 'oldest':
        $order_clause = 'ml.created_at ASC';
        break;
    case 'views':
        $order_clause = 'ml.views_count DESC';
        break;
    default:
        $order_clause = 'ml.created_at DESC';
        break;
}

try {
    // Export CSV disabled
    // Count total listings for pagination
    $count_sql = "
        SELECT COUNT(*) 
        FROM marketplace_listings ml
        JOIN users u ON ml.seller_id = u.user_id
        LEFT JOIN gear_categories gc ON ml.category_id = gc.category_id
        WHERE $where_clause
    ";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_listings = $stmt->fetchColumn();
    $total_pages = ceil($total_listings / $per_page);
    $offset = ($page - 1) * $per_page;

    // Get listings with pagination
    $stmt = $db->prepare(""
        . "SELECT ml.*, u.full_name as seller_name, gc.category_name,"
        . "       (SELECT image_path FROM listing_images li WHERE li.listing_id = ml.listing_id AND li.is_primary = 1 LIMIT 1) as primary_image,"
        . "       (SELECT COUNT(*) FROM direct_messages WHERE listing_id = ml.listing_id) as message_count "
        . "FROM marketplace_listings ml "
        . "JOIN users u ON ml.seller_id = u.user_id "
        . "LEFT JOIN gear_categories gc ON ml.category_id = gc.category_id "
        . "WHERE $where_clause "
        . "ORDER BY $order_clause "
        . "LIMIT $per_page OFFSET $offset"
    );
    $stmt->execute($params);
    $listings = $stmt->fetchAll();

    // Get categories for filter
    $stmt = $db->query("SELECT * FROM gear_categories ORDER BY category_name");
    $categories = $stmt->fetchAll();

    // Get user's own listings count
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM marketplace_listings WHERE seller_id = ? AND status = 'active'");
    $stmt->execute([$user['user_id']]);
    $user_listings_count = $stmt->fetch()['count'];

    // removed auxiliary queries

} catch (PDOException $e) {
    $error_message = "Error loading marketplace: " . $e->getMessage();
    $listings = [];
    $categories = [];
    // removed auxiliary defaults
    $total_listings = 0;
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .filter-card { background: #f8f9fa; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; }
        .listing-card { transition: all 0.3s ease; border: none; border-radius: 15px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .listing-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.15); }
        .listing-image { height: 200px; object-fit: cover; transition: transform 0.3s ease; }
        .listing-card:hover .listing-image { transform: scale(1.05); }
        .price-badge { background: linear-gradient(45deg, #28a745, #20c997); color: white; padding: 0.5rem 1rem; border-radius: 20px; font-weight: bold; }
        .condition-badge { padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.8rem; }
        .pagination .page-link { border-radius: 8px; margin: 0 2px; }
        .pagination .page-item.active .page-link { background-color: var(--primary-color); border-color: var(--primary-color); }
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
                        <i class="fas fa-shopping-cart me-2"></i>Marketplace
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="my-listings.php" class="btn btn-outline-primary">
                                <i class="fas fa-list me-1"></i>My Listings (<?php echo $user_listings_count; ?>)
                            </a>
                        </div>
                        <a href="create-listing.php" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Create Listing
                        </a>
                    </div>
                </div>

                <?php if (isset($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Main Content -->
                    <div class="col-lg-12">
                        <!-- Search and Filters -->
                        <div class="filter-card">
                            <form method="GET" action="" class="row g-3">
                                <div class="col-md-4">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" class="form-control" name="search" 
                                               placeholder="Search listings, sellers..." value="<?php echo htmlspecialchars($search); ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="category">
                                        <option value="">All Categories</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['category_id']; ?>" 
                                                    <?php echo $category_filter == $category['category_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="condition">
                                        <option value="">All Conditions</option>
                                        <option value="new" <?php echo $condition_filter === 'new' ? 'selected' : ''; ?>>New</option>
                                        <option value="like_new" <?php echo $condition_filter === 'like_new' ? 'selected' : ''; ?>>Like New</option>
                                        <option value="good" <?php echo $condition_filter === 'good' ? 'selected' : ''; ?>>Good</option>
                                        <option value="fair" <?php echo $condition_filter === 'fair' ? 'selected' : ''; ?>>Fair</option>
                                        <option value="poor" <?php echo $condition_filter === 'poor' ? 'selected' : ''; ?>>Poor</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="type">
                                        <option value="">All Types</option>
                                        <option value="sale" <?php echo $type_filter === 'sale' ? 'selected' : ''; ?>>For Sale</option>
                                        <option value="barter" <?php echo $type_filter === 'barter' ? 'selected' : ''; ?>>For Barter</option>
                                        <option value="both" <?php echo $type_filter === 'both' ? 'selected' : ''; ?>>Sale/Barter</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    </div>
                                </div>
                                
                                <!-- Price Range Filter -->
                                <div class="col-md-3">
                                    <label class="form-label">Min Price</label>
                                    <input type="number" class="form-control" name="price_min" 
                                           placeholder="₱0" value="<?php echo htmlspecialchars($price_min); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Max Price</label>
                                    <input type="number" class="form-control" name="price_max" 
                                           placeholder="₱∞" value="<?php echo htmlspecialchars($price_max); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Sort By</label>
                                    <select class="form-select" name="sort" onchange="this.form.submit()">
                                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                                        <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                                        <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price (Low to High)</option>
                                        <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price (High to Low)</option>
                                        <option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>Most Viewed</option>
                                    </select>
                                </div>
                            </form>
                        </div>

                        <!-- Results Summary -->
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <p class="mb-0 text-muted">
                                <i class="fas fa-search me-1"></i>
                                <?php echo number_format($total_listings); ?> listings found
                                <?php if ($search || $category_filter || $condition_filter || $type_filter || $price_min || $price_max): ?>
                                    <span class="text-primary">(filtered)</span>
                                <?php endif; ?>
                            </p>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-primary" onclick="toggleViewMode()">
                                    <i class="fas fa-th-large" id="viewModeIcon"></i>
                                </button>
                                
                            </div>
                        </div>

                        <!-- Listings Grid -->
                        <?php if (!empty($listings)): ?>
                            <div class="row g-4" id="listingsGrid">
                                <?php foreach ($listings as $listing): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card listing-card h-100" onclick="viewListing(<?php echo $listing['listing_id']; ?>)">
                                            <div class="position-relative">
                                                <?php if ($listing['primary_image']): ?>
                                                    <img src="../../uploads/listings/<?php echo htmlspecialchars($listing['primary_image']); ?>" 
                                                         class="listing-image w-100" alt="<?php echo htmlspecialchars($listing['title']); ?>">
                                                <?php else: ?>
                                                    <div class="listing-image w-100 bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-image fa-3x text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <!-- Listing Type Badge -->
                                                <span class="position-absolute top-0 start-0 m-2 badge bg-<?php 
                                                    echo $listing['listing_type'] === 'sale' ? 'success' : 
                                                        ($listing['listing_type'] === 'barter' ? 'warning' : 'info'); 
                                                ?>">
                                                    <i class="fas fa-<?php echo $listing['listing_type'] === 'sale' ? 'tag' : ($listing['listing_type'] === 'barter' ? 'exchange-alt' : 'tags'); ?> me-1"></i>
                                                    <?php echo ucfirst($listing['listing_type']); ?>
                                                </span>
                                                
                                                <!-- Condition Badge -->
                                                <span class="position-absolute top-0 end-0 m-2 condition-badge bg-<?php 
                                                    echo $listing['condition_status'] === 'new' ? 'success' : 
                                                        ($listing['condition_status'] === 'like_new' ? 'info' : 
                                                        ($listing['condition_status'] === 'good' ? 'primary' : 
                                                        ($listing['condition_status'] === 'fair' ? 'warning' : 
                                                        ($listing['condition_status'] === 'poor' ? 'danger' : 'secondary'))));
                                                ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $listing['condition_status'])); ?>
                                                </span>
                                                
                                                <!-- Views Badge -->
                                                <span class="position-absolute bottom-0 end-0 m-2 badge bg-dark">
                                                    <i class="fas fa-eye me-1"></i><?php echo number_format($listing['views_count']); ?>
                                                </span>
                                            </div>
                                            
                                            <div class="card-body">
                                                <h5 class="card-title text-truncate"><?php echo htmlspecialchars($listing['title']); ?></h5>
                                                
                                                <?php if ($listing['category_name']): ?>
                                                    <span class="badge bg-light text-dark mb-2">
                                                        <i class="fas fa-tag me-1"></i><?php echo htmlspecialchars($listing['category_name']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                                <p class="card-text text-muted small">
                                                    <?php echo htmlspecialchars(substr($listing['description'], 0, 80)); ?>
                                                    <?php echo strlen($listing['description']) > 80 ? '...' : ''; ?>
                                                </p>
                                                
                                                <?php if ($listing['price'] && in_array($listing['listing_type'], ['sale', 'both'])): ?>
                                                    <div class="mb-2">
                                                        <span class="price-badge">₱<?php echo number_format($listing['price'], 2); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?php echo htmlspecialchars($listing['seller_name']); ?>
                                                    </small>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('M j', strtotime($listing['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                            
                                            <div class="card-footer bg-transparent">
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-primary btn-sm flex-fill" 
                                                            onclick="event.stopPropagation(); viewListing(<?php echo $listing['listing_id']; ?>)">
                                                        <i class="fas fa-eye me-1"></i>View Details
                                                    </button>
                                                    <?php if ($listing['seller_id'] != $user['user_id']): ?>
                                                        <button class="btn btn-outline-primary btn-sm" 
                                                                onclick="event.stopPropagation(); contactSeller(<?php echo $listing['listing_id']; ?>, <?php echo $listing['seller_id']; ?>)">
                                                            <i class="fas fa-envelope"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Marketplace pagination" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">No listings found</h4>
                                <p class="text-muted">Try adjusting your search criteria or browse all listings.</p>
                                <div class="d-flex gap-2 justify-content-center">
                                    <a href="index.php" class="btn btn-outline-primary">Browse All</a>
                                    <a href="create-listing.php" class="btn btn-primary">Create Listing</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    
                </div>
            </main>
        </div>
    </div>

    

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewListing(listingId) {
            window.location.href = `view-listing.php?id=${listingId}`;
        }

        function contactSeller(listingId, sellerId) {
            window.location.href = `../messages/compose.php?listing_id=${listingId}&recipient_id=${sellerId}`;
        }

        function toggleViewMode() {
            const grid = document.getElementById('listingsGrid');
            const icon = document.getElementById('viewModeIcon');
            
            if (grid.classList.contains('list-view')) {
                grid.classList.remove('list-view');
                grid.classList.add('grid-view');
                icon.className = 'fas fa-th-large';
            } else {
                grid.classList.remove('grid-view');
                grid.classList.add('list-view');
                icon.className = 'fas fa-list';
            }
        }

        // Update view count when viewing listing details
        function updateViewCount(listingId) {
            fetch('../../api/update-listing-views.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ listing_id: listingId })
            });
        }

        
    </script>
</body>
</html>
