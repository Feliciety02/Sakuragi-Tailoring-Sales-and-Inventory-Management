<?php
require_once __DIR__ . '/../../../../config/db_connect.php';
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../config/component_helpers.php';
require_once __DIR__ . '/../../../../app/Middleware/auth_check.php';

$pageTitle = 'AQL Lot Inspection';

$stmt = $pdo->prepare("SELECT li.*, o.order_id, o.total_price, ow.assigned_employee, u.full_name AS customer_name, (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) AS total_qty FROM qc_lot_inspections li JOIN orders o ON li.order_id = o.order_id JOIN order_workflow ow ON li.workflow_id = ow.workflow_id JOIN users u ON o.user_id = u.user_id WHERE li.verdict = 'Pending' ORDER BY li.created_at ASC");
$stmt->execute();
$pending = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT li.*, o.order_id FROM qc_lot_inspections li JOIN orders o ON li.order_id = o.order_id WHERE li.inspector_id = ? ORDER BY li.updated_at DESC LIMIT 10");
$stmt->execute([$_SESSION['user_id']]);
$my_recent = $stmt->fetchAll();

$pending_count = $pdo->prepare("SELECT COUNT(*) FROM qc_lot_inspections WHERE inspector_id = ? AND verdict = 'Pending'");
$pending_count->execute([$_SESSION['user_id']]);
$pending_count = $pending_count->fetchColumn();

$passed_today = $pdo->prepare("SELECT COUNT(*) FROM qc_lot_inspections WHERE inspector_id = ? AND verdict = 'Passed' AND DATE(inspected_at) = CURDATE()");
$passed_today->execute([$_SESSION['user_id']]);
$passed_today = $passed_today->fetchColumn();

$failed_today = $pdo->prepare("SELECT COUNT(*) FROM qc_lot_inspections WHERE inspector_id = ? AND verdict = 'Failed' AND DATE(inspected_at) = CURDATE()");
$failed_today->execute([$_SESSION['user_id']]);
$failed_today = $failed_today->fetchColumn();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?> — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="quality_control_inspector">
<div class="dash-layout">
  <?php require_once __DIR__ . '/../../../../app/Views/Shared/Sidebars/qc_inspector.php'; ?>
  <div class="dash-main">
<?php
$kpiRow = renderKPIRow([
    ['value' => $pending_count, 'label' => 'Pending Inspections', 'icon' => 'fas fa-hourglass-half', 'accent' => 'amber'],
    ['value' => $passed_today, 'label' => 'Passed Today', 'icon' => 'fas fa-check', 'accent' => 'green'],
    ['value' => $failed_today, 'label' => 'Failed Today', 'icon' => 'fas fa-times', 'accent' => 'red'],
]);

// Pending lot cards
ob_start();
if (count($pending) === 0):
    echo renderEmptyState('fas fa-check-circle', 'No pending inspections', 'All inspections are up to date.');
else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px">
<?php foreach ($pending as $lot): ?>
<div style="background:var(--bg-primary);border:1px solid var(--border-color);border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:12px">
<div style="display:flex;justify-content:space-between;align-items:center">
<div style="display:flex;gap:6px;align-items:center">
<span class="dash-badge dash-badge-warning"><i class="fas fa-clock"></i> Pending</span>
<span style="font-size:0.75rem;padding:2px 8px;background:var(--bg-secondary);border-radius:4px;color:var(--text-tertiary)">Lot #<?= $lot['lot_inspection_id'] ?></span>
</div>
<span style="font-size:0.8125rem;font-weight:600;color:var(--text-primary)">AQL <?= $lot['aql_level'] ?></span>
</div>
<div style="font-size:0.9375rem;font-weight:600;color:var(--text-primary)">Order #<?= $lot['order_id'] ?></div>
<div style="font-size:0.8125rem;color:var(--text-secondary)"><?= htmlspecialchars($lot['customer_name']) ?></div>
<div style="display:flex;gap:16px;font-size:0.75rem;color:var(--text-tertiary)">
<span><i class="fas fa-box"></i> Lot: <strong style="color:var(--text-primary)"><?= $lot['lot_size'] ?></strong></span>
<span><i class="fas fa-search"></i> Sample: <strong style="color:var(--text-primary)"><?= $lot['sample_size'] ?></strong></span>
<?php $acRe = getAQLAcceptReject($lot['aql_level'], $lot['sample_size']); ?>
<span><i class="fas fa-check-circle"></i> Ac/Re: <strong style="color:var(--text-primary)"><?= $acRe[0] ?>/<?= $acRe[1] ?></strong></span>
</div>
<a href="aql_inspect.php?lot_inspection_id=<?= $lot['lot_inspection_id'] ?>" class="dash-btn dash-btn-primary dash-btn-sm" style="align-self:flex-start"><i class="fas fa-clipboard-check"></i> Inspect Lot</a>
</div>
<?php endforeach; ?>
</div>
<?php endif;
$pendingSection = ob_get_clean();

// Recent inspections table
ob_start();
if (count($my_recent) === 0):
    echo renderEmptyState('fas fa-history', 'No inspections performed yet', 'Your inspection history will appear here.');
else:
    $recentCols = [
        ['label' => 'Lot #'], ['label' => 'Order'], ['label' => 'Lot/Sample'], ['label' => 'Defects C/M/m'], ['label' => 'Verdict', 'type' => 'badge'], ['label' => 'Date'],
    ];
    $recentData = [];
    foreach ($my_recent as $lot):
        $verdict = $lot['verdict'];
        $verdictBadge = $verdict === 'Passed' ? 'success' : ($verdict === 'Failed' ? 'danger' : 'warning');
        $recentData[] = [
            ['text' => '#' . $lot['lot_inspection_id']],
            ['text' => '#' . $lot['order_id']],
            ['text' => $lot['lot_size'] . '/' . $lot['sample_size']],
            ['text' => $lot['critical_defects'] . '/' . $lot['major_defects'] . '/' . $lot['minor_defects']],
            ['text' => $verdict],
            ['text' => $lot['inspected_at'] ? date('M j, g:i A', strtotime($lot['inspected_at'])) : '—'],
        ];
    endforeach;
    echo renderDataTable('recent-table', $recentCols, $recentData);
endif;
$recentSection = ob_get_clean();

$mainWorkspace = '<div style="display:flex;flex-direction:column;gap:20px">' . renderPanelCard('Pending Lot Inspections', $pendingSection, 'fas fa-clock') . renderPanelCard('My Recent Inspections', $recentSection, 'fas fa-history') . '</div>';

echo renderDashboardShell(renderPageHeader($pageTitle, 'Manage AQL lot inspections.'), $kpiRow, $mainWorkspace);
?>
    </div>
  </div>
</div>
</body>
</html>
