<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/auth_required.php';
require_once '../../app/Support/helpers.php';

if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Employee';
$firstName = htmlspecialchars(explode(' ', $full_name)[0]);
$dashboardContext = get_user_position_context($pdo, (int) $user_id);

if ($dashboardContext['sidebar'] !== 'employee') {
    header('Location: ' . $dashboardContext['dashboard']);
    exit();
}

$role = get_user_role();
$roleAttr = in_array($role, ['admin','customer','production_staff','quality_control_inspector','inventory_manager','operations_manager','senior_tailor']) ? $role : 'production_staff';

$position = getEmployeePosition($pdo, $user_id);
$position_id = $position ? (int)$position['position_id'] : 0;
$allowed_stages = getPositionStages($position_id);
$stage_placeholders = implode(',', array_fill(0, count($allowed_stages), '?'));
$stage_params = $allowed_stages;

// Stats
$activeCount = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND o.status NOT IN ('Completed','Cancelled') AND ow.stage IN ({$stage_placeholders})");
$activeCount->execute(array_merge([$user_id], $stage_params));
$activeCountVal = (int)$activeCount->fetchColumn();

$pendingQCCount = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND ow.stage = 'Quality Inspection'");
$pendingQCCount->execute([$user_id]);
$pendingQCCountVal = (int)$pendingQCCount->fetchColumn();

$completedToday = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND o.status = 'Completed' AND DATE(o.completion_date) = CURDATE()");
$completedToday->execute([$user_id]);
$completedTodayVal = (int)$completedToday->fetchColumn();

$weekStart = date('Y-m-d', strtotime('monday this week'));
$weeklyCount = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND o.completion_date >= ? AND o.status = 'Completed'");
$weeklyCount->execute([$user_id, $weekStart]);
$weeklyCountVal = (int)$weeklyCount->fetchColumn();

