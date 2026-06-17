<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/role_admin_only.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar_admin.php';

// Fetch all active orders with timeline data
$orders = $pdo->query("
    SELECT o.order_id, o.order_date, o.completion_date, o.status,
           ow.stage, ow.started_at, ow.expected_completion, ow.priority,
           u.full_name AS customer_name,
           e.full_name AS employee_name,
           s.service_name,
           (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) AS total_qty,
           (SELECT COUNT(*) FROM order_details WHERE order_id = o.order_id) AS total_items
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN services s ON o.service_id = s.service_id
    LEFT JOIN order_workflow ow ON o.order_id = ow.order_id
    LEFT JOIN users e ON ow.assigned_employee = e.user_id
    WHERE o.status NOT IN ('Cancelled', 'Refunded')
    ORDER BY ow.started_at IS NULL, ow.started_at ASC, o.order_date ASC
")->fetchAll();

// Calculate date range for timeline
$minDate = null;
$maxDate = null;
foreach ($orders as $o) {
    $dates = array_filter([$o['order_date'], $o['started_at'], $o['expected_completion'], $o['completion_date']]);
    foreach ($dates as $d) {
        $ts = strtotime($d);
        if ($minDate === null || $ts < $minDate) $minDate = $ts;
        if ($maxDate === null || $ts > $maxDate) $maxDate = $ts;
    }
}
if (!$minDate) $minDate = time();
if (!$maxDate) $maxDate = time() + 86400 * 14;
$minDate = strtotime(date('Y-m-d', $minDate - 86400 * 2)); // 2 days buffer
$maxDate = strtotime(date('Y-m-d', $maxDate + 86400 * 5));  // 5 days buffer
$totalDays = max(1, ceil(($maxDate - $minDate) / 86400));
$today = time();

// Group by stage
$grouped = [];
foreach ($orders as $o) {
    $s = $o['stage'] ?: 'Unassigned';
    if (!isset($grouped[$s])) $grouped[$s] = [];
    $grouped[$s][] = $o;
}
?>
<link rel="stylesheet" href="/public/assets/css/mes.css">
<style>
  body { background: #f5f5f5; }
  .main-content { margin-left: 220px; padding: 24px 32px; background: #f5f5f5; }
  .gantt-container { overflow-x: auto; border-radius: 12px; background: #fff; border: 1px solid #e5e7eb; }
  .gantt-header { display: flex; border-bottom: 2px solid #e5e7eb; position: sticky; top: 0; background: #fff; z-index: 2; }
  .gantt-label-col { width: 220px; min-width: 220px; padding: 10px 12px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; border-right: 1px solid #e5e7eb; }
  .gantt-timeline { flex: 1; display: flex; position: relative; overflow: hidden; }
  .gantt-day { flex: 1; min-width: 28px; text-align: center; font-size: 9px; color: #9ca3af; padding: 4px 0; border-left: 1px solid #f3f4f6; position: relative; }
  .gantt-day.weekend { background: #fafafa; }
  .gantt-day.today { background: #dbeafe; font-weight: 700; color: #2563eb; }
  .gantt-row { display: flex; border-bottom: 1px solid #f3f4f6; min-height: 44px; }
  .gantt-row:hover { background: #fafafa; }
  .gantt-row-label { width: 220px; min-width: 220px; padding: 8px 12px; display: flex; flex-direction: column; justify-content: center; border-right: 1px solid #e5e7eb; }
  .gantt-row-label .order-id { font-size: 13px; font-weight: 600; color: #1f2937; }
  .gantt-row-label .order-meta { font-size: 10px; color: #6b7280; }
  .gantt-row-timeline { flex: 1; position: relative; min-height: 44px; }
  .gantt-bar { position: absolute; height: 22px; border-radius: 4px; top: 50%; transform: translateY(-50%); display: flex; align-items: center; padding: 0 8px; font-size: 9px; font-weight: 500; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: box-shadow .15s; }
  .gantt-bar:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.15); z-index: 5; }
  .gantt-bar.overdue { border: 2px solid #dc2626; }
  .gantt-bar.completed { opacity: .6; }
  .today-line { position: absolute; top: 0; bottom: 0; width: 2px; background: #dc2626; z-index: 3; pointer-events: none; }
  .stage-section-header { display: flex; border-bottom: 2px solid #e5e7eb; background: #f9fafb; }
  .stage-section-header .gantt-label-col { font-weight: 700; font-size: 12px; color: #374151; text-transform: none; }
  .stage-badge { font-size: 9px; padding: 1px 6px; border-radius: 6px; }
</style>

<div class="main-content">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 style="font-size:20px;font-weight:700;margin:0">Production Schedule</h1>
      <p style="font-size:13px;color:#6b7280;margin-top:4px">Timeline view of all active orders by production stage</p>
    </div>
    <div style="display:flex;gap:12px;font-size:11px;align-items:center">
      <span><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#3b82f6;margin-right:4px;vertical-align:middle"></span>In Progress</span>
      <span><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#10b981;margin-right:4px;vertical-align:middle"></span>Completed</span>
      <span><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:#f59e0b;margin-right:4px;vertical-align:middle"></span>Not Started</span>
      <span><span style="display:inline-block;width:12px;height:12px;border:2px solid #dc2626;border-radius:2px;margin-right:4px;vertical-align:middle"></span>Overdue</span>
    </div>
  </div>

  <div class="gantt-container">
    <!-- Timeline Header -->
    <div class="gantt-header">
      <div class="gantt-label-col">Order</div>
      <div class="gantt-timeline">
        <?php for ($i = 0; $i < $totalDays; $i++):
          $day = $minDate + $i * 86400;
          $isWeekend = in_array(date('w', $day), [0, 6]);
          $isToday = date('Y-m-d', $day) === date('Y-m-d');
        ?>
        <div class="gantt-day <?= $isWeekend ? 'weekend' : '' ?> <?= $isToday ? 'today' : '' ?>">
          <?= date('d', $day) ?>
          <div style="font-size:7px"><?= date('D', $day) ?></div>
        </div>
        <?php endfor; ?>
        <!-- Today line -->
        <?php $todayOffset = floor((strtotime(date('Y-m-d')) - $minDate) / 86400); ?>
        <?php if ($todayOffset >= 0 && $todayOffset < $totalDays): ?>
        <div class="today-line" style="left:<?= ($todayOffset / $totalDays) * 100 ?>%"></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Rows by Stage -->
    <?php foreach ($grouped as $stage => $stageOrders):
      $cfg = $STAGE_CONFIG[$stage] ?? ['color' => '#6b7280', 'icon' => 'fas fa-circle'];
    ?>
    <div class="stage-section-header">
      <div class="gantt-label-col" style="color:<?= $cfg['color'] ?>">
        <i class="<?= $cfg['icon'] ?> me-1"></i><?= htmlspecialchars($cfg['label'] ?? $stage) ?>
        <span style="color:#9ca3ab;font-weight:400">(<?= count($stageOrders) ?>)</span>
      </div>
      <div class="gantt-timeline"></div>
    </div>

    <?php foreach ($stageOrders as $o):
      $start = $o['started_at'] ? strtotime($o['started_at']) : strtotime($o['order_date']);
      $end = $o['expected_completion'] ? strtotime($o['expected_completion']) : ($start + 86400 * 7);
      if ($end < $start) $end = $start + 86400;
      if ($o['status'] === 'Completed' && $o['completion_date']) $end = strtotime($o['completion_date']);

      $leftPct = max(0, (($start - $minDate) / ($maxDate - $minDate)) * 100);
      $widthPct = min(100 - $leftPct, max(2, (($end - $start) / ($maxDate - $minDate)) * 100));

      $isCompleted = $o['status'] === 'Completed';
      $isOverdue = !$isCompleted && $o['expected_completion'] && strtotime($o['expected_completion']) < $today;
      $barColor = $isCompleted ? '#10b981' : ($isOverdue ? '#ef4444' : (in_array($o['stage'], [STAGE_ORDER_RECEIVED, STAGE_DESIGN_REVIEW]) ? '#f59e0b' : '#3b82f6'));

      $empName = $o['employee_name'] ?? 'Unassigned';
    ?>
    <div class="gantt-row">
      <div class="gantt-row-label">
        <span class="order-id">#ORD-<?= $o['order_id'] ?></span>
        <span class="order-meta"><?= htmlspecialchars($o['customer_name']) ?> · <?= $o['total_qty'] ?? 0 ?> pcs · <?= htmlspecialchars($empName) ?></span>
      </div>
      <div class="gantt-row-timeline">
        <div class="gantt-bar <?= $isOverdue ? 'overdue' : '' ?> <?= $isCompleted ? 'completed' : '' ?>"
             style="left:<?= $leftPct ?>%;width:<?= $widthPct ?>%;background:<?= $barColor ?>"
             title="#ORD-<?= $o['order_id'] ?>: <?= date('M d', $start) ?> - <?= date('M d', $end) ?>">
          #<?= $o['order_id'] ?>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
