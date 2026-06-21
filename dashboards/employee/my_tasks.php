<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../app/Middleware/auth_required.php';
$pageTitle = 'My Tasks';

if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get employee position for stage filtering
$position = getEmployeePosition($pdo, $user_id);
$position_id = $position ? (int)$position['position_id'] : 0;
$allowed_stages = getPositionStages($position_id);
$stage_placeholders = implode(',', array_fill(0, count($allowed_stages), '?'));
$stage_params = $allowed_stages;

// Handle "Submit to QC" action (POST from dashboard or inline)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_qc'])) {
    $order_id = (int)$_POST['submit_qc'];
    try {
        $pdo->prepare("UPDATE order_workflow SET stage=?, completed_at=NOW() WHERE order_id=? AND assigned_employee=?")
            ->execute([STAGE_QUALITY_INSPECTION, $order_id, $user_id]);
        $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, 'Submitted for quality inspection', 'handoff')")
            ->execute([$order_id, $user_id]);
        $chk = $pdo->prepare("SELECT inspection_id FROM qc_inspections WHERE order_id=?");
        $chk->execute([$order_id]);
        if (!$chk->fetch()) {
            $pdo->prepare("INSERT INTO qc_inspections (order_id, result) VALUES (?, 'Pending')")->execute([$order_id]);
        }
        $msg = 'Submitted for QC';
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
}

// Handle stage update via AJAX-style POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stage'])) {
    $order_id = (int)$_POST['order_id'];
    $new_stage = $_POST['stage'] ?? '';
    $notes = $_POST['notes'] ?? '';
    try {
        $pdo->prepare("UPDATE order_workflow SET stage=?, workflow_notes=?, started_at=COALESCE(started_at,NOW()) WHERE order_id=? AND assigned_employee=?")
            ->execute([$new_stage, $notes, $order_id, $user_id]);
        if ($notes) {
            $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, ?, 'general')")
                ->execute([$order_id, $user_id, $notes]);
        }
        $msg = 'Stage updated';
    } catch (Exception $e) {
        $err = $e->getMessage();
    }
}

$status_filter = $_GET['status'] ?? 'active';
$valid = ['active', 'qc', 'completed'];
if (!in_array($status_filter, $valid)) $status_filter = 'active';

