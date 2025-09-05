<?php
require_once '../../config/database.php';
require_once '../../config/session.php';

requireLogin();

$user = getCurrentUser();
$db = getDB();

// Get gear requirements
$stmt = $db->query("
    SELECT * FROM gear_requirements 
    WHERE is_active = 1 
    ORDER BY display_order ASC, item_name ASC
");
$requirements = $stmt->fetchAll();

// Group requirements by category
$grouped_requirements = [];
foreach ($requirements as $requirement) {
    $category = $requirement['category'] ?: 'General';
    if (!isset($grouped_requirements[$category])) {
        $grouped_requirements[$category] = [];
    }
    $grouped_requirements[$category][] = $requirement;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gear Requirements - AEROZONE</title>
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
                        <i class="fas fa-list-check me-2"></i>Pre-Game Gear Requirements
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-outline-primary" onclick="printChecklist()">
                            <i class="fas fa-print me-1"></i>Print Checklist
                        </button>
                    </div>
                </div>

                <!-- Introduction -->
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Important:</strong> Please ensure you have all required gear before participating in any airsoft game. 
                    This checklist helps ensure safety and compliance with game rules.
                </div>

                <!-- Requirements by Category -->
                <?php foreach ($grouped_requirements as $category => $category_requirements): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-<?php 
                                    echo $category === 'Safety' ? 'shield-alt' : 
                                        ($category === 'Weapons' ? 'crosshairs' : 
                                        ($category === 'Ammunition' ? 'bullets' : 
                                        ($category === 'Game Equipment' ? 'gamepad' : 
                                        ($category === 'Clothing' ? 'tshirt' : 'box')))); 
                                ?> me-2"></i>
                                <?php echo htmlspecialchars($category); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <?php foreach ($category_requirements as $requirement): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="requirement-item p-3 border rounded h-100">
                                            <div class="d-flex align-items-start">
                                                <div class="form-check me-3">
                                                    <input class="form-check-input" type="checkbox" 
                                                           id="req_<?php echo $requirement['requirement_id']; ?>">
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($requirement['item_name']); ?>
                                                        <?php if ($requirement['is_mandatory']): ?>
                                                            <span class="badge bg-danger ms-1">Required</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary ms-1">Optional</span>
                                                        <?php endif; ?>
                                                    </h6>
                                                    <?php if ($requirement['description']): ?>
                                                        <p class="mb-0 small text-muted">
                                                            <?php echo htmlspecialchars($requirement['description']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Quick Summary -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-clipboard-check me-2"></i>Quick Summary
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h6 class="text-danger">
                                    <i class="fas fa-exclamation-triangle me-2"></i>Mandatory Items
                                </h6>
                                <ul class="list-unstyled">
                                    <?php foreach ($requirements as $requirement): ?>
                                        <?php if ($requirement['is_mandatory']): ?>
                                            <li class="mb-1">
                                                <i class="fas fa-check text-success me-2"></i>
                                                <?php echo htmlspecialchars($requirement['item_name']); ?>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-secondary">
                                    <i class="fas fa-plus-circle me-2"></i>Recommended Items
                                </h6>
                                <ul class="list-unstyled">
                                    <?php foreach ($requirements as $requirement): ?>
                                        <?php if (!$requirement['is_mandatory']): ?>
                                            <li class="mb-1">
                                                <i class="fas fa-circle text-muted me-2" style="font-size: 0.5rem;"></i>
                                                <?php echo htmlspecialchars($requirement['item_name']); ?>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="mt-4 p-3 bg-light rounded">
                            <h6><i class="fas fa-lightbulb me-2"></i>Pro Tips:</h6>
                            <ul class="mb-0 small">
                                <li>Always bring extra batteries and BBs</li>
                                <li>Check your gear the night before the game</li>
                                <li>Ensure all safety equipment is in good condition</li>
                                <li>Bring water and snacks for longer games</li>
                                <li>Have a backup plan for essential items</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <?php if ($user['role'] === 'player'): ?>
                    <!-- Personal Inventory Check -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="fas fa-box me-2"></i>Check Against My Inventory
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Compare this checklist with your personal inventory to see what you have and what you might need.</p>
                            <a href="../inventory/index.php" class="btn btn-primary">
                                <i class="fas fa-box me-1"></i>View My Inventory
                            </a>
                            <button type="button" class="btn btn-outline-secondary ms-2" onclick="generatePersonalChecklist()">
                                <i class="fas fa-list me-1"></i>Generate Personal Checklist
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // Print checklist
        function printChecklist() {
            // Create a print-friendly version
            const printWindow = window.open('', '_blank');
            const checklistContent = document.querySelector('main').innerHTML;
            
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Airsoft Gear Requirements Checklist - AEROZONE</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        .card { border: 1px solid #ddd; margin-bottom: 20px; }
                        .card-header { background: #f8f9fa; padding: 10px; font-weight: bold; }
                        .card-body { padding: 15px; }
                        .requirement-item { margin-bottom: 10px; padding: 5px; border: 1px solid #eee; }
                        .badge { background: #6c757d; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; }
                        .bg-danger { background: #dc3545 !important; }
                        .text-muted { color: #6c757d !important; }
                        @media print {
                            .no-print { display: none !important; }
                        }
                    </style>
                </head>
                <body>
                    <h1>Airsoft Gear Requirements Checklist</h1>
                    <p><strong>Date:</strong> ${new Date().toLocaleDateString()}</p>
                    <hr>
                    ${checklistContent.replace(/class="btn[^"]*"/g, 'class="no-print"')}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            printWindow.print();
        }

        // Generate personal checklist
        function generatePersonalChecklist() {
            // This would integrate with the user's inventory
            showToast('Personal checklist feature coming soon!', 'info');
        }

        // Save checklist progress to localStorage
        function saveChecklistProgress() {
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            const progress = {};
            
            checkboxes.forEach(checkbox => {
                progress[checkbox.id] = checkbox.checked;
            });
            
            localStorage.setItem('aerozone_checklist_progress', JSON.stringify(progress));
        }

        // Load checklist progress from localStorage
        function loadChecklistProgress() {
            const saved = localStorage.getItem('aerozone_checklist_progress');
            if (saved) {
                const progress = JSON.parse(saved);
                
                Object.keys(progress).forEach(checkboxId => {
                    const checkbox = document.getElementById(checkboxId);
                    if (checkbox) {
                        checkbox.checked = progress[checkboxId];
                    }
                });
            }
        }

        // Initialize checklist
        document.addEventListener('DOMContentLoaded', function() {
            loadChecklistProgress();
            
            // Add event listeners to checkboxes
            const checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', saveChecklistProgress);
            });
        });
    </script>
</body>
</html>
