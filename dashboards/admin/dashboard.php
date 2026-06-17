<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../middleware/role_admin_only.php';

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Admin';

// Stats
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$inProduction = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Completed','Cancelled','Refunded')")->fetchColumn();
$inQC = $pdo->query("SELECT COUNT(*) FROM order_workflow WHERE stage = 'Quality Inspection'")->fetchColumn();
$completedToday = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(completion_date) = CURDATE() AND status = 'Completed'")->fetchColumn();
$overdueCount = $pdo->query("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.expected_completion IS NOT NULL AND ow.expected_completion < NOW() AND o.status NOT IN ('Completed','Cancelled','Refunded')")->fetchColumn();
$totalSales = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status = 'Completed'")->fetchColumn() ?: 0;
$totalEmployees = $pdo->query("SELECT COUNT(*) FROM employees e JOIN statuses s ON e.status_id = s.status_id WHERE s.status_name = 'Active'")->fetchColumn();

// Charts
$chartLabels = []; $chartData = [];
for ($i = 6; $i >= 0; $i--) {
  $chartLabels[] = date('M d', strtotime("-$i days"));
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(order_date) = ?");
  $stmt->execute([date('Y-m-d', strtotime("-$i days"))]);
  $chartData[] = $stmt->fetchColumn();
}

$statusLabels = ['Pending','In Progress','Completed','Cancelled'];
$statusCounts = [];
foreach ($statusLabels as $s) {
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
  $stmt->execute([$s]);
  $statusCounts[] = $stmt->fetchColumn();
}

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

