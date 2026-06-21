<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../config/db_connect.php';
require_once '../../../../app/Middleware/auth_required.php';

$user_id = $_SESSION['user_id'];

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
$inspectedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = $user_id AND DATE(inspected_at) = CURDATE() AND result != 'Pending'")->fetchColumn();
$passedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = $user_id AND DATE(inspected_at) = CURDATE() AND result = 'Passed'")->fetchColumn();
$failedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = $user_id AND DATE(inspected_at) = CURDATE() AND result = 'Failed'")->fetchColumn();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>QC Dashboard — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <style>
    .mes-stat-row { display: grid; grid-template-columns: repeat(4,1fr); gap: 16px; margin-bottom: 24px; }
    .mes-stat { background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--border); padding: 20px; }
    .mes-stat-label { font-size: .8rem; color: var(--text-secondary); font-weight: 500; margin-bottom: 4px; }
    .mes-stat-value { font-size: 1.75rem; font-weight: 800; color: var(--text-primary); }
    .mes-card { background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--border); }
    .mes-card-header { padding: 16px 20px; border-bottom: 1px solid var(--border); }
    .mes-card-title { font-size: 1rem; font-weight: 700; margin: 0; }
    .mes-card-body { padding: 20px; }
    .mes-badge { font-size: .7rem; font-weight: 600; padding: 2px 10px; border-radius: 100px; }
    .mes-badge-danger { background: #fee2e2; color: #991b1b; }
    .mes-badge-warning { background: #fef3c7; color: #92400e; }
    .mes-badge-gray { background: #f1f5f9; color: #475569; }
    .mes-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: var(--radius-sm); font-size: .85rem; font-weight: 600; font-family: inherit; border: none; cursor: pointer; transition: .2s; text-decoration: none; }
    .mes-btn-primary { background: var(--accent); color: #fff; }
    .mes-btn-primary:hover { background: var(--accent-blue); }
  </style>
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../../../app/Views/Shared/Sidebars/qc_inspector.php'; ?>
  <div class="dash-main">
    <?php require_once '../../../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
      <div class="page-header">
        <h1>QC Dashboard</h1>
        <p>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></p>
      </div>

      <div class="kpi-row">
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-hourglass-half"></i></div>
          <div class="kpi-label">Pending Inspection</div>
          <div class="kpi-value"><?= $pendingCount ?></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-clipboard-check"></i></div>
          <div class="kpi-label">Inspected Today</div>
          <div class="kpi-value"><?= $inspectedToday ?></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-check-circle"></i></div>
          <div class="kpi-label">Passed</div>
          <div class="kpi-value"><?= $passedToday ?></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-times-circle"></i></div>
          <div class="kpi-label">Failed</div>
          <div class="kpi-value"><?= $failedToday ?></div>
        </div>
      </div>

      <div class="panel-card">
        <h3><i class="fas fa-search" style="color:var(--accent-blue)"></i> Awaiting Inspection</h3>
        <?php if ($pendingQC->rowCount() === 0): ?>
        <div style="font-size:.8rem;color:var(--text-tertiary);text-align:center;padding:16px 0">No orders pending inspection</div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px">
          <?php foreach ($pendingQC as $o): ?>
          <div class="task-card" style="border-left:3px solid <?= ($o['priority']??'medium') === 'urgent' ? 'var(--accent-red)' : (($o['priority']??'medium') === 'high' ? 'var(--accent-amber)' : 'var(--accent-blue)') ?>">
            <div class="task-header">
              <span class="task-id">#ORD-<?= $o['order_id'] ?></span>
              <?php if ($o['priority']): ?>
              <span class="qc-status <?= $o['priority'] === 'urgent' ? 'failed' : ($o['priority'] === 'high' ? 'pending' : 'passed') ?>"><?= ucfirst($o['priority']) ?></span>
              <?php endif; ?>
            </div>
            <div class="task-meta"><?= htmlspecialchars($o['product_type'] ?? 'Garment') ?> · <?= htmlspecialchars($o['customer_name']) ?> · by <?= htmlspecialchars($o['employee_name'] ?? 'Unassigned') ?></div>
            <div class="task-actions">
              <a href="inspect.php?order_id=<?= $o['order_id'] ?>" class="dash-btn dash-btn-primary dash-btn-sm"><i class="fas fa-search"></i> Inspect</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>
