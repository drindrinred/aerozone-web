<?php
require_once '../config/session.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Only for store owners; others go to dashboard
$user = getCurrentUser();
if (!$user || $user['role'] !== 'store_owner') {
    header('Location: ./index.php');
    exit();
}

// Get store owner details and documents
$store = null;
try {
    $db = getDB();
    $stmt = $db->prepare('SELECT so.*, u.full_name, u.email FROM store_owners so JOIN users u ON so.user_id = u.user_id WHERE so.user_id = ?');
    $stmt->execute([$user['user_id']]);
    $store = $stmt->fetch();
    if ($store && $store['registration_status'] !== 'pending') {
        header('Location: ./index.php');
        exit();
    }
} catch (Throwable $e) {
    // If DB error, still show the page rather than blocking
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Pending - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="card shadow">
                    <div class="card-body p-5 text-center">
                        <div class="mb-3">
                            <i class="fas fa-hourglass-half text-warning" style="font-size: 2.5rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-2">Your store owner account is pending</h3>
                        <p class="text-muted">Our administrators are reviewing your application. You'll receive a notification once it's approved.</p>
                        
                        <?php if ($store): ?>
                        <div class="row g-4 mt-4">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Business Information</h6>
                                    </div>
                                    <div class="card-body text-start">
                                        <p><strong>Business Name:</strong> <?php echo htmlspecialchars($store['business_name']); ?></p>
                                        <p><strong>Business Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $store['business_type'] ?? 'N/A')); ?></p>
                                        <p><strong>Address:</strong> <?php echo htmlspecialchars($store['business_address']); ?></p>
                                        <p><strong>Owner:</strong> <?php echo htmlspecialchars($store['full_name']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($store['email']); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Submitted Documents</h6>
                                    </div>
                                    <div class="card-body text-start">
                                        <?php
                                        $documents = [
                                            'BIR Document' => $store['bir_document_path'],
                                            'Valid Government ID' => $store['valid_id_path'],
                                            'DTI Business Name Registration' => $store['dti_document_path'],
                                            'SEC Certificate of Partnership' => $store['sec_certificate_partnership_path'],
                                            'Articles of Partnership' => $store['articles_partnership_path'],
                                            'SEC Certificate of Incorporation' => $store['sec_certificate_incorporation_path'],
                                            'Articles/By-laws' => $store['articles_bylaws_path'],
                                            'Board Resolution/Secretary\'s Certificate' => $store['board_resolution_path']
                                        ];
                                        
                                        $hasDocuments = false;
                                        foreach ($documents as $docName => $docPath):
                                            if (!empty($docPath)):
                                                $hasDocuments = true;
                                        ?>
                                            <div class="mb-2">
                                                <a href="../uploads/documents/<?php echo htmlspecialchars($docPath); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye me-1"></i><?php echo htmlspecialchars($docName); ?>
                                                </a>
                                            </div>
                                        <?php 
                                            endif;
                                        endforeach;
                                        
                                        if (!$hasDocuments):
                                        ?>
                                            <p class="text-muted small">No documents uploaded yet.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center mt-4">
                            <a href="../auth/logout.php" class="btn btn-outline-danger"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>


