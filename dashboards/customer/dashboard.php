<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/auth_required.php';

if (get_user_role() === ROLE_ADMIN || get_user_role() === ROLE_MANAGER || get_user_role() === ROLE_EMPLOYEE) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Customer';
$firstName = htmlspecialchars(explode(' ', $full_name)[0]);

// KPIs
$totalOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$totalOrders->execute([$user_id]);
$totalOrdersVal = (int)$totalOrders->fetchColumn();

$activeOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('Pending','In Progress')");
$activeOrders->execute([$user_id]);
$activeOrdersVal = (int)$activeOrders->fetchColumn();

$pendingSample = $pdo->prepare("
    SELECT COUNT(*) FROM sample_approvals sa
    JOIN orders o ON sa.order_id = o.order_id
    WHERE o.user_id = ? AND sa.status = 'pending'
");
$pendingSample->execute([$user_id]);
$pendingSampleVal = (int)$pendingSample->fetchColumn();

$readyPickup = $pdo->prepare("
    SELECT COUNT(*) FROM orders o
    JOIN order_workflow ow ON o.order_id = ow.order_id
    WHERE o.user_id = ? AND ow.stage = ?
");
$readyPickup->execute([$user_id, STAGE_READY_PICKUP]);
$readyPickupVal = (int)$readyPickup->fetchColumn();

$completedOrders = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'Completed'");
$completedOrders->execute([$user_id]);
$completedOrdersVal = (int)$completedOrders->fetchColumn();

// Loyalty rewards
$totalItems = $pdo->prepare("SELECT COALESCE(SUM(od.quantity), 0) FROM order_details od JOIN orders o ON od.order_id = o.order_id WHERE o.user_id = ?");
$totalItems->execute([$user_id]);
$totalItemsPurchased = (int)$totalItems->fetchColumn();
$freeShirtsEarned = floor($totalItemsPurchased / 12);
$freeShirtsClaimed = 0; // Could be fetched from a rewards table

// Recent orders
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
$recentOrdersList = $recentOrders->fetchAll();

// Current active order (first active one for timeline)
$currentOrder = null;
foreach ($recentOrdersList as $o) {
    if (in_array($o['status'], ['Pending', 'In Progress'])) {
        $currentOrder = $o;
        break;
    }
}

// Pending sample approvals
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
$pendingSamples = $pendingSampleOrders->fetchAll();

$pageTitle = 'My Dashboard';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Dashboard — Sakuragi</title>
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
// ── Build order timeline HTML for current order ──
$timelineHtml = '';
if ($currentOrder):
  $timelineStages = [
    ['key' => 'Order Confirmed',      'icon' => 'fas fa-check',         'desc' => 'Order placed'],
    ['key' => 'In Production',        'icon' => 'fas fa-cog',          'desc' => 'Being crafted'],
    ['key' => 'Quality Check',        'icon' => 'fas fa-search',       'desc' => 'QC review'],
    ['key' => 'Ready for Pickup',     'icon' => 'fas fa-box-open',     'desc' => 'Ready to collect'],
    ['key' => 'Completed',            'icon' => 'fas fa-flag-checkered','desc' => 'Delivered'],
  ];
  $currentCs = $CUSTOMER_STAGE_MAP[$currentOrder['stage']] ?? 'Processing';
  $foundCurrent = false;

  ob_start();
?>
<div class="task-card" style="margin-bottom:24px;overflow:hidden">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;flex-wrap:wrap;gap:8px">
    <div>
      <div style="font-size:0.75rem;color:var(--text-tertiary);font-weight:500;margin-bottom:2px">Current Order</div>
      <div style="font-size:1.1rem;font-weight:700;color:var(--text-primary)">#ORD-<?= $currentOrder['order_id'] ?></div>
    </div>
    <?= renderStatusBadge(htmlspecialchars($currentCs), 'accent') ?>
  </div>
  <div class="timeline">
    <?php foreach ($timelineStages as $ts):
      $isActive = ($ts['key'] === $currentCs);
      $isPast = false;
      if (!$foundCurrent && !$isActive) {
        $isPast = true;
      } elseif ($isActive) {
        $foundCurrent = true;
      }
      $dotClass = $isActive ? 'is-active' : ($isPast ? 'is-complete' : 'is-pending');
      $titleClass = (!$isPast && !$isActive) ? 'is-pending' : '';
    ?>
    <div class="timeline-item">
      <div class="timeline-dot <?= $dotClass ?>"><i class="<?= $ts['icon'] ?>" style="font-size:0.65rem"></i></div>
      <div class="timeline-content">
        <div class="timeline-title <?= $titleClass ?>"><?= htmlspecialchars($ts['key']) ?></div>
        <div class="timeline-subtitle"><?= htmlspecialchars($ts['desc']) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php
  $timelineHtml = ob_get_clean();
endif;

// ── Build recent orders HTML ──
$recentOrdersHtml = '';
if (empty($recentOrdersList)):
  $recentOrdersHtml = renderEmptyState('fas fa-inbox', 'No orders yet', 'Place your first bulk order to get started.',
    ['label' => 'Place Your First Order', 'href' => 'place_order.php', 'icon' => 'fas fa-plus']);
else:
  ob_start();
  foreach ($recentOrdersList as $o):
    $cs = $CUSTOMER_STAGE_MAP[$o['stage']] ?? 'Processing';
    $pct = getStageProgress($o['stage']);
    $stageColor = $STAGE_CONFIG[$o['stage']]['color'] ?? '#6b7280';
?>
<a href="view_order.php?id=<?= $o['order_id'] ?>" style="text-decoration:none;display:block;margin-bottom:12px">
  <div class="task-card">
    <div class="task-card-header">
      <span class="task-card-title">#ORD-<?= $o['order_id'] ?></span>
      <?= renderStatusBadge(htmlspecialchars($cs), 'accent') ?>
    </div>
    <div class="task-card-meta">
      <?= htmlspecialchars($o['service_name']) ?> · Qty: <?= (int)$o['total_qty'] ?> · <?= date('M j, Y', strtotime($o['order_date'])) ?>
    </div>
    <div class="progress-bar">
      <div class="progress-bar-track"><div class="progress-bar-fill" style="width:<?= $pct ?>%"></div></div>
      <span class="progress-bar-label"><?= $pct ?>%</span>
    </div>
  </div>
</a>
<?php
  endforeach;
  $recentOrdersHtml = ob_get_clean();
endif;

// ── Build sample approvals sidebar ──
$samplesHtml = '';
if (empty($pendingSamples)):
  $samplesHtml = '<div class="empty-state" style="border:none;padding:8px 0">No pending sample reviews.</div>';
else:
  ob_start();
  foreach ($pendingSamples as $s):
?>
<div class="activity-item">
  <span class="activity-dot" style="background:#7c3aed"></span>
  <div class="activity-content" style="font-size:0.82rem">
    <strong>#ORD-<?= $s['order_id'] ?></strong> — <?= htmlspecialchars($s['service_name']) ?> · <?= (int)$s['total_qty'] ?> pcs
    <div class="activity-time">Submitted <?= date('M j', strtotime($s['submitted_at'])) ?></div>
  </div>
  <a href="sample_review.php" class="dash-btn dash-btn-primary dash-btn-sm flex-shrink-0">Review</a>
</div>
<?php
  endforeach;
  $samplesHtml = ob_get_clean();
endif;

// ── Build bulk order info ──
$infoHtml = '<ul class="info-list">';
$infoHtml .= '<li><i class="fas fa-check-circle" style="color:var(--color-success)"></i>Samples reviewed within 48 hours</li>';
$infoHtml .= '<li><i class="fas fa-check-circle" style="color:var(--color-success)"></i>Bulk production starts after sample approval</li>';
$infoHtml .= '<li><i class="fas fa-check-circle" style="color:var(--color-success)"></i>Real-time production tracking</li>';
$infoHtml .= '<li><i class="fas fa-check-circle" style="color:var(--color-success)"></i>Notifications at every milestone</li>';
$infoHtml .= '</ul>';

// ── Build rewards HTML ──
$rewardsHtml = '<div class="quick-stats-grid" style="grid-template-columns:1fr 1fr">';
$rewardsHtml .= '<div><span class="quick-stats-label">Items Purchased</span><br><strong class="quick-stats-value">' . $totalItemsPurchased . '</strong></div>';
$rewardsHtml .= '<div><span class="quick-stats-label">Free Shirts</span><br><strong class="quick-stats-value">' . $freeShirtsEarned . '</strong></div>';
$rewardsHtml .= '</div>';
$rewardsHtml .= '<div style="font-size:0.75rem;color:var(--text-tertiary);margin-top:8px">1 free shirt per 12 items</div>';

// ── Render ──
$sidebarPanels = renderPanelCard('Sample Approvals', $samplesHtml, 'fas fa-flask')
    . renderPanelCard('Bulk Order Info', $infoHtml, 'fas fa-info-circle')
    . renderPanelCard('Loyalty Rewards', $rewardsHtml, 'fas fa-gift')
    . renderPanelCard('Ready for Pickup',
        '<div class="ready-pickup-value">' . $readyPickupVal . '</div><div class="ready-pickup-label">items waiting for you</div>',
        'fas fa-box-open');

echo renderDashboardShell(
  renderPageHeader(
    'My Dashboard',
    "Welcome back, {$firstName} · Track your orders, review samples, and earn rewards",
    date('l, F j'),
    [['label' => 'Place Order', 'href' => 'place_order.php', 'icon' => 'fas fa-plus', 'variant' => 'primary', 'size' => 'sm'],
     ['label' => 'My Orders', 'href' => 'my_orders.php', 'icon' => 'fas fa-folder-open', 'variant' => 'outline', 'size' => 'sm']]
  ),
  renderQuickActions([
    ['label' => 'Place New Order', 'href' => 'place_order.php', 'icon' => 'fas fa-plus-circle', 'description' => 'Start a bulk order'],
    ['label' => 'Track Orders', 'href' => 'my_orders.php', 'icon' => 'fas fa-search', 'description' => 'View order progress'],
    ['label' => 'Review Samples', 'href' => 'sample_review.php', 'icon' => 'fas fa-flask', 'description' => 'Approve or reject'],
    ['label' => 'Notifications', 'href' => 'notifications.php', 'icon' => 'fas fa-bell', 'description' => 'View updates'],
  ]),
  renderKPIRow([
    ['icon' => 'fas fa-shopping-bag',   'label' => 'Total Orders',    'value' => $totalOrdersVal,   'accent' => 'blue'],
    ['icon' => 'fas fa-spinner',        'label' => 'Active',          'value' => $activeOrdersVal,  'accent' => 'amber'],
    ['icon' => 'fas fa-flask',          'label' => 'Pending Sample',  'value' => $pendingSampleVal, 'accent' => 'purple'],
    ['icon' => 'fas fa-check-circle',   'label' => 'Completed',       'value' => $completedOrdersVal,'accent' => 'green'],
  ])
  . $timelineHtml, // Append current order timeline after KPIs
  renderTwoColumn(
    renderPageSection('Recent Orders', $recentOrdersHtml, 'fas fa-folder-open',
      [['label' => 'View All', 'href' => 'my_orders.php', 'icon' => 'fas fa-external-link-alt', 'variant' => 'outline']]),
    $sidebarPanels
  )
);
?>
    </div>
  </div>
</div>

<script>
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
