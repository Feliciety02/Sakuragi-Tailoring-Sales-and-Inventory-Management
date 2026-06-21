<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once '../../../../app/Middleware/auth_required.php';
require_once '../../../../config/db_connect.php';

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'Senior Tailor';
try {
    $userSql = "SELECT e.position_id FROM employees e WHERE e.user_id = ?";
    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();

    $positionSql = 'SELECT position_name FROM positions WHERE position_id = ?';
    $positionStmt = $pdo->prepare($positionSql);
    $positionStmt->execute([$user['position_id'] ?? 0]);
    $position = $positionStmt->fetch();
    $positionName = $position ? $position['position_name'] : '';

    if ($positionName !== 'Senior Tailor') {
        header('Location: /dashboards/employee/dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    error_log('Error: ' . $e->getMessage());
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

// Stats (placeholder for now - integrate real queries later)
$passedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = $user_id AND DATE(inspected_at) = CURDATE() AND result = 'Passed'")->fetchColumn() ?: 0;
$failedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = $user_id AND DATE(inspected_at) = CURDATE() AND result = 'Failed'")->fetchColumn() ?: 0;
$pendingCount = $pdo->query("SELECT COUNT(*) FROM order_workflow WHERE stage = 'Quality Inspection'")->fetchColumn() ?: 0;
$pageTitle = 'Senior Tailor Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Senior Tailor Dashboard — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <style>
    .st-card { background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--border); padding: 1.5rem; transition: .2s; }
    .st-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); }
    .st-metric { display: flex; align-items: center; gap: 1rem; }
    .st-metric-icon { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .st-metric-icon.passed { background: rgba(40,167,69,.15); color: #28a745; }
    .st-metric-icon.failed { background: rgba(220,53,69,.15); color: #dc3545; }
    .st-metric-icon.pending { background: rgba(255,193,7,.15); color: #ffc107; }
    .st-metric-value { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); }
    .st-metric-label { font-size: .8rem; color: var(--text-secondary); }
    .st-next-item { background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--border); padding: 1.5rem; }
    .st-item-details { display: flex; align-items: center; gap: 1rem; }
    .st-item-image { width: 64px; height: 64px; border-radius: var(--radius-md); background: #f1f5f9; display: flex; align-items: center; justify-content: center; flex-shrink: 0; color: var(--text-tertiary); }
    .st-item-info { flex: 1; }
    .st-item-id { font-weight: 700; font-size: .95rem; color: var(--text-primary); display: flex; align-items: center; gap: 8px; }
    .st-item-name { font-size: 1rem; font-weight: 600; color: var(--text-primary); margin: 2px 0; }
    .st-item-meta { font-size: .8rem; color: var(--text-secondary); }
    .st-priority { display: inline-flex; align-items: center; gap: 4px; background: rgba(220,53,69,.1); color: #dc3545; padding: 2px 10px; border-radius: 100px; font-size: .75rem; font-weight: 600; }
    .st-action-btn { background: var(--accent); color: #fff; border: none; padding: 10px 20px; border-radius: var(--radius-sm); font-weight: 600; font-size: .85rem; cursor: pointer; transition: .2s; display: inline-flex; align-items: center; gap: 6px; }
    .st-action-btn:hover { background: var(--accent-blue); }
    .st-perf { background: var(--surface); border-radius: var(--radius-lg); border: 1px solid var(--border); padding: 1.5rem; }
    .st-perf-title { font-weight: 700; font-size: 1rem; color: var(--text-primary); margin-bottom: 2px; }
    .st-perf-sub { font-size: .8rem; color: var(--text-secondary); margin-bottom: 1.5rem; }
    .st-progress-item { margin-bottom: 1rem; }
    .st-progress-label { display: flex; justify-content: space-between; font-size: .85rem; color: var(--text-secondary); margin-bottom: 6px; }
    .st-progress-bar { height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden; }
    .st-progress-fill { height: 100%; border-radius: 4px; }
    .st-activity-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-light); }
    .st-activity-item:last-child { border-bottom: none; }
    .st-activity-left { display: flex; align-items: center; gap: 10px; }
    .st-activity-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    @media (max-width: 768px) { .st-item-details { flex-direction: column; align-items: flex-start; } }
  </style>
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../../../app/Views/Shared/Sidebars/senior_tailor.php'; ?>
  <div class="dash-main">
    <?php require_once '../../../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
      <div class="page-header">
        <h1>Welcome, <?= htmlspecialchars(explode(' ', $full_name)[0]) ?></h1>
        <p><?= date('l, F j, Y') ?> · Senior Tailor Dashboard</p>
      </div>

      <!-- Status Cards -->
      <div class="kpi-row">
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-check-circle"></i></div>
          <div class="kpi-label">Items Passed Today</div>
          <div class="kpi-value"><?= $passedToday ?></div>
          <div class="kpi-change">Approved</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-times-circle"></i></div>
          <div class="kpi-label">Items Failed</div>
          <div class="kpi-value"><?= $failedToday ?></div>
          <div class="kpi-change" style="color:var(--accent-red)">Requiring attention</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-hourglass-half"></i></div>
          <div class="kpi-label">Pending Inspections</div>
          <div class="kpi-value"><?= $pendingCount ?></div>
          <div class="kpi-change" style="color:var(--text-tertiary)">Waiting for review</div>
        </div>
      </div>

      <!-- Next Item to Inspect -->
      <div class="panel-card" style="margin-bottom:24px">
        <h3><i class="fas fa-binoculars" style="color:var(--accent-blue)"></i> Next Item to Inspect</h3>
        <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
          <div style="width:64px;height:64px;border-radius:var(--radius-md);background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--text-tertiary);font-size:1.5rem">
            <i class="fas fa-tshirt"></i>
          </div>
          <div style="flex:1;min-width:200px">
            <div style="font-weight:700;color:var(--text-primary);display:flex;align-items:center;gap:8px;flex-wrap:wrap">
              QC-5237 <span style="font-weight:400;font-size:.8rem;color:var(--text-secondary)">Order #ORD-7982</span>
            </div>
            <div style="font-size:1rem;font-weight:600;color:var(--text-primary);margin:2px 0">Wool Suit Jacket</div>
            <div style="font-size:.8rem;color:var(--text-secondary)">Crafted by: Marcus Wilson</div>
            <span class="qc-status failed" style="display:inline-flex;align-items:center;gap:4px;margin-top:4px"><i class="fas fa-star"></i> High priority</span>
          </div>
          <a href="item-to-inspect.php" class="dash-btn dash-btn-primary"><i class="fas fa-arrow-right"></i> Start Inspection</a>
        </div>
      </div>

      <!-- Bottom Section -->
      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px">
        <!-- Performance -->
        <div class="panel-card">
          <h3><i class="fas fa-chart-line" style="color:var(--accent-emerald)"></i> Today's Performance</h3>
          <p style="font-size:.8rem;color:var(--text-secondary);margin-bottom:1.5rem">Quality check efficiency</p>
          <div style="margin-bottom:1rem">
            <div style="display:flex;justify-content:space-between;font-size:.85rem;color:var(--text-secondary);margin-bottom:6px">
              <span>Inspection Rate</span><span>75%</span>
            </div>
            <div style="height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden">
              <div style="width:75%;height:100%;border-radius:4px;background:var(--accent-blue)"></div>
            </div>
          </div>
          <div style="margin-bottom:1rem">
            <div style="display:flex;justify-content:space-between;font-size:.85rem;color:var(--text-secondary);margin-bottom:6px">
              <span>Pass Rate</span><span>80%</span>
            </div>
            <div style="height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden">
              <div style="width:80%;height:100%;border-radius:4px;background:var(--accent-emerald)"></div>
            </div>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;font-size:.85rem;color:var(--text-secondary);margin-bottom:6px">
              <span>Accuracy</span><span>95%</span>
            </div>
            <div style="height:8px;background:#f1f5f9;border-radius:4px;overflow:hidden">
              <div style="width:95%;height:100%;border-radius:4px;background:var(--accent-cyan)"></div>
            </div>
          </div>
        </div>

        <!-- Recent Activity -->
        <div class="panel-card">
          <h3><i class="fas fa-clock" style="color:var(--accent-purple)"></i> Recent Activity</h3>
          <p style="font-size:.8rem;color:var(--text-secondary);margin-bottom:1rem">Latest inspection results</p>
          <div class="activity-item">
            <span class="dot" style="background:var(--accent-emerald)"></span>
            <div class="text"><strong>QC-5236</strong> Dress Shirt <div class="time">10:02 AM</div></div>
          </div>
          <div class="activity-item">
            <span class="dot" style="background:var(--accent-red)"></span>
            <div class="text"><strong>QC-5235</strong> Silk Blouse <div class="time">10:15 AM</div></div>
          </div>
          <div class="activity-item">
            <span class="dot" style="background:var(--accent-emerald)"></span>
            <div class="text"><strong>QC-5234</strong> Formal Trousers <div class="time">9:58 AM</div></div>
          </div>
          <div class="activity-item">
            <span class="dot" style="background:var(--accent-amber)"></span>
            <div class="text"><strong>QC-5233</strong> Cashmere Sweater <div class="time">9:30 AM</div></div>
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
