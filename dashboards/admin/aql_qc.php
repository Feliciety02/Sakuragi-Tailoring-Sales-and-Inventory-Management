<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once __DIR__ . '/../../app/Middleware/role_admin_only.php';

$pageTitle = 'AQL Sampling QC';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_aql_config'])) {
    $order_id = (int)$_POST['order_id'];
    $aql_level = $_POST['aql_level'] ?? '2.5';
    $inspection_level = $_POST['inspection_level'] ?? 'II';
    $crit_allowed = (int)$_POST['critical_allowed'];
    try {
        $pdo->prepare("INSERT INTO aql_config (order_id, aql_level, inspection_level, critical_allowed) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE aql_level = VALUES(aql_level), inspection_level = VALUES(inspection_level), critical_allowed = VALUES(critical_allowed)")->execute([$order_id, $aql_level, $inspection_level, $crit_allowed]);
        $success = 'AQL configuration saved.';
    } catch (Exception $e) { $error = 'Failed to save AQL config: ' . $e->getMessage(); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_inspection'])) {
    $order_id = (int)$_POST['order_id'];
    $workflow_id = (int)$_POST['workflow_id'];
    $lot_size = (int)$_POST['lot_size'];
    $cfgStmt = $pdo->prepare("SELECT aql_level, inspection_level FROM aql_config WHERE order_id = ?");
    $cfgStmt->execute([$order_id]);
    $cfg = $cfgStmt->fetch();
    $aql_level = $cfg['aql_level'] ?? '2.5';
    $inspection_level = $cfg['inspection_level'] ?? 'II';
    $sample_size = getAQLSampleSize($lot_size, $inspection_level);
    try {
        $pdo->prepare("INSERT INTO qc_lot_inspections (order_id, workflow_id, inspector_id, lot_size, sample_size, aql_level) VALUES (?, ?, ?, ?, ?, ?)")->execute([$order_id, $workflow_id, $_SESSION['user_id'], $lot_size, $sample_size, $aql_level]);
        $lot_id = $pdo->lastInsertId();
        $success = "Lot inspection #{$lot_id} started. Sample size: {$sample_size}";
    } catch (Exception $e) { $error = 'Failed to start inspection: ' . $e->getMessage(); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_verdict'])) {
    $lot_inspection_id = (int)$_POST['lot_inspection_id'];
    $critical_defects = (int)$_POST['critical_defects'];
    $major_defects = (int)$_POST['major_defects'];
    $minor_defects = (int)$_POST['minor_defects'];
    $notes = $_POST['notes'] ?? '';
    $lot = $pdo->prepare("SELECT * FROM qc_lot_inspections WHERE lot_inspection_id = ?")->execute([$lot_inspection_id])->fetch();
    if ($lot) {
        $verdict = getAQLVerdict($critical_defects, $major_defects, $minor_defects, $lot['aql_level'], $lot['sample_size']);
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
            $success = "Lot #{$lot_inspection_id} {$verdict}.";
        } catch (Exception $e) { $pdo->rollBack(); $error = 'Failed to update verdict: ' . $e->getMessage(); }
    }
}

// ── Data ──
$orders = $pdo->query("SELECT o.order_id, o.order_date, o.total_price, ow.workflow_id, ow.stage, ow.assigned_employee, u.full_name AS customer_name, (SELECT COUNT(*) FROM order_details WHERE order_id = o.order_id) AS total_items, COALESCE((SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id), 0) AS total_qty, ac.aql_level, ac.inspection_level FROM orders o JOIN order_workflow ow ON o.order_id = ow.order_id JOIN users u ON o.user_id = u.user_id LEFT JOIN aql_config ac ON o.order_id = ac.order_id WHERE ow.stage = '" . STAGE_QUALITY_INSPECTION . "' ORDER BY o.order_date DESC")->fetchAll();

$recent_lots = $pdo->query("SELECT li.*, u.full_name AS inspector_name, o.order_id AS ref_order FROM qc_lot_inspections li JOIN users u ON li.inspector_id = u.user_id JOIN orders o ON li.order_id = o.order_id ORDER BY li.created_at DESC LIMIT 20")->fetchAll();

