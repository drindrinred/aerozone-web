<?php
require_once '../config/session.php';
require_once '../config/database.php';

if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

$user = getCurrentUser();
if (!$user || $user['role'] !== 'store_owner') {
    header('Location: ./index.php');
    exit();
}

$reason = null;
try {
    $db = getDB();
    $stmt = $db->prepare('SELECT registration_status, rejection_reason FROM store_owners WHERE user_id = ?');
    $stmt->execute([$user['user_id']]);
    $owner = $stmt->fetch();
    if ($owner) {
        if ($owner['registration_status'] !== 'rejected') {
            header('Location: ./index.php');
            exit();
        }
        $reason = $owner['rejection_reason'] ?? null;
    }
} catch (Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Rejected - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body p-5 text-center">
                        <div class="mb-3">
                            <i class="fas fa-times-circle text-danger" style="font-size: 2.5rem;"></i>
                        </div>
                        <h3 class="fw-bold mb-2">Your store owner application was rejected</h3>
                        <p class="text-muted">You can review the reason below and re-apply if the issues are resolved.</p>
                        <?php if (!empty($reason)): ?>
                            <div class="alert alert-danger text-start">
                                <strong>Reason provided:</strong>
                                <div class="mt-1"><?php echo nl2br(htmlspecialchars($reason)); ?></div>
                            </div>
                        <?php endif; ?>
                        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center mt-4">
                            <a href="./reapply.php" class="btn btn-outline-primary"><i class="fas fa-redo me-2"></i>Re-apply</a>
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


