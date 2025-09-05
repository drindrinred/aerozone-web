<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $price = floatval($_POST['price'] ?? 0);
    $listing_type = $_POST['listing_type'] ?? 'sale';
    $condition_status = $_POST['condition_status'] ?? 'good';
    $location = trim($_POST['location'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($title)) $errors[] = "Title is required";
    if (empty($description)) $errors[] = "Description is required";
    if ($listing_type === 'sale' && $price <= 0) $errors[] = "Price is required for sale listings";
    if (empty($location)) $errors[] = "Location is required";
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Insert listing
            $stmt = $db->prepare("
                INSERT INTO marketplace_listings (seller_id, title, description, category_id, price, listing_type, condition_status, location)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user['user_id'], $title, $description, 
                $category_id ?: null, $price ?: null, 
                $listing_type, $condition_status, $location
            ]);
            
            $listing_id = $db->lastInsertId();
            
            // Handle image uploads
            if (!empty($_FILES['images']['name'][0])) {
                $upload_dir = '../../uploads/listings/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $is_primary = true;
                foreach ($_FILES['images']['name'] as $key => $filename) {
                    if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                        
                        if (in_array($file_extension, $allowed_extensions)) {
                            $new_filename = $listing_id . '_' . time() . '_' . $key . '.' . $file_extension;
                            $upload_path = $upload_dir . $new_filename;
                            
                            if (move_uploaded_file($_FILES['images']['tmp_name'][$key], $upload_path)) {
                                $stmt = $db->prepare("
                                    INSERT INTO listing_images (listing_id, image_path, is_primary)
                                    VALUES (?, ?, ?)
                                ");
                                $stmt->execute([$listing_id, $new_filename, $is_primary]);
                                $is_primary = false; // Only first image is primary
                            }
                        }
                    }
                }
            }
            
            $db->commit();
            
            // Create notification for admins (optional)
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message)
                SELECT u.user_id, 'listing_update', 'New Marketplace Listing', 
                       CONCAT('New listing created: ', ?) 
                FROM users u 
                WHERE u.role = 'admin'
            ");
            $stmt->execute([$title]);
            
            $message = "Listing created successfully!";
            $message_type = "success";
            
            // Redirect to listing view
            header("Location: view-listing.php?id=" . $listing_id);
            exit();
            
        } catch (PDOException $e) {
            $db->rollBack();
            $message = "Error creating listing: " . $e->getMessage();
            $message_type = "danger";
        }
    } else {
        $message = implode(', ', $errors);
        $message_type = "danger";
    }
}

// Get categories
$stmt = $db->query("SELECT * FROM gear_categories ORDER BY category_name");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Listing - AEROZONE</title>
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
                        <i class="fas fa-plus me-2"></i>Create Listing
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Marketplace
                        </a>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Listing Details</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <div class="mb-3">
                                                <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="title" name="title" 
                                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                                       placeholder="Enter a descriptive title for your item" required>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="category_id" class="form-label">Category</label>
                                                <select class="form-select" id="category_id" name="category_id">
                                                    <option value="">Select Category</option>
                                                    <?php foreach ($categories as $category): ?>
                                                        <option value="<?php echo $category['category_id']; ?>"
                                                                <?php echo (($_POST['category_id'] ?? '') == $category['category_id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="description" name="description" rows="5" 
                                                  placeholder="Provide detailed information about your item, including condition, specifications, and any other relevant details" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="listing_type" class="form-label">Listing Type <span class="text-danger">*</span></label>
                                                <select class="form-select" id="listing_type" name="listing_type" onchange="togglePriceField()">
                                                    <option value="sale" <?php echo (($_POST['listing_type'] ?? 'sale') === 'sale') ? 'selected' : ''; ?>>For Sale</option>
                                                    <option value="barter" <?php echo (($_POST['listing_type'] ?? '') === 'barter') ? 'selected' : ''; ?>>For Barter</option>
                                                    <option value="both" <?php echo (($_POST['listing_type'] ?? '') === 'both') ? 'selected' : ''; ?>>Sale or Barter</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3" id="price-field">
                                                <label for="price" class="form-label">Price (₱)</label>
                                                <input type="number" class="form-control" id="price" name="price" 
                                                       value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>" 
                                                       step="0.01" min="0" placeholder="0.00">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="mb-3">
                                                <label for="condition_status" class="form-label">Condition <span class="text-danger">*</span></label>
                                                <select class="form-select" id="condition_status" name="condition_status">
                                                    <option value="new" <?php echo (($_POST['condition_status'] ?? '') === 'new') ? 'selected' : ''; ?>>New</option>
                                                    <option value="like_new" <?php echo (($_POST['condition_status'] ?? '') === 'like_new') ? 'selected' : ''; ?>>Like New</option>
                                                    <option value="good" <?php echo (($_POST['condition_status'] ?? 'good') === 'good') ? 'selected' : ''; ?>>Good</option>
                                                    <option value="fair" <?php echo (($_POST['condition_status'] ?? '') === 'fair') ? 'selected' : ''; ?>>Fair</option>
                                                    <option value="poor" <?php echo (($_POST['condition_status'] ?? '') === 'poor') ? 'selected' : ''; ?>>Poor</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="location" class="form-label">Location <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="location" name="location" 
                                               value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" 
                                               placeholder="City, Province or specific area" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="images" class="form-label">Images</label>
                                        <input type="file" class="form-control" id="images" name="images[]" 
                                               accept="image/*" multiple onchange="previewImages(this)">
                                        <div class="form-text">You can upload multiple images. First image will be the main image.</div>
                                        <div id="image-preview" class="mt-3"></div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-plus me-1"></i>Create Listing
                                        </button>
                                        <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Listing Tips
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Use clear, descriptive titles
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Include detailed descriptions
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Upload high-quality photos
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Set fair, competitive prices
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Be honest about condition
                                    </li>
                                    <li class="mb-0">
                                        <i class="fas fa-check text-success me-2"></i>
                                        Respond promptly to inquiries
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-shield-alt me-2"></i>Safety Guidelines
                                </h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled small">
                                    <li class="mb-2">• Meet in public places for transactions</li>
                                    <li class="mb-2">• Inspect items before purchasing</li>
                                    <li class="mb-2">• Use secure payment methods</li>
                                    <li class="mb-2">• Report suspicious activity</li>
                                    <li class="mb-0">• Follow community guidelines</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function togglePriceField() {
            const listingType = document.getElementById('listing_type').value;
            const priceField = document.getElementById('price-field');
            const priceInput = document.getElementById('price');
            
            if (listingType === 'barter') {
                priceField.style.display = 'none';
                priceInput.required = false;
            } else {
                priceField.style.display = 'block';
                priceInput.required = (listingType === 'sale');
            }
        }

        function previewImages(input) {
            const preview = document.getElementById('image-preview');
            preview.innerHTML = '';
            
            if (input.files) {
                Array.from(input.files).forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const div = document.createElement('div');
                        div.className = 'position-relative d-inline-block me-2 mb-2';
                        div.innerHTML = `
                            <img src="${e.target.result}" class="img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                            ${index === 0 ? '<span class="position-absolute top-0 start-0 badge bg-primary">Main</span>' : ''}
                        `;
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            }
        }

        // Initialize price field visibility
        document.addEventListener('DOMContentLoaded', function() {
            togglePriceField();
        });
    </script>
</body>
</html>