<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/auth_required.php';
require_once __DIR__ . '/../../app/Support/helpers.php';

$pageTitle = 'Kanban Board';

if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$position = getEmployeePosition($pdo, $user_id);
$position_id = $position ? (int)$position['position_id'] : 0;
$allowed_stages = getPositionStages($position_id);
$stage_placeholders = implode(',', array_fill(0, count($allowed_stages), '?'));
$stage_params = $allowed_stages;

$activeCount = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND o.status NOT IN ('Completed','Cancelled') AND ow.stage IN ({$stage_placeholders})");
$activeCount->execute(array_merge([$user_id], $stage_params));
$activeTotal = (int)$activeCount->fetchColumn();

$completedToday = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND o.status = 'Completed' AND DATE(o.completion_date) = CURDATE()");
$completedToday->execute([$user_id]);
$completedTotal = (int)$completedToday->fetchColumn();

$orders = $pdo->prepare("
    SELECT o.order_id, o.order_date, o.total_price, o.status,
           ow.stage, ow.priority, ow.expected_completion, ow.product_type, ow.started_at,
           u.full_name AS customer_name,
           (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) as total_qty
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    WHERE ow.assigned_employee = ? AND o.status NOT IN ('Completed','Cancelled')
    AND ow.stage IN ({$stage_placeholders})
    ORDER BY CASE ow.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
             ow.expected_completion ASC
");
$orders->execute(array_merge([$user_id], $stage_params));
$allOrders = $orders->fetchAll();

$role = get_user_role();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kanban Board — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="emp-kanban-styles">
    .kanban-board { display:flex;gap:12px;overflow-x:auto;padding-bottom:16px;min-height:60vh }
    .kanban-col { min-width:240px;max-width:280px;flex-shrink:0 }
    .kanban-col-header { padding:12px 16px;font-size:0.82rem;font-weight:600;border-radius:8px 8px 0 0;display:flex;justify-content:space-between;align-items:center }
    .kanban-col-header .count { background:rgba(255,255,255,0.3);padding:2px 8px;border-radius:10px;font-size:0.7rem }
    .kanban-cards { padding:8px;min-height:100px }
    .kanban-card { background:var(--bg-primary);border-radius:8px;padding:10px 12px;margin-bottom:8px;border:1px solid var(--border-color);cursor:default;transition:box-shadow .15s }
    .kanban-card:hover { box-shadow:0 2px 8px rgba(0,0,0,0.06) }
    .kanban-card .order-id { font-size:0.82rem;font-weight:600;color:var(--text-primary) }
    .kanban-card .customer-name { font-size:0.75rem;color:var(--text-tertiary) }
    .kanban-card .meta-row { font-size:0.7rem;color:var(--text-tertiary);margin-top:4px;display:flex;gap:6px;align-items:center;flex-wrap:wrap }
    .kanban-empty { font-size:0.75rem;color:var(--text-tertiary);text-align:center;padding:24px 8px }
  </style>
</head>
<body data-role="<?= htmlspecialchars($role) ?>">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
$metricsRow = renderKPIRow([
  ['label' => 'Active Orders', 'value' => (string)$activeTotal, 'icon' => 'fas fa-tasks', 'accent' => 'blue'],
  ['label' => 'Completed Today', 'value' => (string)$completedTotal, 'icon' => 'fas fa-check-circle', 'accent' => 'green'],
]);

ob_start();
$grouped = [];
foreach ($allowed_stages as $stg) { $grouped[$stg] = []; }
foreach ($allOrders as $o) {
    if (isset($grouped[$o['stage']])) $grouped[$o['stage']][] = $o;
}
?>
<div class="kanban-board">
  <?php foreach ($allowed_stages as $stg):
    $cfg = $STAGE_CONFIG[$stg] ?? ['label' => $stg, 'color' => '#6b7280', 'icon' => 'fas fa-circle'];
    $items = $grouped[$stg] ?? [];
  ?>
  <div class="kanban-col">
    <div class="kanban-col-header" style="background:<?= $cfg['color'] ?>18;color:<?= $cfg['color'] ?>">
      <span><i class="<?= $cfg['icon'] ?>" style="margin-right:4px"></i><?= htmlspecialchars($cfg['label']) ?></span>
      <span class="count"><?= count($items) ?></span>
    </div>
    <div class="kanban-cards">
      <?php if (empty($items)): ?>
      <div class="kanban-empty">No orders</div>
      <?php else: foreach ($items as $item): ?>
      <div class="kanban-card">
        <div class="order-id">#ORD-<?= $item['order_id'] ?></div>
        <div class="customer-name"><?= htmlspecialchars($item['customer_name']) ?></div>
        <div class="meta-row">
          <?= renderStatusBadge(ucfirst($item['priority'] ?? 'medium'), ($item['priority']??'')==='urgent'?'danger':(($item['priority']??'')==='high'?'warning':'neutral'), 'sm') ?>
          <span>Qty: <?= (int)$item['total_qty'] ?></span>
          <?php if ($item['expected_completion']): ?>
          <span>Due: <?= date('M d', strtotime($item['expected_completion'])) ?></span>
          <?php endif; ?>
        </div>
        <div style="margin-top:8px">
          <a href="view_task.php?id=<?= $item['order_id'] ?>" class="dash-btn dash-btn-outline dash-btn-sm" style="padding:2px 8px;font-size:0.7rem"><i class="fas fa-eye"></i> View</a>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php
$boardHtml = ob_get_clean();

echo renderDashboardShell(
  renderPageHeader('My Kanban Board', htmlspecialchars($position['position_name'] ?? 'Employee') . ' — Production Stages'),
  $metricsRow,
  $boardHtml
);
?>
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
