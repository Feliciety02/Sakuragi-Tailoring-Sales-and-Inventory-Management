<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once __DIR__ . '/../../app/Middleware/role_admin_only.php';

$pageTitle = 'AQL Lot Inspection';

$lot_inspection_id = (int)($_GET['lot_inspection_id'] ?? 0);
if (!$lot_inspection_id) { header('Location: aql_qc.php'); exit; }

$lotStmt = $pdo->prepare("SELECT li.*, o.order_id, o.total_price, o.order_date, u.full_name AS customer_name, (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) AS total_qty, ow.assigned_employee FROM qc_lot_inspections li JOIN orders o ON li.order_id = o.order_id JOIN order_workflow ow ON li.workflow_id = ow.workflow_id JOIN users u ON o.user_id = u.user_id WHERE li.lot_inspection_id = ?");
$lotStmt->execute([$lot_inspection_id]);
$lot = $lotStmt->fetch();
if (!$lot) { header('Location: aql_qc.php'); exit; }

$acRe = getAQLAcceptReject($lot['aql_level'], $lot['sample_size']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inspection'])) {
    $critical_defects = (int)$_POST['critical_defects'];
    $major_defects = (int)$_POST['major_defects'];
    $minor_defects = (int)$_POST['minor_defects'];
    $notes = $_POST['notes'] ?? '';
    $verdict = getAQLVerdict($critical_defects, $major_defects, $minor_defects, $lot['aql_level'], $lot['sample_size']);
    if (isset($_POST['confirm_verdict'])) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE qc_lot_inspections SET critical_defects = ?, major_defects = ?, minor_defects = ?, verdict = ?, notes = ?, inspected_at = NOW() WHERE lot_inspection_id = ?")->execute([$critical_defects, $major_defects, $minor_defects, $verdict, $notes, $lot_inspection_id]);
            $target_stage = $verdict === 'Passed' ? STAGE_PACKAGING : STAGE_REWORK;
            $pdo->prepare("UPDATE order_workflow SET stage = ?, completed_at = IF(? = 'Passed', NOW(), NULL) WHERE workflow_id = ?")->execute([$target_stage, $verdict, $lot['workflow_id']]);
            $stmt = $pdo->prepare("SELECT order_detail_id FROM garment_tracking WHERE order_id = ? AND stage = ?");
            $stmt->execute([$lot['order_id'], STAGE_QUALITY_INSPECTION]);
            foreach ($stmt->fetchAll() as $item) {
                $pdo->prepare("UPDATE garment_tracking SET stage = ?, employee_id = ?, updated_at = NOW() WHERE order_detail_id = ?")->execute([$target_stage, $_SESSION['user_id'], $item['order_detail_id']]);
                $pdo->prepare("INSERT INTO garment_log (order_detail_id, order_id, from_stage, to_stage, employee_id, notes) VALUES (?, ?, ?, ?, ?, ?)")->execute([$item['order_detail_id'], $lot['order_id'], STAGE_QUALITY_INSPECTION, $target_stage, $_SESSION['user_id'], "AQL {$verdict}: {$critical_defects}C/{$major_defects}M/{$minor_defects}m"]);
            }
            if ($verdict === 'Failed') $pdo->prepare("INSERT INTO rework_log (order_id, triggered_by, from_stage, to_stage, reason, notes) VALUES (?, ?, ?, ?, ?, ?)")->execute([$lot['order_id'], $_SESSION['user_id'], STAGE_QUALITY_INSPECTION, STAGE_REWORK, "AQL Failed", "AQL Lot #{$lot_inspection_id}: {$critical_defects}C/{$major_defects}M/{$minor_defects}m defects"]);
            $pdo->commit();
            $success = "Lot #{$lot_inspection_id} verdict: {$verdict}. Order moved to {$target_stage}.";
            $lotStmt2 = $pdo->prepare("SELECT * FROM qc_lot_inspections WHERE lot_inspection_id = ?");
            $lotStmt2->execute([$lot_inspection_id]);
            $lot = $lotStmt2->fetch();
            $acRe = getAQLAcceptReject($lot['aql_level'], $lot['sample_size']);
        } catch (Exception $e) { $pdo->rollBack(); $error = 'Failed to submit inspection: ' . $e->getMessage(); }
    }
}

