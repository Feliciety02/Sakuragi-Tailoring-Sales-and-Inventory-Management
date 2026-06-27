<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/role_admin_only.php';

$pageTitle = 'Production Operations';

// ── Stats ──
$stats = [];
$stats['total_active'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Completed','Cancelled','Refunded')")->fetchColumn();
$stats['overdue'] = (int)$pdo->query("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.expected_completion IS NOT NULL AND ow.expected_completion < NOW() AND o.status NOT IN ('Completed','Cancelled','Refunded')")->fetchColumn();
$stats['completed_week'] = (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(completion_date) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND status = 'Completed'")->fetchColumn();
$stats['in_qc'] = (int)$pdo->query("SELECT COUNT(*) FROM order_workflow WHERE stage = 'QC'")->fetchColumn();
$avgLead = $pdo->query("SELECT AVG(TIMESTAMPDIFF(DAY, order_date, COALESCE(completion_date, NOW()))) FROM orders WHERE status = 'Completed'")->fetchColumn();
$stats['avg_lead'] = $avgLead ? round((float)$avgLead) : '-';

// ── Orders ──
$orders = $pdo->query("SELECT o.order_id, o.order_date, o.completion_date, o.status, ow.stage, ow.started_at, ow.expected_completion, ow.priority, u.full_name AS customer_name, e.full_name AS employee_name, s.service_name, (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) AS total_qty, (SELECT COUNT(*) FROM order_details WHERE order_id = o.order_id) AS total_items FROM orders o JOIN users u ON o.user_id = u.user_id LEFT JOIN services s ON o.service_id = s.service_id LEFT JOIN order_workflow ow ON o.order_id = ow.order_id LEFT JOIN users e ON ow.assigned_employee = e.user_id WHERE o.status NOT IN ('Cancelled', 'Refunded') ORDER BY ow.started_at IS NULL, ow.started_at ASC, o.order_date ASC")->fetchAll();

// ── Date range for timeline ──
$minDate = null; $maxDate = null;
foreach ($orders as $o) {
    $dates = array_filter([$o['order_date'], $o['started_at'], $o['expected_completion'], $o['completion_date']]);
    foreach ($dates as $d) { $ts = strtotime($d); if ($minDate === null || $ts < $minDate) $minDate = $ts; if ($maxDate === null || $ts > $maxDate) $maxDate = $ts; }
}
if (!$minDate) $minDate = time();
if (!$maxDate) $maxDate = time() + 86400 * 14;
$minDate = strtotime(date('Y-m-d', $minDate - 86400 * 2));
$maxDate = strtotime(date('Y-m-d', $maxDate + 86400 * 5));
$totalDays = max(1, ceil(($maxDate - $minDate) / 86400));
$today = time();

// ── Group by stage ──
$grouped = [];
foreach ($orders as $o) { $s = $o['stage'] ?: 'Unassigned'; if (!isset($grouped[$s])) $grouped[$s] = []; $grouped[$s][] = $o; }

// ── Overdue alerts ──
$alertsHtml = '';
if ($stats['overdue'] > 0) {
    $alertsHtml = '<div class="dash-alert dash-alert-danger" style="margin:0 24px 16px;display:flex;align-items:center;gap:10px"><i class="fas fa-exclamation-triangle"></i> <span>' . $stats['overdue'] . ' order' . ($stats['overdue']>1?'s are':' is') . ' overdue — review the timeline below.</span></div>';
}
$stage_order = [
    STAGE_PENDING_VERIFICATION, STAGE_CUSTOMER_ACTION, STAGE_READY_FOR_PRODUCTION,
    STAGE_WAITING_MATERIALS, STAGE_MATERIALS_RESERVED, STAGE_CUTTING, STAGE_SEWING,
    STAGE_EMBROIDERY, STAGE_FINISHING, STAGE_QC, STAGE_REWORK,
    STAGE_READY_FOR_RELEASE, STAGE_AWAITING_PAYMENT, STAGE_RELEASED,
];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Production Operations — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="production-schedule-styles">
    /* ── Timeline ── */
    .timeline-container { background:var(--bg-primary);border-radius:12px;border:1px solid var(--border-color);overflow:hidden }
    .timeline-scroll { overflow-x:auto;overflow-y:visible;position:relative }
    .timeline-header { display:flex;border-bottom:2px solid var(--border-color);position:sticky;top:0;background:var(--bg-primary);z-index:3 }
    .timeline-label-col { width:280px;min-width:280px;padding:12px 14px;font-size:0.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--text-tertiary);border-right:1px solid var(--border-color);display:flex;align-items:center }
    .timeline-axis { flex:1;display:flex;position:relative;overflow:hidden }
    .tl-day { flex:1;min-width:32px;text-align:center;font-size:0.65rem;color:var(--text-tertiary);padding:6px 0 4px;border-left:1px solid var(--border-color);line-height:1.2 }
    .tl-day.weekend { background:var(--bg-secondary) }
    .tl-day.today { background:rgba(214,40,40,0.06);font-weight:700;color:var(--accent-color) }
    .tl-day .dow { font-size:0.55rem;opacity:.6;margin-top:1px }

    /* ── Week header ── */
    .tl-week-header { display:flex;border-bottom:1px solid var(--border-color);background:var(--bg-secondary);position:sticky;top:0;z-index:2 }
    .tl-week-cell { flex:1;padding:6px 10px;font-size:0.65rem;font-weight:600;color:var(--text-secondary);border-left:1px solid var(--border-color);min-width:160px }
    .tl-week-cell:first-child { border-left:none }

    /* ── Month header ── */
    .tl-month-header { display:flex;border-bottom:1px solid var(--border-color);background:var(--bg-secondary);position:sticky;top:0;z-index:2 }
    .tl-month-cell { flex:1;padding:6px 10px;font-size:0.7rem;font-weight:700;color:var(--text-primary);border-left:1px solid var(--border-color);min-width:200px }

    /* ── Stage group row ── */
    .stage-group { border-bottom:2px solid var(--border-color) }
    .stage-group:last-child { border-bottom:none }
    .stage-row { display:flex }
    .stage-label { width:280px;min-width:280px;padding:10px 14px;display:flex;align-items:center;gap:8px;border-right:1px solid var(--border-color);background:var(--bg-secondary);font-size:0.78rem;font-weight:600;color:var(--text-primary) }
    .stage-label .count { font-size:0.65rem;font-weight:400;color:var(--text-tertiary);margin-left:auto }
    .stage-label .icon { width:20px;text-align:center;font-size:0.85rem }

    /* ── Order card row ── */
    .order-row { display:flex;border-bottom:1px solid var(--border-color);min-height:84px;transition:background .12s }
    .order-row:last-child { border-bottom:none }
    .order-row:hover { background:var(--bg-secondary) }
    .order-card { width:280px;min-width:280px;padding:10px 14px;display:flex;flex-direction:column;justify-content:center;gap:4px;border-right:1px solid var(--border-color);cursor:pointer }
    .order-card .top-row { display:flex;align-items:center;gap:8px }
    .order-card .order-id { font-size:0.82rem;font-weight:700;color:var(--text-primary);text-decoration:none }
    .order-card .order-id:hover { color:var(--role-accent) }
    .order-card .priority-badge { font-size:0.55rem;font-weight:700;text-transform:uppercase;padding:1px 6px;border-radius:3px;letter-spacing:.04em }
    .order-card .meta { font-size:0.72rem;color:var(--text-tertiary);display:flex;gap:12px;flex-wrap:wrap;align-items:center }
    .order-card .meta i { width:14px;text-align:center;font-size:0.65rem;color:var(--text-tertiary) }
    .order-card .assignee { display:inline-flex;align-items:center;gap:4px }
    .order-card .avatar { display:inline-flex;width:20px;height:20px;border-radius:50%;background:var(--role-accent);color:#fff;font-size:0.55rem;font-weight:700;align-items:center;justify-content:center;flex-shrink:0 }
    .order-card .qty-badge { font-size:0.65rem;padding:1px 6px;border-radius:4px;background:var(--bg-secondary);color:var(--text-secondary);font-weight:500 }

    /* ── Timeline bar area ── */
    .order-timeline { flex:1;position:relative;min-height:84px }
    .gantt-bar { position:absolute;height:28px;border-radius:6px;top:50%;transform:translateY(-50%);display:flex;align-items:center;padding:0 10px;font-size:0.65rem;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,0.12);transition:box-shadow .15s,transform .12s;z-index:1 }
    .gantt-bar:hover { box-shadow:0 3px 12px rgba(0,0,0,0.18);transform:translateY(-50%) scaleY(1.08);z-index:5 }
    .gantt-bar.completed { opacity:.55 }
    .gantt-bar.overdue { box-shadow:0 0 0 2px #dc2626 }
    .today-line { position:absolute;top:0;bottom:0;width:2px;background:var(--accent-color);z-index:2;pointer-events:none;opacity:.6 }
    .today-line::before { content:'Today';position:absolute;top:-18px;left:50%;transform:translateX(-50%);font-size:0.55rem;font-weight:700;color:var(--accent-color);white-space:nowrap }

    /* ── Zoom controls ── */
    .zoom-bar { display:flex;gap:4px;margin-bottom:16px }
    .zoom-btn { padding:5px 14px;font-size:0.72rem;font-weight:600;border:1px solid var(--border-color);border-radius:6px;background:var(--bg-primary);color:var(--text-secondary);cursor:pointer;transition:all .12s }
    .zoom-btn:hover { border-color:var(--role-accent);color:var(--role-accent) }
    .zoom-btn.active { background:var(--role-accent);color:#fff;border-color:var(--role-accent) }

    /* ── Empty stage ── */
    .stage-empty { display:flex;border-bottom:1px solid var(--border-color);min-height:60px }
    .stage-empty .empty-msg { width:280px;min-width:280px;padding:16px 14px;font-size:0.72rem;color:var(--text-tertiary);border-right:1px solid var(--border-color);display:flex;align-items:center;justify-content:center }

    /* ── KPI row spacing ── */
  </style>
</head>
<body data-role="admin">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
$kpiRow = renderKPIRow([
  ['label' => 'Active Orders', 'value' => (string)$stats['total_active'], 'icon' => 'fas fa-tasks', 'accent' => 'blue'],
  ['label' => 'Overdue', 'value' => (string)$stats['overdue'], 'icon' => 'fas fa-exclamation-triangle', 'accent' => 'red'],
  ['label' => 'Completed (7d)', 'value' => (string)$stats['completed_week'], 'icon' => 'fas fa-check-circle', 'accent' => 'green'],
  ['label' => 'In QC', 'value' => (string)$stats['in_qc'], 'icon' => 'fas fa-search', 'accent' => 'amber'],
  ['label' => 'Avg. Lead Time', 'value' => (string)$stats['avg_lead'] . 'd', 'icon' => 'fas fa-clock', 'accent' => 'purple'],
]);

// ── Build timeline ──
ob_start(); ?>
<div class="timeline-container">
  <div class="timeline-scroll" id="timelineScroll">
    <?php
    // Render date header (day mode — default visible)
    $headerCells = '';
    for ($i = 0; $i < $totalDays; $i++):
      $day = $minDate + $i * 86400;
      $isWeekend = in_array(date('w', $day), [0, 6]);
      $isToday = date('Y-m-d', $day) === date('Y-m-d');
      $headerCells .= '<div class="tl-day' . ($isWeekend ? ' weekend' : '') . ($isToday ? ' today' : '') . '" data-col="d-' . $i . '">' . date('d', $day) . '<div class="dow">' . date('D', $day) . '</div></div>';
    endfor;
    $todayOffset = floor((strtotime(date('Y-m-d')) - $minDate) / 86400);

    // Render week headers
    $weekCells = '';
    $weekStart = $minDate;
    while ($weekStart < $maxDate):
      $weekEnd = min($weekStart + 86400 * 6, $maxDate);
      $weekLabel = date('M d', $weekStart) . ' — ' . date('M d', $weekEnd);
      $colStart = floor(($weekStart - $minDate) / 86400);
      $colSpan = ceil(($weekEnd - $weekStart) / 86400) + 1;
      $weekCells .= '<div class="tl-week-cell" style="flex:' . $colSpan . ';min-width:' . ($colSpan * 32) . 'px" data-col-start="' . $colStart . '" data-col-span="' . $colSpan . '">' . $weekLabel . '</div>';
      $weekStart = $weekEnd + 86400;
    endwhile;

    // Render month headers
    $monthCells = '';
    $monthStart = $minDate;
    while ($monthStart < $maxDate):
      $monthEnd = min(strtotime(date('Y-m-t', $monthStart)), $maxDate);
      $monthLabel = date('F Y', $monthStart);
      $colStart = floor(($monthStart - $minDate) / 86400);
      $colSpan = ceil(($monthEnd - $monthStart) / 86400) + 1;
      $monthCells .= '<div class="tl-month-cell" style="flex:' . $colSpan . ';min-width:' . ($colSpan * 32) . 'px" data-col-start="' . $colStart . '" data-col-span="' . $colSpan . '">' . $monthLabel . '</div>';
      $monthStart = $monthEnd + 86400;
    endwhile;
    ?>

    <div class="timeline-header">
      <div class="timeline-label-col">Order</div>
      <div class="timeline-axis">
        <?= $headerCells ?>
        <?php if ($todayOffset >= 0 && $todayOffset < $totalDays): ?>
        <div class="today-line" style="left:<?= ($todayOffset / $totalDays) * 100 ?>%"></div>
        <?php endif; ?>
      </div>
    </div>

    <?php foreach ($stage_order as $stg):
      $cfg = $STAGE_CONFIG[$stg] ?? ['label' => $stg, 'color' => '#6b7280', 'icon' => 'fas fa-circle'];
      $ordersInStage = $grouped[$stg] ?? [];
    ?>
    <div class="stage-group" data-stage="<?= htmlspecialchars($stg) ?>">
      <div class="stage-row">
        <div class="stage-label" style="color:<?= $cfg['color'] ?>">
          <span class="icon"><i class="<?= $cfg['icon'] ?>"></i></span>
          <?= htmlspecialchars($cfg['label'] ?? $stg) ?>
          <span class="count"><?= count($ordersInStage) ?> order<?= count($ordersInStage) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="order-timeline" style="background:repeating-linear-gradient(90deg,var(--border-color) 0,var(--border-color) 1px,transparent 1px,transparent <?= 100/$totalDays ?>%)"></div>
      </div>

      <?php if (empty($ordersInStage)): ?>
      <div class="stage-empty">
        <div class="empty-msg"><span style="opacity:.5">No orders in this stage</span></div>
        <div class="order-timeline"></div>
      </div>
      <?php else: foreach ($ordersInStage as $o):
        $start = $o['started_at'] ? strtotime($o['started_at']) : strtotime($o['order_date']);
        $end = $o['expected_completion'] ? strtotime($o['expected_completion']) : ($start + 86400 * 7);
        if ($end < $start) $end = $start + 86400;
        if ($o['status'] === 'Completed' && $o['completion_date']) $end = strtotime($o['completion_date']);
        $leftPct = max(0, (($start - $minDate) / ($maxDate - $minDate)) * 100);
        $widthPct = min(100 - $leftPct, max(1.5, (($end - $start) / ($maxDate - $minDate)) * 100));
        $isCompleted = $o['status'] === 'Completed';
        $isOverdue = !$isCompleted && $o['expected_completion'] && strtotime($o['expected_completion']) < $today;
        $barColor = $isCompleted ? '#10b981' : ($isOverdue ? '#ef4444' : $cfg['color']);
        $empName = $o['employee_name'] ?? '';
        $empInitial = $empName ? substr($empName, 0, 1) : '?';
        $priority = $o['priority'] ?? 'normal';
        $qty = $o['total_qty'] ?? 0;
        $dueDate = $o['expected_completion'] ? date('M d', strtotime($o['expected_completion'])) : '—';
      ?>
      <div class="order-row" data-order="<?= $o['order_id'] ?>">
        <div class="order-card">
          <div class="top-row">
            <a href="view_order.php?id=<?= $o['order_id'] ?>" class="order-id">#ORD-<?= $o['order_id'] ?></a>
            <?php if (strtolower($priority) === 'urgent'): ?>
            <span class="priority-badge priority-badge-urgent">Urgent</span>
            <?php elseif (strtolower($priority) === 'high'): ?>
            <span class="priority-badge priority-badge-high">High</span>
            <?php elseif (strtolower($priority) === 'medium'): ?>
            <span class="priority-badge priority-badge-medium">Medium</span>
            <?php elseif (strtolower($priority) === 'low'): ?>
            <span class="priority-badge priority-badge-low">Low</span>
            <?php endif; ?>
          </div>
          <div class="meta">
            <span><i class="fas fa-user"></i> <?= htmlspecialchars($o['customer_name']) ?></span>
            <?php if ($qty > 0): ?>
            <span class="qty-badge"><?= $qty ?> pc<?= $qty !== 1 ? 's' : '' ?></span>
            <?php endif; ?>
            <span><i class="fas fa-calendar"></i> Due <?= $dueDate ?></span>
            <?php if ($empName): ?>
            <span class="assignee"><span class="avatar"><?= htmlspecialchars($empInitial) ?></span> <?= htmlspecialchars($empName) ?></span>
            <?php endif; ?>
          </div>
        </div>
        <div class="order-timeline">
          <div class="gantt-bar <?= $isOverdue ? 'overdue' : '' ?> <?= $isCompleted ? 'completed' : '' ?>"
               style="left:<?= $leftPct ?>%;width:<?= $widthPct ?>%;background:<?= $barColor ?>"
               title="#ORD-<?= $o['order_id'] ?>: <?= date('M d', $start) ?> – <?= date('M d', $end) ?>">
            #<?= $o['order_id'] ?>
          </div>
          <?php if ($todayOffset >= 0 && $todayOffset < $totalDays): ?>
          <div class="today-line" style="left:<?= ($todayOffset / $totalDays) * 100 ?>%"></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php $timelineHtml = ob_get_clean();

// ── Combine ──
$zoomBar = '<div class="zoom-bar" id="zoomBar"><button class="zoom-btn active" data-zoom="day">Day</button><button class="zoom-btn" data-zoom="week">Week</button><button class="zoom-btn" data-zoom="month">Month</button></div>';

$workspace = $alertsHtml . $zoomBar . $timelineHtml
  . '<script>
document.addEventListener(\'DOMContentLoaded\', function() {
  var zoomBtns = document.querySelectorAll(\'.zoom-btn\');
  var headerCells = document.querySelectorAll(\'.tl-day\');
  var weekCells = document.querySelectorAll(\'.tl-week-cell\');
  var monthCells = document.querySelectorAll(\'.tl-month-cell\');
  zoomBtns.forEach(function(btn) {
    btn.addEventListener(\'click\', function() {
      zoomBtns.forEach(function(b) { b.classList.remove(\'active\'); });
      this.classList.add(\'active\');
      var zoom = this.getAttribute(\'data-zoom\');
      headerCells.forEach(function(c) { c.style.display = zoom === \'day\' ? \'\' : \'none\'; });
      weekCells.forEach(function(c) { c.style.display = zoom === \'week\' ? \'\' : \'none\'; });
      monthCells.forEach(function(c) { c.style.display = zoom === \'month\' ? \'\' : \'none\'; });
    });
  });
  weekCells.forEach(function(c) { c.style.display = \'none\'; });
  monthCells.forEach(function(c) { c.style.display = \'none\'; });
});
</script>';

echo renderDashboardShell(
  renderPageHeader('Production Operations', 'Timeline workspace for all active production orders.', '', [
    ['label' => 'Board', 'href' => 'production_board.php', 'icon' => 'fas fa-columns', 'variant' => 'outline', 'size' => 'sm'],
    ['label' => 'Analytics', 'href' => 'production_analytics.php', 'icon' => 'fas fa-chart-bar', 'variant' => 'outline', 'size' => 'sm'],
    ['label' => 'QC', 'href' => 'quality_control.php', 'icon' => 'fas fa-search', 'variant' => 'outline', 'size' => 'sm'],
  ]),
  $kpiRow,
  $workspace
);
?>
</div>
</div>
</body>
</html>
