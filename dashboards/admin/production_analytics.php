<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/role_admin_only.php';

$range = $_GET['range'] ?? 'week';
$startDate = $range === 'month' ? date('Y-m-d', strtotime('-30 days')) : date('Y-m-d', strtotime('-7 days'));

$dailyOutput = $pdo->prepare("SELECT DATE(order_date) as d, COUNT(*) as cnt FROM orders WHERE order_date >= ? AND order_date <= NOW() GROUP BY DATE(order_date) ORDER BY d");
$dailyOutput->execute([$startDate]);
$dailyData = $dailyOutput->fetchAll();
$dailyLabels = array_map(fn($r) => date('M d', strtotime($r['d'])), $dailyData);
$dailyValues = array_map(fn($r) => (int)$r['cnt'], $dailyData);
$dailyAvg = count($dailyValues) > 0 ? round(array_sum($dailyValues) / count($dailyValues)) : 0;

$stageDist = $pdo->query("SELECT ow.stage, COUNT(*) as cnt FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE o.status NOT IN ('Completed','Cancelled','Refunded') GROUP BY ow.stage ORDER BY cnt DESC")->fetchAll();
$stageTotal = array_sum(array_column($stageDist, 'cnt')) ?: 1;

$empPerf = $pdo->prepare("SELECT u.full_name, e.position_id, p.position_name, COUNT(CASE WHEN o.status = 'Completed' AND o.completion_date >= ? THEN 1 END) as completed, COUNT(CASE WHEN o.status NOT IN ('Completed','Cancelled','Refunded') THEN 1 END) as active FROM users u JOIN employees e ON u.user_id = e.user_id JOIN positions p ON e.position_id = p.position_id JOIN order_workflow ow ON u.user_id = ow.assigned_employee JOIN orders o ON ow.order_id = o.order_id WHERE u.role IN ('employee','manager') GROUP BY u.user_id ORDER BY completed DESC");
$empPerf->execute([$startDate]);