$minorAcRe = getAQLAcceptReject('4.0', $lot['sample_size']);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Sakuragi Admin</title>
    <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
    <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
    <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
    <link rel="manifest" href="/public/manifest.json" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
    <link rel="stylesheet" href="/public/assets/css/components.css">
    <style id="aql-styles">
        .defect-input { width: 100px; text-align: center; font-size: 1.5rem; font-weight: 700; }
        .verdict-box { border-radius: 16px; padding: 2rem; text-align: center; }
        .verdict-pass { background: #d1fae5; color: #065f46; border: 2px solid #059669; }
        .verdict-fail { background: #fee2e2; color: #991b1b; border: 2px solid #dc2626; }
        .verdict-pending { background: var(--bg-secondary); color: var(--text-tertiary); border: 2px solid var(--border-color); }
    </style>
</head>
<body data-role="admin">
<div class="dash-layout">
    <?php render_role_sidebar($pdo); ?>
    <div class="dash-main">
        <?php include __DIR__ . '/../../app/Views/Shared/topnav.php'; ?>

<?php
$alerts = '';
if (isset($success)) $alerts = '<div class="dash-alert dash-alert-success" style="margin:0 24px 16px"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($success) . '</div>';
elseif (isset($error)) $alerts = '<div class="dash-alert dash-alert-danger" style="margin:0 24px 16px"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($error) . '</div>';

$header = renderPageHeader('AQL Lot Inspection #' . $lot_inspection_id, 'Order #' . $lot['order_id'] . ' &middot; ' . htmlspecialchars($lot['customer_name']), '', [['label' => 'Back to AQL QC', 'href' => 'aql_qc.php', 'icon' => 'fas fa-arrow-left', 'variant' => 'outline']]);

$kpiRow = renderKPIRow([
    ['value' => $lot['lot_size'], 'label' => 'Lot Size', 'sub' => 'pieces', 'icon' => 'fas fa-box'],
    ['value' => $lot['sample_size'], 'label' => 'Sample Size', 'sub' => 'pieces to inspect', 'icon' => 'fas fa-search'],
    ['value' => $lot['aql_level'], 'label' => 'AQL Level', 'sub' => 'Acceptable Quality Level', 'icon' => 'fas fa-tag'],
    ['value' => $acRe[0] . ' / ' . $acRe[1], 'label' => 'Accept / Reject', 'sub' => 'Major defects threshold', 'icon' => 'fas fa-check-circle'],
]);

ob_start();
if ($lot['verdict'] === 'Pending'): ?>
<form method="POST" id="inspectionForm">
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px">
<div style="text-align:center;background:var(--bg-secondary);border-radius:12px;padding:20px;border:1px solid var(--border-color)">
<label style="display:block;font-size:0.75rem;font-weight:600;color:#dc2626;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em">Critical Defects</label>
<input type="number" name="critical_defects" class="defect-input" value="0" min="0" id="criticalDefects" oninput="updateVerdict()" style="border:1px solid var(--border-color);border-radius:8px;padding:8px;background:var(--bg-primary);color:var(--text-primary)">
<div style="font-size:0.75rem;color:var(--text-tertiary);margin-top:6px">Safety / non-compliance issues<br><strong>Zero tolerance</strong></div>
</div>
<div style="text-align:center;background:var(--bg-secondary);border-radius:12px;padding:20px;border:1px solid var(--border-color)">
<label style="display:block;font-size:0.75rem;font-weight:600;color:#d97706;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em">Major Defects</label>
<input type="number" name="major_defects" class="defect-input" value="0" min="0" id="majorDefects" oninput="updateVerdict()" style="border:1px solid var(--border-color);border-radius:8px;padding:8px;background:var(--bg-primary);color:var(--text-primary)">
<div style="font-size:0.75rem;color:var(--text-tertiary);margin-top:6px">Functional / appearance failures<br>Accept: <?= $acRe[0] ?>, Reject: <?= $acRe[1] ?></div>
</div>
<div style="text-align:center;background:var(--bg-secondary);border-radius:12px;padding:20px;border:1px solid var(--border-color)">
<label style="display:block;font-size:0.75rem;font-weight:600;color:#6366f1;margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em">Minor Defects</label>
<input type="number" name="minor_defects" class="defect-input" value="0" min="0" id="minorDefects" oninput="updateVerdict()" style="border:1px solid var(--border-color);border-radius:8px;padding:8px;background:var(--bg-primary);color:var(--text-primary)">
<div style="font-size:0.75rem;color:var(--text-tertiary);margin-top:6px">Cosmetic / non-critical flaws<br>Looser threshold (AQL 4.0)</div>
</div>
</div>
<div style="margin-bottom:16px">
<label style="display:block;font-size:0.8125rem;font-weight:600;color:var(--text-primary);margin-bottom:6px">Inspection Notes</label>
<textarea name="notes" rows="3" placeholder="Describe defects found, observations..." style="width:100%;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;font-size:0.85rem;resize:vertical;background:var(--bg-primary);color:var(--text-primary);font-family:inherit"></textarea>
</div>
<div id="verdictPreview" class="verdict-box verdict-pending" style="margin-bottom:16px">
    <i class="fas fa-calculator fa-2x mb-2"></i>
    <h5 style="font-weight:700;margin:0 0 4px">Verdict Preview</h5>
    <p style="margin:0" id="verdictText">Adjust defect counts above to see the verdict.</p>
</div>
<input type="hidden" name="critical_defects_store" id="criticalDefectsStore" value="0">
<input type="hidden" name="major_defects_store" id="majorDefectsStore" value="0">
<input type="hidden" name="minor_defects_store" id="minorDefectsStore" value="0">
<input type="hidden" name="calculated_verdict" id="calculatedVerdict" value="">
<div style="display:flex;gap:8px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border-color)">
    <a href="aql_qc.php" class="dash-btn dash-btn-outline dash-btn-sm"><i class="fas fa-times"></i> Cancel</a>
    <button type="submit" name="submit_inspection" class="dash-btn dash-btn-primary dash-btn-sm" onclick="document.getElementById('criticalDefectsStore').value = document.getElementById('criticalDefects').value; document.getElementById('majorDefectsStore').value = document.getElementById('majorDefects').value; document.getElementById('minorDefectsStore').value = document.getElementById('minorDefects').value;"><i class="fas fa-check"></i> Submit Inspection</button>
</div>
</form>
<?php else:
$vc = $lot['verdict'] === 'Passed' ? 'verdict-pass' : 'verdict-fail';
$vi = $lot['verdict'] === 'Passed' ? 'fa-check-circle' : 'fa-times-circle'; ?>
<div class="verdict-box <?= $vc ?>" style="margin-bottom:16px">
    <i class="fas <?= $vi ?> fa-3x mb-2"></i>
    <h3 style="font-weight:700;margin:0 0 4px"><?= $lot['verdict'] ?></h3>
    <p style="margin:0 0 4px">Critical: <?= $lot['critical_defects'] ?> | Major: <?= $lot['major_defects'] ?> | Minor: <?= $lot['minor_defects'] ?></p>
    <?php if ($lot['notes']): ?><p style="margin:0;font-size:0.85rem;color:var(--text-secondary)"><?= htmlspecialchars($lot['notes']) ?></p><?php endif; ?>
</div>
<p style="font-size:0.85rem;color:var(--text-tertiary);margin-bottom:12px">Inspected at: <?= $lot['inspected_at'] ? date('F j, Y g:i A', strtotime($lot['inspected_at'])) : 'N/A' ?></p>
<a href="aql_qc.php" class="dash-btn dash-btn-outline dash-btn-sm"><i class="fas fa-arrow-left"></i> Back to AQL QC</a>
<?php endif;
$workspace = $alerts . renderPanelCard('Enter Defect Counts', ob_get_clean(), 'fas fa-clipboard-check');

$workspace .= '<script>
document.getElementById(\'menuToggle\')?.addEventListener(\'click\', function() { document.getElementById(\'sidebar\')?.classList.toggle(\'collapsed\'); });
function updateVerdict() {
    const critical = parseInt(document.getElementById(\'criticalDefects\').value) || 0;
    const major = parseInt(document.getElementById(\'majorDefects\').value) || 0;
    const minor = parseInt(document.getElementById(\'minorDefects\').value) || 0;
    const preview = document.getElementById(\'verdictPreview\');
    const text = document.getElementById(\'verdictText\');
    const majorAccept = ' . $acRe[0] . ';
    const majorReject = ' . $acRe[1] . ';
    const minorAccept = ' . $minorAcRe[0] . ';
    const minorReject = ' . $minorAcRe[1] . ';
    let verdict, cls, icon;
    if (critical > 0) { verdict = \'Failed\'; cls = \'verdict-fail\'; icon = \'fa-times-circle\'; text.innerText = \'Failed: Critical defects found (zero tolerance).\'; }
    else if (major >= majorReject) { verdict = \'Failed\'; cls = \'verdict-fail\'; icon = \'fa-times-circle\'; text.innerText = \'Failed: \' + major + \' major defects exceed reject threshold of \' + majorReject + \'.\'; }
    else if (minor >= minorReject) { verdict = \'Failed\'; cls = \'verdict-fail\'; icon = \'fa-times-circle\'; text.innerText = \'Failed: \' + minor + \' minor defects exceed reject threshold of \' + minorReject + \'.\'; }
    else {
        verdict = \'Passed\'; cls = \'verdict-pass\'; icon = \'fa-check-circle\';
        var reasons = [];
        if (critical === 0) reasons.push(\'no critical defects\');
        if (major <= majorAccept) reasons.push(major + \'/\' + majorAccept + \' major\');
        if (minor <= minorAccept) reasons.push(minor + \'/\' + minorAccept + \' minor\');
        text.innerText = \'Passed: \' + reasons.join(\', \') + \'.\';
    }
    preview.className = \'verdict-box \' + cls + \' mb-4\';
    preview.querySelector(\'i\').className = \'fas \' + icon + \' fa-2x mb-2\';
    document.getElementById(\'calculatedVerdict\').value = verdict;
}
updateVerdict();
</script>';

echo renderDashboardShell($header, $kpiRow, $workspace);
?>
