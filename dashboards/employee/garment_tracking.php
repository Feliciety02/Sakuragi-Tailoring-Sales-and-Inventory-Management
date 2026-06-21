<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../app/Middleware/auth_required.php';
$pageTitle = 'Garment Tracking';

$role = get_user_role();
if ($role === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) {
    header('Location: my_tasks.php');
    exit();
}

// Get order info
$order = $pdo->prepare("
    SELECT o.*, ow.stage, ow.product_type, ow.expected_completion, ow.priority,
           u.full_name AS customer_name
    FROM orders o
    JOIN order_workflow ow ON o.order_id = ow.order_id
    JOIN users u ON o.user_id = u.user_id
    WHERE o.order_id = ?
");
$order->execute([$order_id]);
$ord = $order->fetch();
if (!$ord) {
    header('Location: my_tasks.php');
    exit();
}

// Garment tracking data
$garments = $pdo->prepare("
    SELECT od.*, gt.stage AS current_stage, gt.updated_at, gt.employee_id,
           u.full_name AS last_employee
    FROM order_details od
    LEFT JOIN garment_tracking gt ON od.detail_id = gt.order_detail_id
    LEFT JOIN users u ON gt.employee_id = u.user_id
    WHERE od.order_id = ?
    ORDER BY od.size
");
$garments->execute([$order_id]);

// Full history
$history = $pdo->prepare("
    SELECT gl.*, od.size, od.quantity, u.full_name AS employee_name
    FROM garment_log gl
    JOIN order_details od ON gl.order_detail_id = od.detail_id
    LEFT JOIN users u ON gl.employee_id = u.user_id
    WHERE gl.order_id = ?
    ORDER BY gl.created_at DESC LIMIT 50
");
$history->execute([$order_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Garment Tracking — Sakuragi</title>
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
  <div class="d-flex align-items-center gap-2 mb-3" style="font-size:12px;color:#6b7280">
    <a href="my_tasks.php" style="color:var(--mes-primary)">My Tasks</a>
    <span>/</span>
    <span style="color:#374151">#ORD-<?= $order_id ?> — Garment Tracking</span>
  </div>

  <div class="mes-card mb-3">
    <div class="mes-card-body" style="padding:20px 24px">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <h2 style="font-size:18px;font-weight:700;margin:0">Garment Tracking — #ORD-<?= $order_id ?></h2>
          <p style="font-size:13px;color:#6b7280;margin:4px 0 0"><?= htmlspecialchars($ord['customer_name']) ?> · <?= htmlspecialchars($ord['product_type'] ?? 'Garment') ?> · <?= htmlspecialchars($ord['stage']) ?></p>
        </div>
      </div>
    </div>
  </div>

  <div style="margin-bottom:12px;display:flex;gap:8px;max-width:400px">
    <input type="text" id="stageSearch" class="mes-form-input" placeholder="Search items by size or stage..." style="flex:1">
    <button class="mes-btn mes-btn-primary mes-btn-sm" onclick="filterGarments()"><i class="fas fa-search"></i></button>
  </div>

  <div class="mes-layout">
    <div class="mes-main">
      <!-- Per-garment table -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Item-Level Status</h3></div>
        <div class="mes-card-body">
          <div class="mes-table-wrap">
              <table class="mes-table" id="garmentTable">
                <thead>
                <tr>
                  <th>Size</th>
                  <th>Qty</th>
                  <th>Current Stage</th>
                  <th>Last Updated</th>
                  <th>Last Employee</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($garments as $g): $pct = getStageProgress($g['current_stage'] ?? $ord['stage']); ?>
                <tr>
                  <td><strong><?= htmlspecialchars($g['size']) ?></strong></td>
                  <td><?= (int)$g['quantity'] ?></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:6px">
                      <span style="width:8px;height:8px;border-radius:50%;background:<?= ($g['current_stage']??'') === STAGE_COMPLETED ? '#10b981' : (($g['current_stage']??'') === STAGE_REWORK ? '#ef4444' : ($pct > 50 ? '#3b82f6' : '#f59e0b')) ?>;display:inline-block"></span>
                      <?= htmlspecialchars($g['current_stage'] ?? $ord['stage']) ?>
                    </div>
                  </td>
                  <td style="color:#6b7280;font-size:12px"><?= $g['updated_at'] ? date('M d, g:i A', strtotime($g['updated_at'])) : '—' ?></td>
                  <td style="color:#6b7280;font-size:12px"><?= htmlspecialchars($g['last_employee'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Timeline History -->
      <div class="mes-card">
        <div class="mes-card-header"><h3 class="mes-card-title">Stage History</h3></div>
        <div class="mes-card-body">
          <?php if ($history->rowCount() === 0): ?>
          <p style="font-size:13px;color:#6b7280;margin:0;text-align:center;padding:16px 0">No stage transitions recorded yet</p>
          <?php else: ?>
          <div class="mes-feed">
            <?php foreach ($history as $h): ?>
            <div class="mes-feed-item">
              <div class="mes-feed-icon" style="background:#dbeafe;color:#2563eb">
                <i class="fas fa-arrows-alt-h"></i>
              </div>
              <div class="mes-feed-content">
                <p>
                  <strong>Size <?= htmlspecialchars($h['size']) ?></strong>:
                  <?= htmlspecialchars($h['from_stage'] ?? '—') ?> →
                  <?= htmlspecialchars($h['to_stage']) ?>
                  <?php if ($h['notes']): ?>
                  <br><span style="color:#6b7280;font-size:12px"><?= htmlspecialchars($h['notes']) ?></span>
                  <?php endif; ?>
                </p>
                <div class="mes-feed-time"><?= htmlspecialchars($h['employee_name'] ?? 'System') ?> · <?= date('M d, g:i A', strtotime($h['created_at'])) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="mes-sidebar-right">
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Summary</h3></div>
        <div class="mes-card-body" style="font-size:13px">
          <?php
          $totalItems = 0;
          $completedItems = 0;
          foreach ($garments as $g) {
              $totalItems += (int)$g['quantity'];
              if (($g['current_stage'] ?? '') === STAGE_COMPLETED || ($g['current_stage'] ?? '') === STAGE_READY_PICKUP || ($g['current_stage'] ?? '') === STAGE_PACKAGING) {
                  $completedItems += (int)$g['quantity'];
              }
          }
          ?>
          <p style="margin:0 0 6px"><strong>Total Items</strong><br><?= $totalItems ?></p>
          <p style="margin:0 0 6px"><strong>Completed / Ready</strong><br><?= $completedItems ?></p>
          <?php if ($totalItems > 0): ?>
          <p style="margin:0"><strong>Progress</strong><br>
            <div style="height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden;margin-top:4px">
              <div style="width:<?= round($completedItems / $totalItems * 100) ?>%;height:100%;background:var(--mes-primary);border-radius:4px"></div>
            </div>
          </p>
          <?php endif; ?>
        </div>
      </div>

      <div class="mes-card">
        <div class="mes-card-header"><h3 class="mes-card-title">Actions</h3></div>
        <div class="mes-card-body">
          <a href="view_task.php?id=<?= $order_id ?>" class="mes-btn mes-btn-primary" style="width:100%"><i class="fas fa-arrow-left"></i> Back to Task</a>
        </div>
      </div>
    </div>
  </div>
</div>
  </div>
</div>

<script>
function filterGarments() {
  var q = document.getElementById('stageSearch').value.toLowerCase();
  document.querySelectorAll('#garmentTable tbody tr').forEach(function(r) {
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
document.getElementById('stageSearch').addEventListener('keyup', function(e) {
  if (e.key === 'Enter') filterGarments();
  else filterGarments();
});
</script>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>
