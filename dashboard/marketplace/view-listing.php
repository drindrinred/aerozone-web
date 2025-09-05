<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

$listing_id = intval($_GET['id'] ?? 0);

if (!$listing_id) {
    header('Location: index.php');
    exit();
}

try {
    // Get listing details
    $stmt = $db->prepare("
        SELECT ml.*, u.full_name as seller_name, u.user_id as seller_user_id,
               gc.category_name,
               (SELECT COUNT(*) FROM direct_messages WHERE listing_id = ml.listing_id) as message_count
        FROM marketplace_listings ml
        JOIN users u ON ml.seller_id = u.user_id
        LEFT JOIN gear_categories gc ON ml.category_id = gc.category_id
        WHERE ml.listing_id = ? AND ml.status = 'active'
    ");
    $stmt->execute([$listing_id]);
    $listing = $stmt->fetch();
    
    if (!$listing) {
        header('Location: index.php');
        exit();
    }
    
    // Update view count
    $stmt = $db->prepare("UPDATE marketplace_listings SET views_count = views_count + 1 WHERE listing_id = ?");
    $stmt->execute([$listing_id]);
    
    // Get listing images
    $stmt = $db->prepare("
        SELECT image_path, is_primary 
        FROM listing_images 
        WHERE listing_id = ? 
        ORDER BY is_primary DESC, image_id ASC
    ");
    $stmt->execute([$listing_id]);
    $images = $stmt->fetchAll();
    
    // Get seller's other listings
    $stmt = $db->prepare("
        SELECT listing_id, title, price, listing_type,
               (SELECT image_path FROM listing_images li WHERE li.listing_id = ml.listing_id AND li.is_primary = 1 LIMIT 1) as primary_image
        FROM marketplace_listings ml
        WHERE seller_id = ? AND listing_id != ? AND status = 'active'
        ORDER BY created_at DESC
        LIMIT 4
    ");
    $stmt->execute([$listing['seller_id'], $listing_id]);
    $other_listings = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error_message = "Error loading listing: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($listing['title']); ?> - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
    <style>
        .listing-image {
            max-height: 400px;
            object-fit: cover;
            cursor: pointer;
        }
        .thumbnail-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.2s;
        }
        .thumbnail-image:hover,
        .thumbnail-image.active {
            opacity: 1;
        }
        .price-tag {
            font-size: 1.5rem;
            font-weight: bold;
            color: #28a745;
        }
        .condition-badge {
            font-size: 0.9rem;
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
                        <i class="fas fa-eye me-2"></i>Listing Details
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to Marketplace
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
                    <div class="col-lg-8">
                        <!-- Main Listing Card -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <!-- Image Gallery -->
                                        <?php if (!empty($images)): ?>
                                            <div class="mb-3">
                                                <img id="mainImage" 
                                                     src="../../uploads/listings/<?php echo htmlspecialchars($images[0]['image_path']); ?>" 
                                                     class="img-fluid rounded listing-image w-100" 
                                                     alt="<?php echo htmlspecialchars($listing['title']); ?>"
                                                     onclick="openImageModal(this.src)">
                                            </div>
                                            <?php if (count($images) > 1): ?>
                                                <div class="d-flex gap-2 flex-wrap">
                                                    <?php foreach ($images as $index => $image): ?>
                                                        <img src="../../uploads/listings/<?php echo htmlspecialchars($image['image_path']); ?>" 
                                                             class="thumbnail-image rounded <?php echo $index === 0 ? 'active' : ''; ?>" 
                                                             onclick="changeMainImage(this)"
                                                             alt="Thumbnail <?php echo $index + 1; ?>">
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 300px;">
                                                <i class="fas fa-image fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <!-- Listing Info -->
                                        <h2 class="mb-3"><?php echo htmlspecialchars($listing['title']); ?></h2>
                                        
                                        <div class="mb-3">
                                            <span class="badge bg-<?php 
                                                echo $listing['listing_type'] === 'sale' ? 'success' : 
                                                    ($listing['listing_type'] === 'barter' ? 'warning' : 'info'); 
                                            ?> me-2">
                                                <?php echo ucfirst($listing['listing_type']); ?>
                                            </span>
                                            <span class="badge bg-secondary condition-badge">
                                                <?php echo ucfirst(str_replace('_', ' ', $listing['condition_status'])); ?>
                                            </span>
                                            <?php if ($listing['category_name']): ?>
                                                <span class="badge bg-light text-dark ms-2">
                                                    <?php echo htmlspecialchars($listing['category_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($listing['price'] && in_array($listing['listing_type'], ['sale', 'both'])): ?>
                                            <div class="mb-3">
                                                <span class="price-tag">₱<?php echo number_format($listing['price'], 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mb-3">
                                            <h6>Description</h6>
                                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($listing['description'])); ?></p>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <h6>Location</h6>
                                            <p class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($listing['location']); ?>
                                            </p>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <small class="text-muted">
                                                <i class="fas fa-eye me-1"></i><?php echo $listing['views_count']; ?> views •
                                                <i class="fas fa-clock me-1"></i>Posted <?php echo date('M j, Y', strtotime($listing['created_at'])); ?>
                                            </small>
                                        </div>
                                        
                                        <!-- Action Buttons -->
                                        <?php if ($listing['seller_id'] != $user['user_id']): ?>
                                            <div class="d-grid gap-2">
                                                <button class="btn btn-primary btn-lg" onclick="contactSeller()">
                                                    <i class="fas fa-envelope me-2"></i>Contact Seller
                                                </button>
                                                <button class="btn btn-outline-primary" onclick="reportListing()">
                                                    <i class="fas fa-flag me-2"></i>Report Listing
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle me-2"></i>This is your listing
                                            </div>
                                            <div class="d-grid gap-2">
                                                <a href="edit-listing.php?id=<?php echo $listing_id; ?>" class="btn btn-primary">
                                                    <i class="fas fa-edit me-2"></i>Edit Listing
                                                </a>
                                                <button class="btn btn-outline-danger" onclick="deleteListing()">
                                                    <i class="fas fa-trash me-2"></i>Delete Listing
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <!-- Seller Info -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user me-2"></i>Seller Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($listing['seller_name']); ?></h6>
                                        <small class="text-muted">Member since <?php echo date('M Y', strtotime($listing['created_at'])); ?></small>
                                    </div>
                                </div>
                                
                                <!-- Seller Stats (placeholder) -->
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <div class="border-end">
                                            <h6 class="mb-0">4.8</h6>
                                            <small class="text-muted">Rating</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <h6 class="mb-0"><?php echo count($other_listings) + 1; ?></h6>
                                        <small class="text-muted">Listings</small>
                                    </div>
                                </div>
                                
                                <?php if ($listing['seller_id'] != $user['user_id']): ?>
                                    <div class="d-grid">
                                        <button class="btn btn-outline-primary" onclick="viewSellerProfile()">
                                            <i class="fas fa-user me-1"></i>View Profile
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Other Listings from Seller -->
                        <?php if (!empty($other_listings)): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-list me-2"></i>More from this Seller
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($other_listings as $other): ?>
                                        <div class="d-flex mb-3 cursor-pointer" onclick="window.location.href='view-listing.php?id=<?php echo $other['listing_id']; ?>'">
                                            <div class="flex-shrink-0 me-3">
                                                <?php if ($other['primary_image']): ?>
                                                    <img src="../../uploads/listings/<?php echo htmlspecialchars($other['primary_image']); ?>" 
                                                         class="rounded" style="width: 60px; height: 60px; object-fit: cover;" 
                                                         alt="<?php echo htmlspecialchars($other['title']); ?>">
                                                <?php else: ?>
                                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                                        <i class="fas fa-image text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars(substr($other['title'], 0, 30)); ?><?php echo strlen($other['title']) > 30 ? '...' : ''; ?></h6>
                                                <?php if ($other['price'] && in_array($other['listing_type'], ['sale', 'both'])): ?>
                                                    <small class="text-success fw-bold">₱<?php echo number_format($other['price'], 2); ?></small>
                                                <?php else: ?>
                                                    <small class="text-warning">For Barter</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($other !== end($other_listings)): ?>
                                            <hr class="my-2">
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Image Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Image View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid" alt="Full size image">
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Message Modal -->
    <div class="modal fade" id="quickMessageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-envelope me-2"></i>Contact Seller
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Inquiry about:</strong> <?php echo htmlspecialchars($listing['title']); ?>
                    </div>
                    
                    <form id="quickMessageForm">
                        <div class="mb-3">
                            <label for="messageSubject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="messageSubject" 
                                   value="Inquiry about: <?php echo htmlspecialchars($listing['title']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="messageContent" class="form-label">Message</label>
                            <textarea class="form-control" id="messageContent" rows="6" 
                                      placeholder="Hi <?php echo htmlspecialchars($listing['seller_name']); ?>, I'm interested in your listing..." required></textarea>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-1"></i>Send Message
                            </button>
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function changeMainImage(thumbnail) {
            const mainImage = document.getElementById('mainImage');
            mainImage.src = thumbnail.src;
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail-image').forEach(img => img.classList.remove('active'));
            thumbnail.classList.add('active');
        }

        function openImageModal(imageSrc) {
            const modalImage = document.getElementById('modalImage');
            modalImage.src = imageSrc;
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            modal.show();
        }

        function contactSeller() {
            // Show the quick message modal
            const modal = new bootstrap.Modal(document.getElementById('quickMessageModal'));
            modal.show();
        }

        function viewSellerProfile() {
            showToast('Seller profiles feature coming soon!', 'info');
        }

        function reportListing() {
            if (confirm('Are you sure you want to report this listing?')) {
                showToast('Report submitted. Thank you for helping keep our community safe.', 'success');
            }
        }

        function deleteListing() {
            if (confirm('Are you sure you want to delete this listing? This action cannot be undone.')) {
                // Implement delete functionality
                showToast('Delete functionality will be implemented', 'info');
            }
        }

        // Handle quick message form submission
        document.getElementById('quickMessageForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const subject = document.getElementById('messageSubject').value.trim();
            const message = document.getElementById('messageContent').value.trim();
            
            if (!subject || !message) {
                showToast('Please fill in all fields', 'error');
                return;
            }
            
            // Disable submit button
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
            
            // Send message via AJAX
            fetch('../messages/send-quick-message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    receiver_id: <?php echo $listing['seller_user_id']; ?>,
                    listing_id: <?php echo $listing_id; ?>,
                    subject: subject,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Message sent successfully!', 'success');
                    // Close modal and reset form
                    bootstrap.Modal.getInstance(document.getElementById('quickMessageModal')).hide();
                    document.getElementById('quickMessageForm').reset();
                    document.getElementById('messageSubject').value = 'Inquiry about: <?php echo htmlspecialchars($listing['title']); ?>';
                } else {
                    showToast('Error sending message: ' + data.error, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error sending message', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    </script>
</body>
</html>