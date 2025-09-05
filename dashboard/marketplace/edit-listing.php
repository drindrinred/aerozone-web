<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

$listing_id = intval($_GET['id'] ?? 0);
if (!$listing_id) {
	header('Location: my-listings.php');
	exit();
}

$message = '';
$message_type = '';

// Fetch listing and ensure ownership
try {
	$stmt = $db->prepare("SELECT * FROM marketplace_listings WHERE listing_id = ? AND seller_id = ?");
	$stmt->execute([$listing_id, $user['user_id']]);
	$listing = $stmt->fetch();
	if (!$listing) {
		header('Location: my-listings.php');
		exit();
	}
} catch (PDOException $e) {
	$message = 'Error loading listing.';
	$message_type = 'danger';
}

// Handle actions (update details, upload images, delete image, set primary)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$action = $_POST['action'] ?? 'update_details';
	if ($action === 'update_details') {
		$title = trim($_POST['title'] ?? '');
		$description = trim($_POST['description'] ?? '');
		$category_id = intval($_POST['category_id'] ?? 0);
		$price = isset($_POST['price']) ? floatval($_POST['price']) : null;
		$listing_type = $_POST['listing_type'] ?? 'sale';
		$condition_status = $_POST['condition_status'] ?? 'good';
		$location = trim($_POST['location'] ?? '');
		$status = $_POST['status'] ?? $listing['status'];

		$errors = [];
		if ($title === '') $errors[] = 'Title is required';
		if ($description === '') $errors[] = 'Description is required';
		if ($listing_type === 'sale' && ($price === null || $price <= 0)) $errors[] = 'Price is required for sale listings';
		if ($location === '') $errors[] = 'Location is required';
		if (!in_array($status, ['active','sold','bartered','inactive'], true)) $errors[] = 'Invalid status';

		if (empty($errors)) {
			try {
				$stmt = $db->prepare("UPDATE marketplace_listings SET title = ?, description = ?, category_id = ?, price = ?, listing_type = ?, condition_status = ?, location = ?, status = ?, updated_at = NOW() WHERE listing_id = ? AND seller_id = ?");
				$stmt->execute([
					$title,
					$description,
					$category_id ?: null,
					($listing_type === 'barter') ? null : $price,
					$listing_type,
					$condition_status,
					$location,
					$status,
					$listing_id,
					$user['user_id']
				]);
				$message = 'Listing updated successfully!';
				$message_type = 'success';
				// Refresh listing
				$stmt = $db->prepare("SELECT * FROM marketplace_listings WHERE listing_id = ?");
				$stmt->execute([$listing_id]);
				$listing = $stmt->fetch();
			} catch (PDOException $e) {
				$message = 'Error updating listing.';
				$message_type = 'danger';
			}
		} else {
			$message = implode(', ', $errors);
			$message_type = 'danger';
		}
	} elseif ($action === 'upload_images') {
		// Upload new images
		$upload_dir = '../../uploads/listings/';
		if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
		$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
		$hasPrimaryStmt = $db->prepare("SELECT COUNT(*) FROM listing_images WHERE listing_id = ? AND is_primary = 1");
		$hasPrimaryStmt->execute([$listing_id]);
		$hasPrimary = $hasPrimaryStmt->fetchColumn() > 0;
		$madePrimary = false;
		if (!empty($_FILES['images']['name'][0])) {
			foreach ($_FILES['images']['name'] as $i => $filename) {
				if ($_FILES['images']['error'][$i] === UPLOAD_ERR_OK) {
					$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
					if (in_array($ext, $allowed_extensions, true)) {
						$newFilename = $listing_id . '_' . time() . '_' . $i . '.' . $ext;
						if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $upload_dir . $newFilename)) {
							$stmt = $db->prepare("INSERT INTO listing_images (listing_id, image_path, is_primary) VALUES (?, ?, ?)");
							$isPrimary = (!$hasPrimary && !$madePrimary) ? 1 : 0;
							$stmt->execute([$listing_id, $newFilename, $isPrimary]);
							if ($isPrimary) { $madePrimary = true; }
						}
					}
				}
			}
			$message = 'Images uploaded successfully.';
			$message_type = 'success';
		}
	} elseif ($action === 'delete_image') {
		$image_id = intval($_POST['image_id'] ?? 0);
		if ($image_id) {
			$stmt = $db->prepare("SELECT image_path, is_primary FROM listing_images WHERE image_id = ? AND listing_id = ?");
			$stmt->execute([$image_id, $listing_id]);
			$img = $stmt->fetch();
			if ($img) {
				@unlink('../../uploads/listings/' . $img['image_path']);
				$stmt = $db->prepare("DELETE FROM listing_images WHERE image_id = ? AND listing_id = ?");
				$stmt->execute([$image_id, $listing_id]);
				// If deleted image was primary, set another as primary
				if ($img['is_primary']) {
					$stmt = $db->prepare("SELECT image_id FROM listing_images WHERE listing_id = ? ORDER BY image_id ASC LIMIT 1");
					$stmt->execute([$listing_id]);
					$next = $stmt->fetch();
					if ($next) {
						$db->prepare("UPDATE listing_images SET is_primary = 1 WHERE image_id = ?")->execute([$next['image_id']]);
					}
				}
				$message = 'Image deleted.';
				$message_type = 'success';
			}
		}
	} elseif ($action === 'set_primary') {
		$image_id = intval($_POST['image_id'] ?? 0);
		if ($image_id) {
			// Ensure image belongs to listing
			$stmt = $db->prepare("SELECT image_id FROM listing_images WHERE image_id = ? AND listing_id = ?");
			$stmt->execute([$image_id, $listing_id]);
			if ($stmt->fetch()) {
				$db->prepare("UPDATE listing_images SET is_primary = 0 WHERE listing_id = ?")->execute([$listing_id]);
				$db->prepare("UPDATE listing_images SET is_primary = 1 WHERE image_id = ? AND listing_id = ?")->execute([$image_id, $listing_id]);
				$message = 'Primary image updated.';
				$message_type = 'success';
			}
		}
	}
}

