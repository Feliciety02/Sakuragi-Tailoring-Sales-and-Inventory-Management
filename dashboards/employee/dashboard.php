<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../app/Middleware/auth_required.php';
require_once '../../app/Support/helpers.php';

if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Employee';
$dashboardContext = get_user_position_context($pdo, (int) $user_id);

if ($dashboardContext['sidebar'] !== 'employee') {
    header('Location: ' . $dashboardContext['dashboard']);
    exit();
}

$position = getEmployeePosition($pdo, $user_id);
$position_id = $position ? (int)$position['position_id'] : 0;
$allowed_stages = getPositionStages($position_id);
$stage_placeholders = implode(',', array_fill(0, count($allowed_stages), '?'));
$stage_params = $allowed_stages;

$initials = implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', trim($full_name))));

// Stats
$activeCount = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND o.status NOT IN ('Completed','Cancelled') AND ow.stage IN ({$stage_placeholders})");
$activeCount->execute(array_merge([$user_id], $stage_params));

$pendingQCCount = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND ow.stage = 'Quality Inspection'");
$pendingQCCount->execute([$user_id]);

$completedToday = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND o.status = 'Completed' AND DATE(o.completion_date) = CURDATE()");
$completedToday->execute([$user_id]);

$weekStart = date('Y-m-d', strtotime('monday this week'));
$weeklyCount = $pdo->prepare("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.assigned_employee = ? AND o.completion_date >= ? AND o.status = 'Completed'");
$weeklyCount->execute([$user_id, $weekStart]);

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