$avgTime = $pdo->query("SELECT AVG(TIMESTAMPDIFF(HOUR, order_date, COALESCE(completion_date, NOW()))) as avg_hours FROM orders WHERE status = 'Completed'")->fetch();
$qcPass = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN result = 'Passed' THEN 1 ELSE 0 END) as passed FROM qc_inspections WHERE result != 'Pending'")->fetch();
$passRate = $qcPass['total'] > 0 ? round(($qcPass['passed'] / $qcPass['total']) * 100) : 0;
$aqlTotal = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections")->fetchColumn();
$aqlPassed = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections WHERE verdict = 'Passed'")->fetchColumn();
$aqlFailed = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections WHERE verdict = 'Failed'")->fetchColumn();
$aqlRate = $aqlTotal > 0 ? round(($aqlPassed / $aqlTotal) * 100) : 0;
$reworkCount = $pdo->prepare("SELECT COUNT(*) FROM rework_log WHERE created_at >= ?");
$reworkCount->execute([$startDate]);
$reworkCountVal = (int)$reworkCount->fetchColumn();
$reworkTopReasons = $pdo->prepare("SELECT reason, COUNT(*) as cnt FROM rework_log WHERE created_at >= ? GROUP BY reason ORDER BY cnt DESC LIMIT 5");
$reworkTopReasons->execute([$startDate]);
$matConsumed = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM material_consumption_log WHERE created_at >= ? AND consumption_type != 'returned'");
$matConsumed->execute([$startDate]);
$matReturned = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM material_consumption_log WHERE created_at >= ? AND consumption_type = 'returned'");
$matReturned->execute([$startDate]);
$sampleApproved = $pdo->prepare("SELECT COUNT(*) FROM sample_approvals WHERE status = 'approved' AND reviewed_at >= ?");
$sampleApproved->execute([$startDate]);
$sampleRejected = $pdo->prepare("SELECT COUNT(*) FROM sample_approvals WHERE status = 'rejected' AND reviewed_at >= ?");
$sampleRejected->execute([$startDate]);
$bottlenecks = $pdo->query("SELECT ow.stage, COUNT(*) as cnt, AVG(TIMESTAMPDIFF(HOUR, COALESCE(ow.started_at, NOW()), NOW())) as avg_hours FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE o.status NOT IN ('Completed','Cancelled','Refunded') AND ow.started_at IS NOT NULL GROUP BY ow.stage ORDER BY avg_hours DESC LIMIT 5")->fetchAll();
$pageTitle = 'Production Analytics';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Production Analytics — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="analytics-styles">
    .bar-group { display:flex; align-items:flex-end; gap:3px; height:120px; }
    .bar-item { flex:1; border-radius:4px 4px 0 0; min-width:6px; transition:height .4s ease; position:relative; }
    .bar-item:hover { opacity:.7; }
    .bar-item:hover::after { content:attr(data-tip); position:absolute; top:-26px; left:50%; transform:translateX(-50%); background:#1f2937; color:#fff; font-size:10px; padding:3px 8px; border-radius:6px; white-space:nowrap; z-index:10; }
  </style>
</head>
<body data-role="admin">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
$header = renderPageHeader('Production Analytics', 'Batch completion rates, bottlenecks, and performance metrics', '', [
  ['label' => 'Week', 'href' => '?range=week', 'variant' => $range === 'week' ? 'primary' : 'outline', 'size' => 'sm'],
  ['label' => 'Month', 'href' => '?range=month', 'variant' => $range === 'month' ? 'primary' : 'outline', 'size' => 'sm'],
]);

$kpiRow = renderKPIRow([
  ['value' => round($avgTime['avg_hours'] ?? 0) . ' <span style="font-size:0.9rem;font-weight:400;color:var(--text-tertiary)">hrs</span>', 'label' => 'Avg Completion', 'icon' => 'fas fa-clock', 'accent' => 'blue'],
  ['value' => $passRate . '%', 'label' => 'QC Pass Rate', 'icon' => 'fas fa-check-circle', 'accent' => $passRate >= 80 ? 'green' : 'red'],
  ['value' => $aqlRate . '%', 'label' => 'AQL Pass Rate', 'icon' => 'fas fa-clipboard-check', 'accent' => $aqlRate >= 80 ? 'green' : 'red'],
  ['value' => $dailyAvg, 'label' => 'Daily Avg Orders', 'icon' => 'fas fa-chart-line', 'accent' => 'amber'],
  ['value' => $reworkCountVal, 'label' => 'Reworks (period)', 'icon' => 'fas fa-undo', 'accent' => 'red'],
]);

// Daily trend
ob_start(); ?>
<?php if (count($dailyValues) > 0): ?>
<div class="bar-group" style="margin-bottom:4px">
  <?php $maxVal = max($dailyValues) ?: 1; foreach ($dailyValues as $i => $v): ?>
  <div class="bar-item" style="height:<?= ($v / $maxVal) * 100 ?>%;background:var(--accent-color)" data-tip="<?= $dailyLabels[$i] ?>: <?= $v ?>"></div>
  <?php endforeach; ?>
</div>
<div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text-tertiary)">
  <span><?= $dailyLabels[0] ?? '' ?></span><span><?= end($dailyLabels) ?></span>
</div>
<?php else: ?>
<p style="text-align:center;padding:16px;color:var(--text-tertiary);font-size:0.85rem">No data for this period.</p>
<?php endif;
$dailyChart = ob_get_clean();

// Stage distribution
ob_start(); ?>
<div style="display:flex;flex-direction:column;gap:6px">
<?php foreach ($stageDist as $s):
  $pct = round(($s['cnt'] / $stageTotal) * 100);
  $color = $STAGE_CONFIG[$s['stage']]['color'] ?? 'var(--role-accent)';
?>
<div>
  <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:4px">
    <span style="font-weight:500;color:var(--text-primary)"><?= htmlspecialchars($s['stage']) ?></span>
    <span style="color:var(--text-tertiary)"><?= $s['cnt'] ?> (<?= $pct ?>%)</span>
  </div>
  <div class="progress-bar-track">
    <div class="progress-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>"></div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php $stageSection = ob_get_clean();

// Employee performance
ob_start();
if ($empPerf->rowCount() > 0): foreach ($empPerf as $e): ?>
<div class="info-row">
  <div style="display:flex;align-items:center;gap:10px">
    <span class="avatar-initials avatar-initials-sm"><?= strtoupper(substr($e['full_name'],0,2)) ?></span>
    <div>
      <div style="font-weight:500;font-size:0.85rem;color:var(--text-primary)"><?= htmlspecialchars($e['full_name']) ?></div>
      <div style="font-size:0.7rem;color:var(--text-tertiary);margin-top:1px"><?= htmlspecialchars($e['position_name'] ?? '') ?></div>
    </div>
  </div>
  <div style="text-align:right;font-size:0.75rem">
    <div><span style="font-weight:600;color:var(--color-success)"><?= $e['completed'] ?></span> done</div>
    <div><span style="font-weight:600;color:var(--role-accent)"><?= $e['active'] ?></span> active</div>
  </div>
