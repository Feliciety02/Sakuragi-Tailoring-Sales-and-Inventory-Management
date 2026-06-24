<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/role_admin_only.php';

$user_id = $_SESSION['user_id'];

// Stats
$stats = [];
$stats['total_active'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Completed','Cancelled','Refunded')")->fetchColumn();
$stats['in_qc'] = $pdo->query("SELECT COUNT(*) FROM order_workflow WHERE stage = 'QC'")->fetchColumn();
$stats['overdue'] = $pdo->query("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.expected_completion IS NOT NULL AND ow.expected_completion < NOW() AND o.status NOT IN ('Completed','Cancelled','Refunded')")->fetchColumn();
$stats['completed_today'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(completion_date) = CURDATE() AND status = 'Completed'")->fetchColumn();

// Employees for assign modal
$empStmt = $pdo->prepare("SELECT u.user_id, u.full_name FROM users u JOIN employees e ON u.user_id = e.user_id WHERE u.status = 'Active' ORDER BY u.full_name");
$empStmt->execute();
$employees = $empStmt->fetchAll();

$loaded = $pdo->query("SELECT u.full_name, COUNT(*) as cnt FROM order_workflow ow JOIN users u ON ow.assigned_employee = u.user_id JOIN orders o ON ow.order_id = o.order_id WHERE o.status NOT IN ('Completed','Cancelled','Refunded') GROUP BY ow.assigned_employee ORDER BY cnt DESC LIMIT 1")->fetch();

$pageTitle = 'Production Board';

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
  <title>Production Board — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="board-styles">
    .kanban-board { display:flex;gap:16px;overflow-x:auto;padding:0 0 24px;min-height:65vh;scroll-behavior:smooth;scrollbar-width:thin }
    .kanban-column { min-width:280px;max-width:300px;flex-shrink:0;background:var(--surface-secondary);border-radius:var(--radius-lg);display:flex;flex-direction:column;border:1px solid var(--border) }
    .kanban-column-header { padding:14px 16px 10px;font-size:0.82rem;font-weight:600;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid var(--border);position:sticky;top:0;z-index:2;background:var(--surface-secondary);border-radius:var(--radius-lg) var(--radius-lg) 0 0 }
    .kanban-column-title { display:flex;align-items:center;gap:8px;color:var(--text-primary);font-size:.82rem }
    .kanban-column-count { background:var(--surface);padding:1px 10px;border-radius:100px;font-size:0.7rem;font-weight:700;color:var(--text-tertiary);border:1px solid var(--border) }
    .kanban-cards { padding:10px 12px;min-height:100px;flex:1;overflow-y:auto }
    .kanban-card { background:var(--surface);border-radius:var(--radius-md);padding:14px;margin-bottom:10px;border:1px solid var(--border);cursor:grab;position:relative;transition:box-shadow .2s ease,transform .15s ease }
    .kanban-card:hover { box-shadow:var(--shadow-md);transform:translateY(-1px) }
    .kanban-card .card-actions { display:none;position:absolute;top:8px;right:8px;gap:4px }
    .kanban-card:hover .card-actions { display:flex }
    .card-actions button { width:26px;height:26px;border:none;border-radius:6px;background:var(--surface-secondary);cursor:pointer;font-size:0.7rem;display:flex;align-items:center;justify-content:center;color:var(--text-tertiary);transition:all .12s }
    .card-actions button:hover { background:var(--border);color:var(--text-primary) }
    .kanban-card .order-id { font-size:.85rem;font-weight:700;color:var(--text-primary) }
    .kanban-card .customer-name { font-size:.75rem;color:var(--text-tertiary);margin-top:1px }
    .kanban-card .meta-row { font-size:.7rem;color:var(--text-tertiary);margin-top:6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap }
    .kanban-card .assignee { display:flex;align-items:center;gap:4px;font-size:.7rem;color:var(--text-tertiary) }
    .kanban-card .assignee .avatar-sm { display:inline-flex;width:20px;height:20px;border-radius:50%;background:var(--role-accent-soft);color:var(--role-accent);font-size:.6rem;font-weight:700;align-items:center;justify-content:center;flex-shrink:0 }
    .kanban-card .design-thumb { width:100%;height:60px;object-fit:cover;border-radius:6px;margin-top:8px;background:var(--surface-secondary) }
    .kanban-card .progress-bar { height:4px;background:var(--border);border-radius:2px;margin-top:8px;overflow:hidden }
    .kanban-card .progress-fill { height:100%;border-radius:2px;background:var(--role-accent);transition:width .3s }
    .kanban-empty { font-size:.78rem;color:var(--text-tertiary);text-align:center;padding:32px 12px;line-height:1.5 }
    .kanban-card.dragging { opacity:.5 }
    .kanban-column.drag-over { background:var(--role-accent-soft);border-color:var(--role-accent) }

    .priority-urgent { background:#fee2e2;color:#991b1b }
    .priority-high { background:#fef3c7;color:#92400e }
    .priority-medium { background:#dbeafe;color:#1e40af }
    .priority-low { background:#f3f4f6;color:#4b5563 }

    @media (max-width:768px) {
      .kanban-column { min-width:240px;max-width:260px }
    }
  </style>
</head>
<body data-role="admin">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
$metricsRow = renderKPIRow([
  ['label' => 'Active Orders', 'value' => (string)$stats['total_active'], 'icon' => 'fas fa-tasks', 'accent' => 'blue'],
  ['label' => 'In QC', 'value' => (string)$stats['in_qc'], 'icon' => 'fas fa-search', 'accent' => 'amber'],
  ['label' => 'Overdue', 'value' => (string)$stats['overdue'], 'icon' => 'fas fa-exclamation-triangle', 'accent' => 'red'],
  ['label' => 'Completed Today', 'value' => (string)$stats['completed_today'], 'icon' => 'fas fa-check-circle', 'accent' => 'green'],
  ['label' => 'Busiest Employee', 'value' => htmlspecialchars($loaded['full_name'] ?? 'N/A'), 'icon' => 'fas fa-user', 'accent' => 'purple'],
]);

ob_start();
foreach ($stage_order as $stg):
  $cfg = $STAGE_CONFIG[$stg] ?? ['label' => $stg, 'color' => '#6b7280', 'icon' => 'fas fa-circle'];
?>
<div class="kanban-column" data-stage="<?= htmlspecialchars($stg) ?>">
  <div class="kanban-column-header">
    <div class="kanban-column-title"><i class="<?= $cfg['icon'] ?>" style="color:<?= $cfg['color'] ?>;font-size:.75rem"></i><span><?= htmlspecialchars($cfg['label']) ?></span></div>
    <span class="kanban-column-count" id="count-<?= preg_replace('/[^a-z]/i', '', $stg) ?>">0</span>
  </div>
  <div class="kanban-cards" id="col-<?= preg_replace('/[^a-z]/i', '', $stg) ?>">
    <div class="kanban-empty"><i class="fas fa-spinner fa-spin" style="font-size:1.2rem"></i><br>Loading...</div>
  </div>
</div>
<?php endforeach; ?>
<?php $boardHtml = ob_get_clean(); ?>

<?php
// Must keep scripts INSIDE .dash-main so AJAX navigation re-executes them
$scriptsHtml = '
<div id="assignModal" class="modern-modal-overlay" style="display:none" onclick="if(event.target===this)closeAssign()">
  <div class="modern-modal" style="max-width:400px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="margin:0;font-size:1rem;font-weight:700;color:var(--text-primary)">Assign Employee</h3>
      <button onclick="closeAssign()" style="border:none;background:none;cursor:pointer;font-size:1.2rem;color:var(--text-tertiary)">&times;</button>
    </div>
    <input type="hidden" id="assignOrderId">
    <div style="margin:16px 0">
      <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px">Select Employee</label>
      <select id="assignEmployeeId" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.85rem;background:var(--surface);color:var(--text-primary)">
        <option value="">— Unassign —</option>';
foreach ($employees as $emp):
  $scriptsHtml .= '<option value="' . $emp['user_id'] . '">' . htmlspecialchars($emp['full_name']) . '</option>';
endforeach;
$scriptsHtml .= '
      </select>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button class="dash-btn dash-btn-outline dash-btn-sm" onclick="closeAssign()">Cancel</button>
      <button class="dash-btn dash-btn-primary dash-btn-sm" onclick="saveAssign()">Assign</button>
    </div>
  </div>
</div>

<script>
const STAGES = ' . json_encode(array_values($stage_order)) . ';
let employees = ' . json_encode(array_column($employees, 'full_name', 'user_id')) . ';
let allOrders = [];

function loadBoard() {
  fetch(\'/app/Controllers/production_api.php?action=get_board\')
    .then(r => r.json())
    .then(data => { allOrders = data.orders || []; renderBoard(); })
    .catch(() => { STAGES.forEach(s => { const col = document.getElementById(\'col-\' + s.replace(/[^a-z]/gi, \'\')); if (col) col.innerHTML = \'<div class="kanban-empty"><i class="fas fa-exclamation-triangle" style="font-size:1.2rem;color:var(--color-danger)"></i><br>Failed to load</div>\'; }); });
}

function renderBoard() {
  const grouped = {};
  STAGES.forEach(s => grouped[s] = []);
  allOrders.forEach(o => { const stage = o.stage || STAGES[0]; if (grouped[stage]) grouped[stage].push(o); });
  STAGES.forEach(stage => {
    const colId = \'col-\' + stage.replace(/[^a-z]/gi, \'\');
    const countId = \'count-\' + stage.replace(/[^a-z]/gi, \'\');
    const container = document.getElementById(colId);
    const orders = grouped[stage] || [];
    const counter = document.getElementById(countId);
    if (counter) counter.textContent = orders.length;
    if (!container) return;
    if (orders.length === 0) {
      container.innerHTML = \'<div class="kanban-empty"><i class="fas fa-inbox" style="font-size:1.2rem;color:var(--text-muted)"></i><br>No orders in this stage</div>\';
      return;
    }
    container.innerHTML = orders.map(o => renderCard(o)).join(\'\');
  });
}

function renderCard(o) {
  const empName = employees[o.assigned_employee] || \'Unassigned\';
  const initials = empName.split(\' \').map(w=>w[0]).join(\'\').toUpperCase().slice(0,2) || \'?\';
  const priority = o.priority || \'medium\';
  const thumb = o.design_preview ? \'<img src="/public/\' + o.design_preview + \'" class="design-thumb" alt="">\' : \'\';
  const overdue = o.is_overdue ? \' <span style="color:var(--color-danger);font-weight:700">&#9888;</span>\' : \'\';
  return \'<div class="kanban-card" draggable="true" ondragstart="drag(event,\' + o.order_id + \')" data-id="\' + o.order_id + \'">\'
    + \'<div class="card-actions">\'
    + \'<button onclick="openAssign(\' + o.order_id + \')" title="Assign"><i class="fas fa-user-plus"></i></button>\'
    + \'<button onclick="setPriority(\' + o.order_id + \')" title="Set Priority"><i class="fas fa-flag"></i></button>\'
    + \'<button onclick="window.location.href=\\\'order_details.php?order_id=\' + o.order_id + \'\\\'" title="View"><i class="fas fa-external-link-alt"></i></button>\'
    + \'</div>\'
    + \'<div class="order-id">#ORD-\' + o.order_id + overdue + \'</div>\'
    + \'<div class="customer-name">\' + escapeHtml(o.customer_name || \'\') + \'</div>\'
    + \'<div class="meta-row"><span class="priority-badge priority-\' + priority + \'" style="display:inline-flex;align-items:center;gap:3px;font-size:.6rem;font-weight:700;padding:2px 7px;border-radius:100px;text-transform:uppercase;letter-spacing:.04em">\' + priority + \'</span>\'
    + \'<span><i class="far fa-calendar-alt"></i> \' + (o.expected_completion ? o.expected_completion.slice(0,10) : \'&mdash;\') + \'</span>\'
    + \'<span>Qty: \' + (o.total_quantity || 0) + \'</span></div>\'
    + \'<div class="meta-row"><span class="assignee"><span class="avatar-sm">\' + initials + \'</span> \' + escapeHtml(empName) + \'</span></div>\'
    + \'<div class="progress-bar"><div class="progress-fill" style="width:\' + (o.progress || 0) + \'%"></div></div>\'
    + thumb
    + \'</div>\';
}

function allowDrop(e) { e.preventDefault(); }
function drag(e, id) {
  e.dataTransfer.setData(\'order_id\', id);
  e.target.classList.add(\'dragging\');
}
function drop(e) {
  e.preventDefault();
  const orderId = e.dataTransfer.getData(\'order_id\');
  const column = e.target.closest(\'.kanban-column\');
  if (!column) return;
  const newStage = column.dataset.stage;
  const card = document.querySelector(\'[data-id="\' + orderId + \'"]\');
  if (card) card.style.opacity = \'.4\';
  fetch(\'/app/Controllers/production_api.php\', {
    method:\'POST\',
    headers:{\'Content-Type\':\'application/x-www-form-urlencoded\'},
    body:\'action=move_stage&order_id=\' + orderId + \'&stage=\' + encodeURIComponent(newStage)
  }).then(r=>r.json()).then(data => {
    if (data.success) { loadBoard(); }
    else { alert(data.error || \'Move failed\'); loadBoard(); }
  }).catch(() => loadBoard());
}

document.addEventListener(\'dragenter\', function(e) {
  const col = e.target.closest(\'.kanban-column\');
  if (col) col.classList.add(\'drag-over\');
});
document.addEventListener(\'dragleave\', function(e) {
  const col = e.target.closest(\'.kanban-column\');
  if (col && !col.contains(e.relatedTarget)) col.classList.remove(\'drag-over\');
});
document.addEventListener(\'drop\', function(e) {
  document.querySelectorAll(\'.kanban-column\').forEach(c => c.classList.remove(\'drag-over\'));
});

function openAssign(orderId) { document.getElementById(\'assignOrderId\').value = orderId; document.getElementById(\'assignModal\').style.display = \'flex\'; }
function closeAssign() { document.getElementById(\'assignModal\').style.display = \'none\'; }
function saveAssign() {
  const orderId = document.getElementById(\'assignOrderId\').value;
  const employeeId = document.getElementById(\'assignEmployeeId\').value;
  fetch(\'/app/Controllers/production_api.php\', {method:\'POST\',headers:{\'Content-Type\':\'application/x-www-form-urlencoded\'},body:\'action=assign_employee&order_id=\' + orderId + \'&employee_id=\' + employeeId})
    .then(r=>r.json()).then(d => { if(d.success) { closeAssign(); loadBoard(); } else alert(d.error); });
}
function setPriority(orderId) {
  const p = prompt(\'Set priority: low, medium, high, urgent\');
  if (!p || ![\'low\',\'medium\',\'high\',\'urgent\'].includes(p)) return;
  fetch(\'/app/Controllers/production_api.php\', {method:\'POST\',headers:{\'Content-Type\':\'application/x-www-form-urlencoded\'},body:\'action=set_priority&order_id=\' + orderId + \'&priority=\' + p})
    .then(r=>r.json()).then(d => { if(d.success) loadBoard(); else alert(d.error); });
}
function escapeHtml(t) { if (!t) return \'\'; return document.createElement(\'div\').appendChild(document.createTextNode(t)).parentNode.innerHTML; }

loadBoard();
setInterval(loadBoard, 30000);
document.getElementById(\'menuToggle\')?.addEventListener(\'click\', function() { document.getElementById(\'sidebar\')?.classList.toggle(\'collapsed\'); });
</script>';

echo renderDashboardShell(
  renderPageHeader('Production Board', 'Drag orders between stages to update the production pipeline.', '', [
    ['label' => 'Analytics', 'href' => 'production_analytics.php', 'icon' => 'fas fa-chart-bar', 'variant' => 'outline', 'size' => 'sm'],
    ['label' => 'QC Dashboard', 'href' => 'quality_control.php', 'icon' => 'fas fa-search', 'variant' => 'outline', 'size' => 'sm'],
  ]),
  $metricsRow,
  '<div class="kanban-board" id="kanbanBoard" ondragover="allowDrop(event)" ondrop="drop(event)">' . $boardHtml . '</div>' . $scriptsHtml
);
?>
    </div>
  </div>
</div>
</body>
</html>
