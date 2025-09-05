<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireRole('admin');

$user = getCurrentUser();
$db = getDB();

$message = '';
$message_type = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'update_content') {
                $stmt = $db->prepare("UPDATE content_pages SET title = ?, content = ?, updated_at = NOW() WHERE page_key = ?");
                $stmt->execute([
                    $_POST['title'],
                    $_POST['content'],
                    $_POST['page_key']
                ]);
                $message = 'Content updated successfully!';
                $message_type = 'success';
            } elseif ($_POST['action'] === 'add_requirement') {
                $stmt = $db->prepare("INSERT INTO gear_requirements (item_name, category, is_mandatory, description, display_order) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['item_name'],
                    $_POST['category'],
                    isset($_POST['is_mandatory']) ? 1 : 0,
                    $_POST['description'],
                    $_POST['display_order'] ?: 0
                ]);
                $message = 'Gear requirement added successfully!';
                $message_type = 'success';
            } elseif ($_POST['action'] === 'update_requirement') {
                $stmt = $db->prepare("UPDATE gear_requirements SET item_name = ?, category = ?, is_mandatory = ?, description = ?, display_order = ? WHERE requirement_id = ?");
                $stmt->execute([
                    $_POST['item_name'],
                    $_POST['category'],
                    isset($_POST['is_mandatory']) ? 1 : 0,
                    $_POST['description'],
                    $_POST['display_order'] ?: 0,
                    $_POST['requirement_id']
                ]);
                $message = 'Gear requirement updated successfully!';
                $message_type = 'success';
            } elseif ($_POST['action'] === 'delete_requirement') {
                $stmt = $db->prepare("UPDATE gear_requirements SET is_active = 0 WHERE requirement_id = ?");
                $stmt->execute([$_POST['requirement_id']]);
                $message = 'Gear requirement removed successfully!';
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
            $message_type = 'danger';
        }
    }
}

// Get content pages
$stmt = $db->query("SELECT * FROM content_pages WHERE is_active = 1 ORDER BY page_key");
$content_pages = $stmt->fetchAll();

