<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../app/Middleware/role_admin_only.php';


$user_id = $_SESSION['user_id'];

// Fetch employees for assign dropdown
$empStmt = $pdo->prepare("SELECT u.user_id, u.full_name FROM users u JOIN employees e ON u.user_id = e.user_id WHERE u.status = 'Active' ORDER BY u.full_name");
$empStmt->execute();
$employees = $empStmt->fetchAll();

// Stats
$stats = [];
$stats['total_active'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('Completed','Cancelled','Refunded')")->fetchColumn();
$stats['in_qc'] = $pdo->query("SELECT COUNT(*) FROM order_workflow WHERE stage = 'Quality Inspection'")->fetchColumn();
$stats['overdue'] = $pdo->query("SELECT COUNT(*) FROM order_workflow ow JOIN orders o ON ow.order_id = o.order_id WHERE ow.expected_completion IS NOT NULL AND ow.expected_completion < NOW() AND o.status NOT IN ('Completed','Cancelled','Refunded')")->fetchColumn();
$stats['completed_today'] = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(completion_date) = CURDATE() AND status = 'Completed'")->fetchColumn();

// Most loaded employee
$loaded = $pdo->query("SELECT u.full_name, COUNT(*) as cnt FROM order_workflow ow JOIN users u ON ow.assigned_employee = u.user_id JOIN orders o ON ow.order_id = o.order_id WHERE o.status NOT IN ('Completed','Cancelled','Refunded') GROUP BY ow.assigned_employee ORDER BY cnt DESC LIMIT 1")->fetch();
$pageTitle = 'Production Board';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Production Board — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/mes.css" />
<style>
  body { background: #f5f5f5; }
  .board-header { margin-bottom: 20px; }
  .board-header h1 { font-size: 20px; font-weight: 700; color: #1f2937; margin: 0; }
  .board-header p { font-size: 13px; color: #6b7280; margin-top: 4px; }
  .kanban-board { padding-bottom: 24px; }
  .kanban-card { position: relative; }
  .kanban-card .card-actions { display: none; position: absolute; top: 8px; right: 8px; gap: 4px; }
  .kanban-card:hover .card-actions { display: flex; }
  .card-actions button { width: 24px; height: 24px; border: none; border-radius: 4px; background: #f3f4f6; cursor: pointer; font-size: 11px; display: flex; align-items: center; justify-content: center; color: #6b7280; }
  .card-actions button:hover { background: #e5e7eb; color: #1f2937; }
  .kanban-card .design-thumb { width: 100%; height: 60px; object-fit: cover; border-radius: 4px; margin-top: 8px; background: #f9fafb; }
</style>
</head>
<body>
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
    <?php require_once '../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
  <div class="board-header d-flex align-items-center justify-content-between">
    <div>
      <h1>Production Board</h1>
      <p>Drag orders between stages to update the production pipeline</p>
    </div>
    <div class="d-flex gap-3">
      <a href="production_analytics.php" class="mes-btn mes-btn-sm"><i class="fas fa-chart-bar"></i> Analytics</a>
      <a href="quality_control.php" class="mes-btn mes-btn-sm"><i class="fas fa-search"></i> QC</a>
    </div>
  </div>

  <!-- Stats row -->
  <div class="mes-stat-row">
    <div class="mes-stat"><div class="mes-stat-label">Active Orders</div><div class="mes-stat-value"><?= $stats['total_active'] ?></div></div>
    <div class="mes-stat"><div class="mes-stat-label">Waiting for QC</div><div class="mes-stat-value"><?= $stats['in_qc'] ?></div></div>
    <div class="mes-stat"><div class="mes-stat-label">Overdue</div><div class="mes-stat-value" style="color:var(--mes-danger)"><?= $stats['overdue'] ?></div></div>
    <div class="mes-stat"><div class="mes-stat-label">Completed Today</div><div class="mes-stat-value" style="color:var(--mes-success)"><?= $stats['completed_today'] ?></div></div>
    <div class="mes-stat"><div class="mes-stat-label">Busiest Employee</div><div class="mes-stat-value" style="font-size:16px;font-weight:600"><?= htmlspecialchars($loaded['full_name'] ?? 'N/A') ?></div></div>
  </div>

  <!-- Kanban Board -->
  <div class="kanban-board" id="kanbanBoard">
    <?php $stage_order = [STAGE_ORDER_RECEIVED, STAGE_DESIGN_REVIEW, STAGE_MATERIAL_PREP, STAGE_SAMPLE_REVIEW, STAGE_BULK_PRODUCTION, STAGE_CUTTING, STAGE_PRINTING, STAGE_SEWING, STAGE_QUALITY_INSPECTION, STAGE_REWORK, STAGE_PACKAGING, STAGE_READY_PICKUP];
    foreach ($stage_order as $stg):
      $cfg = $STAGE_CONFIG[$stg] ?? ['label' => $stg, 'color' => '#6b7280', 'icon' => 'fas fa-circle'];
    ?>
    <div class="kanban-column" data-stage="<?= htmlspecialchars($stg) ?>">
      <div class="kanban-column-header">
        <div class="kanban-column-title">
          <i class="<?= $cfg['icon'] ?>" style="color:<?= $cfg['color'] ?>"></i>
          <span><?= htmlspecialchars($cfg['label']) ?></span>
        </div>
        <span class="kanban-column-count" id="count-<?= preg_replace('/[^a-z]/i', '', $stg) ?>">0</span>
      </div>
      <div class="kanban-cards" id="col-<?= preg_replace('/[^a-z]/i', '', $stg) ?>" ondrop="drop(event)" ondragover="allowDrop(event)">
        <div class="kanban-empty">Loading...</div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Assign Employee Modal -->
<div id="assignModal" class="mes-modal-overlay" style="display:none" onclick="if(event.target===this)closeAssign()">
  <div class="mes-modal">
    <div class="mes-modal-header">
      <h3 class="mes-card-title">Assign Employee</h3>
      <button onclick="closeAssign()" style="border:none;background:none;cursor:pointer;font-size:18px">&times;</button>
    </div>
    <div class="mes-modal-body">
      <input type="hidden" id="assignOrderId">
      <div class="mes-form-group">
        <label class="mes-form-label">Select Employee</label>
        <select class="mes-form-select" id="assignEmployeeId">
          <option value="">— Unassign —</option>
          <?php foreach ($employees as $emp): ?>
          <option value="<?= $emp['user_id'] ?>"><?= htmlspecialchars($emp['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="mes-modal-footer">
      <button class="mes-btn" onclick="closeAssign()">Cancel</button>
      <button class="mes-btn mes-btn-primary" onclick="saveAssign()">Assign</button>
    </div>
  </div>
</div>

<script>
const STAGES = <?= json_encode(array_values($stage_order)) ?>;
let employees = <?= json_encode(array_column($employees, 'full_name', 'user_id')) ?>;
let allOrders = [];

function loadBoard() {
  fetch('/app/Controllers/production_api.php?action=get_board')
    .then(r => r.json())
    .then(data => {
      allOrders = data.orders || [];
      renderBoard();
    });
}

function renderBoard() {
  // Group by stage
  const grouped = {};
  STAGES.forEach(s => grouped[s] = []);
  allOrders.forEach(o => {
    const stage = o.stage || STAGES[0];
    if (grouped[stage]) grouped[stage].push(o);
  });

  STAGES.forEach(stage => {
    const colId = 'col-' + stage.replace(/[^a-z]/gi, '');
    const countId = 'count-' + stage.replace(/[^a-z]/gi, '');
    const container = document.getElementById(colId);
    const orders = grouped[stage] || [];

    document.getElementById(countId).textContent = orders.length;

    if (orders.length === 0) {
      container.innerHTML = '<div class="kanban-empty">No orders</div>';
      return;
    }

    container.innerHTML = orders.map(o => renderCard(o)).join('');
  });
}

function renderCard(o) {
  const overdue = o.is_overdue ? 'overdue' : '';
  const days = o.days_remaining !== null ? Math.round(o.days_remaining) : '—';
  const empName = employees[o.assigned_employee] || 'Unassigned';
  const initials = empName.split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2) || '?';
  const priority = o.priority || 'medium';
  const thumb = o.design_preview ? '<img src="/public/' + o.design_preview + '" class="design-thumb">' : '';

  // Batch quantity progress: get quantity at this card's stage vs total
  const stageQty = o.stage_quantities || {};
  const sq = stageQty[o.stage] || 0;
  const totalQty = o.total_quantity || 0;
  const qtyPct = totalQty > 0 ? Math.round((sq / totalQty) * 100) : 0;
  const qtyBar = totalQty > 0 ? `<div style="margin-top:6px">
    <div style="display:flex;justify-content:space-between;font-size:10px;color:#6b7280;margin-bottom:2px">
      <span>Batch: ${sq}/${totalQty}</span>
      <span>${qtyPct}%</span>
    </div>
    <div class="progress-bar" style="height:4px;background:#e5e7eb"><div class="progress-fill" style="width:${qtyPct}%;background:#059669"></div></div>
  </div>` : '';

  return `<div class="kanban-card" draggable="true" ondragstart="drag(event, ${o.order_id})" data-id="${o.order_id}">
    <div class="card-actions">
      <button onclick="openAssign(${o.order_id})" title="Assign"><i class="fas fa-user-plus"></i></button>
      <button onclick="setPriority(${o.order_id})" title="Priority"><i class="fas fa-flag"></i></button>
    </div>
    <div class="order-id">#ORD-${o.order_id}</div>
    <div class="customer-name">${escapeHtml(o.customer_name)}</div>
    <div class="meta-row">
      <span class="priority-badge priority-${priority}">${priority}</span>
      <span><i class="far fa-calendar-alt"></i> ${o.expected_completion ? o.expected_completion.slice(0,10) : '—'}</span>
      <span>Qty: ${totalQty}</span>
    </div>
    <div class="meta-row">
      <span class="assignee"><span class="avatar-sm">${initials}</span> ${escapeHtml(empName)}</span>
    </div>
    <div class="progress-bar"><div class="progress-fill" style="width:${o.progress || 0}%"></div></div>
    ${qtyBar}
    ${thumb}
  </div>`;
}

function allowDrop(e) { e.preventDefault(); }
function drag(e, id) { e.dataTransfer.setData('order_id', id); e.target.classList.add('dragging'); }

function drop(e) {
  e.preventDefault();
  const orderId = e.dataTransfer.getData('order_id');
  const column = e.target.closest('.kanban-column');
  if (!column) return;
  const newStage = column.dataset.stage;

  fetch('/app/Controllers/production_api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=move_stage&order_id=' + orderId + '&stage=' + encodeURIComponent(newStage)
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      const card = document.querySelector(`[data-id="${orderId}"]`);
      if (card) card.closest('.kanban-card')?.remove();
      loadBoard();
    } else {
      alert(data.error);
    }
  });
}

function openAssign(orderId) {
  document.getElementById('assignOrderId').value = orderId;
  document.getElementById('assignModal').style.display = 'flex';
}

function closeAssign() {
  document.getElementById('assignModal').style.display = 'none';
}

function saveAssign() {
  const orderId = document.getElementById('assignOrderId').value;
  const employeeId = document.getElementById('assignEmployeeId').value;
  fetch('/app/Controllers/production_api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=assign_employee&order_id=' + orderId + '&employee_id=' + employeeId
  })
  .then(r => r.json())
  .then(d => { if(d.success) { closeAssign(); loadBoard(); } else alert(d.error); });
}

function setPriority(orderId) {
  const p = prompt('Set priority: low, medium, high, urgent');
  if (!p || !['low','medium','high','urgent'].includes(p)) return;
  fetch('/app/Controllers/production_api.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=set_priority&order_id=' + orderId + '&priority=' + p
  })
  .then(r => r.json())
  .then(d => { if(d.success) loadBoard(); else alert(d.error); });
}

function escapeHtml(t) {
  return document.createElement('div').appendChild(document.createTextNode(t)).parentNode.innerHTML;
}

loadBoard();
setInterval(loadBoard, 30000);
</script>

  </div>
</div>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>
