<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../config/db_connect.php';
require_once __DIR__ . '/../../../../config/component_helpers.php';
require_once '../../../../app/Middleware/auth_required.php';

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Senior Tailor';
$firstName = htmlspecialchars(explode(' ', $full_name)[0]);

try {
    $userSql = "SELECT e.position_id FROM employees e WHERE e.user_id = ?";
    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();

    $positionSql = 'SELECT position_name FROM positions WHERE position_id = ?';
    $positionStmt = $pdo->prepare($positionSql);
    $positionStmt->execute([$user['position_id'] ?? 0]);
    $position = $positionStmt->fetch();
    $positionName = $position ? $position['position_name'] : '';

    if ($positionName !== 'Senior Tailor') {
        header('Location: /dashboards/employee/dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    error_log('Error: ' . $e->getMessage());
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$pageTitle = 'Quality Workspace';

// Stats (real DB queries)
$stmtPassed = $pdo->prepare("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = ? AND DATE(inspected_at) = CURDATE() AND result = 'Passed'");
$stmtPassed->execute([$user_id]);
$passedToday = $stmtPassed->fetchColumn() ?: 0;
$stmtFailed = $pdo->prepare("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = ? AND DATE(inspected_at) = CURDATE() AND result = 'Failed'");
$stmtFailed->execute([$user_id]);
$failedToday = $stmtFailed->fetchColumn() ?: 0;
$pendingCount = $pdo->query("SELECT COUNT(*) FROM order_workflow WHERE stage = 'Quality Inspection'")->fetchColumn() ?: 0;

// Next item to inspect
$nextItem = $pdo->query("
  SELECT o.order_id, ow.product_type, ow.priority,
         u.full_name AS customer_name, e.full_name AS employee_name
  FROM order_workflow ow
  JOIN orders o ON ow.order_id = o.order_id
  JOIN users u ON o.user_id = u.user_id
  LEFT JOIN users e ON ow.assigned_employee = e.user_id
  WHERE ow.stage = 'Quality Inspection'
  ORDER BY ow.expected_completion ASC LIMIT 1
")->fetch();

// Recent inspections
$stmtRecent = $pdo->prepare("
  SELECT qc.inspection_id, qc.result, qc.inspected_at, o.order_id
  FROM qc_inspections qc
  JOIN orders o ON qc.order_id = o.order_id
  WHERE qc.inspector_id = ?
  ORDER BY qc.inspected_at DESC LIMIT 5
");
$stmtRecent->execute([$user_id]);
$recentInspections = $stmtRecent->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quality Workspace — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="senior_tailor">
<div class="dash-layout">
  <?php require_once '../../../../app/Views/Shared/Sidebars/senior_tailor.php'; ?>
  <div class="dash-main">
<?php
// ── Build next item card ──
$nextItemHtml = '';
if ($nextItem):
  ob_start();
?>
<div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap">
  <div style="width:48px;height:48px;border-radius:12px;background:var(--role-accent-soft);color:var(--role-accent);display:flex;align-items:center;justify-content:center;font-size:1.3rem;flex-shrink:0">
    <i class="fas fa-tshirt"></i>
  </div>
  <div style="flex:1;min-width:0">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <strong style="font-size:0.95rem">QC-<?= $nextItem['order_id'] ?></strong>
      <span style="font-size:0.78rem;color:var(--text-tertiary)">Order #ORD-<?= $nextItem['order_id'] ?></span>
      <?php if ($nextItem['priority'] === 'urgent' || $nextItem['priority'] === 'high'):
        echo renderStatusBadge(ucfirst($nextItem['priority']), 'danger', 'sm');
      endif; ?>
    </div>
    <div style="font-size:0.85rem;font-weight:600;margin:2px 0"><?= htmlspecialchars($nextItem['product_type'] ?? 'Garment Item') ?></div>
    <div style="font-size:0.78rem;color:var(--text-tertiary)">Crafted by: <?= htmlspecialchars($nextItem['employee_name'] ?? 'Unassigned') ?></div>
  </div>
  <a href="item-to-inspect.php" class="dash-btn dash-btn-accent"><i class="fas fa-arrow-right"></i> Start Inspection</a>
</div>
<?php
  $nextItemHtml = ob_get_clean();
else:
  $nextItemHtml = renderEmptyState('fas fa-check-circle', 'No items pending inspection', 'All items have been reviewed.');
endif;

// ── Build performance metrics ──
$perfBody = '<p class="perf-sub" style="font-size:0.8rem;color:var(--text-tertiary);margin-bottom:14px">Quality check efficiency</p>';
$perfBody .= '<div class="metric-item" style="margin-bottom:12px">';
$perfBody .= '<div class="metric-label" style="display:flex;justify-content:space-between;font-size:0.78rem;margin-bottom:4px"><span>Inspection Rate</span><span>75%</span></div>';
$perfBody .= '<div class="metric-bar" style="height:6px;background:var(--surface-secondary);border-radius:3px;overflow:hidden"><div style="height:100%;width:75%;background:var(--role-accent);border-radius:3px"></div></div>';
$perfBody .= '</div>';
$perfBody .= '<div class="metric-item" style="margin-bottom:12px">';
$perfBody .= '<div class="metric-label" style="display:flex;justify-content:space-between;font-size:0.78rem;margin-bottom:4px"><span>Pass Rate</span><span>80%</span></div>';
$perfBody .= '<div class="metric-bar" style="height:6px;background:var(--surface-secondary);border-radius:3px;overflow:hidden"><div style="height:100%;width:80%;background:var(--color-success);border-radius:3px"></div></div>';
$perfBody .= '</div>';
$perfBody .= '<div class="metric-item">';
$perfBody .= '<div class="metric-label" style="display:flex;justify-content:space-between;font-size:0.78rem;margin-bottom:4px"><span>Accuracy</span><span>95%</span></div>';
$perfBody .= '<div class="metric-bar" style="height:6px;background:var(--surface-secondary);border-radius:3px;overflow:hidden"><div style="height:100%;width:95%;background:var(--color-info);border-radius:3px"></div></div>';
$perfBody .= '</div>';

// ── Build activity feed ──
$activityItems = [];
foreach ($recentInspections as $ri):
  $dotColor = $ri['result'] === 'Passed' ? 'var(--color-success)' : ($ri['result'] === 'Failed' ? 'var(--color-danger)' : 'var(--color-warning)');
  $activityItems[] = [
    'title' => 'QC-' . $ri['order_id'],
    'description' => htmlspecialchars(ucfirst($ri['result'] ?? 'Pending')),
    'time' => $ri['inspected_at'] ? date('g:i A', strtotime($ri['inspected_at'])) : '—',
    'dotColor' => $dotColor,
  ];
endforeach;

// ── Sidebar panels ──
$sidebarPanels = renderPanelCard('Today\'s Performance', $perfBody, 'fas fa-chart-line')
    . renderPanelCard('Recent Activity', renderActivityFeed($activityItems, ['emptyMessage' => 'No inspections performed today']), 'fas fa-clock');

echo renderDashboardShell(
  renderPageHeader(
    'Quality Workspace',
    "Welcome, {$firstName} · " . date('l, F j, Y'),
    ''),
  renderKPIRow([
    ['icon' => 'fas fa-check-circle',   'label' => 'Items Passed Today', 'value' => $passedToday,  'accent' => 'green'],
    ['icon' => 'fas fa-times-circle',   'label' => 'Items Failed',      'value' => $failedToday,  'accent' => 'red'],
    ['icon' => 'fas fa-hourglass-half', 'label' => 'Pending Inspections','value' => $pendingCount, 'accent' => 'amber'],
  ]),
  renderPageSection('Next Item to Inspect', $nextItemHtml, 'fas fa-binoculars')
);
echo renderTwoColumn(
  renderPageSection('Performance', $perfBody, 'fas fa-chart-line'),
  renderPageSection('Recent Activity', renderActivityFeed($activityItems, ['emptyMessage' => 'No inspections performed today']), 'fas fa-clock')
);
?>
    </div>
  </div>
</div>
</body>
</html>
