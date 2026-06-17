<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../middleware/auth_required.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar_customer.php';

if (get_user_role() === ROLE_ADMIN || get_user_role() === ROLE_MANAGER || get_user_role() === ROLE_EMPLOYEE) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

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
?>
<link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
<style>
  .main-content { margin-left: 220px; padding: 24px 32px; background: #f8fafc; min-height: 100vh; }
  @media (max-width: 768px) { .main-content { margin-left: 0; padding: 16px; } }
  .kpi-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
  .order-card { border: 1px solid #e2e8f0; border-radius: 16px; padding: 1.25rem; background: #fff; transition: box-shadow 0.2s; }
  .order-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
  .stage-badge { font-size: 0.7rem; padding: 2px 8px; border-radius: 10px; font-weight: 500; }
  .progress-thin { height: 6px; border-radius: 3px; }
  .sample-card { border-left: 4px solid #7c3aed; }
</style>

<main class="main-content">
  <div class="content-container">

    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h4 class="fw-bold mb-1">My Dashboard</h4>
        <p class="text-muted small mb-0">Track your bulk orders and sample approvals</p>
      </div>
      <a href="place_order.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Order</a>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
          <div class="card-body d-flex align-items-center gap-3 p-3">
            <div class="kpi-icon" style="background: #dbeafe;"><i class="fas fa-shopping-bag" style="color: #2563eb;"></i></div>
            <div><div class="text-muted small lh-1">Total</div><div class="fs-5 fw-bold"><?= $totalOrders->fetchColumn() ?></div></div>
          </div>
        </div>
      </div>
      <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
          <div class="card-body d-flex align-items-center gap-3 p-3">
            <div class="kpi-icon" style="background: #fef3c7;"><i class="fas fa-spinner" style="color: #d97706;"></i></div>
            <div><div class="text-muted small lh-1">Active</div><div class="fs-5 fw-bold"><?= $activeOrders->fetchColumn() ?></div></div>
          </div>
        </div>
      </div>
      <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
          <div class="card-body d-flex align-items-center gap-3 p-3">
            <div class="kpi-icon" style="background: #f3e8ff;"><i class="fas fa-flask" style="color: #7c3aed;"></i></div>
            <div><div class="text-muted small lh-1">Sample</div><div class="fs-5 fw-bold"><?= $pendingSample->fetchColumn() ?></div></div>
          </div>
        </div>
      </div>
      <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
          <div class="card-body d-flex align-items-center gap-3 p-3">
            <div class="kpi-icon" style="background: #d1fae5;"><i class="fas fa-check-circle" style="color: #059669;"></i></div>
            <div><div class="text-muted small lh-1">Completed</div><div class="fs-5 fw-bold"><?= $completedOrders->fetchColumn() ?></div></div>
          </div>
        </div>
      </div>
      <div class="col-md-2 col-6">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
          <div class="card-body d-flex align-items-center gap-3 p-3">
            <div class="kpi-icon" style="background: #ccfbf1;"><i class="fas fa-box-open" style="color: #0d9488;"></i></div>
            <div><div class="text-muted small lh-1">Ready</div><div class="fs-5 fw-bold"><?= $readyPickup->fetchColumn() ?></div></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- Recent Orders -->
      <div class="col-lg-8">
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
          <div class="card-header bg-transparent border-bottom-0 pt-3 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-semibold mb-0"><i class="fas fa-folder-open me-2" style="color: #2563eb;"></i>Recent Orders</h5>
            <a href="my_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
          </div>
          <div class="card-body p-4">
            <?php if ($recentOrders->rowCount() === 0): ?>
              <div class="text-center text-muted py-4">
                <i class="fas fa-inbox fa-2x mb-2" style="color: #94a3b8;"></i>
                <p class="mb-0">No orders yet. <a href="place_order.php" class="text-decoration-none">Place your first order</a></p>
              </div>
            <?php else: ?>
              <?php foreach ($recentOrders as $o):
                $cs = $CUSTOMER_STAGE_MAP[$o['stage']] ?? 'Processing';
                $pct = getStageProgress($o['stage']);
                $stageColor = $STAGE_CONFIG[$o['stage']]['color'] ?? '#6b7280';
              ?>
              <a href="view_order.php?id=<?= $o['order_id'] ?>" class="text-decoration-none">
                <div class="order-card mb-3">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                      <span class="fw-semibold" style="color:#1e293b;">#ORD-<?= $o['order_id'] ?></span>
                      <span class="text-muted small ms-2"><?= date('M j, Y', strtotime($o['order_date'])) ?></span>
                    </div>
                    <span class="stage-badge" style="background:<?= $stageColor ?>20;color:<?= $stageColor ?>">
                      <?= htmlspecialchars($cs) ?>
                    </span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center">
                    <div>
                      <span class="text-muted small"><?= htmlspecialchars($o['service_name']) ?></span>
                      <span class="mx-2 text-muted">|</span>
                      <span class="text-muted small">Qty: <?= (int)$o['total_qty'] ?></span>
                    </div>
                    <span class="small text-muted"><?= $pct ?>%</span>
                  </div>
                  <div class="progress progress-thin mt-2">
                    <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $stageColor ?>"></div>
                  </div>
                </div>
              </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Right Column: Sample Approvals + Info -->
      <div class="col-lg-4">
        <!-- Pending Sample Approvals -->
        <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
          <div class="card-header bg-transparent border-bottom-0 pt-3 px-4">
            <h5 class="fw-semibold mb-0"><i class="fas fa-flask me-2" style="color: #7c3aed;"></i>Sample Approvals</h5>
          </div>
          <div class="card-body p-4">
            <?php if ($pendingSampleOrders->rowCount() === 0): ?>
              <div class="text-muted small py-3 text-center">No pending sample reviews.</div>
            <?php else: foreach ($pendingSampleOrders as $s): ?>
              <div class="sample-card p-3 mb-2" style="background:#faf5ff;border-radius:12px;">
                <div class="d-flex justify-content-between align-items-start">
                  <div>
                    <div class="fw-medium small">#ORD-<?= $s['order_id'] ?></div>
                    <div class="text-muted small"><?= htmlspecialchars($s['service_name']) ?> · <?= (int)$s['total_qty'] ?> pcs</div>
                    <div class="text-muted small">Submitted: <?= date('M j', strtotime($s['submitted_at'])) ?></div>
                  </div>
                  <a href="sample_review.php" class="btn btn-sm" style="background:#7c3aed;color:#fff;border-radius:8px;font-size:11px;">Review</a>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>

        <!-- Quick Info -->
        <div class="card border-0 shadow-sm" style="border-radius: 16px;">
          <div class="card-header bg-transparent border-bottom-0 pt-3 px-4">
            <h5 class="fw-semibold mb-0"><i class="fas fa-info-circle me-2" style="color: #3b82f6;"></i>Bulk Order Info</h5>
          </div>
          <div class="card-body p-4">
            <ul class="list-unstyled small mb-0">
              <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Samples reviewed within 48 hours</li>
              <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Bulk production starts after sample approval</li>
              <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>AQL sampling QC on every batch</li>
              <li class="mb-2"><i class="fas fa-check-circle text-success me-2"></i>Real-time production tracking</li>
              <li><i class="fas fa-check-circle text-success me-2"></i>Notifications at every milestone</li>
            </ul>
          </div>
        </div>
      </div>
    </div>

  </div>
</main>

<?php require_once '../../includes/footer.php'; ?>