$initials = $full_name ? implode('', array_map(fn($w) => strtoupper($w[0]), explode(' ', trim($full_name)))) : 'A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — Sakuragi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="dash-layout">

    <!-- ── Sidebar ── -->
    <aside class="sidebar-modern" id="sidebar">
      <div class="sidebar-brand">
        <svg viewBox="0 0 28 28" fill="none" style="width:24px;height:24px">
          <rect width="28" height="28" rx="6" fill="#1e3a5f"/>
          <path d="M7 10h14l-3 8H10L7 10z" fill="#fff" opacity=".9"/>
        </svg>
        <span>Sakuragi</span>
      </div>
      <nav class="sidebar-nav">
        <div class="section-label">Main</div>
        <a href="dashboard.php" class="sidebar-item active"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="production_board.php" class="sidebar-item"><i class="fas fa-columns"></i> Production</a>
        <a href="orders.php" class="sidebar-item"><i class="fas fa-shopping-bag"></i> Orders</a>
        <a href="employees.php" class="sidebar-item"><i class="fas fa-users"></i> Employees</a>
        <div class="section-label">Operations</div>
        <a href="inventory.php" class="sidebar-item"><i class="fas fa-box"></i> Inventory</a>
        <a href="quality_control.php" class="sidebar-item"><i class="fas fa-clipboard-check"></i> Quality Control</a>
        <a href="reports.php" class="sidebar-item"><i class="fas fa-chart-bar"></i> Reports</a>
        <a href="production_analytics.php" class="sidebar-item"><i class="fas fa-analytics"></i> Analytics</a>
        <div class="section-label">System</div>
        <a href="workload.php" class="sidebar-item"><i class="fas fa-tasks"></i> Workload</a>
        <a href="/auth/logout.php" class="sidebar-item" style="color:var(--accent-red)"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
      </nav>
    </aside>

    <!-- ── Main ── -->
    <div class="dash-main">

      <!-- Top Nav -->
      <header class="top-nav">
        <div class="top-nav-left">
          <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
          <div class="top-nav-search">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search orders, customers..." id="globalSearch">
          </div>
        </div>
        <div class="top-nav-right">
          <button class="icon-btn"><i class="fas fa-bell"></i><span class="badge">3</span></button>
          <button class="icon-btn"><i class="fas fa-cog"></i></button>
          <div class="avatar"><?= htmlspecialchars(substr($initials, 0, 2)) ?></div>
        </div>
      </header>

      <!-- Content -->
      <div class="dash-content">

        <!-- Page Header -->
        <div class="page-header">
          <h1>Production Overview</h1>
          <p>Welcome back, <?= htmlspecialchars(explode(' ', $full_name)[0]) ?> · <?= date('l, F j') ?></p>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-row">
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#eef2ff;color:#2563eb"><i class="fas fa-shopping-bag"></i></div>
            <div class="kpi-label">Total Orders</div>
            <div class="kpi-value"><?= $totalOrders ?></div>
            <div class="kpi-change">↑ 12% this month</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-cog"></i></div>
            <div class="kpi-label">In Production</div>
            <div class="kpi-value"><?= $inProduction ?></div>
            <div class="kpi-change" style="color:var(--accent-amber)"><?= $overdueCount ?> overdue</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-clipboard-check"></i></div>
            <div class="kpi-label">Waiting for QC</div>
            <div class="kpi-value"><?= $inQC ?></div>
            <div class="kpi-change" style="color:var(--text-tertiary)">Needs review</div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#fce7f3;color:#db2777"><i class="fas fa-check-circle"></i></div>
            <div class="kpi-label">Completed Today</div>
            <div class="kpi-value"><?= $completedToday ?></div>
            <div class="kpi-change" style="color:var(--accent-emerald)">+<?= $completedToday ?> today</div>
          </div>
        </div>

        <!-- Two Column: Kanban + Side Panel -->
        <div class="dash-two-col">

          <!-- Left: Production Board -->
          <div class="kanban-section">
            <div class="section-header">
              <h2><i class="fas fa-columns" style="margin-right:8px;color:var(--accent-blue)"></i>Production Board</h2>
              <div class="action-bar" style="margin-bottom:0">
                <a href="production_board.php" class="dash-btn dash-btn-outline dash-btn-sm"><i class="fas fa-external-link-alt"></i> Full Board</a>
                <a href="quality_control.php" class="dash-btn dash-btn-primary dash-btn-sm"><i class="fas fa-clipboard-check"></i> QC Center</a>
              </div>
            </div>
            <div class="kanban-scroll">
              <div class="kanban-board" id="miniBoard">
                <?php
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
                foreach ($stage_order as $stg):
                  $cfg = $STAGE_CONFIG[$stg] ?? ['color' => '#6b7280'];
                  $items = $grouped[$stg] ?? [];
                ?>
                <div class="kanban-col">
                  <div class="kanban-col-header">
                    <span class="col-title">
                      <span style="width:8px;height:8px;border-radius:50%;background:<?= $cfg['color'] ?>;display:inline-block"></span>
                      <?= htmlspecialchars($STAGE_CONFIG[$stg]['label'] ?? $stg) ?>
                    </span>
                    <span class="col-count"><?= count($items) ?></span>
                  </div>
                  <?php if (empty($items)): ?>
                  <div style="font-size:.7rem;color:var(--text-tertiary);text-align:center;padding:16px 0">No orders</div>
                  <?php else: foreach (array_slice($items, 0, 4) as $item):
                    $pct = getStageProgress($item['stage']);
                    $empName = $item['employee_name'] ?? 'Unassigned';
                    $empInit = $empName === 'Unassigned' ? '?' : implode('', array_map(fn($w)=>strtoupper($w[0]), explode(' ', $empName)));
                    $urgent = ($item['priority'] ?? 'medium') === 'urgent' || ($item['priority'] ?? 'medium') === 'high';
                  ?>
                  <div class="kanban-card" style="<?= $urgent ? 'border-left:3px solid var(--accent-red)' : '' ?>">
                    <div class="card-top">
                      <span class="order-id">#ORD-<?= $item['order_id'] ?></span>
                      <span class="priority-dot" style="background:<?= $item['priority']==='urgent'?'var(--accent-red)':($item['priority']==='high'?'var(--accent-amber)':'var(--text-tertiary)') ?>"></span>
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
                  <div style="text-align:center;font-size:.7rem;color:var(--accent-blue);margin-top:4px">+<?= count($items)-4 ?> more</div>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- Right: Side Panel -->
          <div class="side-panel">

            <!-- Quick Stats -->
            <div class="panel-card">
              <h3><i class="fas fa-chart-pie" style="color:var(--accent-blue)"></i> Quick Stats</h3>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:.8rem">
                <div><span style="color:var(--text-tertiary)">Revenue</span><br><strong style="font-size:1.1rem">₱<?= number_format($totalSales, 0) ?></strong></div>
                <div><span style="color:var(--text-tertiary)">Employees</span><br><strong style="font-size:1.1rem"><?= $totalEmployees ?></strong></div>
                <div><span style="color:var(--text-tertiary)">Overdue</span><br><strong style="color:var(--accent-red)"><?= $overdueCount ?></strong></div>
                <div><span style="color:var(--text-tertiary)">Busiest</span><br><strong style="font-size:.8rem"><?= htmlspecialchars($busyEmp['full_name'] ?? 'N/A') ?></strong></div>
              </div>
            </div>

            <!-- QC Queue -->
            <div class="panel-card">
              <h3><i class="fas fa-clipboard-check" style="color:var(--accent-emerald)"></i> QC Queue</h3>
              <div class="qc-list">
                <?php if (empty($qcQueue)): ?>
                <div style="font-size:.8rem;color:var(--text-tertiary);text-align:center;padding:8px 0">No pending inspections</div>
                <?php else: foreach ($qcQueue as $q): ?>
                <div class="qc-item">
                  <span class="qc-order">#ORD-<?= $q['order_id'] ?></span>
                  <span class="qc-status <?= strtolower($q['result'] ?? 'pending') ?>"><?= $q['result'] ?? 'Pending' ?></span>
                </div>
                <?php endforeach; endif; ?>
              </div>
              <a href="quality_control.php" style="display:block;text-align:center;margin-top:12px;font-size:.8rem;color:var(--accent-blue);font-weight:600">View All</a>
            </div>

            <!-- Recent Activity -->
            <div class="panel-card">
              <h3><i class="fas fa-clock" style="color:var(--accent-purple)"></i> Recent Activity</h3>
              <?php foreach ($activity as $a): ?>
              <div class="activity-item">
                <span class="dot" style="background:<?= $a['note_type']==='issue'?'var(--accent-red)':($a['note_type']==='handoff'?'var(--accent-amber)':'var(--accent-blue)') ?>"></span>
                <div class="text">
                  <strong>#ORD-<?= $a['order_id'] ?></strong> <?= htmlspecialchars(substr($a['content'], 0, 60)) ?>
                  <div class="time">by <?= htmlspecialchars($a['author']) ?> · <?= date('g:i A', strtotime($a['created_at'])) ?></div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>

          </div>
        </div>

      </div>
    </div>
  </div>

  <script>
  // Sidebar toggle
  document.getElementById('menuToggle')?.addEventListener('click', function() {
    document.getElementById('sidebar')?.classList.toggle('collapsed');
  });

  // Global search
  document.getElementById('globalSearch')?.addEventListener('keyup', function(e) {
    if (e.key === 'Enter' && this.value.trim()) {
      window.location.href = 'orders.php?search=' + encodeURIComponent(this.value.trim());
    }
  });
  </script>

</body>
</html>
