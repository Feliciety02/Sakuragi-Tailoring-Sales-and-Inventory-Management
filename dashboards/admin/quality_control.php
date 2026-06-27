<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/role_admin_only.php';

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qc_review'])) {
    $order_id = (int)$_POST['order_id'];
    $result = $_POST['result'];
    $feedback = $_POST['feedback'] ?? '';
    $failure_reason = $_POST['failure_reason'] ?? '';
    $corrections = $_POST['required_corrections'] ?? '';
    $checklist = ['design_accuracy'=>0,'print_alignment'=>0,'embroidery_quality'=>0,'stitching_quality'=>0,'size_accuracy'=>0,'fabric_condition'=>0,'cleanliness'=>0,'packaging_readiness'=>0];
    foreach ($checklist as $k => &$v) { $v = (int)($_POST[$k] ?? 0); } unset($v);

    try {
        $pdo->beginTransaction();
        $ins = $pdo->prepare("SELECT inspection_id FROM qc_inspections WHERE order_id = ?");
        $ins->execute([$order_id]); $existing = $ins->fetch();
        if ($existing) {
            $pdo->prepare("UPDATE qc_inspections SET result=?, inspector_id=?, inspected_at=NOW(), feedback=?, failure_reason=?, required_corrections=?, design_accuracy=?, print_alignment=?, embroidery_quality=?, stitching_quality=?, size_accuracy=?, fabric_condition=?, cleanliness=?, packaging_readiness=? WHERE order_id=?")
                ->execute([$result, $user_id, $feedback, $failure_reason, $corrections, $checklist['design_accuracy'], $checklist['print_alignment'], $checklist['embroidery_quality'], $checklist['stitching_quality'], $checklist['size_accuracy'], $checklist['fabric_condition'], $checklist['cleanliness'], $checklist['packaging_readiness'], $order_id]);
        } else {
            $checklist['result'] = $result; $checklist['inspector_id'] = $user_id; $checklist['inspected_at'] = date('Y-m-d H:i:s');
            $checklist['feedback'] = $feedback; $checklist['failure_reason'] = $failure_reason; $checklist['required_corrections'] = $corrections; $checklist['order_id'] = $order_id;
            $cols = implode(',', array_keys($checklist)); $vals = implode(',', array_fill(0, count($checklist), '?'));
            $pdo->prepare("INSERT INTO qc_inspections ({$cols}) VALUES ({$vals})")->execute(array_values($checklist));
        }
        if ($result === 'Passed') {
            $pdo->prepare("UPDATE order_workflow SET stage='Packaging', completed_at=NOW() WHERE order_id=?")->execute([$order_id]);
        } else {
            $pdo->prepare("INSERT INTO rework_log (order_id, triggered_by, from_stage, to_stage, reason, notes) VALUES (?, ?, ?, 'Rework', ?, ?)")->execute([$order_id, $user_id, $failure_reason, $corrections]);
            $pdo->prepare("UPDATE order_workflow SET stage='Rework' WHERE order_id=?")->execute([$order_id]);
        }
        $pdo->commit(); $message = 'QC review submitted successfully';
    } catch (Exception $e) { $pdo->rollBack(); $message = 'Error: ' . $e->getMessage(); }
}

