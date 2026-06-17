<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/role_admin_only.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar_admin.php';

$range = $_GET['range'] ?? 'week';
$startDate = $range === 'month' ? date('Y-m-d', strtotime('-30 days')) : date('Y-m-d', strtotime('-7 days'));

// Daily output
$dailyOutput = $pdo->prepare("SELECT DATE(order_date) as d, COUNT(*) as cnt FROM orders WHERE order_date >= ? AND order_date <= NOW() GROUP BY DATE(order_date) ORDER BY d");
$dailyOutput->execute([$startDate]);
$dailyData = $dailyOutput->fetchAll();
$dailyLabels = array_map(fn($r) => date('M d', strtotime($r['d'])), $dailyData);
$dailyValues = array_map(fn($r) => (int)$r['cnt'], $dailyData);
$dailyAvg = count($dailyValues) > 0 ? round(array_sum($dailyValues) / count($dailyValues)) : 0;

// Stage distribution
$stageDist = $pdo->query("SELECT ow.stage, COUNT(*) as cnt FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE o.status NOT IN ('Completed','Cancelled','Refunded') GROUP BY ow.stage ORDER BY cnt DESC")->fetchAll();
$stageTotal = array_sum(array_column($stageDist, 'cnt')) ?: 1;

// Employee performance
$empPerf = $pdo->prepare("SELECT u.full_name, e.position_id, p.position_name, COUNT(CASE WHEN o.status = 'Completed' AND o.completion_date >= ? THEN 1 END) as completed, COUNT(CASE WHEN o.status NOT IN ('Completed','Cancelled','Refunded') THEN 1 END) as active FROM users u JOIN employees e ON u.user_id = e.user_id JOIN positions p ON e.position_id = p.position_id JOIN order_workflow ow ON u.user_id = ow.assigned_employee JOIN orders o ON ow.order_id = o.order_id WHERE u.role IN ('employee','manager') GROUP BY u.user_id ORDER BY completed DESC");
$empPerf->execute([$startDate]);

// Avg completion time
$avgTime = $pdo->query("SELECT AVG(TIMESTAMPDIFF(HOUR, order_date, COALESCE(completion_date, NOW()))) as avg_hours FROM orders WHERE status = 'Completed'")->fetch();

// QC pass rate
$qcPass = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN result = 'Passed' THEN 1 ELSE 0 END) as passed FROM qc_inspections WHERE result != 'Pending'")->fetch();
$passRate = $qcPass['total'] > 0 ? round(($qcPass['passed'] / $qcPass['total']) * 100) : 0;

// AQL stats
$aqlTotal = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections")->fetchColumn();
$aqlPassed = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections WHERE verdict = 'Passed'")->fetchColumn();
$aqlFailed = $pdo->query("SELECT COUNT(*) FROM qc_lot_inspections WHERE verdict = 'Failed'")->fetchColumn();
$aqlRate = $aqlTotal > 0 ? round(($aqlPassed / $aqlTotal) * 100) : 0;

// Rework analysis
$reworkCount = $pdo->prepare("SELECT COUNT(*) FROM rework_log WHERE created_at >= ?");
$reworkCount->execute([$startDate]);
$reworkTopReasons = $pdo->prepare("SELECT reason, COUNT(*) as cnt FROM rework_log WHERE created_at >= ? GROUP BY reason ORDER BY cnt DESC LIMIT 5");
$reworkTopReasons->execute([$startDate]);

// Material consumption
$matConsumed = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM material_consumption_log WHERE created_at >= ? AND consumption_type != 'returned'");
$matConsumed->execute([$startDate]);
$matReturned = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM material_consumption_log WHERE created_at >= ? AND consumption_type = 'returned'");
$matReturned->execute([$startDate]);

// Sample approval stats
$sampleApproved = $pdo->prepare("SELECT COUNT(*) FROM sample_approvals WHERE status = 'approved' AND reviewed_at >= ?");
$sampleApproved->execute([$startDate]);
$sampleRejected = $pdo->prepare("SELECT COUNT(*) FROM sample_approvals WHERE status = 'rejected' AND reviewed_at >= ?");
$sampleRejected->execute([$startDate]);

// Bottleneck detection (stages with longest avg dwell time)
$bottlenecks = $pdo->query("SELECT ow.stage, COUNT(*) as cnt, AVG(TIMESTAMPDIFF(HOUR, COALESCE(ow.started_at, NOW()), NOW())) as avg_hours FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE o.status NOT IN ('Completed','Cancelled','Refunded') AND ow.started_at IS NOT NULL GROUP BY ow.stage ORDER BY avg_hours DESC LIMIT 5")->fetchAll();