</div>
<?php endforeach; else: ?>
<?= renderEmptyState('fas fa-users', 'No data', 'No employee activity in this period.') ?>
<?php endif;
$empSection = ob_get_clean();

// ── Build clean 3-row layout ──
$mainWorkspace = '';

// Row 1: Production Trend + Stage Distribution
$row1Main = renderPanelCard('Production Trend', $dailyChart, 'fas fa-chart-bar');
$row1Side = renderPanelCard('Orders by Stage', $stageSection, 'fas fa-layer-group');
$mainWorkspace .= '<div class="dash-two-col" style="margin-bottom:24px"><div class="dash-main-col">' . $row1Main . '</div><div class="dash-side-col">' . $row1Side . '</div></div>';

// Row 2: Employee Performance + Bottlenecks
$bottleneckBody = '';
if (count($bottlenecks) > 0):
  foreach ($bottlenecks as $b):
    $days = round($b['avg_hours'] / 24, 1);
    $pri = $days > 14 ? 'danger' : ($days > 7 ? 'warning' : 'info');
    $bottleneckBody .= '<div class="info-row"><span class="info-row-label"><span class="status-dot" style="background:var(--color-' . $pri . ')"></span> ' . htmlspecialchars($b['stage']) . '</span><span class="info-row-value" style="color:var(--color-' . $pri . ')">' . $b['cnt'] . ' orders &middot; ' . $days . 'd avg</span></div>';
  endforeach;
else:
  $bottleneckBody = renderEmptyState('fas fa-check-circle', 'No Bottlenecks', 'All stages running smoothly.');
endif;
$row2Main = renderPanelCard('Employee Performance', $empSection, 'fas fa-users');
$row2Side = renderPanelCard('Bottlenecks', $bottleneckBody, 'fas fa-exclamation-triangle');
$mainWorkspace .= '<div class="dash-two-col" style="margin-bottom:24px"><div class="dash-main-col">' . $row2Main . '</div><div class="dash-side-col">' . $row2Side . '</div></div>';

// Row 3: Quality + Materials
$qcPending = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Pending'")->fetchColumn();
$qcPassedCount = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Passed'")->fetchColumn();
$qcFailedCount = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Failed'")->fetchColumn();
$samplePending = $pdo->query("SELECT COUNT(*) FROM sample_approvals WHERE status = 'pending'")->fetchColumn();

$qualityBody = '';
$qualityBody .= '<div class="info-row"><span class="info-row-label">QC Pass Rate</span><span class="info-row-value" style="color:var(--color-success)">' . $passRate . '%</span></div>';
$qualityBody .= '<div class="info-row"><span class="info-row-label">Reworks</span><span class="info-row-value" style="color:var(--color-danger)">' . $reworkCountVal . '</span></div>';
$qualityBody .= '<div class="info-row"><span class="info-row-label">QC Passed</span><span class="info-row-value" style="color:var(--color-success)">' . $qcPassedCount . '</span></div>';
$qualityBody .= '<div class="info-row"><span class="info-row-label">QC Failed</span><span class="info-row-value" style="color:var(--color-danger)">' . $qcFailedCount . '</span></div>';
$qualityBody .= '<div class="info-row"><span class="info-row-label">QC Pending</span><span class="info-row-value" style="color:var(--color-warning)">' . $qcPending . '</span></div>';

$materialsBody = '';
$materialsBody .= '<div class="info-row"><span class="info-row-label">Material Consumed</span><span class="info-row-value">' . number_format($matConsumed->fetchColumn(), 1) . '</span></div>';
$materialsBody .= '<div class="info-row"><span class="info-row-label">Material Returned</span><span class="info-row-value" style="color:var(--color-success)">' . number_format($matReturned->fetchColumn(), 1) . '</span></div>';
$materialsBody .= '<div class="info-row"><span class="info-row-label">Samples Pending</span><span class="info-row-value" style="color:var(--color-warning)">' . $samplePending . '</span></div>';

$row3Main = renderPanelCard('Quality Overview', $qualityBody, 'fas fa-clipboard-check');
$row3Side = renderPanelCard('Materials & Samples', $materialsBody, 'fas fa-box');
$mainWorkspace .= '<div class="dash-two-col" style="margin-bottom:24px"><div class="dash-main-col">' . $row3Main . '</div><div class="dash-side-col">' . $row3Side . '</div></div>';

echo renderDashboardShell($header, $kpiRow, $mainWorkspace);
?>
</div>
</div>
</body>
</html>
