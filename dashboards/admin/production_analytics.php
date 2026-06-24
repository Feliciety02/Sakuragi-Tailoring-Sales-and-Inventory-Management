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
    .chart-bar { height: 8px; border-radius: 4px; transition: width .5s; }
    .bar-group { display: flex; gap: 2px; align-items: flex-end; height: 80px; }
    .bar-item { flex: 1; border-radius: 3px 3px 0 0; min-width: 8px; transition: height .3s; position: relative; background: var(--accent-color); }
    .bar-item:hover { opacity: .8; }
    .bar-item:hover::after { content: attr(data-tip); position: absolute; top: -24px; left: 50%; transform: translateX(-50%); background: #1f2937; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; white-space: nowrap; z-index:10; }
    .insight-row { display: flex; gap: 8px; align-items: center; font-size: 12px; padding: 6px 0; border-bottom: 1px solid var(--border-color); }
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
  ['value' => round($avgTime['avg_hours'] ?? 0) . ' <span style="font-size:0.9rem;font-weight:400;color:var(--text-tertiary)">hrs</span>', 'label' => 'Avg Completion', 'icon' => 'fas fa-clock'],
  ['value' => $passRate . '%', 'label' => 'QC Pass Rate', 'icon' => 'fas fa-check-circle', 'accent' => $passRate >= 80 ? 'green' : 'red'],
  ['value' => $aqlRate . '%', 'label' => 'AQL Pass Rate', 'icon' => 'fas fa-clipboard-check', 'accent' => $aqlRate >= 80 ? 'green' : 'red'],
  ['value' => $dailyAvg, 'label' => 'Daily Avg Orders', 'icon' => 'fas fa-chart-line'],
  ['value' => $reworkCount->fetchColumn(), 'label' => 'Reworks (period)', 'icon' => 'fas fa-undo', 'accent' => 'red'],
]);

// Daily trend
ob_start(); ?>
<?php if (count($dailyValues) > 0): ?>
<div class="bar-group" style="height:100px;margin-bottom:4px">
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
<div style="display:flex;flex-direction:column;gap:8px">
<?php foreach ($stageDist as $s):
  $pct = round(($s['cnt'] / $stageTotal) * 100);
  $color = $STAGE_CONFIG[$s['stage']]['color'] ?? '#6b7280';
?>
<div>
  <div style="display:flex;justify-content:space-between;font-size:0.8125rem;margin-bottom:2px">
    <span style="color:var(--text-primary)"><?= htmlspecialchars($s['stage']) ?></span>
    <span style="color:var(--text-tertiary)"><?= $s['cnt'] ?> (<?= $pct ?>%)</span>
  </div>
  <div style="height:6px;background:var(--border-color);border-radius:3px;overflow:hidden">
    <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:3px"></div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php $stageSection = ob_get_clean();

// Employee performance
ob_start();
if ($empPerf->rowCount() > 0): foreach ($empPerf as $e): ?>
<div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid var(--border-color)">
<div style="width:32px;height:32px;border-radius:50%;background:var(--accent-bg);color:var(--accent-color);display:flex;align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0"><?= strtoupper(substr($e['full_name'],0,2)) ?></div>
<div style="flex:1;min-width:0">
<div style="font-size:0.8125rem;font-weight:500;color:var(--text-primary)"><?= htmlspecialchars($e['full_name']) ?></div>
<div style="font-size:0.7rem;color:var(--text-tertiary)"><?= htmlspecialchars($e['position_name'] ?? '') ?></div>
</div>
<div style="text-align:right;font-size:0.75rem">
<div><span style="font-weight:600;color:#10b981"><?= $e['completed'] ?></span> done</div>
<div><span style="font-weight:600;color:var(--accent-color)"><?= $e['active'] ?></span> active</div>
</div>
</div>
<?php endforeach; else: ?>
<p style="text-align:center;padding:8px;color:var(--text-tertiary);font-size:0.85rem">No employee data.</p>
<?php endif;
$empSection = ob_get_clean();

$qcPending = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Pending'")->fetchColumn();
$qcPassedCount = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Passed'")->fetchColumn();
$qcFailedCount = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Failed'")->fetchColumn();
$samplePending = $pdo->query("SELECT COUNT(*) FROM sample_approvals WHERE status = 'pending'")->fetchColumn();