// Order status breakdown
$orderStatus = $pdo->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status")->fetchAll();
$orderStatusTotal = array_sum(array_column($orderStatus, 'cnt')) ?: 1;
?>
<link rel="stylesheet" href="/public/assets/css/mes.css">
<style>
  body { background: #f5f5f5; }
  .main-content { margin-left: 220px; padding: 24px 32px; background: #f5f5f5; }
  .chart-bar { height: 8px; border-radius: 4px; transition: width .5s; }
  .stat-value-lg { font-size: 1.75rem; font-weight: 700; line-height: 1.2; }
  .stat-label-sm { font-size: 0.7rem; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; }
  .insight-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; }
  .bar-group { display: flex; gap: 2px; align-items: flex-end; height: 80px; }
  .bar-item { flex: 1; border-radius: 3px 3px 0 0; min-width: 8px; transition: height .3s; position: relative; }
  .bar-item:hover { opacity: .8; }
  .bar-item:hover::after { content: attr(data-tip); position: absolute; top: -24px; left: 50%; transform: translateX(-50%); background: #1f2937; color: #fff; font-size: 10px; padding: 2px 6px; border-radius: 4px; white-space: nowrap; }
</style>

<div class="main-content">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 style="font-size:20px;font-weight:700;margin:0">Production Analytics</h1>
      <p style="font-size:13px;color:#6b7280;margin-top:4px">Batch completion rates, bottlenecks, and performance metrics</p>
    </div>
    <div class="btn-group">
      <a href="?range=week" class="mes-btn mes-btn-sm <?= $range==='week'?'mes-btn-primary':'' ?>">Week</a>
      <a href="?range=month" class="mes-btn mes-btn-sm <?= $range==='month'?'mes-btn-primary':'' ?>">Month</a>
    </div>
  </div>

  <!-- KPI Row -->
  <div class="mes-stat-row" style="flex-wrap:wrap">
    <div class="mes-stat"><div class="mes-stat-label">Avg Completion</div><div class="mes-stat-value"><?= round($avgTime['avg_hours'] ?? 0) ?> <span style="font-size:14px;font-weight:400">hrs</span></div></div>
    <div class="mes-stat"><div class="mes-stat-label">QC Pass Rate</div><div class="mes-stat-value" style="color:<?= $passRate >= 80 ? 'var(--mes-success)' : 'var(--mes-danger)' ?>"><?= $passRate ?>%</div></div>
    <div class="mes-stat"><div class="mes-stat-label">AQL Pass Rate</div><div class="mes-stat-value" style="color:<?= $aqlRate >= 80 ? 'var(--mes-success)' : 'var(--mes-danger)' ?>"><?= $aqlRate ?>%</div></div>
    <div class="mes-stat"><div class="mes-stat-label">Daily Avg Orders</div><div class="mes-stat-value"><?= $dailyAvg ?></div></div>
    <div class="mes-stat"><div class="mes-stat-label">Reworks (period)</div><div class="mes-stat-value" style="color:var(--mes-danger)"><?= $reworkCount->fetchColumn() ?></div></div>
  </div>

  <div class="mes-layout">
    <div class="mes-main">

      <!-- Daily Trend -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Daily Order Volume</h3></div>
        <div class="mes-card-body">
          <?php if (count($dailyValues) > 0): ?>
          <div class="bar-group" style="height:100px">
            <?php $maxVal = max($dailyValues) ?: 1; foreach ($dailyValues as $i => $v): ?>
            <div class="bar-item" style="height:<?= ($v / $maxVal) * 100 ?>%;background:#3b82f6;flex:1;min-width:12px" data-tip="<?= $dailyLabels[$i] ?>: <?= $v ?>"></div>
            <?php endforeach; ?>
          </div>
          <div style="display:flex;justify-content:space-between;font-size:10px;color:#9ca3af;margin-top:4px">
            <span><?= $dailyLabels[0] ?? '' ?></span>
            <span><?= end($dailyLabels) ?></span>
          </div>
          <?php else: ?>
          <p class="text-muted small">No data for this period.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Stage Distribution -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Orders by Production Stage</h3></div>
        <div class="mes-card-body">
          <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach ($stageDist as $s):
              $pct = round(($s['cnt'] / $stageTotal) * 100);
              $color = $STAGE_CONFIG[$s['stage']]['color'] ?? '#6b7280';
            ?>
            <div>
              <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:2px">
                <span><?= htmlspecialchars($s['stage']) ?></span>
                <span style="color:#6b7280"><?= $s['cnt'] ?> (<?= $pct ?>%)</span>
              </div>
              <div style="height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:3px"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Employee Performance -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Employee Performance</h3></div>
        <div class="mes-card-body">
          <?php if ($empPerf->rowCount() > 0): foreach ($empPerf as $e): ?>
          <div style="display:flex;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #f3f4f6">
            <div style="width:32px;height:32px;border-radius:50%;background:#3b82f6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0">
              <?= strtoupper(substr($e['full_name'],0,2)) ?>
            </div>
            <div style="flex:1">
              <div style="font-size:13px;font-weight:500"><?= htmlspecialchars($e['full_name']) ?></div>
              <div style="font-size:11px;color:#6b7280"><?= htmlspecialchars($e['position_name']) ?></div>
            </div>
            <div style="text-align:right;font-size:12px">
              <div><span style="font-weight:600;color:#10b981"><?= $e['completed'] ?></span> done</div>
              <div><span style="font-weight:600;color:#3b82f6"><?= $e['active'] ?></span> active</div>
            </div>
          </div>
          <?php endforeach; else: ?>
          <p class="text-muted small">No employee data.</p>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <div class="mes-sidebar-right">

      <!-- Bottlenecks -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Bottleneck Stages</h3></div>
        <div class="mes-card-body">
          <?php if (count($bottlenecks) > 0): foreach ($bottlenecks as $b):
            $days = round($b['avg_hours'] / 24, 1);
          ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:12px">
            <span style="font-weight:500"><?= htmlspecialchars($b['stage']) ?></span>
            <span style="color:#6b7280"><?= $b['cnt'] ?> orders · <?= $days ?>d avg</span>
          </div>
          <?php endforeach; else: ?>
          <p class="text-muted small">No bottlenecks detected.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- QC & AQL Summary -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">QC &amp; AQL Summary</h3></div>
        <div class="mes-card-body" style="font-size:13px">
          <div style="display:flex;flex-direction:column;gap:8px">
            <div style="display:flex;justify-content:space-between"><span>QC Pending</span><span style="font-weight:600;color:var(--mes-warning)"><?= $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Pending'")->fetchColumn() ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>QC Passed</span><span style="font-weight:600;color:var(--mes-success)"><?= $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Passed'")->fetchColumn() ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>QC Failed</span><span style="font-weight:600;color:var(--mes-danger)"><?= $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Failed'")->fetchColumn() ?></span></div>
            <hr style="margin:4px 0">
            <div style="display:flex;justify-content:space-between"><span>AQL Lots</span><span style="font-weight:600"><?= $aqlTotal ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>AQL Passed</span><span style="font-weight:600;color:var(--mes-success)"><?= $aqlPassed ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>AQL Failed</span><span style="font-weight:600;color:var(--mes-danger)"><?= $aqlFailed ?></span></div>
          </div>
        </div>
      </div>

      <!-- Rework Analysis -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Rework Reasons</h3></div>
        <div class="mes-card-body">
          <?php if ($reworkTopReasons->rowCount() > 0): foreach ($reworkTopReasons as $r): ?>
          <div style="display:flex;justify-content:space-between;font-size:12px;padding:6px 0;border-bottom:1px solid #f3f4f6">
            <span><?= htmlspecialchars($r['reason'] ?: 'No reason') ?></span>
            <span style="font-weight:600;color:var(--mes-danger)"><?= $r['cnt'] ?></span>
          </div>
          <?php endforeach; else: ?>
          <p class="text-muted small">No reworks in this period.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Sample Approval Stats -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Sample Approvals</h3></div>
        <div class="mes-card-body" style="font-size:13px">
          <div style="display:flex;flex-direction:column;gap:8px">
            <div style="display:flex;justify-content:space-between"><span>Approved</span><span style="font-weight:600;color:var(--mes-success)"><?= $sampleApproved->fetchColumn() ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>Rejected</span><span style="font-weight:600;color:var(--mes-danger)"><?= $sampleRejected->fetchColumn() ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>Pending</span><span style="font-weight:600;color:var(--mes-warning)"><?= $pdo->query("SELECT COUNT(*) FROM sample_approvals WHERE status = 'pending'")->fetchColumn() ?></span></div>
          </div>
        </div>
      </div>

      <!-- Material Consumption -->
      <div class="mes-card">
        <div class="mes-card-header"><h3 class="mes-card-title">Materials (Period)</h3></div>
        <div class="mes-card-body" style="font-size:13px">
          <div style="display:flex;flex-direction:column;gap:8px">
            <div style="display:flex;justify-content:space-between"><span>Consumed</span><span style="font-weight:600"><?= number_format($matConsumed->fetchColumn(), 1) ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>Returned</span><span style="font-weight:600;color:var(--mes-success)"><?= number_format($matReturned->fetchColumn(), 1) ?></span></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
