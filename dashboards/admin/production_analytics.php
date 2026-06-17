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
$dailyOutput = $pdo->prepare("
  SELECT DATE(order_date) as d, COUNT(*) as cnt
  FROM orders WHERE order_date >= ? AND order_date <= NOW()
  GROUP BY DATE(order_date) ORDER BY d
");
$dailyOutput->execute([$startDate]);

// Stage distribution
$stageDist = $pdo->query("
  SELECT ow.stage, COUNT(*) as cnt FROM order_workflow ow
  JOIN orders o ON ow.order_id = o.order_id
  WHERE o.status NOT IN ('Completed','Cancelled','Refunded')
  GROUP BY ow.stage ORDER BY cnt DESC
");

// Employee performance
$empPerf = $pdo->prepare("
  SELECT u.full_name,
    COUNT(CASE WHEN o.status = 'Completed' AND o.completion_date >= ? THEN 1 END) as completed,
    COUNT(CASE WHEN o.status NOT IN ('Completed','Cancelled','Refunded') THEN 1 END) as active
  FROM users u
  JOIN order_workflow ow ON u.user_id = ow.assigned_employee
  JOIN orders o ON ow.order_id = o.order_id
  WHERE u.role IN ('employee','manager')
  GROUP BY u.user_id
  ORDER BY completed DESC
");
$empPerf->execute([$startDate]);

// Efficiency
$avgTime = $pdo->query("
  SELECT AVG(TIMESTAMPDIFF(HOUR, order_date, COALESCE(completion_date, NOW()))) as avg_hours
  FROM orders WHERE status = 'Completed'
")->fetch();

// QC pass rate
$qcPass = $pdo->query("
  SELECT
    COUNT(*) as total,
    SUM(CASE WHEN result = 'Passed' THEN 1 ELSE 0 END) as passed
  FROM qc_inspections WHERE result != 'Pending'
")->fetch();
$passRate = $qcPass['total'] > 0 ? round(($qcPass['passed'] / $qcPass['total']) * 100) : 0;
?>
<link rel="stylesheet" href="/public/assets/css/mes.css">
<style>
  body { background: #f5f5f5; }
  .main-content { margin-left: 220px; padding: 24px 32px; background: #f5f5f5; }
</style>

<div class="main-content">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 style="font-size:20px;font-weight:700;margin:0">Production Analytics</h1>
      <p style="font-size:13px;color:#6b7280;margin-top:4px">Operational insights and performance metrics</p>
    </div>
    <div class="btn-group">
      <a href="?range=week" class="mes-btn mes-btn-sm <?= $range==='week'?'mes-btn-primary':'' ?>">Week</a>
      <a href="?range=month" class="mes-btn mes-btn-sm <?= $range==='month'?'mes-btn-primary':'' ?>">Month</a>
    </div>
  </div>

  <div class="mes-stat-row">
    <div class="mes-stat"><div class="mes-stat-label">Avg Completion Time</div><div class="mes-stat-value"><?= round($avgTime['avg_hours'] ?? 0) ?> <span style="font-size:14px;font-weight:400">hours</span></div></div>
    <div class="mes-stat"><div class="mes-stat-label">QC Pass Rate</div><div class="mes-stat-value" style="color:<?= $passRate >= 80 ? 'var(--mes-success)' : 'var(--mes-danger)' ?>"><?= $passRate ?>%</div></div>
    <div class="mes-stat"><div class="mes-stat-label">QC Inspections</div><div class="mes-stat-value"><?= $qcPass['total'] ?? 0 ?></div></div>
    <div class="mes-stat"><div class="mes-stat-label">Daily Avg Output</div><div class="mes-stat-value"><?= $dailyOutput->rowCount() > 0 ? round($dailyOutput->rowCount() / max(1, (strtotime(date('Y-m-d')) - strtotime($startDate)) / 86400)) : 0 ?></div></div>
  </div>

  <div class="mes-layout">
    <div class="mes-main">
      <!-- Stage Distribution -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Orders by Stage</h3></div>
        <div class="mes-card-body">
          <div style="display:flex;flex-direction:column;gap:10px">
            <?php foreach ($stageDist as $s):
              $total = $stageDist->rowCount() > 0 ? max(1, array_sum(array_column($stageDist->fetchAll(PDO::FETCH_ASSOC), 'cnt'))) : 1;
              $pct = round(($s['cnt'] / $total) * 100);
            ?>
            <div>
              <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                <span><?= htmlspecialchars($s['stage']) ?></span>
                <span style="color:#6b7280"><?= $s['cnt'] ?></span>
              </div>
              <div style="height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
                <div style="height:100%;width:<?= $pct ?>%;background:#3b82f6;border-radius:3px;transition:width 0.3s"></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Employee Performance -->
      <div class="mes-card">
        <div class="mes-card-header"><h3 class="mes-card-title">Employee Performance</h3></div>
        <div class="mes-card-body">
          <div style="display:flex;flex-direction:column;gap:12px">
            <?php foreach ($empPerf as $e): ?>
            <div style="display:flex;align-items:center;gap:12px">
              <div style="width:32px;height:32px;border-radius:50%;background:#3b82f6;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0">
                <?= strtoupper(substr($e['full_name'],0,2)) ?>
              </div>
              <div style="flex:1">
                <div style="font-size:13px;font-weight:500"><?= htmlspecialchars($e['full_name']) ?></div>
                <div style="display:flex;gap:16px;font-size:12px;color:#6b7280;margin-top:2px">
                  <span><span style="font-weight:600;color:#10b981"><?= $e['completed'] ?></span> completed</span>
                  <span><span style="font-weight:600;color:#3b82f6"><?= $e['active'] ?></span> active</span>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="mes-sidebar-right">
      <!-- Bottlenecks -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Bottlenecks</h3></div>
        <div class="mes-card-body">
          <?php
          $bottlenecks = $pdo->query("
            SELECT ow.stage, COUNT(*) as cnt, AVG(TIMESTAMPDIFF(DAY, ow.started_at, NOW())) as avg_days
            FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id
            WHERE o.status NOT IN ('Completed','Cancelled','Refunded') AND ow.started_at IS NOT NULL
            GROUP BY ow.stage ORDER BY avg_days DESC LIMIT 5
          ");
          if ($bottlenecks->rowCount() > 0): foreach ($bottlenecks as $b): ?>
          <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:13px">
            <span><?= htmlspecialchars($b['stage']) ?></span>
            <span style="color:#6b7280"><?= $b['cnt'] ?> orders · <?= round($b['avg_days']) ?>d avg</span>
          </div>
          <?php endforeach; else: ?>
          <p style="font-size:13px;color:#6b7280;margin:0">No bottlenecks detected</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- QC Stats -->
      <div class="mes-card">
        <div class="mes-card-header"><h3 class="mes-card-title">QC Summary</h3></div>
        <div class="mes-card-body">
          <?php
          $qcPending = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Pending'")->fetchColumn();
          $qcPassed = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Passed'")->fetchColumn();
          $qcFailed = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Failed'")->fetchColumn();
          ?>
          <div style="display:flex;flex-direction:column;gap:10px;font-size:13px">
            <div style="display:flex;justify-content:space-between"><span>Pending</span><span style="font-weight:600;color:var(--mes-warning)"><?= $qcPending ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>Passed</span><span style="font-weight:600;color:var(--mes-success)"><?= $qcPassed ?></span></div>
            <div style="display:flex;justify-content:space-between"><span>Failed</span><span style="font-weight:600;color:var(--mes-danger)"><?= $qcFailed ?></span></div>
          </div>
          <a href="quality_control.php" class="mes-btn mes-btn-sm" style="margin-top:12px;width:100%;justify-content:center">Open QC Panel</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
