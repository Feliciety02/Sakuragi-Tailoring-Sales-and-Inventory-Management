<?php
session_start();
require_once __DIR__ . '/../../../../config/database.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../includes/auth_check.php';

$pageTitle = 'AQL Lot Inspection';

$lot_inspection_id = (int)($_GET['lot_inspection_id'] ?? 0);
if (!$lot_inspection_id) {
    header('Location: aql_dashboard.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT li.*, o.order_id, o.total_price, o.order_date,
           u.full_name AS customer_name,
           (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) AS total_qty,
           ow.assigned_employee
    FROM qc_lot_inspections li
    JOIN orders o ON li.order_id = o.order_id
    JOIN order_workflow ow ON li.workflow_id = ow.workflow_id
    JOIN users u ON o.user_id = u.user_id
    WHERE li.lot_inspection_id = ?
");
$stmt->execute([$lot_inspection_id]);
$lot = $stmt->fetch();

if (!$lot) {
    header('Location: aql_dashboard.php');
    exit;
}

$acRe = getAQLAcceptReject($lot['aql_level'], $lot['sample_size']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inspection'])) {
    $critical_defects = (int)$_POST['critical_defects'];
    $major_defects = (int)$_POST['major_defects'];
    $minor_defects = (int)$_POST['minor_defects'];
    $notes = $_POST['notes'] ?? '';

    $verdict = getAQLVerdict($critical_defects, $major_defects, $minor_defects, $lot['aql_level'], $lot['sample_size']);

    try {
        $pdo->beginTransaction();

        // Update lot inspection
        $stmt = $pdo->prepare("
            UPDATE qc_lot_inspections
            SET critical_defects = ?, major_defects = ?, minor_defects = ?,
                verdict = ?, notes = ?, inspected_at = NOW()
            WHERE lot_inspection_id = ?
        ");
        $stmt->execute([$critical_defects, $major_defects, $minor_defects, $verdict, $notes, $lot_inspection_id]);

        // Update workflow stage
        $target_stage = $verdict === 'Passed' ? STAGE_PACKAGING : STAGE_REWORK;
        $stmt = $pdo->prepare("UPDATE order_workflow SET stage = ?, completed_at = IF(? = 'Passed', NOW(), NULL) WHERE workflow_id = ?");
        $stmt->execute([$target_stage, $verdict, $lot['workflow_id']]);

        // Log garment transitions
        $stmt = $pdo->prepare("SELECT order_detail_id FROM garment_tracking WHERE order_id = ? AND stage = ?");
        $stmt->execute([$lot['order_id'], STAGE_QUALITY_INSPECTION]);
        $items = $stmt->fetchAll();
        foreach ($items as $item) {
            $stmt = $pdo->prepare("UPDATE garment_tracking SET stage = ?, employee_id = ?, updated_at = NOW() WHERE order_detail_id = ?");
            $stmt->execute([$target_stage, $_SESSION['user_id'], $item['order_detail_id']]);

            $stmt = $pdo->prepare("INSERT INTO garment_log (order_detail_id, order_id, from_stage, to_stage, employee_id, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$item['order_detail_id'], $lot['order_id'], STAGE_QUALITY_INSPECTION, $target_stage, $_SESSION['user_id'], "AQL {$verdict}: {$critical_defects}C/{$major_defects}M/{$minor_defects}m"]);
        }

        if ($verdict === 'Failed') {
            $stmt = $pdo->prepare("INSERT INTO rework_log (order_id, triggered_by, from_stage, to_stage, reason, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$lot['order_id'], $_SESSION['user_id'], STAGE_QUALITY_INSPECTION, STAGE_REWORK, "AQL Failed", "AQL Lot #{$lot_inspection_id}: {$critical_defects}C/{$major_defects}M/{$minor_defects}m defects"]);
        }

        $pdo->commit();
        $success = "Lot #{$lot_inspection_id} verdict: {$verdict}.";
        // Refresh
        $stmt = $pdo->prepare("SELECT * FROM qc_lot_inspections WHERE lot_inspection_id = ?");
        $stmt->execute([$lot_inspection_id]);
        $lot = $stmt->fetch();
        $acRe = getAQLAcceptReject($lot['aql_level'], $lot['sample_size']);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to submit inspection: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - QC Inspector</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
    <style>
        .defect-input { width: 100px; text-align: center; font-size: 1.5rem; font-weight: 700; }
        .verdict-box { border-radius: 16px; padding: 2rem; text-align: center; }
        .verdict-pass { background: #d1fae5; color: #065f46; border: 2px solid #059669; }
        .verdict-fail { background: #fee2e2; color: #991b1b; border: 2px solid #dc2626; }
        .verdict-pending { background: #f8fafc; color: #64748b; border: 2px solid #cbd5e1; }
        .stat-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
        .stat-value { font-size: 1.75rem; font-weight: 700; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../../../includes/sidebar_qc_inspector.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../../../includes/topnav.php'; ?>
        <div class="content-container">
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="aql_dashboard.php" class="text-decoration-none text-muted small"><i class="fas fa-arrow-left me-1"></i>Back to AQL Dashboard</a>
                    <h4 class="fw-bold mb-0 mt-1">AQL Lot Inspection #<?= $lot_inspection_id ?></h4>
                </div>
                <span class="badge bg-light text-dark px-3 py-2 fs-6">Order #<?= $lot['order_id'] ?> | <?= htmlspecialchars($lot['customer_name']) ?></span>
            </div>

            <!-- Lot Summary -->
            <div class="row g-3 mb-4">
                <div class="col-3">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-body text-center">
                            <div class="stat-label">Lot Size</div>
                            <div class="stat-value"><?= $lot['lot_size'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-body text-center">
                            <div class="stat-label">Sample Size</div>
                            <div class="stat-value"><?= $lot['sample_size'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-body text-center">
                            <div class="stat-label">AQL</div>
                            <div class="stat-value"><?= $lot['aql_level'] ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-3">
                    <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                        <div class="card-body text-center">
                            <div class="stat-label">Accept/Reject</div>
                            <div class="stat-value"><?= $acRe[0] ?>/<?= $acRe[1] ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($lot['verdict'] === 'Pending'): ?>
                <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                    <div class="card-header bg-transparent border-bottom-0 pt-3 px-4">
                        <h5 class="fw-semibold mb-0"><i class="fas fa-clipboard-check me-2" style="color: #2563eb;"></i>Enter Defect Counts</h5>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="row g-4 mb-4">
                                <div class="col-md-4 text-center">
                                    <label class="form-label fw-semibold text-danger">Critical Defects</label>
                                    <input type="number" name="critical_defects" class="form-control defect-input mx-auto" value="0" min="0" id="criticalDefects" oninput="updateVerdict()">
                                    <div class="form-text">Zero tolerance</div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <label class="form-label fw-semibold" style="color: #d97706;">Major Defects</label>
                                    <input type="number" name="major_defects" class="form-control defect-input mx-auto" value="0" min="0" id="majorDefects" oninput="updateVerdict()">
                                    <div class="form-text">Ac: <?= $acRe[0] ?>, Re: <?= $acRe[1] ?></div>
                                </div>
                                <div class="col-md-4 text-center">
                                    <label class="form-label fw-semibold" style="color: #6366f1;">Minor Defects</label>
                                    <input type="number" name="minor_defects" class="form-control defect-input mx-auto" value="0" min="0" id="minorDefects" oninput="updateVerdict()">
                                    <div class="form-text">Looser threshold (AQL 4.0)</div>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-medium">Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Describe defects found..."></textarea>
                            </div>
                            <div id="verdictPreview" class="verdict-box verdict-pending mb-4">
                                <i class="fas fa-calculator fa-2x mb-2"></i>
                                <h5 class="fw-bold mb-1">Verdict Preview</h5>
                                <p class="mb-0" id="verdictText">Adjust defect counts to see verdict.</p>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="aql_dashboard.php" class="btn btn-light">Cancel</a>
                                <button type="submit" name="submit_inspection" class="btn btn-primary" onclick="this.form.querySelector('[name=critical_defects_store]').value=document.getElementById('criticalDefects').value;this.form.querySelector('[name=major_defects_store]').value=document.getElementById('majorDefects').value;this.form.querySelector('[name=minor_defects_store]').value=document.getElementById('minorDefects').value;">
                                    <i class="fas fa-check me-1"></i>Submit Inspection
                                </button>
                            </div>
                            <input type="hidden" name="critical_defects_store" value="0">
                            <input type="hidden" name="major_defects_store" value="0">
                            <input type="hidden" name="minor_defects_store" value="0">
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <?php $vc = $lot['verdict'] === 'Passed' ? 'verdict-pass' : 'verdict-fail'; $vi = $lot['verdict'] === 'Passed' ? 'fa-check-circle' : 'fa-times-circle'; ?>
                <div class="verdict-box <?= $vc ?> mb-4">
                    <i class="fas <?= $vi ?> fa-3x mb-2"></i>
                    <h3 class="fw-bold mb-1"><?= $lot['verdict'] ?></h3>
                    <p class="mb-1">Crit: <?= $lot['critical_defects'] ?> | Maj: <?= $lot['major_defects'] ?> | Min: <?= $lot['minor_defects'] ?></p>
                    <?php if ($lot['notes']): ?><p class="mb-0 small"><?= htmlspecialchars($lot['notes']) ?></p><?php endif; ?>
                </div>
                <a href="aql_dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back</a>
            <?php endif; ?>
        </div>
    </div>

    <?php
    $aql_level_js = $lot['aql_level'];
    $sample_size_js = $lot['sample_size'];
    $acRe_js = getAQLAcceptReject($aql_level_js, $sample_size_js);
    $ac = $acRe_js[0];
    $re = $acRe_js[1];
    $minorAcRe_js = getAQLAcceptReject('4.0', $sample_size_js);
    $minorAc = $minorAcRe_js[0];
    $minorRe = $minorAcRe_js[1];
    ?>
    <script>
        function updateVerdict() {
            const critical = parseInt(document.getElementById('criticalDefects').value) || 0;
            const major = parseInt(document.getElementById('majorDefects').value) || 0;
            const minor = parseInt(document.getElementById('minorDefects').value) || 0;
            const preview = document.getElementById('verdictPreview');
            const text = document.getElementById('verdictText');

            const majorAccept = <?= $ac ?>;
            const majorReject = <?= $re ?>;
            const minorAccept = <?= $minorAc ?>;
            const minorReject = <?= $minorRe ?>;

            let verdict, cls, icon;

            if (critical > 0) {
                verdict = 'Failed'; cls = 'verdict-fail'; icon = 'fa-times-circle';
                text.innerText = 'Failed: Critical defects found (zero tolerance).';
            } else if (major >= majorReject) {
                verdict = 'Failed'; cls = 'verdict-fail'; icon = 'fa-times-circle';
                text.innerText = `Failed: ${major} major defects exceeds reject of ${majorReject}.`;
            } else if (minor >= minorReject) {
                verdict = 'Failed'; cls = 'verdict-fail'; icon = 'fa-times-circle';
                text.innerText = `Failed: ${minor} minor defects exceeds reject of ${minorReject}.`;
            } else {
                verdict = 'Passed'; cls = 'verdict-pass'; icon = 'fa-check-circle';
                text.innerText = `Passed: ${critical === 0 ? 'no critical' : ''} major ${major}/${majorAccept} minor ${minor}/${minorAccept}.`;
            }

            preview.className = `verdict-box ${cls} mb-4`;
            preview.querySelector('i').className = `fas ${icon} fa-2x mb-2`;
        }
        updateVerdict();
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