$tasks = [];
if ($status_filter === 'active') {
    $stmt = $pdo->prepare("
        SELECT o.order_id, o.order_date, o.status, o.total_price,
               ow.stage, ow.expected_completion, ow.product_type, ow.priority,
               u.full_name AS customer_name,
               (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) as total_qty
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        JOIN users u ON o.user_id = u.user_id
        WHERE ow.assigned_employee = ? AND o.status NOT IN ('Completed','Cancelled')
        AND ow.stage != ? AND ow.stage IN ({$stage_placeholders})
        ORDER BY CASE ow.priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
                 ow.expected_completion ASC
    ");
    $stmt->execute(array_merge([$user_id, STAGE_QUALITY_INSPECTION], $stage_params));
    $tasks = $stmt->fetchAll();
} elseif ($status_filter === 'qc') {
    $stmt = $pdo->prepare("
        SELECT o.order_id, o.order_date, o.status, o.total_price,
               ow.stage, ow.expected_completion, ow.product_type, ow.priority,
               u.full_name AS customer_name, qc.result AS qc_result,
               (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) as total_qty
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN qc_inspections qc ON o.order_id = qc.order_id
        WHERE ow.assigned_employee = ? AND ow.stage = ?
        ORDER BY ow.expected_completion ASC
    ");
    $stmt->execute([$user_id, STAGE_QUALITY_INSPECTION]);
    $tasks = $stmt->fetchAll();
} else {
    $stmt = $pdo->prepare("
        SELECT o.order_id, o.order_date, o.completion_date, o.total_price,
               ow.stage, ow.product_type,
               u.full_name AS customer_name,
               (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) as total_qty
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        JOIN users u ON o.user_id = u.user_id
        WHERE ow.assigned_employee = ? AND o.status = 'Completed'
        ORDER BY o.completion_date DESC LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $tasks = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Tasks — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/mes.css">
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/employee.php'; ?>
  <div class="dash-main">
    <?php require_once '../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
  <div class="d-flex align-items-center justify-content-between mb-2">
    <div>
      <h1 style="font-size:20px;font-weight:700;margin:0">My Tasks</h1>
      <p style="font-size:13px;color:#6b7280;margin-top:4px"><?= $status_filter === 'active' ? 'Active tasks' : ($status_filter === 'qc' ? 'Pending quality inspection' : 'Completed tasks') ?></p>
    </div>
    <div class="d-flex gap-2">
      <a href="?status=active" class="mes-btn mes-btn-sm <?= $status_filter === 'active' ? 'mes-btn-primary' : '' ?>">Active</a>
      <a href="?status=qc" class="mes-btn mes-btn-sm <?= $status_filter === 'qc' ? 'mes-btn-primary' : '' ?>">QC</a>
      <a href="?status=completed" class="mes-btn mes-btn-sm <?= $status_filter === 'completed' ? 'mes-btn-primary' : '' ?>">Completed</a>
    </div>
  </div>

  <div style="margin-bottom:16px">
    <div style="display:flex;gap:8px;max-width:400px">
      <input type="text" id="taskSearch" class="mes-form-input" placeholder="Search by order #, customer, or product..." style="flex:1">
      <button class="mes-btn mes-btn-primary mes-btn-sm" onclick="filterTasks()"><i class="fas fa-search"></i></button>
    </div>
  </div>

  <?php if (isset($msg)): ?>
  <div class="mes-card mb-3" style="padding:12px 20px;background:#d1fae5;border-color:#a7f3d0"><p style="margin:0;font-size:13px;color:#065f46"><?= htmlspecialchars($msg) ?></p></div>
  <?php endif; ?>
  <?php if (isset($err)): ?>
  <div class="mes-card mb-3" style="padding:12px 20px;background:#fef2f2;border-color:#fecaca"><p style="margin:0;font-size:13px;color:#991b1b"><?= htmlspecialchars($err) ?></p></div>
  <?php endif; ?>

  <?php if (empty($tasks)): ?>
  <div class="mes-card"><div class="mes-card-body"><p style="font-size:13px;color:#6b7280;margin:0;text-align:center;padding:32px 0">No tasks found</p></div></div>
  <?php else: ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:12px">
    <?php foreach ($tasks as $t): $pct = getStageProgress($t['stage']); ?>
    <div class="mes-card" id="task-card-<?= $t['order_id'] ?>" style="border-left:3px solid <?= ($t['priority']??'medium') === 'urgent' ? 'var(--mes-danger)' : (($t['priority']??'medium') === 'high' ? 'var(--mes-warning)' : 'var(--mes-info)') ?>">
      <div class="mes-card-body" style="padding:16px">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <strong style="font-size:14px">#ORD-<?= $t['order_id'] ?></strong>
            <?php if (isset($t['priority'])): ?>
            <span class="mes-badge <?= $t['priority'] === 'urgent' ? 'mes-badge-danger' : ($t['priority'] === 'high' ? 'mes-badge-warning' : 'mes-badge-gray') ?> ms-1"><?= ucfirst($t['priority']) ?></span>
            <?php endif; ?>
            <?php if (isset($t['qc_result'])): ?>
            <span class="mes-badge <?= $t['qc_result'] === 'Passed' ? 'mes-badge-success' : ($t['qc_result'] === 'Failed' ? 'mes-badge-danger' : 'mes-badge-warning') ?> ms-1"><?= $t['qc_result'] ?? 'Pending' ?></span>
            <?php endif; ?>
          </div>
          <?php if ($status_filter !== 'completed'): ?>
          <div class="dropdown" style="position:relative">
            <button class="mes-btn mes-btn-sm" style="padding:2px 8px" onclick="this.nextElementSibling.classList.toggle('show')">⋮</button>
            <div class="mes-dropdown">
              <form method="post" style="padding:8px;min-width:200px">
                <input type="hidden" name="order_id" value="<?= $t['order_id'] ?>">
                <div class="mes-form-group mb-1">
                  <select name="stage" class="mes-form-select mes-form-select-sm">
                    <option value="<?= STAGE_DESIGN_REVIEW ?>" <?= $t['stage']===STAGE_DESIGN_REVIEW?'selected':'' ?>>Design Review</option>
                    <option value="<?= STAGE_MATERIAL_PREP ?>" <?= $t['stage']===STAGE_MATERIAL_PREP?'selected':'' ?>>Material Prep</option>
                    <option value="<?= STAGE_CUTTING ?>" <?= $t['stage']===STAGE_CUTTING?'selected':'' ?>>Cutting</option>
                    <option value="<?= STAGE_PRINTING ?>" <?= $t['stage']===STAGE_PRINTING?'selected':'' ?>>Print/Embroider</option>
                    <option value="<?= STAGE_SEWING ?>" <?= $t['stage']===STAGE_SEWING?'selected':'' ?>>Sewing & Assembly</option>
                  </select>
                </div>
                <div class="mes-form-group mb-1">
                  <input type="text" name="notes" class="mes-form-input mes-form-input-sm" placeholder="Notes (optional)">
                </div>
                <button type="submit" name="update_stage" class="mes-btn mes-btn-primary mes-btn-sm" style="width:100%">Update</button>
              </form>
              <hr style="margin:4px 0">
              <form method="post">
                <button type="submit" name="submit_qc" value="<?= $t['order_id'] ?>" class="mes-btn mes-btn-success mes-btn-sm" style="width:100%">Submit to QC</button>
              </form>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <p style="font-size:13px;margin:0;color:#374151"><?= htmlspecialchars($t['product_type'] ?? 'Custom Garment') ?></p>
        <p style="font-size:12px;color:#6b7280;margin:2px 0 8px"><?= htmlspecialchars($t['customer_name']) ?> · Qty: <?= $t['total_qty'] ?? 0 ?></p>

        <?php if ($status_filter !== 'completed'): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px">
          <div style="flex:1;height:6px;background:#e5e7eb;border-radius:3px;overflow:hidden">
            <div style="width:<?= $pct ?>%;height:100%;background:<?= $t['priority']==='urgent'?'var(--mes-danger)':($t['priority']==='high'?'var(--mes-warning)':'var(--mes-primary)') ?>;border-radius:3px;transition:width .3s"></div>
          </div>
          <span style="font-size:11px;color:#6b7280;white-space:nowrap"><?= $pct ?>%</span>
        </div>
        <?php endif; ?>

        <div class="d-flex gap-2">
          <a href="view_task.php?id=<?= $t['order_id'] ?>" class="mes-btn mes-btn-primary mes-btn-sm"><i class="fas fa-arrow-right"></i> View</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
  </div>
</div>

<script>
document.addEventListener('click', function(e) {
  document.querySelectorAll('.mes-dropdown.show').forEach(d => {
    if (!d.parentElement.contains(e.target)) d.classList.remove('show');
  });
});

function filterTasks() {
  var q = document.getElementById('taskSearch').value.toLowerCase();
  document.querySelectorAll('[id^="task-card-"]').forEach(function(c) {
    c.style.display = c.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

document.getElementById('taskSearch').addEventListener('keyup', function(e) {
  if (e.key === 'Enter') filterTasks();
  else filterTasks();
});
</script>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>