// Get gear requirements
$stmt = $db->query("SELECT * FROM gear_requirements WHERE is_active = 1 ORDER BY display_order ASC, item_name ASC");
$gear_requirements = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management - AEROZONE</title>
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
                        <i class="fas fa-edit me-2"></i>Content Management
                    </h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Navigation Tabs -->
                <ul class="nav nav-tabs mb-4" id="contentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pages-tab" data-bs-toggle="tab" data-bs-target="#pages" type="button" role="tab">
                            <i class="fas fa-file-alt me-2"></i>Content Pages
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="requirements-tab" data-bs-toggle="tab" data-bs-target="#requirements" type="button" role="tab">
                            <i class="fas fa-list-check me-2"></i>Gear Requirements
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="contentTabsContent">
                    <!-- Content Pages Tab -->
                    <div class="tab-pane fade show active" id="pages" role="tabpanel">
                        <div class="row g-4">
                            <?php foreach ($content_pages as $page): ?>
                                <div class="col-lg-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="card-title mb-0">
                                                <?php echo htmlspecialchars($page['title']); ?>
                                            </h5>
                                            <small class="text-muted">Key: <?php echo htmlspecialchars($page['page_key']); ?></small>
                                        </div>
                                        <div class="card-body">
                                            <form method="POST" action="">
                                                <input type="hidden" name="action" value="update_content">
                                                <input type="hidden" name="page_key" value="<?php echo htmlspecialchars($page['page_key']); ?>">
                                                
                                                <div class="mb-3">
                                                    <label for="title_<?php echo $page['page_id']; ?>" class="form-label">Title</label>
                                                    <input type="text" class="form-control" id="title_<?php echo $page['page_id']; ?>" 
                                                           name="title" value="<?php echo htmlspecialchars($page['title']); ?>" required>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="content_<?php echo $page['page_id']; ?>" class="form-label">Content</label>
                                                    <textarea class="form-control" id="content_<?php echo $page['page_id']; ?>" 
                                                              name="content" rows="8" required><?php echo htmlspecialchars($page['content']); ?></textarea>
                                                </div>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        Last updated: <?php echo date('M j, Y g:i A', strtotime($page['updated_at'])); ?>
                                                    </small>
                                                    <button type="submit" class="btn btn-primary">
                                                        <i class="fas fa-save me-1"></i>Update
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Gear Requirements Tab -->
                    <div class="tab-pane fade" id="requirements" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5>Manage Gear Requirements</h5>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRequirementModal">
                                <i class="fas fa-plus me-1"></i>Add Requirement
                            </button>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Order</th>
                                                <th>Item</th>
                                                <th>Category</th>
                                                <th>Type</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($gear_requirements as $requirement): ?>
                                                <tr>
                                                    <td><?php echo $requirement['display_order']; ?></td>
                                                    <td><strong><?php echo htmlspecialchars($requirement['item_name']); ?></strong></td>
                                                    <td>
                                                        <span class="badge bg-secondary">
                                                            <?php echo htmlspecialchars($requirement['category'] ?: 'General'); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $requirement['is_mandatory'] ? 'danger' : 'secondary'; ?>">
                                                            <?php echo $requirement['is_mandatory'] ? 'Required' : 'Optional'; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <small><?php echo htmlspecialchars(substr($requirement['description'], 0, 50)); ?>
                                                        <?php echo strlen($requirement['description']) > 50 ? '...' : ''; ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-outline-primary" 
                                                                    onclick="editRequirement(<?php echo htmlspecialchars(json_encode($requirement)); ?>)">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    onclick="deleteRequirement(<?php echo $requirement['requirement_id']; ?>, '<?php echo htmlspecialchars($requirement['item_name']); ?>')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Requirement Modal -->
    <div class="modal fade" id="addRequirementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Gear Requirement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_requirement">
                        
                        <div class="mb-3">
                            <label for="item_name" class="form-label">Item Name *</label>
                            <input type="text" class="form-control" id="item_name" name="item_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category">
                                <option value="">General</option>
                                <option value="Safety">Safety</option>
                                <option value="Weapons">Weapons</option>
                                <option value="Ammunition">Ammunition</option>
                                <option value="Game Equipment">Game Equipment</option>
                                <option value="Clothing">Clothing</option>
                                <option value="Personal">Personal</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_mandatory" name="is_mandatory" checked>
                                <label class="form-check-label" for="is_mandatory">
                                    Required Item
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="display_order" name="display_order" min="0" value="0">
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Requirement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Requirement Modal -->
    <div class="modal fade" id="editRequirementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Gear Requirement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="editRequirementForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_requirement">
                        <input type="hidden" name="requirement_id" id="edit_requirement_id">
                        
                        <div class="mb-3">
                            <label for="edit_item_name" class="form-label">Item Name *</label>
                            <input type="text" class="form-control" id="edit_item_name" name="item_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_category" class="form-label">Category</label>
                            <select class="form-select" id="edit_category" name="category">
                                <option value="">General</option>
                                <option value="Safety">Safety</option>
                                <option value="Weapons">Weapons</option>
                                <option value="Ammunition">Ammunition</option>
                                <option value="Game Equipment">Game Equipment</option>
                                <option value="Clothing">Clothing</option>
                                <option value="Personal">Personal</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_mandatory" name="is_mandatory">
                                <label class="form-check-label" for="edit_is_mandatory">
                                    Required Item
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_display_order" class="form-label">Display Order</label>
                            <input type="number" class="form-control" id="edit_display_order" name="display_order" min="0">
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Requirement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        function editRequirement(requirement) {
            document.getElementById('edit_requirement_id').value = requirement.requirement_id;
            document.getElementById('edit_item_name').value = requirement.item_name;
            document.getElementById('edit_category').value = requirement.category || '';
            document.getElementById('edit_is_mandatory').checked = requirement.is_mandatory == 1;
            document.getElementById('edit_display_order').value = requirement.display_order;
            document.getElementById('edit_description').value = requirement.description || '';
            
            new bootstrap.Modal(document.getElementById('editRequirementModal')).show();
        }

        function deleteRequirement(requirementId, itemName) {
            if (confirm(`Are you sure you want to remove "${itemName}" from the requirements list?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_requirement">
                    <input type="hidden" name="requirement_id" value="${requirementId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