$pending_lots = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections WHERE verdict = 'Pending'")->fetchColumn();
$passed_today = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections WHERE verdict = 'Passed' AND DATE(inspected_at) = CURDATE()")->fetchColumn();
$failed_today = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections WHERE verdict = 'Failed' AND DATE(inspected_at) = CURDATE()")->fetchColumn();
$total_lots = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections")->fetchColumn();
$pass_rate = $total_lots > 0 ? round($pdo->query("SELECT COUNT(*) FROM qc_lot_inspections WHERE verdict = 'Passed'")->fetchColumn() / $total_lots * 100) : 0;
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Sakuragi</title>
    <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
    <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
    <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
    <link rel="manifest" href="/public/manifest.json" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
    <link rel="stylesheet" href="/public/assets/css/components.css">
    <style id="aql-styles">
        .aql-card { background:var(--surface);border-radius:var(--radius-md);border:1px solid var(--border);padding:16px 18px;margin-bottom:10px;transition:box-shadow .2s;border-left:4px solid var(--border) }
        .aql-card:hover { box-shadow:var(--shadow-md) }
        .aql-card.verdict-pending { border-left-color:var(--color-warning) }
        .aql-card.verdict-passed { border-left-color:var(--color-success) }
        .aql-card.verdict-failed { border-left-color:var(--color-danger) }

        .aql-badge { display:inline-flex;align-items:center;gap:4px;font-size:.7rem;font-weight:600;padding:3px 10px;border-radius:100px;background:var(--surface-secondary);color:var(--text-secondary);border:1px solid var(--border) }
        .aql-badge.aql-i { background:#dbeafe;color:#1e40af;border-color:#93c5fd }
        .aql-badge.aql-ii { background:#fef3c7;color:#92400e;border-color:#fcd34d }
        .aql-badge.aql-iii { background:#fee2e2;color:#991b1b;border-color:#fca5a5 }

        .aql-grid { display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px }
        @media (max-width:900px) { .aql-grid { grid-template-columns:1fr } }
    </style>
</head>
<body data-role="admin">
<div class="dash-layout">
    <?php render_role_sidebar($pdo); ?>
    <div class="dash-main">
        <?php include __DIR__ . '/../../app/Views/Shared/topnav.php'; ?>

<?php
$alerts = '';
if (isset($success)) $alerts = '<div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);color:var(--color-success);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px;font-size:.85rem"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($success) . '</div>';
elseif (isset($error)) $alerts = '<div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:var(--color-danger);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px;font-size:.85rem"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($error) . '</div>';

$kpiRow = renderKPIRow([
    ['value' => (string)$pending_lots, 'label' => 'Pending Lots', 'icon' => 'fas fa-hourglass-half', 'accent' => 'amber'],
    ['value' => (string)$passed_today, 'label' => 'Passed Today', 'icon' => 'fas fa-check', 'accent' => 'green'],
    ['value' => (string)$failed_today, 'label' => 'Failed Today', 'icon' => 'fas fa-times', 'accent' => 'red'],
    ['value' => $pass_rate . '%', 'label' => 'Pass Rate', 'icon' => 'fas fa-chart-line', 'accent' => 'blue'],
]);

// ── Row 2: Inspection Queue (left) + Sampling Insights (right) ──
// Inspection Queue: card-based
$queueHtml = '';
if (count($orders) === 0):
    $queueHtml = '<div style="padding:32px;text-align:center">' . renderEmptyState('fas fa-check-double', 'No orders awaiting AQL', 'All QC orders have been inspected.') . '</div>';
else:
    ob_start();
    foreach ($orders as $order):
        $lotSize = (int)$order['total_qty'];
        $aql = $order['aql_level'] ?? '2.5';
        $il = $order['inspection_level'] ?? 'II';
        $sampleSize = getAQLSampleSize($lotSize, $il);
        $acRe = getAQLAcceptReject($aql, $sampleSize);
        $ilClass = $il === 'I' ? 'aql-i' : ($il === 'II' ? 'aql-ii' : 'aql-iii');
        $daysWaiting = $order['order_date'] ? max(0, (int)((time() - strtotime($order['order_date'])) / 86400)) : 0;
?>
<div class="aql-card verdict-pending">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
        <div style="flex:1;min-width:0">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <span style="font-size:.88rem;font-weight:700;color:var(--text-primary)">#ORD-<?= $order['order_id'] ?></span>
                <span class="status-badge status-badge-warning status-badge-sm">Awaiting AQL</span>
            </div>
            <p style="font-size:.78rem;color:var(--text-secondary);margin:4px 0 2px"><?= htmlspecialchars($order['customer_name']) ?> · <?= $order['total_items'] ?> items</p>
            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:4px">
                <span class="aql-badge">Lot: <?= $lotSize ?> pcs</span>
                <span class="aql-badge">Sample: <?= $sampleSize ?> pcs</span>
                <span class="aql-badge <?= $ilClass ?>">AQL <?= $aql ?> / Level <?= $il ?></span>
                <span class="aql-badge">Ac/Re: <?= $acRe[0] ?>/<?= $acRe[1] ?></span>
                <?php if ($daysWaiting > 0): ?><span class="aql-badge" style="color:var(--color-warning)"><?= $daysWaiting ?> day<?= $daysWaiting > 1 ? 's' : '' ?> in queue</span><?php endif; ?>
            </div>
        </div>
        <div style="display:flex;gap:6px;flex-shrink:0">
            <button class="dash-btn dash-btn-outline dash-btn-sm" onclick="openConfigModal(<?= $order['order_id'] ?>,'<?= $aql ?>','<?= $il ?>')"><i class="fas fa-cog"></i></button>
            <button class="dash-btn dash-btn-accent dash-btn-sm" onclick="openInspectModal(<?= $order['order_id'] ?>,<?= $order['workflow_id'] ?>,<?= $order['total_qty'] ?>,<?= $sampleSize ?>,<?= $acRe[0] ?>,<?= $acRe[1] ?>,'<?= $aql ?>')"><i class="fas fa-search"></i> Inspect</button>
        </div>
    </div>
</div>
<?php
    endforeach;
    $queueHtml = ob_get_clean();
endif;
$queueSection = renderPageSection('Inspection Queue', $queueHtml, 'fas fa-clipboard-list');

// Sampling Insights Panel
$insightsBody = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">';
$insightsBody .= '<div class="metric-tile"><div class="metric-tile-label">Pending Lots</div><div class="metric-tile-value" style="color:var(--color-warning)">' . $pending_lots . '</div></div>';
$insightsBody .= '<div class="metric-tile"><div class="metric-tile-label">Total Inspections</div><div class="metric-tile-value">' . $total_lots . '</div></div>';
$insightsBody .= '<div class="metric-tile"><div class="metric-tile-label">Overall Pass Rate</div><div class="metric-tile-value" style="color:var(--color-success)">' . $pass_rate . '%</div></div>';
$insightsBody .= '<div class="metric-tile"><div class="metric-tile-label">Failed Today</div><div class="metric-tile-value" style="color:var(--color-danger)">' . $failed_today . '</div></div>';
$insightsBody .= '</div>';

// AQL level distribution
$aqlDist = $pdo->query("SELECT aql_level, COUNT(*) AS cnt FROM qc_lot_inspections GROUP BY aql_level ORDER BY cnt DESC")->fetchAll();
if (!empty($aqlDist)):
    $insightsBody .= '<h4 style="font-size:.78rem;font-weight:700;color:var(--text-secondary);margin:16px 0 8px"><i class="fas fa-chart-pie"></i> AQL Distribution</h4>';
    foreach ($aqlDist as $d):
        $pct = $total_lots > 0 ? round($d['cnt'] / $total_lots * 100) : 0;
        $insightsBody .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:.82rem"><span>AQL ' . htmlspecialchars($d['aql_level']) . '</span><span style="color:var(--text-tertiary)">' . $d['cnt'] . ' (' . $pct . '%)</span></div>';
    endforeach;
endif;

// Failed inspections
$failReasons = $pdo->query("SELECT notes, COUNT(*) AS cnt FROM qc_lot_inspections WHERE verdict = 'Failed' AND notes != '' AND notes IS NOT NULL GROUP BY notes ORDER BY cnt DESC LIMIT 5")->fetchAll();
if (!empty($failReasons)):
    $insightsBody .= '<h4 style="font-size:.78rem;font-weight:700;color:var(--text-secondary);margin:16px 0 8px"><i class="fas fa-exclamation-triangle"></i> Common Failure Notes</h4>';
    foreach ($failReasons as $fr):
        $insightsBody .= '<div style="display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:.78rem"><span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--text-primary)">' . htmlspecialchars(substr($fr['notes'], 0, 40)) . '</span><span style="color:var(--text-tertiary);margin-left:8px">' . $fr['cnt'] . '</span></div>';
    endforeach;
endif;
$insightsSection = renderPageSection('Sampling Insights', $insightsBody, 'fas fa-chart-bar');

$row2 = '<div class="aql-grid">' . $queueSection . $insightsSection . '</div>';

// ── Row 3: Recent Inspections ──
$recentHtml = '';
if (count($recent_lots) === 0):
    $recentHtml = '<div style="padding:32px;text-align:center">' . renderEmptyState('fas fa-clipboard', 'No inspections yet', 'Submit your first AQL inspection.', ['label' => 'Inspect Order', 'href' => 'aql_qc.php', 'icon' => 'fas fa-search']) . '</div>';
else:
    ob_start();
    foreach ($recent_lots as $lot):
        $verdict = $lot['verdict'];
        $dotClass = $verdict === 'Passed' ? 'pass' : ($verdict === 'Failed' ? 'fail' : 'rework');
?>
<div style="display:flex;gap:14px;padding:12px 0;border-bottom:1px solid var(--border-light);align-items:flex-start">
    <div style="width:12px;height:12px;border-radius:50%;flex-shrink:0;margin-top:5px;background:<?= $verdict === 'Passed' ? 'var(--color-success)' : ($verdict === 'Failed' ? 'var(--color-danger)' : 'var(--color-warning)') ?>;box-shadow:0 0 0 3px var(--surface)"></div>
    <div style="flex:1;min-width:0">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:4px">
            <span style="font-size:.82rem;font-weight:600;color:var(--text-primary)">Lot #<?= $lot['lot_inspection_id'] ?> — #ORD-<?= $lot['order_id'] ?></span>
            <span class="status-badge status-badge-<?= $verdict === 'Passed' ? 'success' : 'danger' ?> status-badge-sm"><?= htmlspecialchars($verdict) ?></span>
        </div>
        <p style="font-size:.75rem;color:var(--text-tertiary);margin:2px 0 0">
            <?= htmlspecialchars($lot['inspector_name']) ?> · 
            Lot: <?= $lot['lot_size'] ?>/Sample: <?= $lot['sample_size'] ?> · 
            Defects: <?= (int)$lot['critical_defects'] ?>C/<?= (int)$lot['major_defects'] ?>M/<?= (int)$lot['minor_defects'] ?>m
            <?php if ($lot['inspected_at']): ?> · <?= date('M j, g:i A', strtotime($lot['inspected_at'])) ?><?php endif; ?>
        </p>
        <?php if ($lot['notes']): ?>
        <p style="font-size:.7rem;color:var(--text-tertiary);margin:2px 0 0;font-style:italic"><?= htmlspecialchars(substr($lot['notes'], 0, 80)) ?></p>
        <?php endif; ?>
    </div>
</div>
<?php
    endforeach;
    $recentHtml = ob_get_clean();
endif;
$recentSection = renderPageSection('Recent Lot Inspections', $recentHtml, 'fas fa-history');

$scriptsHtml = '';
ob_start(); ?>
<!-- Config Modal -->
<div class="modern-modal-overlay" id="configModal" style="display:none" onclick="if(event.target===this)closeConfigModal()">
  <div class="modern-modal" style="max-width:460px">
    <h3 style="margin:0 0 16px;font-size:1.05rem;font-weight:700;color:var(--text-primary)">AQL Configuration</h3>
    <form method="POST" id="configForm">
      <input type="hidden" name="order_id" id="configOrderId">
      <div style="display:flex;flex-direction:column;gap:14px">
        <div><label style="font-size:0.8125rem;font-weight:600;color:var(--text-primary);display:block;margin-bottom:4px">AQL Level</label>
          <select name="aql_level" id="configAql" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.85rem;background:var(--surface);color:var(--text-primary)">
            <option value="1.0">AQL 1.0 (Strict)</option><option value="2.5">AQL 2.5 (Normal)</option><option value="4.0">AQL 4.0 (Loose)</option>
          </select></div>
        <div><label style="font-size:0.8125rem;font-weight:600;color:var(--text-primary);display:block;margin-bottom:4px">Inspection Level</label>
          <select name="inspection_level" id="configIl" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.85rem;background:var(--surface);color:var(--text-primary)">
            <option value="I">Level I (Reduced)</option><option value="II" selected>Level II (Normal)</option><option value="III">Level III (Tightened)</option>
          </select></div>
        <div><label style="font-size:0.8125rem;font-weight:600;color:var(--text-primary);display:block;margin-bottom:4px">Critical Defects Allowed</label>
          <input type="number" name="critical_allowed" value="0" min="0" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.85rem;background:var(--surface);color:var(--text-primary)">
          <p style="font-size:0.75rem;color:var(--text-tertiary);margin:4px 0 0">Zero tolerance for critical defects (recommended: 0).</p></div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="dash-btn dash-btn-outline dash-btn-sm" onclick="closeConfigModal()">Cancel</button>
        <button type="submit" name="save_aql_config" class="dash-btn dash-btn-primary dash-btn-sm">Save Configuration</button>
      </div>
    </form>
  </div>
</div>

<!-- Inspect Modal -->
<div class="modern-modal-overlay" id="inspectModal" style="display:none" onclick="if(event.target===this)closeInspectModal()">
  <div class="modern-modal" style="max-width:460px">
    <h3 style="margin:0 0 16px;font-size:1.05rem;font-weight:700;color:var(--text-primary)">Start AQL Inspection</h3>
    <form method="POST" id="inspectForm">
      <input type="hidden" name="order_id" id="inspectOrderId">
      <input type="hidden" name="workflow_id" id="inspectWorkflowId">
      <div style="display:flex;flex-direction:column;gap:14px">
        <div><label style="font-size:0.8125rem;font-weight:600;color:var(--text-primary);display:block;margin-bottom:4px">Lot Size (Total Pieces)</label>
          <input type="number" name="lot_size" id="inspectLotSize" min="1" required style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.85rem;background:var(--surface);color:var(--text-primary)">
          <p style="font-size:0.75rem;color:var(--text-tertiary);margin:4px 0 0">Based on order: <span id="inspectTotalQty"></span> pcs.</p></div>
        <div style="background:var(--role-accent-soft);border-radius:var(--radius-sm);padding:12px;font-size:0.8125rem;color:var(--text-primary)">
          <i class="fas fa-calculator" style="margin-right:6px;color:var(--role-accent)"></i>
          Sample size: <strong id="inspectSampleSize"></strong> | Accept: <strong id="inspectAccept"></strong> | Reject: <strong id="inspectReject"></strong> (AQL <span id="inspectAql"></span>)
        </div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="dash-btn dash-btn-outline dash-btn-sm" onclick="closeInspectModal()">Cancel</button>
        <button type="submit" name="start_inspection" class="dash-btn dash-btn-accent dash-btn-sm"><i class="fas fa-play"></i> Start Inspection</button>
      </div>
    </form>
  </div>
</div>
<script>
function openConfigModal(orderId, aql, il) {
  document.getElementById('configOrderId').value = orderId;
  document.getElementById('configAql').value = aql;
  document.getElementById('configIl').value = il;
  document.getElementById('configModal').style.display = 'flex';
}
function closeConfigModal() { document.getElementById('configModal').style.display = 'none'; }
function openInspectModal(orderId, workflowId, totalQty, sampleSize, accept, reject, aql) {
  document.getElementById('inspectOrderId').value = orderId;
  document.getElementById('inspectWorkflowId').value = workflowId;
  document.getElementById('inspectLotSize').value = totalQty;
  document.getElementById('inspectTotalQty').textContent = totalQty;
  document.getElementById('inspectSampleSize').textContent = sampleSize;
  document.getElementById('inspectAccept').textContent = accept;
  document.getElementById('inspectReject').textContent = reject;
  document.getElementById('inspectAql').textContent = aql;
  document.getElementById('inspectModal').style.display = 'flex';
}
function closeInspectModal() { document.getElementById('inspectModal').style.display = 'none'; }
document.getElementById('menuToggle')?.addEventListener('click', function() { document.getElementById('sidebar')?.classList.toggle('collapsed'); });
</script>
<?php $scriptsHtml = ob_get_clean();

$mainWorkspace = $alerts . $row2 . $recentSection . $scriptsHtml;

echo renderDashboardShell(renderPageHeader($pageTitle, 'Manage AQL sampling inspections for orders in QC.'), $kpiRow, $mainWorkspace);
?>
