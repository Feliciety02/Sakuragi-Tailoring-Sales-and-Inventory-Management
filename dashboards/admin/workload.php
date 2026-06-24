<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/role_admin_only.php';

$employees = $pdo->query("
    SELECT u.user_id, u.full_name, u.email,
        (SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = u.user_id AND o.status NOT IN ('Completed','Cancelled')) AS active_tasks,
        (SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = u.user_id AND o.status = 'Completed' AND o.completion_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS weekly_completed,
        (SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = u.user_id AND o.status NOT IN ('Completed','Cancelled') AND ow.stage IN ('Rework','Quality Inspection')) AS qc_pending,
        (SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = u.user_id AND o.status NOT IN ('Completed','Cancelled') AND ow.priority = 'urgent') AS urgent_tasks
    FROM users u
    WHERE u.role = 'employee'
    ORDER BY active_tasks DESC
");

$totalActive = 0;
$employeeData = [];
foreach ($employees as $e) {
    $totalActive += $e['active_tasks'];
    $employeeData[] = $e;
}
$totalEmployees = count($employeeData);
$avgLoad = $totalEmployees > 0 ? round($totalActive / $totalEmployees, 1) : 0;

$stageDist = $pdo->query("
    SELECT ow.stage, COUNT(*) as cnt, GROUP_CONCAT(DISTINCT e.full_name SEPARATOR ', ') as emp_names
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    LEFT JOIN users e ON ow.assigned_employee = e.user_id
    WHERE o.status NOT IN ('Completed','Cancelled')
    GROUP BY ow.stage
    ORDER BY FIELD(ow.stage,
        'Order Received','Design Review','Material Preparation','Cutting',
        'Printing / Embroidery','Sewing & Assembly','Quality Inspection',
        'Rework','Packaging','Ready for Pickup')
");

$recentDone = $pdo->query("
    SELECT o.order_id, u.full_name, o.completion_date, o.total_price
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.status = 'Completed'
    ORDER BY o.completion_date DESC LIMIT 10
");

$pageTitle = 'Workload Dashboard';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Workload Dashboard — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="admin">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
$metricsRow = renderKPIRow([
  ['label' => 'Active Employees', 'value' => (string)$totalEmployees, 'icon' => 'fas fa-users', 'accent' => 'blue'],
  ['label' => 'Total Active Tasks', 'value' => (string)$totalActive, 'icon' => 'fas fa-tasks', 'accent' => 'amber'],
  ['label' => 'Avg Load / Employee', 'value' => (string)$avgLoad, 'icon' => 'fas fa-chart-bar', 'accent' => 'red'],
]);

// Employee load cards
$empHtml = '';
if (empty($employeeData)):
  $empHtml = '<p style="font-size:0.85rem;color:var(--text-tertiary);text-align:center;padding:16px 0;margin:0">No employees found</p>';
else:
  ob_start();
  foreach ($employeeData as $e):
    $initial = strtoupper(substr($e['full_name'], 0, 1));
?>
<div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-color)">
  <div style="width:36px;height:36px;border-radius:50%;background:var(--role-accent);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:0.88rem;flex-shrink:0"><?= $initial ?></div>
  <div style="flex:1;min-width:0">
    <p style="margin:0;font-size:0.82rem;font-weight:600;color:var(--text-primary)"><?= htmlspecialchars($e['full_name']) ?></p>
    <p style="margin:2px 0 0;font-size:0.75rem;color:var(--text-tertiary)"><?= $e['active_tasks'] ?> active · <?= $e['weekly_completed'] ?> this week</p>
  </div>
  <div style="display:flex;gap:4px;flex-shrink:0">
    <?php if ($e['urgent_tasks'] > 0): ?><?= renderStatusBadge($e['urgent_tasks'] . ' urgent', 'danger', 'sm') ?><?php endif; ?>
    <?php if ($e['qc_pending'] > 0): ?><?= renderStatusBadge($e['qc_pending'] . ' QC', 'warning', 'sm') ?><?php endif; ?>
  </div>
</div>
<?php
  endforeach;
  $empHtml = ob_get_clean();
endif;
$mainCol = renderPageSection('Employee Load', $empHtml);

// Recently Completed
$recentHtml = '';
if ($recentDone->rowCount() === 0):
  $recentHtml = '<p style="font-size:0.85rem;color:var(--text-tertiary);text-align:center;padding:8px 0;margin:0">None yet</p>';
else:
  ob_start();
  foreach ($recentDone as $r):
?>
<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border-color);font-size:0.82rem">
  <span style="color:var(--text-primary)"><strong>#ORD-<?= $r['order_id'] ?></strong> — <?= htmlspecialchars($r['full_name']) ?></span>
  <span style="color:var(--text-tertiary)">₱<?= number_format($r['total_price'], 2) ?> · <?= date('M d', strtotime($r['completion_date'])) ?></span>
</div>
<?php
  endforeach;
  $recentHtml = ob_get_clean();
endif;
$mainCol .= renderPageSection('Recently Completed', $recentHtml);

// Stage Distribution sidebar
$sidebarHtml = '';
ob_start();
foreach ($stageDist as $s):
  $cfg = $STAGE_CONFIG[$s['stage']] ?? ['color' => '#6b7280', 'label' => $s['stage']];
  $pct = $totalActive > 0 ? round($s['cnt'] / $totalActive * 100) : 0;
?>
<div style="margin-bottom:10px">
  <div style="display:flex;justify-content:space-between;font-size:0.75rem;margin-bottom:3px">
    <span style="color:var(--text-primary);font-weight:500"><?= htmlspecialchars($cfg['label']) ?></span>
    <span style="color:var(--text-tertiary)"><?= $s['cnt'] ?> (<?= $pct ?>%)</span>
  </div>
  <div class="progress-bar" style="height:6px">
    <div class="progress-bar-track" style="height:100%"><div class="progress-bar-fill" style="width:<?= $pct ?>%;height:100%;background:<?= $cfg['color'] ?>"></div></div>
  </div>
</div>
<?php
endforeach;
$sidebarHtml = renderPageSection('Stage Distribution', ob_get_clean(), '', [], 'sidebar');

$workspace = renderTwoColumn($mainCol, $sidebarHtml) . '<script>document.getElementById(\'menuToggle\')?.addEventListener(\'click\', function() { document.getElementById(\'sidebar\')?.classList.toggle(\'collapsed\'); });</script>';
echo renderDashboardShell(
  renderPageHeader('Workload Dashboard', 'Employee capacity and task distribution.'),
  $metricsRow,
  $workspace
);
