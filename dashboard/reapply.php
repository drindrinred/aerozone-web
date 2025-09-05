<?php
require_once '../config/session.php';
require_once '../config/database.php';

requireLogin();
$user = getCurrentUser();
if ($user['role'] !== 'store_owner') {
    header('Location: ./index.php');
    exit();
}

$db = getDB();
$message = '';
$message_type = '';

// Load existing data
$store = null;
try {
    $stmt = $db->prepare("SELECT so.*, u.full_name, u.email FROM store_owners so JOIN users u ON so.user_id = u.user_id WHERE so.user_id = ?");
    $stmt->execute([$user['user_id']]);
    $store = $stmt->fetch();
    if (!$store) {
        header('Location: ./index.php');
        exit();
    }
} catch (Throwable $e) {
    $store = null;
}

// Build business-type specific instructions
$bizType = $store['business_type'] ?? '';
$bizTypeLabel = $bizType ? ucfirst(str_replace('_', ' ', $bizType)) : 'Not set';
$docInstructions = '';
switch ($bizType) {
    case 'sole_proprietor':
        $docInstructions = '<ul class="mb-0"><li>BIR Certificate of Registration or Mayor\'s/Business Permit</li><li>DTI Business Name Registration (if applicable)</li><li>Valid Government ID of the owner</li></ul>';
        break;
    case 'partnership':
        $docInstructions = '<ul class="mb-0"><li>SEC Certificate of Partnership</li><li>Articles of Partnership</li><li>BIR Certificate of Registration</li><li>Valid Government ID of managing partner/authorized representative</li></ul>';
        break;
    case 'corporation':
        $docInstructions = '<ul class="mb-0"><li>SEC Certificate of Incorporation</li><li>Articles/By-laws</li><li>Board Resolution/Secretary\'s Certificate authorizing representative</li><li>BIR Certificate of Registration</li><li>Valid Government ID of authorized representative</li></ul>';
        break;
    default:
        $docInstructions = '<ul class="mb-0"><li>BIR Certificate of Registration or Mayor\'s/Business Permit</li><li>Valid Government ID of owner/representative</li></ul>';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        // Update user basic info (optional)
        $full_name = trim($_POST['full_name'] ?? $store['full_name']);
        $email = trim($_POST['email'] ?? $store['email']);
        $stmt = $db->prepare('UPDATE users SET full_name = ?, email = ? WHERE user_id = ?');
        $stmt->execute([$full_name, $email, $user['user_id']]);

        // Update store details
        $business_name = trim($_POST['business_name'] ?? $store['business_name']);
        $business_address = trim($_POST['business_address'] ?? $store['business_address']);
        $business_type = $_POST['business_type'] ?? $store['business_type'];
        $corp_other_requirements = trim($_POST['corp_other_requirements'] ?? ($store['corp_other_requirements'] ?? ''));

        $stmt = $db->prepare('UPDATE store_owners SET business_name = ?, business_address = ?, business_type = ?, corp_other_requirements = ? WHERE user_id = ?');
        $stmt->execute([$business_name, $business_address, $business_type ?: null, $corp_other_requirements ?: null, $user['user_id']]);

        // Handle optional document uploads
        $upload_dir = '../uploads/documents/';
        if (!is_dir($upload_dir)) { @mkdir($upload_dir, 0755, true); }

        $updateDoc = function($field, $prefix) use ($user, $upload_dir, $db) {
            if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) { return; }
            $ext = pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION);
            $filename = $prefix . '_' . $user['user_id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES[$field]['tmp_name'], $upload_dir . $filename)) {
                $stmt = $db->prepare("UPDATE store_owners SET {$field}_path = ? WHERE user_id = ?");
                $stmt->execute([$filename, $user['user_id']]);
            }
        };

        $updateDoc('bir_document', 'bir');
        $updateDoc('valid_id', 'id');
        $updateDoc('dti_document', 'dti');
        $updateDoc('sec_certificate_partnership', 'sec_partnership');
        $updateDoc('articles_partnership', 'articles_partnership');
        $updateDoc('sec_certificate_incorporation', 'sec_incorporation');
        $updateDoc('articles_bylaws', 'articles_bylaws');
        $updateDoc('board_resolution', 'board_resolution');

        // Set status to pending on re-apply and clear rejection fields
        $stmt = $db->prepare("UPDATE store_owners SET registration_status = 'pending', rejected_by = NULL, rejected_at = NULL, rejection_reason = NULL WHERE user_id = ?");
        $stmt->execute([$user['user_id']]);

        $db->commit();
        header('Location: ./pending.php');
        exit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) { $db->rollBack(); }
        $message = 'Re-apply failed. Please try again.';
        $message_type = 'danger';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Re-apply Store Owner - AEROZONE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h3 class="fw-bold mb-3 text-center"><i class="fas fa-redo me-2"></i>Re-apply as Store Owner</h3>
                        <p class="text-muted text-center">Update your details and optionally resubmit documents before sending for review.</p>
                        <div class="alert alert-info">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-info-circle me-2 mt-1"></i>
                                <div>
                                    <div><strong>Instructions:</strong> Upload only the documents that changed or were requested. Leave a field empty to keep your previously submitted file. Accepted types: PDF/JPG/PNG (max 5MB each).</div>
                                    <div class="mt-2"><strong>Current business type:</strong> <?php echo htmlspecialchars($bizTypeLabel); ?></div>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $message_type; ?>"><?php echo htmlspecialchars($message); ?></div>
                        <?php endif; ?>

                        <form method="POST" action="" enctype="multipart/form-data">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Owner Full Name</label>
                                    <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($store['full_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($store['email'] ?? ''); ?>">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Business Name</label>
                                    <input type="text" name="business_name" class="form-control" value="<?php echo htmlspecialchars($store['business_name'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Business Type</label>
                                    <select class="form-select" name="business_type">
                                        <option value="" <?php echo empty($store['business_type']) ? 'selected' : ''; ?>>Select business type</option>
                                        <option value="sole_proprietor" <?php echo (($store['business_type'] ?? '') === 'sole_proprietor') ? 'selected' : ''; ?>>Sole Proprietor</option>
                                        <option value="partnership" <?php echo (($store['business_type'] ?? '') === 'partnership') ? 'selected' : ''; ?>>Partnership</option>
                                        <option value="corporation" <?php echo (($store['business_type'] ?? '') === 'corporation') ? 'selected' : ''; ?>>Corporation</option>
                                    </select>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Business Address</label>
                                    <textarea name="business_address" class="form-control" rows="2"><?php echo htmlspecialchars($store['business_address'] ?? ''); ?></textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Other Requirements / Notes (optional)</label>
                                    <textarea name="corp_other_requirements" class="form-control" rows="2"><?php echo htmlspecialchars($store['corp_other_requirements'] ?? ''); ?></textarea>
                                </div>

                                <hr class="mt-3">
                                <h6 class="text-muted">Optional Documents</h6>
                                <div class="alert alert-secondary small">
                                    <div class="mb-1"><strong>Recommended for <?php echo htmlspecialchars($bizTypeLabel); ?>:</strong></div>
                                    <?php echo $docInstructions; ?>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">BIR Document</label>
                                    <input type="file" name="bir_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Valid Government ID</label>
                                    <input type="file" name="valid_id" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">DTI Business Name Registration</label>
                                    <input type="file" name="dti_document" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">SEC Certificate of Partnership</label>
                                    <input type="file" name="sec_certificate_partnership" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Articles of Partnership</label>
                                    <input type="file" name="articles_partnership" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">SEC Certificate of Incorporation</label>
                                    <input type="file" name="sec_certificate_incorporation" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Articles/By-laws</label>
                                    <input type="file" name="articles_bylaws" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Board Resolution/Secretary's Certificate</label>
                                    <input type="file" name="board_resolution" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <a href="./rejected.php" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Submit for Review</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
