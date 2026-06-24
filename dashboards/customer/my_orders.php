<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/auth_required.php';
require_once '../../app/Controllers/OrderController.php';

if (get_user_role() !== ROLE_CUSTOMER) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$orderController = new OrderController($pdo);
try {
    $orders = $orderController->getCustomerOrders($_SESSION['user_id']);
} catch (PDOException $e) {
    error_log('Error fetching orders: ' . $e->getMessage());
    $orders = [];
}

$pageTitle = 'My Orders';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="customer">
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/customer.php'; ?>
  <div class="dash-main">
<?php
// ── Build order cards ──
$ordersHtml = '';
if (empty($orders)):
  $ordersHtml = renderEmptyState('fas fa-inbox', 'No orders yet',
    'Place your first bulk order and track it here from start to finish.',
    ['label' => 'Place Your First Order', 'href' => 'place_order.php', 'icon' => 'fas fa-plus']);
else:
  ob_start();
  foreach ($orders as $o):
    $stage = $o['stage'] ?? '';
    $cs = $CUSTOMER_STAGE_MAP[$stage] ?? 'Processing';
    $pct = getStageProgress($stage);
    $stageColor = $STAGE_CONFIG[$stage]['color'] ?? 'var(--role-accent)';
    $pcVariant = strtolower($o['payment_status'] ?? 'pending') === 'paid' ? 'success' : 'warning';
    $completion = $o['expected_completion'] ? date('M j, Y', strtotime($o['expected_completion'])) : 'TBD';
    $staffName = $o['employee_name'] ? htmlspecialchars($o['employee_name']) : 'Not assigned';
    $designType = in_array($o['service_category'] ?? '', ['Embroidery', 'Screen Printing']) ? 'Standard' : 'Custom';
?>
<a href="view_order.php?id=<?= $o['order_id'] ?>" style="text-decoration:none;display:block;margin-bottom:16px">
  <div class="task-card" style="border-left:4px solid <?= $stageColor ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:12px">
      <div>
        <div style="display:flex;align-items:center;gap:8px">
          <span style="font-size:1.05rem;font-weight:700;color:var(--text-primary)">#ORD-<?= $o['order_id'] ?></span>
          <span style="font-size:0.85rem;color:var(--text-secondary)"><?= htmlspecialchars($o['service_name'] ?? '') ?></span>
        </div>
        <div style="font-size:0.78rem;color:var(--text-tertiary);margin-top:2px">
          <?= date('M d, Y', strtotime($o['order_date'])) ?> · <?= $designType ?> · Qty: <?= (int)($o['total_quantity'] ?? 0) ?>
        </div>
      </div>
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <?= renderStatusBadge(htmlspecialchars($cs), 'accent', 'sm') ?>
        <?= renderStatusBadge(htmlspecialchars($o['payment_status'] ?? 'Pending'), $pcVariant, 'sm') ?>
      </div>
    </div>

    <!-- Visual timeline dots for the 5 simplified stages -->
    <?php
    $tlStages = ['Order Confirmed', 'In Production', 'Quality Check', 'Ready for Pickup', 'Completed'];
    $currentIdx = array_search($cs, $tlStages);
    if ($currentIdx === false) $currentIdx = 0;
    ?>
    <div style="display:flex;gap:4px;margin-bottom:12px">
      <?php foreach ($tlStages as $i => $s):
        $dotClass = $i < $currentIdx ? 'is-complete' : ($i === $currentIdx ? 'is-active' : 'is-pending');
      ?>
      <div style="flex:1;text-align:center">
        <div class="timeline-dot <?= $dotClass ?>" style="width:20px;height:20px;margin:0 auto;font-size:0.5rem">
          <?php if ($i < $currentIdx): ?><i class="fas fa-check"></i><?php endif; ?>
        </div>
        <div style="font-size:0.6rem;color:var(--text-tertiary);margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= $s ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Progress bar -->
    <div class="progress-bar" style="margin-bottom:8px">
      <div class="progress-bar-track"><div class="progress-bar-fill" style="width:<?= $pct ?>%;background:<?= $stageColor ?>"></div></div>
      <span class="progress-bar-label"><?= $pct ?>%</span>
    </div>

    <!-- Meta info row -->
    <div style="display:flex;flex-wrap:wrap;gap:12px;font-size:0.78rem;color:var(--text-tertiary)">
      <span><i class="fas fa-user"></i> <?= $staffName ?></span>
      <span><i class="fas fa-calendar"></i> Due: <?= $completion ?></span>
      <span><i class="fas fa-tag"></i> ₱<?= number_format($o['total_price'] ?? 0, 2) ?></span>
    </div>
  </div>
</a>
<?php
  endforeach;
  $ordersHtml = ob_get_clean();
endif;

echo renderDashboardShell(
  renderPageHeader(
    'My Orders',
    'Track every order from placement to pickup',
    '',
    [['label' => 'Place New Order', 'href' => 'place_order.php', 'icon' => 'fas fa-plus', 'variant' => 'primary', 'size' => 'sm']]
  ),
  '',
  $ordersHtml
);
?>
    </div>
  </div>
</div>

<script src="/public/assets/js/order.js"></script>
</body>
</html>
