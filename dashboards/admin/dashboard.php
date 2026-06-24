<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once __DIR__ . '/../../app/Middleware/role_admin_only.php';

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Admin';
$firstName = htmlspecialchars(explode(' ', $full_name)[0]);

// Stats
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$inProduction = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Completed','Cancelled','Refunded')")->fetchColumn();
$inQC = $pdo->query("SELECT COUNT(*) FROM order_workflow WHERE stage = 'Quality Inspection'")->fetchColumn();
$completedToday = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(completion_date) = CURDATE() AND status = 'Completed'")->fetchColumn();
$overdueCount = $pdo->query("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.expected_completion IS NOT NULL AND ow.expected_completion < NOW() AND o.status NOT IN ('Completed','Cancelled','Refunded')")->fetchColumn();
$totalSales = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status = 'Completed'")->fetchColumn() ?: 0;
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees e JOIN statuses s ON e.status_id = s.status_id WHERE s.status_name = 'Active'")->fetchColumn();

// Recent activity
$activity = $pdo->query("
  SELECT pn.content, pn.created_at, pn.order_id, pn.note_type, u.full_name AS author
  FROM production_notes pn
  JOIN users u ON pn.author_id = u.user_id
  ORDER BY pn.created_at DESC LIMIT 8
")->fetchAll();

// QC queue
$qcQueue = $pdo->query("
  SELECT o.order_id, qc.result, u.full_name AS customer
  FROM order_workflow ow
  JOIN orders o ON ow.order_id = o.order_id
  JOIN users u ON o.user_id = u.user_id
  LEFT JOIN qc_inspections qc ON o.order_id = qc.order_id
  WHERE ow.stage = 'Quality Inspection'
  ORDER BY ow.expected_completion ASC LIMIT 6
")->fetchAll();

// Busiest employee
$busyEmp = $pdo->query("
  SELECT u.full_name, COUNT(*) as cnt
  FROM order_workflow ow
  JOIN users u ON ow.assigned_employee = u.user_id
  JOIN orders o ON ow.order_id = o.order_id
  WHERE o.status NOT IN ('Completed','Cancelled','Refunded')
  GROUP BY ow.assigned_employee ORDER BY cnt DESC LIMIT 1
")->fetch();

$pageTitle = 'Production Overview';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Production Overview — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
  <link rel="stylesheet" href="/public/assets/css/components.css">
</head>
<body data-role="admin">
  <div class="dash-layout">
    <?php render_role_sidebar($pdo); ?>
    <div class="dash-main">
<?php
// ── Build Kanban section HTML ──
ob_start();
$stage_order = [STAGE_ORDER_RECEIVED, STAGE_DESIGN_REVIEW, STAGE_CUTTING, STAGE_PRINTING, STAGE_SEWING, STAGE_QUALITY_INSPECTION, STAGE_PACKAGING];
$boardOrders = $pdo->query("
  SELECT o.order_id, ow.stage, ow.priority, ow.expected_completion, ow.assigned_employee, ow.product_type,
         u.full_name AS customer, e.full_name AS employee_name,
         (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) as qty
  FROM orders o
  JOIN users u ON o.user_id = u.user_id
  LEFT JOIN order_workflow ow ON o.order_id = ow.order_id
  LEFT JOIN users e ON ow.assigned_employee = e.user_id
  WHERE o.status NOT IN ('Cancelled','Refunded')
  ORDER BY ow.stage
")->fetchAll();
$grouped = [];
foreach ($boardOrders as $b) {
  $grouped[$b['stage']][] = $b;
}
?>
<div class="kanban-scroll">
  <div class="kanban-board" id="miniBoard">
    <?php foreach ($stage_order as $stg):
      $cfg = $STAGE_CONFIG[$stg] ?? ['color' => '#6b7280'];
      $items = $grouped[$stg] ?? [];
    ?>
    <div class="kanban-col">
      <div class="kanban-col-header">
        <span class="col-title">
          <span class="status-dot" style="background:<?= $cfg['color'] ?>"></span>
          <?= htmlspecialchars($STAGE_CONFIG[$stg]['label'] ?? $stg) ?>
        </span>
        <span class="col-count"><?= count($items) ?></span>
      </div>
      <?php if (empty($items)): ?>
      <div class="kanban-empty">No orders</div>
      <?php else: foreach (array_slice($items, 0, 4) as $item):
        $pct = getStageProgress($item['stage']);
        $empName = $item['employee_name'] ?? 'Unassigned';
        $empInit = $empName === 'Unassigned' ? '?' : implode('', array_map(fn($w)=>strtoupper($w[0]), explode(' ', $empName)));
        $bClass = $item['priority']==='urgent' ? 'border-left-urgent' : ($item['priority']==='high' ? 'border-left-high' : '');
        $pDot = $item['priority']==='urgent' ? 'var(--accent-red)' : ($item['priority']==='high' ? 'var(--accent-amber)' : 'var(--text-tertiary)');
      ?>
      <div class="kanban-card <?= $bClass ?>">
        <div class="card-top">
          <span class="order-id">#ORD-<?= $item['order_id'] ?></span>
          <span class="priority-dot" style="background:<?= $pDot ?>"></span>
        </div>
        <div class="customer"><?= htmlspecialchars($item['customer']) ?></div>
        <div class="meta">
          <span><?= htmlspecialchars($item['product_type'] ?? 'Garment') ?></span>
          <span>Qty: <?= (int)$item['qty'] ?></span>
        </div>
        <div class="assignee">
          <span class="initial"><?= htmlspecialchars(substr($empInit, 0, 2)) ?></span>
          <?= htmlspecialchars($empName) ?>
        </div>
        <div class="progress"><div class="fill" style="width:<?= $pct ?>%;background:<?= $cfg['color'] ?>"></div></div>
      </div>
      <?php endforeach; endif; ?>
      <?php if (count($items) > 4): ?>
      <div class="kanban-more">+<?= count($items)-4 ?> more</div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php
$kanbanHtml = ob_get_clean();

// ── Build Quick Stats panel ──
$statsBody = '<div class="quick-stats-grid">';
$statsBody .= '<div><span class="quick-stats-label">Revenue</span><br><strong class="quick-stats-value">₱' . number_format($totalSales, 0) . '</strong></div>';
$statsBody .= '<div><span class="quick-stats-label">Employees</span><br><strong class="quick-stats-value">' . $totalEmployees . '</strong></div>';
$statsBody .= '<div><span class="quick-stats-label">Overdue</span><br><strong style="font-size:1.1rem;color:var(--color-danger)">' . $overdueCount . '</strong></div>';
$statsBody .= '<div><span class="quick-stats-label">Busiest</span><br><strong style="font-size:.8rem">' . htmlspecialchars($busyEmp['full_name'] ?? 'N/A') . '</strong></div>';
$statsBody .= '</div>';

// ── Build QC Queue panel ──
$qcBody = '<div class="qc-list">';
if (empty($qcQueue)):
  $qcBody .= '<div class="empty-state" style="padding:8px 0;border:none">No pending inspections</div>';
else: foreach ($qcQueue as $q):
  $result = strtolower($q['result'] ?? 'pending');
  $qcBody .= '<div class="qc-item"><span class="qc-order">#ORD-' . $q['order_id'] . '</span>'
          .  '<span class="qc-status ' . $result . '">' . ($q['result'] ?? 'Pending') . '</span></div>';
endforeach; endif;
$qcBody .= '</div>';

// ── Build Activity feed items ──
$activityItems = [];
foreach ($activity as $a):
  $dotColor = $a['note_type']==='issue' ? 'var(--color-danger)' : ($a['note_type']==='handoff' ? 'var(--color-warning)' : 'var(--role-accent)');
  $activityItems[] = [
    'title' => '#ORD-' . $a['order_id'],
    'description' => htmlspecialchars(substr($a['content'], 0, 60)),
    'author' => htmlspecialchars($a['author']),
    'time' => date('g:i A', strtotime($a['created_at'])),
    'dotColor' => $dotColor,
  ];
endforeach;

// ── Render via design system ──
echo renderDashboardShell(
  renderPageHeader(
    'Production Overview',
    "Welcome back, {$firstName} · " . date('l, F j'),
    '',
    [['label' => 'Full Board', 'href' => 'production_board.php', 'icon' => 'fas fa-external-link-alt', 'variant' => 'outline', 'size' => 'sm'],
     ['label' => 'QC Center', 'href' => 'quality_control.php', 'icon' => 'fas fa-clipboard-check', 'variant' => 'primary', 'size' => 'sm']]
  ),
  renderKPIRow([
    ['icon' => 'fas fa-shopping-bag',  'label' => 'Total Orders',     'value' => $totalOrders,   'trendLabel' => '↑ 12% this month'],
    ['icon' => 'fas fa-cog',            'label' => 'In Production',    'value' => $inProduction,  'trendLabel' => "{$overdueCount} overdue", 'accent' => 'amber'],
    ['icon' => 'fas fa-clipboard-check','label' => 'Waiting for QC',  'value' => $inQC,          'trendLabel' => 'Needs review'],
    ['icon' => 'fas fa-check-circle',   'label' => 'Completed Today',  'value' => $completedToday,'trendLabel' => "+{$completedToday} today", 'accent' => 'green'],
  ]),
  renderPageSection(
    'Production Board',
    $kanbanHtml,
    'fas fa-columns',
    [['label' => 'Full Board', 'href' => 'production_board.php', 'icon' => 'fas fa-external-link-alt', 'variant' => 'outline'],
     ['label' => 'QC Center',  'href' => 'quality_control.php',  'icon' => 'fas fa-clipboard-check',  'variant' => 'primary']],
    renderPanelCard('Quick Stats', $statsBody, 'fas fa-chart-pie')
    . renderPanelCard('QC Queue', $qcBody, 'fas fa-clipboard-check', 'quality_control.php', 'View All')
    . renderPanelCard('Recent Activity', renderActivityFeed($activityItems, ['viewAllLink' => '../admin/quality_control.php']), 'fas fa-clock')
  )
);
?>
    </div>
  </div>

</body>
</html>
