<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/role_admin_only.php';

$user_id = $_SESSION['user_id'];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qc_review'])) {
    $order_id = (int)$_POST['order_id'];
    $result = $_POST['result'];
    $feedback = $_POST['feedback'] ?? '';
    $failure_reason = $_POST['failure_reason'] ?? '';
    $corrections = $_POST['required_corrections'] ?? '';
    $checklist = ['design_accuracy'=>0,'print_alignment'=>0,'embroidery_quality'=>0,'stitching_quality'=>0,'size_accuracy'=>0,'fabric_condition'=>0,'cleanliness'=>0,'packaging_readiness'=>0];
    foreach ($checklist as $k => &$v) { $v = (int)($_POST[$k] ?? 0); } unset($v);

    try {
        $pdo->beginTransaction();
        $ins = $pdo->prepare("SELECT inspection_id FROM qc_inspections WHERE order_id = ?");
        $ins->execute([$order_id]); $existing = $ins->fetch();
        if ($existing) {
            $pdo->prepare("UPDATE qc_inspections SET result=?, inspector_id=?, inspected_at=NOW(), feedback=?, failure_reason=?, required_corrections=?, design_accuracy=?, print_alignment=?, embroidery_quality=?, stitching_quality=?, size_accuracy=?, fabric_condition=?, cleanliness=?, packaging_readiness=? WHERE order_id=?")
                ->execute([$result, $user_id, $feedback, $failure_reason, $corrections, $checklist['design_accuracy'], $checklist['print_alignment'], $checklist['embroidery_quality'], $checklist['stitching_quality'], $checklist['size_accuracy'], $checklist['fabric_condition'], $checklist['cleanliness'], $checklist['packaging_readiness'], $order_id]);
        } else {
            $checklist['result'] = $result; $checklist['inspector_id'] = $user_id; $checklist['inspected_at'] = date('Y-m-d H:i:s');
            $checklist['feedback'] = $feedback; $checklist['failure_reason'] = $failure_reason; $checklist['required_corrections'] = $corrections; $checklist['order_id'] = $order_id;
            $cols = implode(',', array_keys($checklist)); $vals = implode(',', array_fill(0, count($checklist), '?'));
            $pdo->prepare("INSERT INTO qc_inspections ({$cols}) VALUES ({$vals})")->execute(array_values($checklist));
        }
        if ($result === 'Passed') {
            $pdo->prepare("UPDATE order_workflow SET stage='Packaging', completed_at=NOW() WHERE order_id=?")->execute([$order_id]);
        } else {
            $pdo->prepare("INSERT INTO rework_log (order_id, triggered_by, from_stage, to_stage, reason, notes) VALUES (?, ?, 'Quality Inspection', 'Rework', ?, ?)")->execute([$order_id, $user_id, $failure_reason, $corrections]);
            $pdo->prepare("UPDATE order_workflow SET stage='Rework' WHERE order_id=?")->execute([$order_id]);
        }
        $pdo->commit(); $message = 'QC review submitted successfully';
    } catch (Exception $e) { $pdo->rollBack(); $message = 'Error: ' . $e->getMessage(); }
}

