<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../app/Middleware/auth_required.php';

if (get_user_role() === ROLE_ADMIN || get_user_role() === ROLE_MANAGER || get_user_role() === ROLE_EMPLOYEE) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Customer';

// KPIs
$totalOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$totalOrders->execute([$user_id]);

$activeOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('Pending','In Progress')");
$activeOrders->execute([$user_id]);

$pendingSample = $pdo->prepare("
    SELECT COUNT(*) FROM sample_approvals sa
    JOIN orders o ON sa.order_id = o.order_id
    WHERE o.user_id = ? AND sa.status = 'pending'
");
$pendingSample->execute([$user_id]);

$readyPickup = $pdo->prepare("
    SELECT COUNT(*) FROM orders o
    JOIN order_workflow ow ON o.order_id = ow.order_id
    WHERE o.user_id = ? AND ow.stage = ?
");
$readyPickup->execute([$user_id, STAGE_READY_PICKUP]);

$completedOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'Completed'");
$completedOrders->execute([$user_id]);

$recentOrders = $pdo->prepare("
    SELECT o.*, ow.stage, ow.priority, ow.expected_completion,
           s.service_name,
           (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) AS total_qty
    FROM orders o
    JOIN order_workflow ow ON o.order_id = ow.order_id
    JOIN services s ON o.service_id = s.service_id
    WHERE o.user_id = ?
    ORDER BY o.order_date DESC
    LIMIT 10
");
$recentOrders->execute([$user_id]);

$pendingSampleOrders = $pdo->prepare("
    SELECT o.order_id, o.order_date, sa.submitted_at, sa.approval_id, sa.status AS sample_status,
           s.service_name,
           (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) AS total_qty
    FROM sample_approvals sa
    JOIN orders o ON sa.order_id = o.order_id
    JOIN services s ON o.service_id = s.service_id
    WHERE o.user_id = ? AND sa.status = 'pending'
    ORDER BY sa.submitted_at DESC
");
$pendingSampleOrders->execute([$user_id]);
$pageTitle = 'My Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Dashboard — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <style>
    .kpi-icon-cust { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; }
    .order-card-cust { border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.25rem; background: var(--surface); transition: box-shadow 0.2s; }
    .order-card-cust:hover { box-shadow: var(--shadow-md); }
  </style>
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/customer.php'; ?>
  <div class="dash-main">
    <?php require_once '../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
      <div class="page-header">
        <h1>My Dashboard</h1>
        <p>Track your bulk orders and sample approvals, <?= htmlspecialchars(explode(' ', $full_name)[0]) ?></p>
      </div>

      <!-- KPI Cards -->
      <div class="kpi-row">
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#dbeafe;color:#2563eb"><i class="fas fa-shopping-bag"></i></div>
          <div class="kpi-label">Total Orders</div>
          <div class="kpi-value"><?= $totalOrders->fetchColumn() ?></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-spinner"></i></div>
          <div class="kpi-label">Active</div>
          <div class="kpi-value"><?= $activeOrders->fetchColumn() ?></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#f3e8ff;color:#7c3aed"><i class="fas fa-flask"></i></div>
          <div class="kpi-label">Pending Sample</div>
          <div class="kpi-value"><?= $pendingSample->fetchColumn() ?></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-check-circle"></i></div>
          <div class="kpi-label">Completed</div>
          <div class="kpi-value"><?= $completedOrders->fetchColumn() ?></div>
        </div>
      </div>

      <div class="dash-two-col">
        <!-- Recent Orders -->
        <div>
          <div class="section-header" style="margin-bottom:16px">
            <h2><i class="fas fa-folder-open" style="margin-right:8px;color:var(--accent-blue)"></i>Recent Orders</h2>
            <a href="my_orders.php" class="dash-btn dash-btn-outline dash-btn-sm"><i class="fas fa-external-link-alt"></i> View All</a>
          </div>

          <?php if ($recentOrders->rowCount() === 0): ?>
            <div class="panel-card" style="text-align:center;padding:32px">
              <i class="fas fa-inbox fa-2x" style="color:var(--text-tertiary);margin-bottom:12px"></i>
              <p style="color:var(--text-secondary)">No orders yet. <a href="place_order.php" style="color:var(--accent-blue);font-weight:600">Place your first order</a></p>
            </div>
          <?php else: ?>
            <?php foreach ($recentOrders as $o):
              $cs = $CUSTOMER_STAGE_MAP[$o['stage']] ?? 'Processing';
              $pct = getStageProgress($o['stage']);
              $stageColor = $STAGE_CONFIG[$o['stage']]['color'] ?? '#6b7280';
            ?>
            <a href="view_order.php?id=<?= $o['order_id'] ?>" class="text-decoration-none">
              <div class="task-card" style="margin-bottom:12px">
                <div class="task-header">
                  <span class="task-id">#ORD-<?= $o['order_id'] ?></span>
                  <span style="font-size:.7rem;padding:2px 10px;border-radius:100px;background:<?= $stageColor ?>20;color:<?= $stageColor ?>;font-weight:600"><?= htmlspecialchars($cs) ?></span>
                </div>
                <div class="task-meta"><?= htmlspecialchars($o['service_name']) ?> · Qty: <?= (int)$o['total_qty'] ?> · <?= date('M j, Y', strtotime($o['order_date'])) ?></div>
                <div class="task-progress">
                  <div class="bar"><div class="fill" style="width:<?= $pct ?>%;background:<?= $stageColor ?>"></div></div>
                  <span style="font-size:.7rem;color:var(--text-tertiary)"><?= $pct ?>%</span>
                </div>
              </div>
            </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Right Panel -->
        <div class="side-panel">
          <!-- Pending Sample Approvals -->
          <div class="panel-card">
            <h3><i class="fas fa-flask" style="color:#7c3aed"></i> Sample Approvals</h3>
            <?php if ($pendingSampleOrders->rowCount() === 0): ?>
              <div style="font-size:.8rem;color:var(--text-tertiary);text-align:center;padding:8px 0">No pending sample reviews.</div>
            <?php else: foreach ($pendingSampleOrders as $s): ?>
              <div class="activity-item">
                <span class="dot" style="background:#7c3aed"></span>
                <div class="text">
                  <strong>#ORD-<?= $s['order_id'] ?></strong> — <?= htmlspecialchars($s['service_name']) ?> · <?= (int)$s['total_qty'] ?> pcs
                  <div class="time">Submitted <?= date('M j', strtotime($s['submitted_at'])) ?></div>
                </div>
                <a href="sample_review.php" class="dash-btn dash-btn-primary dash-btn-sm" style="flex-shrink:0">Review</a>
              </div>
            <?php endforeach; endif; ?>
          </div>

          <!-- Quick Info -->
          <div class="panel-card">
            <h3><i class="fas fa-info-circle" style="color:var(--accent-blue)"></i> Bulk Order Info</h3>
            <ul style="list-style:none;padding:0;margin:0;font-size:.8rem;color:var(--text-secondary)">
              <li style="padding:6px 0"><i class="fas fa-check-circle text-success me-2"></i>Samples reviewed within 48 hours</li>
              <li style="padding:6px 0"><i class="fas fa-check-circle text-success me-2"></i>Bulk production starts after sample approval</li>
              <li style="padding:6px 0"><i class="fas fa-check-circle text-success me-2"></i>Real-time production tracking</li>
              <li style="padding:6px 0"><i class="fas fa-check-circle text-success me-2"></i>Notifications at every milestone</li>
            </ul>
          </div>

          <!-- Quick Stats -->
          <div class="panel-card">
            <h3><i class="fas fa-box-open" style="color:var(--accent-emerald)"></i> Ready for Pickup</h3>
            <div style="font-size:2rem;font-weight:800;color:var(--accent-emerald)"><?= $readyPickup->fetchColumn() ?></div>
            <div style="font-size:.8rem;color:var(--text-secondary)">items waiting for you</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});

function toggleNotifications() {
  fetch('/app/Controllers/notifications_api.php?action=list')
    .then(r => r.json())
    .then(data => {
      if (data.notifications?.length > 0) {
        alert('You have ' + data.notifications.length + ' notification(s). Check the Notifications page.');
      } else {
        alert('No new notifications.');
      }
    });
}
</script>
</body>
</html>