// Active task
$activeTask = $pdo->prepare("
    SELECT o.order_id, ow.stage, ow.product_type, o.total_price,
           u.full_name AS customer_name, ow.expected_completion,
           ow.priority, ow.started_at,
           (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) as total_qty
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    WHERE ow.assigned_employee = ? AND o.status NOT IN ('Completed','Cancelled')
    AND ow.stage IN ({$stage_placeholders})
    ORDER BY ow.priority DESC, ow.expected_completion ASC LIMIT 1
"); $activeTask->execute(array_merge([$user_id], $stage_params));
$active = $activeTask->fetch();

// Task list
$tasks = $pdo->prepare("
    SELECT o.order_id, ow.stage, ow.product_type, ow.expected_completion,
           ow.priority, ow.workflow_notes,
           u.full_name AS customer_name,
           (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) as total_qty
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    WHERE ow.assigned_employee = ? AND o.status NOT IN ('Completed','Cancelled')
    AND ow.stage != 'Quality Inspection' AND ow.stage IN ({$stage_placeholders})
    ORDER BY CASE ow.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
             ow.expected_completion ASC
"); $tasks->execute(array_merge([$user_id], $stage_params));
$tasksList = $tasks->fetchAll();

// QC tasks
$qcTasks = $pdo->prepare("
    SELECT o.order_id, ow.stage, ow.product_type, ow.expected_completion,
           u.full_name AS customer_name, qc.result AS qc_result,
           (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) as total_qty
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN qc_inspections qc ON o.order_id = qc.order_id
    WHERE ow.assigned_employee = ? AND ow.stage = 'Quality Inspection'
    ORDER BY ow.expected_completion ASC
"); $qcTasks->execute([$user_id]);
$qcTasksList = $qcTasks->fetchAll();

// Activity
$activity = $pdo->prepare("
    SELECT content, created_at, order_id, note_type
    FROM production_notes
    WHERE author_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC LIMIT 8
"); $activity->execute([$user_id]);
$activityList = $activity->fetchAll();

$positionName = htmlspecialchars($position['position_name'] ?? 'Employee');
$pageTitle = $positionName . ' Dashboard';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
  <link rel="stylesheet" href="/public/assets/css/components.css">
</head>
<body data-role="<?= $roleAttr ?>">
  <div class="dash-layout">
    <?php require_once __DIR__ . '/../../app/Views/Shared/Sidebars/employee.php'; ?>
    <div class="dash-main">
<?php
// ── Build active task card ──
$activeTaskHtml = '';
if ($active):
  $aPclass = $active['priority']==='urgent' ? 'border-left-urgent' : ($active['priority']==='high' ? 'border-left-high' : 'border-left-medium');
  $aStatus = $active['priority']==='urgent' ? 'danger' : ($active['priority']==='high' ? 'warning' : 'info');
  $aColor = $active['priority']==='urgent' ? 'var(--color-danger)' : ($active['priority']==='high' ? 'var(--color-warning)' : 'var(--role-accent)');
  $pct = getStageProgress($active['stage']);
  ob_start();
?>
<div class="panel-card" style="margin-bottom:16px;<?= $aPclass ?>;border-left:4px solid <?= $aColor ?>">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px">
    <div>
      <div style="font-size:0.9rem;font-weight:700;color:var(--text-primary)">#ORD-<?= $active['order_id'] ?> — <?= htmlspecialchars($active['product_type'] ?? 'Garment') ?></div>
      <div style="font-size:0.8rem;color:var(--text-secondary)"><?= htmlspecialchars($active['customer_name']) ?> · Qty: <?= (int)$active['total_qty'] ?></div>
    </div>
    <?= renderStatusBadge(ucfirst($active['priority'] ?? 'medium'), $aStatus, 'sm') ?>
  </div>
  <div class="progress-bar" style="margin-bottom:12px">
    <div class="progress-bar-track"><div class="progress-bar-fill" style="width:<?= $pct ?>%;background:<?= $aColor ?>"></div></div>
    <span class="progress-bar-label"><?= $pct ?>%</span>
  </div>
  <div style="display:flex;gap:8px">
    <a href="view_task.php?id=<?= $active['order_id'] ?>" class="dash-btn dash-btn-accent dash-btn-sm"><i class="fas fa-play"></i> Continue</a>
    <?php if ($active['stage'] !== 'Quality Inspection'): ?>
    <form method="post" action="my_tasks.php" style="display:inline">
      <input type="hidden" name="submit_qc" value="<?= $active['order_id'] ?>">
      <button type="submit" class="dash-btn dash-btn-outline dash-btn-sm"><i class="fas fa-check"></i> Submit to QC</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<?php
  $activeTaskHtml = ob_get_clean();
else:
  $activeTaskHtml = renderEmptyState('fas fa-inbox', 'No active task', 'Check your full task list to find work.',
    ['label' => 'View Tasks', 'href' => 'my_tasks.php', 'icon' => 'fas fa-tasks', 'size' => 'sm']);
endif;

// ── Build task grid ──
$tasksHtml = '';
if (empty($tasksList)):
  $tasksHtml = renderEmptyState('fas fa-check-circle', 'All caught up!', 'No pending tasks assigned to you.');
else:
  ob_start();
  $count = 0;
  foreach ($tasksList as $t):
    if (++$count > 4) break;
    $tStatus = $t['priority']==='urgent' ? 'danger' : ($t['priority']==='high' ? 'warning' : 'info');
    $p = getStageProgress($t['stage']);
?>
<div class="task-card">
  <div class="task-card-header">
    <span class="task-card-title">#ORD-<?= $t['order_id'] ?></span>
    <?= renderStatusBadge(ucfirst($t['priority'] ?? 'med'), $tStatus, 'sm') ?>
  </div>
  <div class="task-card-meta">
    <?= htmlspecialchars($t['customer_name']) ?> · <?= htmlspecialchars($t['product_type'] ?? 'Garment') ?> · Qty: <?= (int)$t['total_qty'] ?>
  </div>
  <div class="progress-bar">
    <div class="progress-bar-track"><div class="progress-bar-fill" style="width:<?= $p ?>%"></div></div>
    <span class="progress-bar-label"><?= $p ?>%</span>
  </div>
  <div class="task-card-footer">
    <a href="view_task.php?id=<?= $t['order_id'] ?>" class="dash-btn dash-btn-accent dash-btn-sm"><i class="fas fa-eye"></i> View</a>
  </div>
</div>
<?php
  endforeach;
  $tasksHtml = ob_get_clean();
endif;

// ── Build QC sidebar ──
$qcBody = '<div class="qc-list">';
if (empty($qcTasksList)):
  $qcBody .= '<div class="empty-state" style="padding:8px 0;border:none">No items in QC</div>';
else: foreach ($qcTasksList as $q):
  $qr = strtolower($q['qc_result'] ?? 'pending');
  $qcBody .= '<div class="qc-item"><span class="qc-order">#ORD-' . $q['order_id'] . '</span>'
          .  '<span class="qc-status ' . $qr . '">' . ($q['qc_result'] ?? 'Pending') . '</span></div>';
endforeach; endif;
$qcBody .= '</div>';

// ── Build activity feed ──
$activityItems = [];
foreach ($activityList as $a):
  $dotColor = $a['note_type']==='issue' ? 'var(--color-danger)' : ($a['note_type']==='handoff' ? 'var(--color-warning)' : 'var(--role-accent)');
  $activityItems[] = [
    'title' => '#ORD-' . $a['order_id'],
    'description' => htmlspecialchars(substr($a['content'], 0, 50)),
    'time' => date('g:i A', strtotime($a['created_at'])),
    'dotColor' => $dotColor,
  ];
endforeach;

// ── Render ──
echo renderDashboardShell(
  renderPageHeader(
    $positionName . ' Dashboard',
    "Welcome back, {$firstName} · " . date('l, F j, Y'),
    ''),
  renderKPIRow([
    ['icon' => 'fas fa-tasks',         'label' => 'Active Tasks',    'value' => $activeCountVal,    'accent' => 'blue'],
    ['icon' => 'fas fa-clipboard-check','label' => 'Pending QC',     'value' => $pendingQCCountVal, 'accent' => 'amber'],
    ['icon' => 'fas fa-check-circle',   'label' => 'Completed Today','value' => $completedTodayVal,  'accent' => 'green'],
    ['icon' => 'fas fa-calendar-week',  'label' => 'This Week',      'value' => $weeklyCountVal,    'accent' => 'purple'],
  ]),
  $activeTaskHtml, // Before main workspace
  ''
);
// Use two-column for work area
echo renderTwoColumn(
  renderPageSection('My Tasks', $tasksHtml, 'fas fa-tasks',
    [['label' => 'View All', 'href' => 'my_tasks.php', 'icon' => 'fas fa-external-link-alt', 'variant' => 'outline']]),
  renderPanelCard('QC Status', $qcBody, 'fas fa-clipboard-check')
  . renderPanelCard('My Activity', renderActivityFeed($activityItems), 'fas fa-clock')
);
?>
    </div>
  </div>
</body>
</html>