ob_start(); ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
<div style="display:flex;flex-direction:column;gap:20px">
<?= renderPanelCard('Daily Order Volume', $dailyChart, 'fas fa-chart-bar') ?>
<?= renderPanelCard('Employee Performance', $empSection, 'fas fa-users') ?>
</div>
<div style="display:flex;flex-direction:column;gap:20px">
<?= renderPanelCard('Orders by Production Stage', $stageSection, 'fas fa-layer-group') ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
<?php
$sidebarCards = '';
// Bottlenecks
ob_start(); ?>
<?php if (count($bottlenecks) > 0): foreach ($bottlenecks as $b): $days = round($b['avg_hours'] / 24, 1); ?>
<div class="insight-row">
<span style="font-weight:500;color:var(--text-primary)"><?= htmlspecialchars($b['stage']) ?></span>
<span style="color:var(--text-tertiary)"><?= $b['cnt'] ?> orders · <?= $days ?>d avg</span>
</div>
<?php endforeach; else: ?>
<p style="text-align:center;padding:8px;color:var(--text-tertiary);font-size:0.85rem">No bottlenecks detected.</p>
<?php endif;
$sidebarCards .= renderPanelCard('Bottleneck Stages', ob_get_clean(), 'fas fa-exclamation-triangle');

ob_start(); ?>
<div style="display:flex;flex-direction:column;gap:8px;font-size:0.8125rem">
<div class="insight-row"><span>QC Pending</span><span style="font-weight:600;color:#f59e0b"><?= $qcPending ?></span></div>
<div class="insight-row"><span>QC Passed</span><span style="font-weight:600;color:#10b981"><?= $qcPassedCount ?></span></div>
<div class="insight-row"><span>QC Failed</span><span style="font-weight:600;color:#ef4444"><?= $qcFailedCount ?></span></div>
<hr style="margin:2px 0;border:none;border-top:1px solid var(--border-color)">
<div class="insight-row"><span>AQL Lots</span><span style="font-weight:600"><?= $aqlTotal ?></span></div>
<div class="insight-row"><span>AQL Passed</span><span style="font-weight:600;color:#10b981"><?= $aqlPassed ?></span></div>
<div class="insight-row"><span>AQL Failed</span><span style="font-weight:600;color:#ef4444"><?= $aqlFailed ?></span></div>
</div>
<?php $sidebarCards .= renderPanelCard('QC & AQL Summary', ob_get_clean(), 'fas fa-clipboard-check');

ob_start(); ?>
<?php if ($reworkTopReasons->rowCount() > 0): foreach ($reworkTopReasons as $r): ?>
<div class="insight-row">
<span><?= htmlspecialchars($r['reason'] ?: 'No reason') ?></span>
<span style="font-weight:600;color:#ef4444"><?= $r['cnt'] ?></span>
</div>
<?php endforeach; else: ?>
<p style="text-align:center;padding:8px;color:var(--text-tertiary);font-size:0.85rem">No reworks in this period.</p>
<?php endif;
$sidebarCards .= renderPanelCard('Rework Reasons', ob_get_clean(), 'fas fa-undo');

ob_start(); ?>
<div style="display:flex;flex-direction:column;gap:8px;font-size:0.8125rem">
<div class="insight-row"><span>Approved</span><span style="font-weight:600;color:#10b981"><?= $sampleApproved->fetchColumn() ?></span></div>
<div class="insight-row"><span>Rejected</span><span style="font-weight:600;color:#ef4444"><?= $sampleRejected->fetchColumn() ?></span></div>
<div class="insight-row"><span>Pending</span><span style="font-weight:600;color:#f59e0b"><?= $samplePending ?></span></div>
</div>
<?php $sidebarCards .= renderPanelCard('Sample Approvals', ob_get_clean(), 'fas fa-check-double');

ob_start(); ?>
<div style="display:flex;flex-direction:column;gap:8px;font-size:0.8125rem">
<div class="insight-row"><span>Consumed</span><span style="font-weight:600"><?= number_format($matConsumed->fetchColumn(), 1) ?></span></div>
<div class="insight-row"><span>Returned</span><span style="font-weight:600;color:#10b981"><?= number_format($matReturned->fetchColumn(), 1) ?></span></div>
</div>
<?php $sidebarCards .= renderPanelCard('Materials (Period)', ob_get_clean(), 'fas fa-box');

echo $sidebarCards;
?>
</div>
</div>
</div>
<?php
$mainWorkspace = ob_get_clean() . '<script>document.getElementById(\'menuToggle\')?.addEventListener(\'click\', function() { document.getElementById(\'sidebar\')?.classList.toggle(\'collapsed\'); });</script>';

echo renderDashboardShell($header, $kpiRow, $mainWorkspace);
?>