$qcOrders = $pdo->query("
    SELECT o.order_id, o.order_date, o.total_price, ow.stage, ow.product_type, ow.expected_completion, ow.assigned_employee,
           u.full_name AS customer_name, e.full_name AS employee_name, qc.result AS qc_result, qc.inspection_id
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN users e ON ow.assigned_employee = e.user_id
    LEFT JOIN qc_inspections qc ON o.order_id = qc.order_id
    WHERE ow.stage IN ('Quality Inspection', 'Rework')
    ORDER BY CASE WHEN qc.result = 'Pending' OR qc.result IS NULL THEN 0 ELSE 1 END, ow.expected_completion ASC
");

$qcHistory = $pdo->query("
    SELECT qc.*, o.order_id, u.full_name AS inspector_name
    FROM qc_inspections qc JOIN orders o ON qc.order_id = o.order_id LEFT JOIN users u ON qc.inspector_id = u.user_id
    WHERE qc.result != 'Pending' ORDER BY qc.inspected_at DESC LIMIT 20
");

// Stats
$pendingCount = $pdo->query("SELECT COUNT(*) FROM order_workflow WHERE stage = 'Quality Inspection'")->fetchColumn();
$failedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Failed' AND DATE(inspected_at) = CURDATE()")->fetchColumn();
$passedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE result = 'Passed' AND DATE(inspected_at) = CURDATE()")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quality Control — Sakuragi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
</head>
<body>
  <div class="dash-layout">
    <aside class="sidebar-modern" id="sidebar">
      <div class="sidebar-brand">
        <svg viewBox="0 0 28 28" fill="none" style="width:24px;height:24px"><rect width="28" height="28" rx="6" fill="#1e3a5f"/><path d="M7 10h14l-3 8H10L7 10z" fill="#fff" opacity=".9"/></svg>
        <span>Sakuragi</span>
      </div>
      <nav class="sidebar-nav">
        <div class="section-label">Main</div>
        <a href="dashboard.php" class="sidebar-item"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="production_board.php" class="sidebar-item"><i class="fas fa-columns"></i> Production</a>
        <a href="orders.php" class="sidebar-item"><i class="fas fa-shopping-bag"></i> Orders</a>
        <a href="employees.php" class="sidebar-item"><i class="fas fa-users"></i> Employees</a>
        <div class="section-label">Operations</div>
        <a href="inventory.php" class="sidebar-item"><i class="fas fa-box"></i> Inventory</a>
        <a href="quality_control.php" class="sidebar-item active"><i class="fas fa-clipboard-check"></i> Quality Control</a>
        <a href="reports.php" class="sidebar-item"><i class="fas fa-chart-bar"></i> Reports</a>
        <div class="sidebar-footer"><a href="/auth/logout.php" class="sidebar-item" style="color:var(--accent-red)"><i class="fas fa-sign-out-alt"></i> Sign Out</a></div>
      </nav>
    </aside>

    <div class="dash-main">
      <header class="top-nav">
        <div class="top-nav-left">
          <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
          <div style="font-size:.9rem;font-weight:600;color:var(--text-secondary)">Quality Control Center</div>
        </div>
        <div class="top-nav-right">
          <div class="avatar"><?= htmlspecialchars(substr($_SESSION['full_name']??'A', 0, 2)) ?></div>
        </div>
      </header>

      <div class="dash-content">
        <div class="page-header">
          <h1>Quality Control</h1>
          <p>Inspect completed work, manage rework, and track quality metrics</p>
        </div>

        <?php if ($message): ?>
        <div class="panel-card" style="margin-bottom:16px;padding:12px 20px;background:<?= strpos($message,'Error')!==false?'#fee2e2':'#d1fae5' ?>">
          <p style="margin:0;font-size:.85rem;color:<?= strpos($message,'Error')!==false?'#991b1b':'#065f46' ?>"><?= htmlspecialchars($message) ?></p>
        </div>
        <?php endif; ?>

        <!-- KPI -->
        <div class="kpi-row">
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef3c7;color:#d97706"><i class="fas fa-hourglass-half"></i></div>
            <div class="kpi-label">Pending Inspections</div>
            <div class="kpi-value"><?= $pendingCount ?></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#d1fae5;color:#059669"><i class="fas fa-check-circle"></i></div>
            <div class="kpi-label">Passed Today</div>
            <div class="kpi-value"><?= $passedToday ?></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#fee2e2;color:#dc2626"><i class="fas fa-times-circle"></i></div>
            <div class="kpi-label">Failed Today</div>
            <div class="kpi-value"><?= $failedToday ?></div>
          </div>
          <div class="kpi-card">
            <div class="kpi-icon" style="background:#eef2ff;color:#2563eb"><i class="fas fa-history"></i></div>
            <div class="kpi-label">Pass Rate</div>
            <div class="kpi-value"><?php $total = $passedToday+$failedToday; echo $total ? round($passedToday/$total*100) : 100 ?>%</div>
          </div>
        </div>

        <div class="dash-two-col">
          <div>
            <!-- Pending Reviews -->
            <div class="panel-card">
              <h3><i class="fas fa-clipboard-list" style="color:var(--accent-amber)"></i> Pending QC Reviews</h3>
              <?php if ($qcOrders->rowCount() === 0): ?>
              <p style="font-size:.85rem;color:var(--text-tertiary);text-align:center;padding:24px 0">No orders pending review</p>
              <?php else: ?>
              <div style="display:flex;flex-direction:column;gap:8px;margin-top:12px">
                <?php foreach ($qcOrders as $o): ?>
                <div class="task-card" style="border-left:3px solid <?= $o['qc_result']==='Failed'?'var(--accent-red)':($o['stage']==='Rework'?'var(--accent-amber)':'var(--accent-blue)') ?>">
                  <div style="display:flex;justify-content:space-between;align-items:start">
                    <div>
                      <span style="font-size:.85rem;font-weight:700">#ORD-<?= $o['order_id'] ?></span>
                      <span class="qc-status <?= strtolower($o['qc_result'] ?? 'pending') ?>" style="margin-left:8px"><?= $o['qc_result'] ?? 'Pending' ?></span>
                      <p style="font-size:.8rem;color:var(--text-secondary);margin:4px 0 0"><?= htmlspecialchars($o['customer_name']) ?> · <?= htmlspecialchars($o['product_type'] ?? 'Garment') ?> · by <?= htmlspecialchars($o['employee_name'] ?? 'Unassigned') ?></p>
                    </div>
                    <button class="dash-btn dash-btn-primary dash-btn-sm" onclick="openQC(<?= $o['order_id'] ?>)">Review</button>
                  </div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>

            <!-- History -->
            <div class="panel-card" style="margin-top:16px">
              <h3><i class="fas fa-history" style="color:var(--accent-purple)"></i> Recent Inspections</h3>
              <?php if ($qcHistory->rowCount() === 0): ?>
              <p style="font-size:.85rem;color:var(--text-tertiary);text-align:center;padding:16px 0">No inspections yet</p>
              <?php else: ?>
              <div style="display:flex;flex-direction:column;gap:6px;margin-top:12px;font-size:.8rem">
                <?php foreach ($qcHistory as $h): ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light)">
                  <div><strong>#ORD-<?= $h['order_id'] ?></strong> <span class="qc-status <?= strtolower($h['result']) ?>"><?= $h['result'] ?></span></div>
                  <div style="color:var(--text-tertiary)"><?= htmlspecialchars($h['inspector_name'] ?? 'N/A') ?> · <?= date('M d', strtotime($h['inspected_at'])) ?></div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- QC Checklist Sidebar -->
          <div class="side-panel">
            <div class="panel-card">
              <h3><i class="fas fa-check-double" style="color:var(--accent-emerald)"></i> QC Checklist</h3>
              <p style="font-size:.8rem;color:var(--text-tertiary);margin-bottom:12px">Items checked during inspection</p>
              <div style="display:flex;flex-direction:column;gap:8px;font-size:.85rem">
                <div style="display:flex;align-items:center;gap:8px"><span style="width:18px;height:18px;border-radius:4px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;font-size:.65rem"><i class="fas fa-check"></i></span> Design Accuracy</div>
                <div style="display:flex;align-items:center;gap:8px"><span style="width:18px;height:18px;border-radius:4px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;font-size:.65rem"><i class="fas fa-check"></i></span> Print / Embroidery Alignment</div>
                <div style="display:flex;align-items:center;gap:8px"><span style="width:18px;height:18px;border-radius:4px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;font-size:.65rem"><i class="fas fa-check"></i></span> Embroidery Quality</div>
                <div style="display:flex;align-items:center;gap:8px"><span style="width:18px;height:18px;border-radius:4px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;font-size:.65rem"><i class="fas fa-check"></i></span> Stitching Quality</div>
                <div style="display:flex;align-items:center;gap:8px"><span style="width:18px;height:18px;border-radius:4px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;font-size:.65rem"><i class="fas fa-check"></i></span> Size Accuracy</div>
                <div style="display:flex;align-items:center;gap:8px"><span style="width:18px;height:18px;border-radius:4px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;font-size:.65rem"><i class="fas fa-check"></i></span> Fabric Condition</div>
                <div style="display:flex;align-items:center;gap:8px"><span style="width:18px;height:18px;border-radius:4px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;font-size:.65rem"><i class="fas fa-check"></i></span> Cleanliness</div>
                <div style="display:flex;align-items:center;gap:8px"><span style="width:18px;height:18px;border-radius:4px;background:#d1fae5;display:flex;align-items:center;justify-content:center;color:#059669;font-size:.65rem"><i class="fas fa-check"></i></span> Packaging Readiness</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- QC Modal -->
  <div class="modern-modal-overlay" id="qcModal" style="display:none" onclick="if(event.target===this)closeQC()">
    <div class="modern-modal" style="max-width:600px">
      <form method="post">
        <input type="hidden" name="order_id" id="qcOrderId">
        <h3>QC Review — <span id="qcOrderLabel"></span></h3>
        <div class="form-group">
          <label>Result</label>
          <select name="result" id="resultSelect" required>
            <option value="Passed">Passed</option>
            <option value="Failed">Failed</option>
          </select>
        </div>
        <div class="form-group">
          <label>Inspection Checklist</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px">
            <?php $items = ['design_accuracy'=>'Design Accuracy','print_alignment'=>'Print Alignment','embroidery_quality'=>'Embroidery Quality','stitching_quality'=>'Stitching Quality','size_accuracy'=>'Size Accuracy','fabric_condition'=>'Fabric Condition','cleanliness'=>'Cleanliness','packaging_readiness'=>'Packaging'];
            foreach ($items as $k => $l): ?>
            <label style="display:flex;align-items:center;gap:8px;font-size:.8rem;cursor:pointer">
              <input type="checkbox" name="<?= $k ?>" value="1" style="width:16px;height:16px;accent-color:var(--accent-blue)"> <?= $l ?>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group">
          <label>Feedback</label>
          <textarea name="feedback" rows="2" placeholder="Overall feedback..."></textarea>
        </div>
        <div class="form-group" id="failureFields" style="display:none">
          <label>Failure Reason</label>
          <input type="text" name="failure_reason" placeholder="e.g. Print misalignment">
          <label style="margin-top:12px">Required Corrections</label>
          <textarea name="required_corrections" rows="2" placeholder="What needs to be fixed?"></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="dash-btn dash-btn-outline" onclick="closeQC()">Cancel</button>
          <button type="submit" name="qc_review" class="dash-btn dash-btn-primary">Submit Review</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function openQC(id) {
    document.getElementById('qcOrderId').value = id;
    document.getElementById('qcOrderLabel').textContent = '#ORD-' + id;
    document.getElementById('qcModal').style.display = 'flex';
    document.getElementById('failureFields').style.display = 'none';
  }
  function closeQC() { document.getElementById('qcModal').style.display = 'none'; }
  document.getElementById('resultSelect')?.addEventListener('change', function() {
    document.getElementById('failureFields').style.display = this.value === 'Failed' ? 'block' : 'none';
  });
  document.getElementById('menuToggle')?.addEventListener('click', function() {
    document.getElementById('sidebar')?.classList.toggle('collapsed');
  });
  </script>
</body>
</html>