// Categories
$stmt = $db->query("SELECT * FROM gear_categories ORDER BY category_name");
$categories = $stmt->fetchAll();

// Images
$stmt = $db->prepare("SELECT image_id, image_path, is_primary FROM listing_images WHERE listing_id = ? ORDER BY is_primary DESC, image_id ASC");
$stmt->execute([$listing_id]);
$images = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Edit Listing - AEROZONE</title>
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
					<h1 class="h2"><i class="fas fa-edit me-2"></i>Edit Listing</h1>
					<div class="btn-toolbar mb-2 mb-md-0">
						<a href="view-listing.php?id=<?php echo $listing_id; ?>" class="btn btn-outline-secondary">
							<i class="fas fa-arrow-left me-1"></i>Back to Listing
						</a>
					</div>
				</div>

				<?php if (!empty($message)): ?>
					<div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
						<?php echo htmlspecialchars($message); ?>
						<button type="button" class="btn-close" data-bs-dismiss="alert"></button>
					</div>
				<?php endif; ?>

				<div class="row">
					<div class="col-lg-8">
						<div class="card">
							<div class="card-header"><h5 class="mb-0">Listing Details</h5></div>
							<div class="card-body">
								<form method="POST">
									<input type="hidden" name="action" value="update_details">
									<div class="row">
										<div class="col-md-8">
											<div class="mb-3">
												<label class="form-label">Title</label>
												<input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($listing['title']); ?>" required>
											</div>
										</div>
										<div class="col-md-4">
											<div class="mb-3">
												<label class="form-label">Category</label>
												<select name="category_id" class="form-select">
													<option value="">Select Category</option>
													<?php foreach ($categories as $category): ?>
														<option value="<?php echo $category['category_id']; ?>" <?php echo ((int)$listing['category_id'] === (int)$category['category_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['category_name']); ?></option>
													<?php endforeach; ?>
												</select>
											</div>
										</div>
									</div>

									<div class="mb-3">
										<label class="form-label">Description</label>
										<textarea name="description" class="form-control" rows="5" required><?php echo htmlspecialchars($listing['description']); ?></textarea>
									</div>

									<div class="row">
										<div class="col-md-4">
											<div class="mb-3">
												<label class="form-label">Listing Type</label>
												<select name="listing_type" class="form-select" onchange="togglePriceField()">
													<option value="sale" <?php echo ($listing['listing_type'] === 'sale') ? 'selected' : ''; ?>>For Sale</option>
													<option value="barter" <?php echo ($listing['listing_type'] === 'barter') ? 'selected' : ''; ?>>For Barter</option>
													<option value="both" <?php echo ($listing['listing_type'] === 'both') ? 'selected' : ''; ?>>Sale or Barter</option>
												</select>
											</div>
										</div>
										<div class="col-md-4">
											<div class="mb-3" id="price-field">
												<label class="form-label">Price (₱)</label>
												<input type="number" step="0.01" min="0" name="price" class="form-control" value="<?php echo htmlspecialchars($listing['price']); ?>">
											</div>
										</div>
										<div class="col-md-4">
											<div class="mb-3">
												<label class="form-label">Condition</label>
												<select name="condition_status" class="form-select">
													<?php foreach (['new','like_new','good','fair','poor'] as $c): ?>
														<option value="<?php echo $c; ?>" <?php echo ($listing['condition_status'] === $c) ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_',' ', $c)); ?></option>
													<?php endforeach; ?>
												</select>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-6">
											<div class="mb-3">
												<label class="form-label">Location</label>
												<input type="text" class="form-control" name="location" value="<?php echo htmlspecialchars($listing['location']); ?>" required>
											</div>
										</div>
										<div class="col-md-6">
											<div class="mb-3">
												<label class="form-label">Status</label>
												<select name="status" class="form-select">
													<?php foreach (['active','sold','bartered','inactive'] as $s): ?>
														<option value="<?php echo $s; ?>" <?php echo ($listing['status'] === $s) ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
													<?php endforeach; ?>
												</select>
											</div>
										</div>
									</div>

									<div class="d-flex gap-2">
										<button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i>Save Changes</button>
										<a href="view-listing.php?id=<?php echo $listing_id; ?>" class="btn btn-outline-secondary">Cancel</a>
									</div>
								</form>
							</div>
						</div>
					</div>

					<div class="col-lg-4">
						<div class="card">
							<div class="card-header"><h5 class="mb-0">Images</h5></div>
							<div class="card-body">
								<?php if (!empty($images)): ?>
									<div class="d-flex flex-wrap gap-2">
										<?php foreach ($images as $img): ?>
											<div class="position-relative border rounded p-2" style="width: 120px;">
												<img src="../../uploads/listings/<?php echo htmlspecialchars($img['image_path']); ?>" class="rounded mb-2" style="width: 100%; height: 100px; object-fit: cover;">
												<?php if ($img['is_primary']): ?><span class="badge bg-primary position-absolute top-0 start-0">Main</span><?php endif; ?>
												<form method="POST" class="d-grid gap-1">
													<input type="hidden" name="image_id" value="<?php echo $img['image_id']; ?>">
													<button name="action" value="set_primary" class="btn btn-sm btn-outline-primary" <?php echo $img['is_primary'] ? 'disabled' : ''; ?>>Set Primary</button>
													<button name="action" value="delete_image" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this image?')">Delete</button>
												</form>
											</div>
										<?php endforeach; ?>
									</div>
								<?php else: ?>
									<p class="text-muted">No images uploaded.</p>
								<?php endif; ?>

								<hr>
								<form method="POST" enctype="multipart/form-data" class="d-grid gap-2">
									<input type="hidden" name="action" value="upload_images">
									<label for="images" class="form-label">Add Images</label>
									<input type="file" class="form-control" id="images" name="images[]" accept="image/*" multiple>
									<button class="btn btn-sm btn-primary" type="submit"><i class="fas fa-upload me-1"></i>Upload</button>
								</form>
							</div>
						</div>
					</div>
				</div>
			</main>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
	<script>
		function togglePriceField() {
			const listingType = document.querySelector('select[name="listing_type"]').value;
			const priceField = document.getElementById('price-field');
			if (listingType === 'barter') {
				priceField.style.display = 'none';
			} else {
				priceField.style.display = 'block';
			}
		}
		document.addEventListener('DOMContentLoaded', togglePriceField);
	</script>
</body>
</html>


