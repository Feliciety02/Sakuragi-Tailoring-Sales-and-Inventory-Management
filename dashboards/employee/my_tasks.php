<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/auth_required.php';
require_once __DIR__ . '/../../app/Support/helpers.php';

$pageTitle = 'My Tasks';

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

// Determine role for data-role attribute
$role = get_user_role();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Tasks — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="emp-tasks-styles">
    .task-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:12px }
    .stage-dropdown { position:relative;display:inline-block }
    .stage-menu { display:none;position:absolute;right:0;top:100%;z-index:20;background:var(--bg-primary);border:1px solid var(--border-color);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.1);padding:8px;min-width:210px;margin-top:4px }
    .stage-menu.show { display:block }
    .stage-menu select { width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:6px;font-size:0.8rem;background:var(--bg-secondary);color:var(--text-primary);margin-bottom:6px }
    .stage-menu input { width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:6px;font-size:0.8rem;background:var(--bg-secondary);color:var(--text-primary);margin-bottom:6px }
    .stage-menu .dash-btn { width:100%;justify-content:center;margin-bottom:4px }
    .stage-menu hr { margin:6px 0;border:none;border-top:1px solid var(--border-color) }
    .tab-nav { display:flex;gap:4px }
    .tab-nav .dash-btn { border-radius:8px;font-size:0.8rem }
    .tab-nav .dash-btn.active { background:var(--role-accent);color:#fff;border-color:var(--role-accent) }
  </style>
</head>
<body data-role="<?= htmlspecialchars($role) ?>">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
// Alert messages
$alerts = '';
if (isset($msg)) $alerts .= '<div class="dash-alert dash-alert-success"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($msg) . '</div>';
if (isset($err)) $alerts .= '<div class="dash-alert dash-alert-danger"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($err) . '</div>';

$subtitle = $status_filter === 'active' ? 'Active tasks assigned to you' : ($status_filter === 'qc' ? 'Pending quality inspection' : 'Completed tasks');

$pageActions = [
  ['label' => 'Active', 'href' => '?status=active', 'variant' => $status_filter === 'active' ? 'primary' : 'outline', 'size' => 'sm'],
  ['label' => 'QC', 'href' => '?status=qc', 'variant' => $status_filter === 'qc' ? 'primary' : 'outline', 'size' => 'sm'],
  ['label' => 'Completed', 'href' => '?status=completed', 'variant' => $status_filter === 'completed' ? 'primary' : 'outline', 'size' => 'sm'],
];

$taskCards = '';
if (empty($tasks)):
  $taskCards = renderEmptyState('fas fa-tasks', 'No tasks found', 'No ' . $subtitle . '.');
else:
  ob_start();
?>
<div style="margin-bottom:14px">
  <div class="search-bar" style="max-width:380px">
    <i class="fas fa-search search-bar-icon"></i>
    <input type="text" class="search-bar-input" id="taskSearch" placeholder="Search by order #, customer, or product...">
  </div>
</div>
<div class="task-grid">
  <?php foreach ($tasks as $t):
    $pct = getStageProgress($t['stage']);
    $priorityColor = ($t['priority']??'medium') === 'urgent' ? '#ef4444' : (($t['priority']??'medium') === 'high' ? '#eab308' : 'var(--role-accent)');
    $pVariant = ($t['priority']??'medium') === 'urgent' ? 'danger' : (($t['priority']??'medium') === 'high' ? 'warning' : 'accent');
  ?>
  <div class="task-card" id="task-card-<?= $t['order_id'] ?>" style="border-left:3px solid <?= $priorityColor ?>">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
      <div>
        <strong style="font-size:0.88rem;color:var(--text-primary)">#ORD-<?= $t['order_id'] ?></strong>
        <?= renderStatusBadge(ucfirst($t['priority']??'medium'), $pVariant, 'sm') ?>
        <?php if (isset($t['qc_result'])): ?>
          <?= renderStatusBadge($t['qc_result'] ?? 'Pending', strtolower($t['qc_result']??'')==='passed'?'success':(strtolower($t['qc_result']??'')==='failed'?'danger':'warning'), 'sm') ?>
        <?php endif; ?>
      </div>
      <?php if ($status_filter !== 'completed'): ?>
      <div class="stage-dropdown">
        <button class="dash-btn dash-btn-outline dash-btn-sm" style="padding:2px 8px" onclick="this.nextElementSibling.classList.toggle('show')">⋮</button>
        <div class="stage-menu">
          <form method="post">
            <input type="hidden" name="order_id" value="<?= $t['order_id'] ?>">
            <select name="stage">
              <option value="<?= STAGE_DESIGN_REVIEW ?>" <?= $t['stage']===STAGE_DESIGN_REVIEW?'selected':'' ?>>Design Review</option>
              <option value="<?= STAGE_MATERIAL_PREP ?>" <?= $t['stage']===STAGE_MATERIAL_PREP?'selected':'' ?>>Material Prep</option>
              <option value="<?= STAGE_CUTTING ?>" <?= $t['stage']===STAGE_CUTTING?'selected':'' ?>>Cutting</option>
              <option value="<?= STAGE_PRINTING ?>" <?= $t['stage']===STAGE_PRINTING?'selected':'' ?>>Print/Embroider</option>
              <option value="<?= STAGE_SEWING ?>" <?= $t['stage']===STAGE_SEWING?'selected':'' ?>>Sewing & Assembly</option>
            </select>
            <input type="text" name="notes" placeholder="Notes (optional)">
            <button type="submit" name="update_stage" class="dash-btn dash-btn-primary dash-btn-sm">Update Stage</button>
          </form>
          <hr>
          <form method="post">
            <button type="submit" name="submit_qc" value="<?= $t['order_id'] ?>" class="dash-btn dash-btn-success dash-btn-sm">Submit to QC</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <p style="font-size:0.82rem;margin:0;color:var(--text-primary)"><?= htmlspecialchars($t['product_type'] ?? 'Custom Garment') ?></p>
    <p style="font-size:0.75rem;color:var(--text-tertiary);margin:2px 0 8px"><?= htmlspecialchars($t['customer_name']) ?> · Qty: <?= $t['total_qty'] ?? 0 ?></p>
    <?php if ($status_filter !== 'completed'): ?>
    <div class="progress-bar" style="margin-bottom:10px">
      <div class="progress-bar-track"><div class="progress-bar-fill" style="width:<?= $pct ?>%;background:<?= $priorityColor ?>"></div></div>
      <span class="progress-bar-label"><?= $pct ?>%</span>
    </div>
    <?php endif; ?>
    <a href="view_task.php?id=<?= $t['order_id'] ?>" class="dash-btn dash-btn-outline dash-btn-sm"><i class="fas fa-arrow-right"></i> View</a>
  </div>
  <?php endforeach; ?>
</div>
<?php
  $taskCards = ob_get_clean();
endif;

echo renderDashboardShell(
  renderPageHeader('My Tasks', $subtitle, '', $pageActions),
  '',
  $alerts . $taskCards
);
?>
    </div>
  </div>
</div>

<script>
document.addEventListener('click', function(e) {
  document.querySelectorAll('.stage-menu.show').forEach(d => {
    if (!d.parentElement.contains(e.target)) d.classList.remove('show');
  });
});

document.getElementById('taskSearch')?.addEventListener('input', function() {
  var q = this.value.toLowerCase();
  document.querySelectorAll('[id^="task-card-"]').forEach(function(c) {
    c.style.display = c.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
});

document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>
