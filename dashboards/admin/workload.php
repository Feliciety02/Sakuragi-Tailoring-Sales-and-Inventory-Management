<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/role_admin_only.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar_admin.php';

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

// Department stats
$totalActive = 0;
$totalCapacity = 0;
$employeeData = [];
foreach ($employees as $e) {
    $totalActive += $e['active_tasks'];
    $employeeData[] = $e;
}
$totalEmployees = count($employeeData);
$avgLoad = $totalEmployees > 0 ? round($totalActive / $totalEmployees, 1) : 0;

// Stage distribution with employee names
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

// Recently completed
$recentDone = $pdo->query("
    SELECT o.order_id, u.full_name, o.completion_date, o.total_price
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    WHERE o.status = 'Completed'
    ORDER BY o.completion_date DESC LIMIT 10
");
?>
<link rel="stylesheet" href="/public/assets/css/mes.css">
<style>
  body { background: #f5f5f5; }
  .main-content { margin-left: 220px; padding: 24px 32px; background: #f5f5f5; }
</style>

<div class="main-content">
  <div class="mb-4">
    <h1 style="font-size:20px;font-weight:700;margin:0">Workload Dashboard</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:4px">Employee capacity and task distribution</p>
  </div>

  <!-- Summary stats -->
  <div class="mes-stat-row">
    <div class="mes-stat">
      <div class="mes-stat-label">Active Employees</div>
      <div class="mes-stat-value"><?= $totalEmployees ?></div>
    </div>
    <div class="mes-stat">
      <div class="mes-stat-label">Total Active Tasks</div>
      <div class="mes-stat-value"><?= $totalActive ?></div>
    </div>
    <div class="mes-stat">
      <div class="mes-stat-label">Avg Load / Employee</div>
      <div class="mes-stat-value"><?= $avgLoad ?></div>
    </div>
  </div>

  <div class="mes-layout">
    <div class="mes-main">
      <!-- Employee workload cards -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Employee Load</h3></div>
        <div class="mes-card-body" style="padding:16px 20px">
          <?php if (empty($employeeData)): ?>
          <p style="font-size:13px;color:#6b7280;margin:0;text-align:center;padding:16px 0">No employees found</p>
          <?php else: foreach ($employeeData as $e): ?>
          <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid #f3f4f6">
            <div style="width:36px;height:36px;border-radius:50%;background:var(--mes-primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:600;font-size:14px;flex-shrink:0">
              <?= strtoupper(substr($e['full_name'], 0, 1)) ?>
            </div>
            <div style="flex:1;min-width:0">
              <p style="margin:0;font-size:13px;font-weight:600;color:#374151"><?= htmlspecialchars($e['full_name']) ?></p>
              <p style="margin:2px 0 0;font-size:12px;color:#6b7280"><?= $e['active_tasks'] ?> active · <?= $e['weekly_completed'] ?> this week</p>
            </div>
            <div style="text-align:right;flex-shrink:0">
              <div style="display:flex;gap:4px;justify-content:flex-end">
                <?php if ($e['urgent_tasks'] > 0): ?>
                <span class="mes-badge mes-badge-danger"><?= $e['urgent_tasks'] ?> urgent</span>
                <?php endif; ?>
                <?php if ($e['qc_pending'] > 0): ?>
                <span class="mes-badge mes-badge-warning"><?= $e['qc_pending'] ?> QC</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <!-- Recently Completed -->
      <div class="mes-card">
        <div class="mes-card-header"><h3 class="mes-card-title">Recently Completed</h3></div>
        <div class="mes-card-body">
          <?php if ($recentDone->rowCount() === 0): ?>
          <p style="font-size:13px;color:#6b7280;margin:0;text-align:center;padding:8px 0">None yet</p>
          <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:4px;font-size:13px">
            <?php foreach ($recentDone as $r): ?>
            <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f3f4f6">
              <span><strong>#ORD-<?= $r['order_id'] ?></strong> — <?= htmlspecialchars($r['full_name']) ?></span>
              <span style="color:#6b7280">₱<?= number_format($r['total_price'], 2) ?> · <?= date('M d', strtotime($r['completion_date'])) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="mes-sidebar-right">
      <!-- Stage Distribution -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Stage Distribution</h3></div>
        <div class="mes-card-body" style="padding:12px 16px">
          <?php foreach ($stageDist as $s):
            $cfg = $STAGE_CONFIG[$s['stage']] ?? ['color'=>'#6b7280','label'=>$s['stage']];
            $pct = $totalActive > 0 ? round($s['cnt'] / $totalActive * 100) : 0;
          ?>
          <div style="margin-bottom:10px">
            <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:3px">
              <span style="color:#374151;font-weight:500"><?= htmlspecialchars($cfg['label']) ?></span>
              <span style="color:#6b7280"><?= $s['cnt'] ?> (<?= $pct ?>%)</span>
            </div>
            <div style="height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
              <div style="width:<?= $pct ?>%;height:100%;background:<?= $cfg['color'] ?>;border-radius:3px"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