// Activity
$activity = $pdo->prepare("
    SELECT content, created_at, order_id, note_type
    FROM production_notes
    WHERE author_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC LIMIT 8
"); $activity->execute([$user_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Sakuragi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
</head>
<body>
  <div class="dash-layout">

    <!-- Sidebar -->
    <aside class="sidebar-modern" id="sidebar">
      <div class="sidebar-brand">
        <svg viewBox="0 0 28 28" fill="none" style="width:24px;height:24px">
          <rect width="28" height="28" rx="6" fill="#1e3a5f"/>
          <path d="M7 10h14l-3 8H10L7 10z" fill="#fff" opacity=".9"/>
        </svg>
        <span>Sakuragi</span>
      </div>
      <nav class="sidebar-nav">
        <div class="section-label">Workspace</div>
        <a href="dashboard.php" class="sidebar-item active"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="my_tasks.php" class="sidebar-item"><i class="fas fa-tasks"></i> My Tasks</a>
        <a href="kanban.php" class="sidebar-item"><i class="fas fa-columns"></i> Kanban</a>
        <a href="completed_tasks.php" class="sidebar-item"><i class="fas fa-check-circle"></i> Completed</a>
        <div class="section-label">Resources</div>
        <a href="inventory.php" class="sidebar-item"><i class="fas fa-box"></i> Inventory</a>
        <a href="profile.php" class="sidebar-item"><i class="fas fa-user"></i> Profile</a>
        <div class="sidebar-footer">
          <a href="/auth/logout.php" class="sidebar-item" style="color:var(--accent-red)"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
        </div>
      </nav>
    </aside>

    <!-- Main -->
    <div class="dash-main">

      <header class="top-nav">
        <div class="top-nav-left">
          <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
          <div style="font-size:.9rem;font-weight:600;color:var(--text-secondary)">
            <?= htmlspecialchars($position['position_name'] ?? 'Employee') ?>
          </div>
        </div>
        <div class="top-nav-right">
          <button class="icon-btn"><i class="fas fa-bell"></i></button>
          <div class="avatar"><?= htmlspecialchars(substr($initials, 0, 2)) ?></div>
        </div>
      </header>

      <div class="dash-content">

        <div class="page-header">
          <h1>Welcome, <?= htmlspecialchars(explode(' ', $full_name)[0]) ?></h1>
          <p><?= date('l, F j, Y') ?> · <?= htmlspecialchars($position['position_name'] ?? 'Employee') ?> Dashboard</p>
        </div>

        <!-- KPI -->
        <div class="kpi-row">
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#eef2ff;color:#2563eb"><i class="fas fa-tasks"></i></div>
            <div class="kpi-label">Active Tasks</div>
            <div class="kpi-value"><?= $activeCount->fetchColumn() ?></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-clipboard-check"></i></div>
            <div class="kpi-label">Pending QC</div>
            <div class="kpi-value"><?= $pendingQCCount->fetchColumn() ?></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-check-circle"></i></div>
            <div class="kpi-label">Completed Today</div>
            <div class="kpi-value"><?= $completedToday->fetchColumn() ?></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#fce7f3;color:#db2777"><i class="fas fa-calendar-week"></i></div>
            <div class="kpi-label">This Week</div>
            <div class="kpi-value"><?= $weeklyCount->fetchColumn() ?></div>
          </div>
        </div>

        <!-- Main layout -->
        <div class="dash-two-col">
          <div>

            <!-- Current Active Job -->
            <?php if ($active): ?>
            <div class="panel-card" style="margin-bottom:16px;border-left:4px solid <?= $active['priority']==='urgent'?'var(--accent-red)':($active['priority']==='high'?'var(--accent-amber)':'var(--accent-blue)') ?>">
              <div style="display:flex;justify-content:space-between;align-items:start">
                <div>
                  <h3 style="font-size:.9rem;font-weight:700;color:var(--text-primary);margin-bottom:4px">#ORD-<?= $active['order_id'] ?> — <?= htmlspecialchars($active['product_type'] ?? 'Garment') ?></h3>
                  <p style="font-size:.8rem;color:var(--text-secondary)"><?= htmlspecialchars($active['customer_name']) ?> · Qty: <?= (int)$active['total_qty'] ?></p>
                </div>
                <span class="qc-status <?= $active['priority']==='urgent'?'failed':($active['priority']==='high'?'pending':'passed') ?>"><?= ucfirst($active['priority'] ?? 'med') ?></span>
              </div>
              <?php $pct = getStageProgress($active['stage']); ?>
              <div style="display:flex;align-items:center;gap:8px;margin:12px 0">
                <div style="flex:1;height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden">
                  <div style="width:<?= $pct ?>%;height:100%;background:<?= $active['priority']==='urgent'?'var(--accent-red)':($active['priority']==='high'?'var(--accent-amber)':'var(--accent-blue)') ?>;border-radius:3px"></div>
                </div>
                <span style="font-size:.75rem;color:var(--text-tertiary)"><?= $pct ?>%</span>
              </div>
              <div style="display:flex;gap:8px">
                <a href="view_task.php?id=<?= $active['order_id'] ?>" class="dash-btn dash-btn-primary dash-btn-sm"><i class="fas fa-play"></i> Continue</a>
                <?php if ($active['stage'] !== STAGE_QUALITY_INSPECTION): ?>
                <form method="post" action="my_tasks.php" style="display:inline">
                  <input type="hidden" name="submit_qc" value="<?= $active['order_id'] ?>">
                  <button type="submit" class="dash-btn dash-btn-outline dash-btn-sm"><i class="fas fa-check"></i> Submit to QC</button>
                </form>
                <?php endif; ?>
              </div>
            </div>
            <?php else: ?>
            <div class="panel-card" style="margin-bottom:16px">
              <p style="font-size:.85rem;color:var(--text-tertiary);text-align:center;padding:16px 0">No active task. Check your <a href="my_tasks.php" style="color:var(--accent-blue);font-weight:600">task list</a>.</p>
            </div>
            <?php endif; ?>

            <!-- Task Cards -->
            <div class="section-header" style="margin-bottom:12px">
              <h2 style="font-size:1rem">My Tasks</h2>
              <a href="my_tasks.php" style="font-size:.8rem;color:var(--accent-blue);font-weight:600">View all</a>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
              <?php $count = 0; foreach ($tasks as $t): if (++$count > 4) break; ?>
              <div class="task-card">
                <div class="task-header">
                  <span class="task-id">#ORD-<?= $t['order_id'] ?></span>
                  <span class="qc-status <?= $t['priority']==='urgent'?'failed':($t['priority']==='high'?'pending':'passed') ?>"><?= ucfirst($t['priority'] ?? 'med') ?></span>
                </div>
                <div class="task-meta"><?= htmlspecialchars($t['customer_name']) ?> · <?= htmlspecialchars($t['product_type'] ?? 'Garment') ?> · Qty: <?= (int)$t['total_qty'] ?></div>
                <?php $p = getStageProgress($t['stage']); ?>
                <div class="task-progress">
                  <div class="bar"><div class="fill" style="width:<?= $p ?>%;background:var(--accent-blue)"></div></div>
                  <span style="font-size:.7rem;color:var(--text-tertiary)"><?= $p ?>%</span>
                </div>
                <div class="task-actions">
                  <a href="view_task.php?id=<?= $t['order_id'] ?>" class="dash-btn dash-btn-primary dash-btn-sm"><i class="fas fa-eye"></i> View</a>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

          </div>

          <!-- Right Panel -->
          <div class="side-panel">

            <!-- QC Status -->
            <div class="panel-card">
              <h3><i class="fas fa-clipboard-check" style="color:var(--accent-emerald)"></i> QC Status</h3>
              <div class="qc-list">
                <?php if ($qcTasks->rowCount() === 0): ?>
                <div style="font-size:.8rem;color:var(--text-tertiary);text-align:center;padding:8px 0">No items in QC</div>
                <?php else: foreach ($qcTasks as $q): ?>
                <div class="qc-item">
                  <span class="qc-order">#ORD-<?= $q['order_id'] ?></span>
                  <span class="qc-status <?= strtolower($q['qc_result'] ?? 'pending') ?>"><?= $q['qc_result'] ?? 'Pending' ?></span>
                </div>
                <?php endforeach; endif; ?>
              </div>
            </div>

            <!-- Recent Activity -->
            <div class="panel-card">
              <h3><i class="fas fa-clock" style="color:var(--accent-purple)"></i> My Activity</h3>
              <?php if ($activity->rowCount() === 0): ?>
              <div style="font-size:.8rem;color:var(--text-tertiary);text-align:center;padding:8px 0">No recent activity</div>
              <?php else: foreach ($activity as $a): ?>
              <div class="activity-item">
                <span class="dot" style="background:<?= $a['note_type']==='issue'?'var(--accent-red)':($a['note_type']==='handoff'?'var(--accent-amber)':'var(--accent-blue)') ?>"></span>
                <div class="text">
                  <strong>#ORD-<?= $a['order_id'] ?></strong> <?= htmlspecialchars(substr($a['content'], 0, 50)) ?>
                  <div class="time"><?= date('g:i A', strtotime($a['created_at'])) ?></div>
                </div>
              </div>
              <?php endforeach; endif; ?>
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
  </script>

</body>
</html>
