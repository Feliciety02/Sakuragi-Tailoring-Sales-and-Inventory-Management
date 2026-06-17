<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/auth_required.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar_employee.php';

if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: my_tasks.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = (int)$_GET['id'];

// Verify ownership and fetch task
$taskStmt = $pdo->prepare("
    SELECT o.order_id, o.order_date, o.status, o.total_price, o.completion_date,
           ow.stage, ow.product_type, ow.workflow_notes, ow.assigned_employee,
           ow.expected_completion, ow.priority, ow.started_at,
           s.service_name
    FROM orders o
    JOIN order_workflow ow ON o.order_id = ow.order_id
    LEFT JOIN services s ON o.service_id = s.service_id
    WHERE o.order_id = ? AND ow.assigned_employee = ?
");
$taskStmt->execute([$order_id, $user_id]);
$task = $taskStmt->fetch();

if (!$task) {
    header('Location: my_tasks.php');
    exit();
}

// Customer info
$custStmt = $pdo->prepare("SELECT u.full_name, u.email, u.phone_number FROM users u JOIN orders o ON u.user_id = o.user_id WHERE o.order_id = ?");
$custStmt->execute([$order_id]);
$customer = $custStmt->fetch();

// Size breakdown
$sizes = $pdo->prepare("SELECT size, quantity FROM order_details WHERE order_id = ?");
$sizes->execute([$order_id]);
$sizeItems = $sizes->fetchAll();
$totalQty = array_sum(array_column($sizeItems, 'quantity'));

// Design files
$files = $pdo->prepare("SELECT file_id, file_path, file_type, upload_date FROM order_files WHERE order_id = ?");
$files->execute([$order_id]);
$designFiles = $files->fetchAll();

// Production notes
$notes = $pdo->prepare("
    SELECT pn.*, u.full_name AS author_name
    FROM production_notes pn
    LEFT JOIN users u ON pn.author_id = u.user_id
    WHERE pn.order_id = ?
    ORDER BY pn.created_at DESC LIMIT 20
");
$notes->execute([$order_id]);
$prodNotes = $notes->fetchAll();

// Task media
$media = $pdo->prepare("SELECT * FROM task_media WHERE order_id = ? ORDER BY uploaded_at DESC");
$media->execute([$order_id]);
$taskMedia = $media->fetchAll();

// QC status
$qcStmt = $pdo->prepare("SELECT * FROM qc_inspections WHERE order_id = ?");
$qcStmt->execute([$order_id]);
$qc = $qcStmt->fetch();

// Rework log
$rework = $pdo->prepare("SELECT * FROM rework_log WHERE order_id = ? ORDER BY created_at DESC");
$rework->execute([$order_id]);
$reworkLog = $rework->fetchAll();

$progress = getStageProgress($task['stage']);
$daysRemaining = $task['expected_completion'] ? max(0, (strtotime($task['expected_completion']) - time()) / 86400) : null;
$isOverdue = $task['expected_completion'] && strtotime($task['expected_completion']) < time();
?>
<link rel="stylesheet" href="/public/assets/css/mes.css">
<style>
  body { background: #f5f5f5; }
  .main-content { margin-left: 220px; padding: 24px 32px; background: #f5f5f5; }
  @media (max-width: 768px) { .main-content { margin-left: 0; padding: 16px; } }
  .mes-timeline-dot { width:12px;height:12px;border-radius:50%;flex-shrink:0;margin-top:3px }
  .mes-timeline-line { width:2px;flex-shrink:0;margin-left:5px;min-height:24px;background:#e5e7eb }
  .mes-timeline-line.active { background:var(--mes-primary) }
</style>

<div class="main-content">
  <!-- Breadcrumb -->
  <div class="d-flex align-items-center gap-2 mb-3" style="font-size:12px;color:#6b7280">
    <a href="dashboard.php" style="color:var(--mes-primary)">Dashboard</a>
    <span>/</span>
    <a href="my_tasks.php" style="color:var(--mes-primary)">My Tasks</a>
    <span>/</span>
    <span style="color:#374151">#ORD-<?= $order_id ?></span>
  </div>

  <!-- Header -->
  <div class="mes-card mb-3">
    <div class="mes-card-body" style="padding:20px 24px">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <h2 style="font-size:18px;font-weight:700;margin:0">#ORD-<?= $order_id ?> — <?= htmlspecialchars($task['product_type'] ?? 'Garment') ?></h2>
          <p style="font-size:13px;color:#6b7280;margin:4px 0 0"><?= htmlspecialchars($customer['full_name'] ?? 'N/A') ?> · Qty: <?= $totalQty ?> · <?= htmlspecialchars($task['service_name'] ?? 'Custom') ?></p>
        </div>
        <div class="d-flex gap-2 align-items-center">
          <span class="mes-badge <?= $task['priority']==='urgent'?'mes-badge-danger':($task['priority']==='high'?'mes-badge-warning':'mes-badge-gray') ?>"><?= ucfirst($task['priority'] ?? 'med') ?></span>
          <?php if ($isOverdue): ?>
          <span class="mes-badge mes-badge-danger">Overdue</span>
          <?php elseif ($daysRemaining !== null && $daysRemaining <= 2): ?>
          <span class="mes-badge mes-badge-warning">Due soon</span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Progress -->
      <div style="display:flex;align-items:center;gap:12px;margin-top:16px">
        <div style="flex:1;height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden">
          <div style="width:<?= $progress ?>%;height:100%;background:var(--mes-primary);border-radius:4px;transition:width .5s"></div>
        </div>
        <span style="font-size:13px;font-weight:600;color:var(--mes-primary);white-space:nowrap"><?= $progress ?>%</span>
        <span style="font-size:12px;color:#6b7280;white-space:nowrap">Stage: <?= htmlspecialchars($task['stage']) ?></span>
      </div>
    </div>
  </div>

  <div class="mes-layout">
    <div class="mes-main">
      <!-- Stage Timeline -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Production Timeline</h3></div>
        <div class="mes-card-body">
          <div style="display:flex;flex-direction:column;gap:0">
            <?php
            $stages = [
              STAGE_ORDER_RECEIVED, STAGE_DESIGN_REVIEW, STAGE_MATERIAL_PREP,
              STAGE_CUTTING, STAGE_PRINTING, STAGE_SEWING,
              STAGE_QUALITY_INSPECTION, STAGE_PACKAGING, STAGE_READY_PICKUP
            ];
            $currentIdx = array_search($task['stage'], $stages);
            foreach ($stages as $i => $s):
              $active = $i <= $currentIdx;
              $isCurrent = $s === $task['stage'];
            ?>
            <div style="display:flex;gap:12px;padding:6px 0">
              <div style="display:flex;flex-direction:column;align-items:center">
                <div class="mes-timeline-dot" style="background:<?= $active ? ($isCurrent ? 'var(--mes-primary)' : '#10b981') : '#d1d5db' ?>;border:2px solid <?= $active ? ($isCurrent ? 'var(--mes-primary)' : '#10b981') : '#e5e7eb' ?>"></div>
                <?php if ($i < count($stages)-1): ?>
                <div class="mes-timeline-line<?= $active && $i < $currentIdx ? ' active' : '' ?>"></div>
                <?php endif; ?>
              </div>
              <div style="font-size:13px;<?= $active ? 'color:#374151;font-weight:500' : 'color:#9ca3af' ?>">
                <?= htmlspecialchars($s) ?>
                <?php if ($isCurrent): ?><span class="mes-badge mes-badge-primary ms-2" style="font-size:10px">Current</span><?php endif; ?>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Size Breakdown -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Order Items</h3></div>
        <div class="mes-card-body">
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($sizeItems as $s): ?>
            <div style="background:#f3f4f6;border-radius:8px;padding:12px 20px;text-align:center;min-width:80px">
              <div style="font-size:16px;font-weight:700;color:#374151"><?= (int)$s['quantity'] ?></div>
              <div style="font-size:11px;color:#6b7280">Size <?= htmlspecialchars($s['size']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Design Files -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Design Files</h3></div>
        <div class="mes-card-body">
          <?php if (empty($designFiles)): ?>
          <p style="font-size:13px;color:#6b7280;margin:0;text-align:center;padding:12px 0">No design files uploaded</p>
          <?php else: ?>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($designFiles as $f):
              $path = '/public/uploads/designs/' . $f['file_path'];
              $ext = strtolower(pathinfo($f['file_path'], PATHINFO_EXTENSION));
              $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']);
            ?>
            <a href="<?= $path ?>" target="_blank" style="display:block;width:120px;height:120px;border-radius:8px;overflow:hidden;background:#f3f4f6;border:1px solid #e5e7eb">
              <?php if ($isImg): ?>
              <img src="<?= $path ?>" style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
              <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:24px;color:#9ca3af"><i class="fas fa-file-alt"></i></div>
              <?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Production Notes -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Production Notes</h3></div>
        <div class="mes-card-body">
          <form id="noteForm" style="margin-bottom:12px;display:flex;gap:8px">
            <input type="hidden" name="action" value="add_note">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            <input type="text" name="content" class="mes-form-input" placeholder="Add a note..." required style="flex:1">
            <button type="submit" class="mes-btn mes-btn-primary mes-btn-sm">Add</button>
          </form>
          <?php if (empty($prodNotes)): ?>
          <p style="font-size:13px;color:#6b7280;margin:0;text-align:center;padding:8px 0">No notes yet</p>
          <?php else: ?>
          <div class="mes-feed">
            <?php foreach ($prodNotes as $n): ?>
            <div class="mes-feed-item">
              <div class="mes-feed-icon" style="background:<?= $n['note_type']==='issue'?'#fee2e2':($n['note_type']==='handoff'?'#d1fae5':'#dbeafe') ?>;color:<?= $n['note_type']==='issue'?'#dc2626':($n['note_type']==='handoff'?'#059669':'#2563eb') ?>">
                <i class="fas fa-<?= $n['note_type']==='issue'?'exclamation-triangle':($n['note_type']==='handoff'?'check-double':'comment') ?>"></i>
              </div>
              <div class="mes-feed-content">
                <p><?= htmlspecialchars($n['content']) ?></p>
                <div class="mes-feed-time"><?= htmlspecialchars($n['author_name'] ?? 'System') ?> · <?= date('M d, g:i A', strtotime($n['created_at'])) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Media Uploads -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Progress Media</h3></div>
        <div class="mes-card-body">
          <form id="mediaForm" enctype="multipart/form-data" style="margin-bottom:12px">
            <input type="hidden" name="action" value="upload_media">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            <div style="display:flex;gap:8px;align-items:center">
              <input type="file" name="file" class="mes-form-input" accept="image/*" required style="flex:1">
              <input type="text" name="caption" class="mes-form-input" placeholder="Caption" style="flex:1">
              <button type="submit" class="mes-btn mes-btn-primary mes-btn-sm">Upload</button>
            </div>
          </form>
          <?php if (empty($taskMedia)): ?>
          <p style="font-size:13px;color:#6b7280;margin:0;text-align:center;padding:8px 0">No media uploaded</p>
          <?php else: ?>
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($taskMedia as $m): ?>
            <div style="width:140px">
              <img src="/public/<?= $m['file_path'] ?>" style="width:100%;height:100px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb">
              <?php if ($m['caption']): ?>
              <p style="font-size:11px;color:#6b7280;margin:4px 0 0"><?= htmlspecialchars($m['caption']) ?></p>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="mes-sidebar-right">
      <!-- Update Stage -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Update Stage</h3></div>
        <div class="mes-card-body">
          <form method="post" action="my_tasks.php">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            <div class="mes-form-group">
              <select name="stage" class="mes-form-select">
                <option value="<?= STAGE_DESIGN_REVIEW ?>" <?= $task['stage']===STAGE_DESIGN_REVIEW?'selected':'' ?>>Design Review</option>
                <option value="<?= STAGE_MATERIAL_PREP ?>" <?= $task['stage']===STAGE_MATERIAL_PREP?'selected':'' ?>>Material Prep</option>
                <option value="<?= STAGE_CUTTING ?>" <?= $task['stage']===STAGE_CUTTING?'selected':'' ?>>Cutting</option>
                <option value="<?= STAGE_PRINTING ?>" <?= $task['stage']===STAGE_PRINTING?'selected':'' ?>>Print/Embroider</option>
                <option value="<?= STAGE_SEWING ?>" <?= $task['stage']===STAGE_SEWING?'selected':'' ?>>Sewing & Assembly</option>
              </select>
            </div>
            <div class="mes-form-group">
              <input type="text" name="notes" class="mes-form-input" placeholder="Notes (optional)" value="<?= htmlspecialchars($task['workflow_notes'] ?? '') ?>">
            </div>
            <button type="submit" name="update_stage" class="mes-btn mes-btn-primary" style="width:100%">Update</button>
          </form>

          <?php if ($task['stage'] !== STAGE_QUALITY_INSPECTION): ?>
          <form method="post" action="my_tasks.php" style="margin-top:8px">
            <button type="submit" name="submit_qc" value="<?= $order_id ?>" class="mes-btn mes-btn-success" style="width:100%"><i class="fas fa-check"></i> Submit to QC</button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- QC Status -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">QC Status</h3></div>
        <div class="mes-card-body">
          <?php if ($qc): ?>
          <p style="font-size:13px;margin:0 0 4px">Result: <span class="mes-badge <?= $qc['result']==='Passed'?'mes-badge-success':($qc['result']==='Failed'?'mes-badge-danger':'mes-badge-warning') ?>"><?= $qc['result'] ?? 'Pending' ?></span></p>
          <?php if ($qc['inspected_at']): ?>
          <p style="font-size:12px;color:#6b7280;margin:0">Inspected: <?= date('M d, g:i A', strtotime($qc['inspected_at'])) ?></p>
          <?php endif; ?>
          <?php if ($qc['feedback']): ?>
          <p style="font-size:12px;color:#6b7280;margin:4px 0 0">Feedback: <?= htmlspecialchars($qc['feedback']) ?></p>
          <?php endif; ?>
          <?php else: ?>
          <p style="font-size:13px;color:#6b7280;margin:0;text-align:center;padding:8px 0">Not yet submitted to QC</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Rework History -->
      <?php if (!empty($reworkLog)): ?>
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Rework History</h3></div>
        <div class="mes-card-body">
          <?php foreach ($reworkLog as $r): ?>
          <div style="padding:8px 0;border-bottom:1px solid #f3f4f6;font-size:12px">
            <p style="margin:0;font-weight:500"><?= htmlspecialchars($r['from_stage']) ?> → <?= htmlspecialchars($r['to_stage']) ?></p>
            <p style="margin:2px 0 0;color:#6b7280"><?= htmlspecialchars($r['reason']) ?></p>
            <p style="margin:2px 0 0;color:#9ca3af"><?= date('M d, g:i A', strtotime($r['created_at'])) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Garment Tracking -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Garment Tracking</h3></div>
        <div class="mes-card-body">
          <p style="font-size:12px;color:#6b7280;margin-bottom:8px">Per-item stage tracking for this order</p>
          <a href="garment_tracking.php?order_id=<?= $order_id ?>" class="mes-btn mes-btn-primary mes-btn-sm" style="width:100%"><i class="fas fa-table"></i> View Item Status</a>
        </div>
      </div>

      <!-- Customer Info -->
      <div class="mes-card">
        <div class="mes-card-header"><h3 class="mes-card-title">Customer</h3></div>
        <div class="mes-card-body" style="font-size:13px">
          <p style="margin:0 0 4px"><strong><?= htmlspecialchars($customer['full_name'] ?? 'N/A') ?></strong></p>
          <p style="margin:0 0 2px;color:#6b7280"><?= htmlspecialchars($customer['email'] ?? '') ?></p>
          <p style="margin:0;color:#6b7280"><?= htmlspecialchars($customer['phone_number'] ?? '') ?></p>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Quick note via AJAX
document.getElementById('noteForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  const res = await fetch('/controller/production_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    location.reload();
  } else {
    alert('Error: ' + (data.error || 'Unknown'));
  }
});

// Media upload
document.getElementById('mediaForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  const res = await fetch('/controller/production_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    location.reload();
  } else {
    alert('Error: ' + (data.error || 'Unknown'));
  }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
