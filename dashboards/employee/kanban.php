<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/auth_required.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar_employee.php';

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

// Stats
$activeCount = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND o.status NOT IN ('Completed','Cancelled') AND ow.stage IN ({$stage_placeholders})");
$activeCount->execute(array_merge([$user_id], $stage_params));

$completedToday = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND o.status = 'Completed' AND DATE(o.completion_date) = CURDATE()");
$completedToday->execute([$user_id]);

// Fetch orders grouped by stage
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
?>
<link rel="stylesheet" href="/public/assets/css/mes.css">
<style>
  body { background: #f5f5f5; }
  .main-content { margin-left: 220px; padding: 24px 32px; background: #f5f5f5; min-height: 100vh; }
  @media (max-width: 768px) { .main-content { margin-left: 0; padding: 16px; } }
  .kanban-board { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 16px; min-height: 60vh; }
  .kanban-column { min-width: 240px; max-width: 280px; flex-shrink: 0; }
  .kanban-column-header { padding: 12px 16px; font-size: 13px; font-weight: 600; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; }
  .kanban-column-header .count { background: rgba(255,255,255,.3); padding: 2px 8px; border-radius: 10px; font-size: 11px; }
  .kanban-cards { padding: 8px; min-height: 100px; }
  .kanban-card { background: #fff; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px; border: 1px solid #e5e7eb; cursor: default; }
  .kanban-card .order-id { font-size: 13px; font-weight: 600; color: #1f2937; }
  .kanban-card .customer-name { font-size: 12px; color: #6b7280; }
  .kanban-card .meta-row { font-size: 11px; color: #9ca3af; margin-top: 4px; display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
  .kanban-empty { font-size: 12px; color: #9ca3af; text-align: center; padding: 24px 8px; }
</style>

<div class="main-content">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 style="font-size:20px;font-weight:700;margin:0">My Kanban Board</h1>
      <p style="font-size:13px;color:#6b7280;margin-top:4px"><?= htmlspecialchars($position['position_name'] ?? 'Employee') ?> — My Production Stages</p>
    </div>
    <div class="d-flex gap-2">
      <span class="mes-stat" style="padding:8px 16px"><span class="mes-stat-label" style="font-size:11px">Active</span><span class="mes-stat-value" style="font-size:16px"><?= $activeCount->fetchColumn() ?></span></span>
      <span class="mes-stat" style="padding:8px 16px"><span class="mes-stat-label" style="font-size:11px">Today</span><span class="mes-stat-value" style="font-size:16px;color:var(--mes-success)"><?= $completedToday->fetchColumn() ?></span></span>
    </div>
  </div>

  <div class="kanban-board">
    <?php
    $grouped = [];
    foreach ($allowed_stages as $stg) { $grouped[$stg] = []; }
    foreach ($allOrders as $o) {
        if (isset($grouped[$o['stage']])) $grouped[$o['stage']][] = $o;
    }
    foreach ($allowed_stages as $stg):
        $cfg = $STAGE_CONFIG[$stg] ?? ['label' => $stg, 'color' => '#6b7280', 'icon' => 'fas fa-circle'];
        $items = $grouped[$stg] ?? [];
    ?>
    <div class="kanban-column">
      <div class="kanban-column-header" style="background:<?= $cfg['color'] ?>20;color:<?= $cfg['color'] ?>">
        <span><i class="<?= $cfg['icon'] ?>"></i> <?= htmlspecialchars($cfg['label']) ?></span>
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
            <span class="priority-badge priority-<?= $item['priority'] ?? 'medium' ?>"><?= ucfirst($item['priority'] ?? 'med') ?></span>
            <span>Qty: <?= (int)$item['total_qty'] ?></span>
            <?php if ($item['expected_completion']): ?>
            <span>Due: <?= date('M d', strtotime($item['expected_completion'])) ?></span>
            <?php endif; ?>
          </div>
          <div style="margin-top:8px">
            <a href="view_task.php?id=<?= $item['order_id'] ?>" class="mes-btn mes-btn-sm mes-btn-primary" style="padding:2px 8px;font-size:11px"><i class="fas fa-eye"></i> View</a>
          </div>
        </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
