<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../config/db_connect.php';
require_once __DIR__ . '/../../../../config/component_helpers.php';
require_once '../../../../app/Middleware/auth_required.php';

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'QC Inspector';
$firstName = htmlspecialchars(explode(' ', $full_name)[0]);

// Restrict to QC Inspectors
$pos = $pdo->prepare("SELECT p.position_name FROM employees e JOIN positions p ON e.position_id = p.position_id WHERE e.user_id = ?");
$pos->execute([$user_id]);
$posName = $pos->fetchColumn();
if ($posName !== 'Quality Control Inspector') {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$pageTitle = 'QC Dashboard';

// Stats
$pendingCount = $pdo->query("SELECT COUNT(*) FROM order_workflow WHERE stage = 'Quality Inspection'")->fetchColumn();
$stmtInsp = $pdo->prepare("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = ? AND DATE(inspected_at) = CURDATE() AND result != 'Pending'");
$stmtInsp->execute([$user_id]);
$inspectedToday = $stmtInsp->fetchColumn();
$stmtPass = $pdo->prepare("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = ? AND DATE(inspected_at) = CURDATE() AND result = 'Passed'");
$stmtPass->execute([$user_id]);
$passedToday = $stmtPass->fetchColumn();
$stmtFail = $pdo->prepare("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = ? AND DATE(inspected_at) = CURDATE() AND result = 'Failed'");
$stmtFail->execute([$user_id]);
$failedToday = $stmtFail->fetchColumn();

// Orders awaiting QC
$pendingQC = $pdo->query("
    SELECT o.order_id, o.order_date, o.total_price,
           ow.product_type, ow.expected_completion, ow.priority, ow.assigned_employee,
           u.full_name AS customer_name,
           e.full_name AS employee_name,
           qc.result AS qc_result
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN users e ON ow.assigned_employee = e.user_id
    LEFT JOIN qc_inspections qc ON o.order_id = qc.order_id
    WHERE ow.stage = 'Quality Inspection' AND (qc.result IS NULL OR qc.result = 'Pending')
    ORDER BY ow.expected_completion ASC
");
$pendingQCList = $pendingQC->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QC Dashboard — Sakuragi</title>
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
  <?php require_once '../../../../app/Views/Shared/Sidebars/qc_inspector.php'; ?>
  <div class="dash-main">
<?php
// ── Build awaiting inspection cards ──
$inspectHtml = '';
if (empty($pendingQCList)):
  $inspectHtml = renderEmptyState('fas fa-check-circle', 'No orders pending inspection', 'All QC reviews are up to date.');
else:
  ob_start();
  echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px">';
  foreach ($pendingQCList as $o):
    $bClass = ($o['priority']??'medium') === 'urgent' ? 'border-left-urgent' : (($o['priority']??'medium') === 'high' ? 'border-left-high' : '');
    $sVariant = $o['priority'] === 'urgent' ? 'danger' : ($o['priority'] === 'high' ? 'warning' : 'neutral');
?>
<div class="task-card <?= $bClass ?>">
  <div class="task-card-header">
    <span class="task-card-title">#ORD-<?= $o['order_id'] ?></span>
    <?php if ($o['priority']): ?><?= renderStatusBadge(ucfirst($o['priority']), $sVariant, 'sm') ?><?php endif; ?>
  </div>
  <div class="task-card-meta">
    <?= htmlspecialchars($o['product_type'] ?? 'Garment') ?> · <?= htmlspecialchars($o['customer_name']) ?> · by <?= htmlspecialchars($o['employee_name'] ?? 'Unassigned') ?>
  </div>
  <div class="task-card-footer">
    <a href="inspect.php?order_id=<?= $o['order_id'] ?>" class="dash-btn dash-btn-accent dash-btn-sm"><i class="fas fa-search"></i> Inspect</a>
  </div>
</div>
<?php
  endforeach;
  echo '</div>';
  $inspectHtml = ob_get_clean();
endif;

echo renderDashboardShell(
  renderPageHeader(
    'QC Dashboard',
    "Welcome, {$firstName} · " . date('l, F j'),
    ''),
  renderKPIRow([
    ['icon' => 'fas fa-hourglass-half',  'label' => 'Pending Inspection', 'value' => $pendingCount,    'accent' => 'amber'],
    ['icon' => 'fas fa-clipboard-check',  'label' => 'Inspected Today',   'value' => $inspectedToday,  'accent' => 'blue'],
    ['icon' => 'fas fa-check-circle',     'label' => 'Passed',            'value' => $passedToday,     'accent' => 'green'],
    ['icon' => 'fas fa-times-circle',     'label' => 'Failed',            'value' => $failedToday,     'accent' => 'red'],
  ]),
  renderPageSection('Awaiting Inspection', $inspectHtml, 'fas fa-search',
    [['label' => 'AQL Lot Inspection', 'href' => 'aql_dashboard.php', 'icon' => 'fas fa-chart-pie', 'variant' => 'outline'],
     ['label' => 'History', 'href' => 'history.php', 'icon' => 'fas fa-history', 'variant' => 'outline']])
);
?>
    </div>
  </div>
</div>
</body>
</html>
