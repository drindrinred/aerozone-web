<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

$message = '';
$message_type = '';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
	$listing_id = intval($_POST['listing_id'] ?? 0);
	$new_status = $_POST['status'] ?? '';
	$allowed_status = ['active', 'sold', 'bartered', 'inactive'];
	if ($listing_id && in_array($new_status, $allowed_status, true)) {
		try {
			// Ensure the listing belongs to the current user
			$stmt = $db->prepare("SELECT listing_id FROM marketplace_listings WHERE listing_id = ? AND seller_id = ?");
			$stmt->execute([$listing_id, $user['user_id']]);
			if ($stmt->fetch()) {
				$stmt = $db->prepare("UPDATE marketplace_listings SET status = ?, updated_at = NOW() WHERE listing_id = ?");
				$stmt->execute([$new_status, $listing_id]);
				$message = 'Listing status updated.';
				$message_type = 'success';
			} else {
				$message = 'Invalid listing.';
				$message_type = 'danger';
			}
		} catch (PDOException $e) {
			$message = 'Failed to update status.';
			$message_type = 'danger';
		}
	}
}

// Filters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 12;

$where_conditions = ['ml.seller_id = ?', "ml.status IN ('active','sold','bartered','inactive')"];
$params = [$user['user_id']];

if ($search !== '') {
	$where_conditions[] = '(ml.title LIKE ? OR ml.description LIKE ?)';
	$params[] = "%$search%";
	$params[] = "%$search%";
}

if (in_array($status_filter, ['active','sold','bartered','inactive'], true)) {
	$where_conditions[] = 'ml.status = ?';
	$params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

switch ($sort) {
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
	// Count for pagination
	$count_sql = "
		SELECT COUNT(*)
		FROM marketplace_listings ml
		WHERE $where_clause
	";
	$stmt = $db->prepare($count_sql);
	$stmt->execute($params);
	$total_listings = (int)$stmt->fetchColumn();
	$total_pages = (int)ceil($total_listings / $per_page);
	$offset = ($page - 1) * $per_page;

	// Fetch listings
	$stmt = $db->prepare(""
		. "SELECT ml.*, "
		. "(SELECT image_path FROM listing_images li WHERE li.listing_id = ml.listing_id AND li.is_primary = 1 LIMIT 1) AS primary_image "
		. "FROM marketplace_listings ml "
		. "WHERE $where_clause "
		. "ORDER BY $order_clause "
		. "LIMIT $per_page OFFSET $offset"
	);
	$stmt->execute($params);
	$listings = $stmt->fetchAll();
} catch (PDOException $e) {
	$error_message = 'Error loading your listings.';
	$listings = [];
	$total_listings = 0;
	$total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Listings - AEROZONE</title>
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
					<h1 class="h2"><i class="fas fa-list me-2"></i>My Listings</h1>
					<div class="btn-toolbar mb-2 mb-md-0">
						<a href="create-listing.php" class="btn btn-primary">
							<i class="fas fa-plus me-1"></i>Create Listing
						</a>
					</div>
				</div>

				<?php if (!empty($message)): ?>
					<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
						<?php echo htmlspecialchars($message); ?>
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
				<?php endif; ?>

				<?php if (isset($error_message)): ?>
					<div class="alert alert-danger alert-dismissible fade show" role="alert">
						<?php echo htmlspecialchars($error_message); ?>
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
				<?php endif; ?>

				<div class="card mb-3">
					<div class="card-body">
						<form class="row g-3" method="GET" action="">
							<div class="col-md-4">
								<div class="input-group">
									<span class="input-group-text"><i class="fas fa-search"></i></span>
									<input type="text" class="form-control" name="search" placeholder="Search my listings..." value="<?php echo htmlspecialchars($search); ?>">
								</div>
							</div>
							<div class="col-md-3">
								<select class="form-select" name="status">
									<option value="">All Statuses</option>
									<?php foreach (['active','sold','bartered','inactive'] as $s): ?>
										<option value="<?php echo $s; ?>" <?php echo $status_filter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
							<div class="col-md-3">
								<select class="form-select" name="sort" onchange="this.form.submit()">
									<option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Newest</option>
									<option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
									<option value="views" <?php echo $sort === 'views' ? 'selected' : ''; ?>>Most Viewed</option>
								</select>
							</div>
							<div class="col-md-2">
								<div class="d-flex gap-2">
									<button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
									<a class="btn btn-outline-secondary" href="my-listings.php"><i class="fas fa-times"></i></a>
								</div>
							</div>
						</form>
					</div>
				</div>

				<?php if (!empty($listings)): ?>
					<div class="row g-4">
						<?php foreach ($listings as $listing): ?>
							<div class="col-md-6 col-lg-4">
								<div class="card h-100">
									<?php if ($listing['primary_image']): ?>
										<img src="../../uploads/listings/<?php echo htmlspecialchars($listing['primary_image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($listing['title']); ?>">
									<?php endif; ?>
									<div class="card-body">
										<h5 class="card-title text-truncate"><?php echo htmlspecialchars($listing['title']); ?></h5>
										<p class="card-text text-muted small mb-1">Status: <span class="badge bg-secondary"><?php echo ucfirst($listing['status']); ?></span></p>
										<p class="card-text text-muted small">Views: <?php echo number_format($listing['views_count']); ?> · Created: <?php echo date('M j, Y', strtotime($listing['created_at'])); ?></p>
									</div>
									<div class="card-footer bg-transparent">
										<div class="d-flex gap-2">
											<a href="view-listing.php?id=<?php echo $listing['listing_id']; ?>" class="btn btn-sm btn-outline-primary flex-fill"><i class="fas fa-eye me-1"></i>Open</a>
											<form method="POST" action="" class="d-flex gap-2">
												<input type="hidden" name="action" value="update_status">
												<input type="hidden" name="listing_id" value="<?php echo $listing['listing_id']; ?>">
												<select name="status" class="form-select form-select-sm">
													<?php foreach (['active','sold','bartered','inactive'] as $s): ?>
														<option value="<?php echo $s; ?>" <?php echo $listing['status'] === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
													<?php endforeach; ?>
												</select>
												<button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-save"></i></button>
											</form>
										</div>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<?php if ($total_pages > 1): ?>
						<nav aria-label="My listings pagination" class="mt-4">
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
										<a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
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
						<i class="fas fa-box-open fa-3x text-muted mb-3"></i>
						<h4 class="text-muted">You have no listings yet</h4>
						<p class="text-muted">Create your first listing to start selling or trading.</p>
						<a href="create-listing.php" class="btn btn-primary">
							<i class="fas fa-plus me-1"></i>Create Listing
						</a>
					</div>
				<?php endif; ?>
			</main>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>