// ── Data ──
$qcOrders = $pdo->query("
    SELECT o.order_id, o.order_date, o.total_price, ow.stage, ow.product_type, ow.expected_completion, ow.assigned_employee,
           u.full_name AS customer_name, e.full_name AS employee_name, qc.result AS qc_result, qc.inspection_id,
           TIMESTAMPDIFF(HOUR, ow.expected_completion, NOW()) AS hours_overdue
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN users e ON ow.assigned_employee = e.user_id
    LEFT JOIN qc_inspections qc ON o.order_id = qc.order_id
    WHERE ow.stage IN ('QC', 'Rework')
    ORDER BY CASE WHEN qc.result = 'Pending' OR qc.result IS NULL THEN 0 ELSE 1 END, ow.expected_completion ASC
");

$qcHistory = $pdo->query("
    SELECT qc.*, o.order_id, u.full_name AS inspector_name
    FROM qc_inspections qc JOIN orders o ON qc.order_id = o.order_id LEFT JOIN users u ON qc.inspector_id = u.user_id
    WHERE qc.result != 'Pending' ORDER BY qc.inspected_at DESC LIMIT 20
");

$pendingCount = $pdo->query("SELECT COUNT(*) FROM order_workflow WHERE stage = 'QC'")->fetchColumn();
$failedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Failed' AND DATE(inspected_at) = CURDATE()")->fetchColumn();
$passedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Passed' AND DATE(inspected_at) = CURDATE()")->fetchColumn();
$passRate = ($passedToday + $failedToday) ? round($passedToday / ($passedToday + $failedToday) * 100) : 100;

// Rework data
$reworkOrders = $pdo->query("
    SELECT rl.*, o.order_id, ow.product_type, u.full_name AS customer_name, DATEDIFF(NOW(), rl.created_at) AS days_waiting
    FROM rework_log rl
    JOIN orders o ON rl.order_id = o.order_id
    JOIN order_workflow ow ON o.order_id = ow.order_id
    LEFT JOIN users e ON rl.triggered_by = e.user_id
    LEFT JOIN users u ON o.user_id = u.user_id
    WHERE o.status NOT IN ('Completed','Cancelled')
    ORDER BY rl.created_at DESC LIMIT 10
")->fetchAll();

// Defect analytics
$defectTypes = $pdo->query("
    SELECT failure_reason AS defect, COUNT(*) AS cnt
    FROM qc_inspections WHERE result = 'Failed' AND failure_reason != '' AND failure_reason IS NOT NULL
    GROUP BY failure_reason ORDER BY cnt DESC LIMIT 8
")->fetchAll();

$pageTitle = 'Quality Control';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quality Control — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="qc-styles">
    .inspection-card { background:var(--surface);border-radius:var(--radius-md);border:1px solid var(--border);padding:16px 18px;margin-bottom:10px;transition:box-shadow .2s;border-left:4px solid var(--border) }
    .inspection-card:hover { box-shadow:var(--shadow-md) }
    .inspection-card.severity-critical { border-left-color:#dc2626 }
    .inspection-card.severity-major { border-left-color:#d97706 }
    .inspection-card.severity-minor { border-left-color:#eab308 }

    .timeline-node { display:flex;gap:14px;padding:12px 0;position:relative }
    .timeline-node + .timeline-node { border-top:1px solid var(--border-light) }
    .timeline-dot { width:12px;height:12px;border-radius:50%;flex-shrink:0;margin-top:5px;box-shadow:0 0 0 3px var(--surface) }
    .timeline-dot.pass { background:var(--color-success) }
    .timeline-dot.fail { background:var(--color-danger) }
    .timeline-dot.rework { background:var(--color-warning) }

    .rework-item { display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border-light);gap:8px;flex-wrap:wrap }
    .rework-item:last-child { border-bottom:none }

    .defect-stat { display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-light) }
    .defect-stat:last-child { border-bottom:none }
    .defect-stat .defect-name { font-size:.82rem;color:var(--text-primary);font-weight:500 }
    .defect-stat .defect-count { font-size:.82rem;color:var(--text-tertiary) }


  </style>
</head>
<body data-role="admin">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
$alerts = '';
if ($message):
  $isErr = strpos($message, 'Error') !== false;
  $alerts = '<div class="dash-alert ' . ($isErr ? 'dash-alert-danger' : 'dash-alert-success') . '"><i class="fas ' . ($isErr ? 'fa-exclamation-circle' : 'fa-check-circle') . '"></i> ' . htmlspecialchars($message) . '</div>';
endif;

$metricsRow = renderKPIRow([
  ['label' => 'Pending Inspections', 'value' => (string)$pendingCount, 'icon' => 'fas fa-hourglass-half', 'accent' => 'amber'],
  ['label' => 'Passed Today', 'value' => (string)$passedToday, 'icon' => 'fas fa-check-circle', 'accent' => 'green'],
  ['label' => 'Failed Today', 'value' => (string)$failedToday, 'icon' => 'fas fa-times-circle', 'accent' => 'red'],
  ['label' => 'Pass Rate', 'value' => $passRate . '%', 'icon' => 'fas fa-chart-line', 'accent' => 'blue'],
]);

// ── Priority Review Queue ──
$pendingHtml = '';
if ($qcOrders->rowCount() === 0):
  $pendingHtml = '<div style="padding:32px;text-align:center">' . renderEmptyState('fas fa-check-double', 'All caught up', 'No orders pending QC review.') . '</div>';
else:
  ob_start();
  foreach ($qcOrders as $o):
    $severity = $o['qc_result'] === 'Failed' ? 'critical' : ($o['stage'] === 'Rework' ? 'major' : 'minor');
    $severityLabel = $o['qc_result'] === 'Failed' ? 'Failed' : ($o['stage'] === 'Rework' ? 'In Rework' : 'Pending');
    $daysWaiting = $o['expected_completion'] ? max(0, (int)((time() - strtotime($o['expected_completion'])) / 86400)) : 0;
?>
<div class="inspection-card severity-<?= $severity ?>">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
    <div style="flex:1;min-width:0">
      <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <span style="font-size:.88rem;font-weight:700;color:var(--text-primary)">#ORD-<?= $o['order_id'] ?></span>
        <span class="status-badge status-badge-<?= $severity === 'critical' ? 'danger' : ($severity === 'major' ? 'warning' : 'info') ?> status-badge-sm"><?= $severityLabel ?></span>
      </div>
      <p style="font-size:.78rem;color:var(--text-secondary);margin:4px 0 0">
        <?= htmlspecialchars($o['customer_name']) ?> · <?= htmlspecialchars($o['product_type'] ?? 'Garment') ?> · Inspector: <?= htmlspecialchars($o['employee_name'] ?? 'Unassigned') ?>
      </p>
      <p style="font-size:.7rem;color:var(--text-tertiary);margin:4px 0 0">
        Due: <?= $o['expected_completion'] ? date('M d', strtotime($o['expected_completion'])) : '—' ?>
        <?php if ($daysWaiting > 0): ?> · <span style="color:var(--color-danger)"><?= $daysWaiting ?> day<?= $daysWaiting > 1 ? 's' : '' ?> overdue</span><?php endif; ?>
      </p>
    </div>
    <button class="dash-btn dash-btn-primary dash-btn-sm" onclick="openQC(<?= $o['order_id'] ?>)"><i class="fas fa-search"></i> Review</button>
  </div>
</div>
<?php
  endforeach;
  $pendingHtml = ob_get_clean();
endif;

$queueSection = renderPageSection('Priority Review Queue', $pendingHtml, 'fas fa-clipboard-list');

// ── Quality Analytics Panel ──
$analyticsBody = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">';
$analyticsBody .= '<div class="metric-tile"><div class="metric-tile-label">Today Pass Rate</div><div class="metric-tile-value" style="color:var(--color-success)">' . $passRate . '%</div></div>';
$analyticsBody .= '<div class="metric-tile"><div class="metric-tile-label">Failed Today</div><div class="metric-tile-value" style="color:var(--color-danger)">' . $failedToday . '</div></div>';
$totalInspections = $passedToday + $failedToday;
$analyticsBody .= '<div class="metric-tile"><div class="metric-tile-label">Inspections Today</div><div class="metric-tile-value">' . $totalInspections . '</div></div>';
$analyticsBody .= '<div class="metric-tile"><div class="metric-tile-label">Pass Rate (All Time)</div><div class="metric-tile-value">' . ($pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result='Passed'")->fetchColumn() > 0 ? round($pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result='Passed'")->fetchColumn() / max(1, $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result!='Pending'")->fetchColumn()) * 100) . '%' : 'N/A') . '</div></div>';
$analyticsBody .= '</div>';

// Defect insights
if (!empty($defectTypes)):
  $totalFailed = array_sum(array_column($defectTypes, 'cnt'));
  $analyticsBody .= '<h4 style="font-size:.78rem;font-weight:700;color:var(--text-secondary);margin:16px 0 8px"><i class="fas fa-bug"></i> Most Common Defects</h4>';
  foreach ($defectTypes as $d):
    $pct = $totalFailed > 0 ? round($d['cnt'] / $totalFailed * 100) : 0;
    $analyticsBody .= '<div class="defect-stat"><span class="defect-name">' . htmlspecialchars($d['defect']) . '</span><span class="defect-count">' . $d['cnt'] . ' (' . $pct . '%)</span></div>';
  endforeach;
endif;

$analyticsSection = renderPageSection('Quality Analytics', $analyticsBody, 'fas fa-chart-bar');

// ── Row 2: Queue + Analytics side by side ──
$row2 = '<div class="dash-two-col" style="margin-bottom:20px"><div class="dash-main-col">' . $queueSection . '</div><div class="dash-side-col">' . $analyticsSection . '</div></div>';

// ── Recent Inspections Timeline ──
$historyHtml = '';
if ($qcHistory->rowCount() === 0):
  $historyHtml = '<div style="padding:24px;text-align:center">' . renderEmptyState('fas fa-history', 'No inspections yet', 'Inspection results will appear here.') . '</div>';
else:
  ob_start();
  foreach ($qcHistory as $h):
    $dotClass = $h['result'] === 'Passed' ? 'pass' : ($h['result'] === 'Failed' ? 'fail' : 'rework');
    $iconClass = $h['result'] === 'Passed' ? 'fa-check-circle' : ($h['result'] === 'Failed' ? 'fa-times-circle' : 'fa-undo-alt');
?>
<div class="timeline-node">
  <div class="timeline-dot <?= $dotClass ?>"></div>
  <div style="flex:1;min-width:0">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:4px">
      <span style="font-size:.82rem;font-weight:600;color:var(--text-primary)">#ORD-<?= $h['order_id'] ?></span>
      <span class="status-badge status-badge-<?= $h['result'] === 'Passed' ? 'success' : 'danger' ?> status-badge-sm"><?= htmlspecialchars($h['result']) ?></span>
    </div>
    <p style="font-size:.75rem;color:var(--text-tertiary);margin:2px 0 0">
      <?= htmlspecialchars($h['inspector_name'] ?? 'N/A') ?> · <?= $h['inspected_at'] ? date('M d, g:i A', strtotime($h['inspected_at'])) : '—' ?>
      <?php if ($h['feedback']): ?> · <?= htmlspecialchars(substr($h['feedback'], 0, 60)) ?><?php endif; ?>
    </p>
  </div>
</div>
<?php
  endforeach;
  $historyHtml = ob_get_clean();
endif;
$timelineSection = renderPageSection('Recent Inspections', $historyHtml, 'fas fa-clock');

// ── Rework Monitoring ──
$reworkHtml = '';
if (count($reworkOrders) === 0):
  $reworkHtml = '<div style="padding:24px;text-align:center">' . renderEmptyState('fas fa-check', 'No rework orders', 'All orders are on track.') . '</div>';
else:
  ob_start();
  foreach ($reworkOrders as $rw):
    $urgency = $rw['days_waiting'] > 5 ? 'style="color:var(--color-danger);font-weight:700"' : ($rw['days_waiting'] > 2 ? 'style="color:var(--color-warning)"' : '');
?>
<div class="rework-item">
  <div>
    <span style="font-weight:600;font-size:.82rem">#ORD-<?= $rw['order_id'] ?></span>
    <span style="font-size:.75rem;color:var(--text-tertiary);display:block"><?= htmlspecialchars($rw['reason'] ?: 'QC Failed') ?> · <?= htmlspecialchars($rw['product_type'] ?? '') ?></span>
  </div>
  <span <?= $urgency ?>><?= $rw['days_waiting'] ?> day<?= $rw['days_waiting'] > 1 ? 's' : '' ?></span>
</div>
<?php
  endforeach;
  $reworkHtml = ob_get_clean();
endif;
$reworkSection = renderPageSection('Rework Monitoring', $reworkHtml, 'fas fa-undo-alt');

// ── Row 3: Timeline + Rework side by side ──
$row3 = '<div class="dash-two-col" style="margin-bottom:20px"><div class="dash-main-col">' . $timelineSection . '</div><div class="dash-side-col">' . $reworkSection . '</div></div>';

$scriptsHtml = '';
ob_start(); ?>
<!-- QC Modal -->
<div class="modern-modal-overlay" id="qcModal" style="display:none" onclick="if(event.target===this)closeQC()">
  <div class="modern-modal" style="max-width:600px">
    <form method="post">
      <input type="hidden" name="order_id" id="qcOrderId">
      <h3 style="margin:0 0 16px;font-size:1.1rem;font-weight:700;color:var(--text-primary)">QC Review — <span id="qcOrderLabel"></span></h3>
      <div style="margin-bottom:14px">
        <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px">Result</label>
        <select name="result" id="resultSelect" required style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.85rem;background:var(--surface);color:var(--text-primary)">
          <option value="Passed">Passed</option>
          <option value="Failed">Failed</option>
        </select>
      </div>
      <div style="margin-bottom:14px">
        <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px">Inspection Checklist</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <?php foreach (['design_accuracy'=>'Design Accuracy','print_alignment'=>'Print Alignment','embroidery_quality'=>'Embroidery Quality','stitching_quality'=>'Stitching Quality','size_accuracy'=>'Size Accuracy','fabric_condition'=>'Fabric Condition','cleanliness'=>'Cleanliness','packaging_readiness'=>'Packaging'] as $k => $l): ?>
          <label style="display:flex;align-items:center;gap:8px;font-size:0.8rem;cursor:pointer;color:var(--text-primary);padding:6px 8px;border-radius:6px;background:var(--surface-secondary)">
            <input type="checkbox" name="<?= $k ?>" value="1" style="width:16px;height:16px;accent-color:var(--role-accent)"> <?= $l ?>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div style="margin-bottom:14px">
        <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px">Feedback</label>
        <textarea name="feedback" rows="2" placeholder="Overall feedback..." style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.85rem;background:var(--surface);color:var(--text-primary);resize:vertical;font-family:inherit"></textarea>
      </div>
      <div id="failureFields" style="display:none;margin-bottom:14px">
        <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px">Failure Reason</label>
        <input type="text" name="failure_reason" placeholder="e.g. Print misalignment" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.85rem;background:var(--surface);color:var(--text-primary);margin-bottom:10px">
        <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px">Required Corrections</label>
        <textarea name="required_corrections" rows="2" placeholder="What needs to be fixed?" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.85rem;background:var(--surface);color:var(--text-primary);resize:vertical;font-family:inherit"></textarea>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="dash-btn dash-btn-outline dash-btn-sm" onclick="closeQC()">Cancel</button>
        <button type="submit" name="qc_review" class="dash-btn dash-btn-primary dash-btn-sm"><i class="fas fa-check"></i> Submit Review</button>
      </div>
    </form>
  </div>
</div>
<script>
function openQC(id) {
  document.getElementById('qcOrderId').value = id;
  document.getElementById('qcOrderLabel').textContent = '#ORD-' + id;
  document.getElementById('qcModal').style.display = 'flex';
  document.getElementById('failureFields').style.display = 'none';
}
function closeQC() { document.getElementById('qcModal').style.display = 'none'; }
document.getElementById('resultSelect')?.addEventListener('change', function() {
  document.getElementById('failureFields').style.display = this.value === 'Failed' ? 'block' : 'none';
});
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
<?php $scriptsHtml = ob_get_clean();

$workspace = $alerts . $row2 . $row3 . $scriptsHtml;

echo renderDashboardShell(
  renderPageHeader('Quality Control', 'Inspect completed work, manage rework, and track quality metrics.'),
  $metricsRow,
  $workspace
);
?>
    </div>
  </div>
</div>
</body>
</html>
