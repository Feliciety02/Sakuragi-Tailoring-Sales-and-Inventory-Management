<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/auth_required.php';
require_once __DIR__ . '/../../app/Support/helpers.php';

$pageTitle = 'View Task';

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

$custStmt = $pdo->prepare("SELECT u.full_name, u.email, u.phone_number FROM users u JOIN orders o ON u.user_id = o.user_id WHERE o.order_id = ?");
$custStmt->execute([$order_id]);
$customer = $custStmt->fetch();

$sizes = $pdo->prepare("SELECT size, quantity FROM order_details WHERE order_id = ?");
$sizes->execute([$order_id]);
$sizeItems = $sizes->fetchAll();
$totalQty = array_sum(array_column($sizeItems, 'quantity'));

$files = $pdo->prepare("SELECT file_id, file_path, file_type, uploaded_at FROM order_files WHERE order_id = ?");
$files->execute([$order_id]);
$designFiles = $files->fetchAll();

$notes = $pdo->prepare("
    SELECT pn.*, u.full_name AS author_name
    FROM production_notes pn
    LEFT JOIN users u ON pn.author_id = u.user_id
    WHERE pn.order_id = ?
    ORDER BY pn.created_at DESC LIMIT 20
");
$notes->execute([$order_id]);
$prodNotes = $notes->fetchAll();

$media = $pdo->prepare("SELECT * FROM task_media WHERE order_id = ? ORDER BY created_at DESC");
$media->execute([$order_id]);
$taskMedia = $media->fetchAll();

$qcStmt = $pdo->prepare("SELECT * FROM qc_inspections WHERE order_id = ?");
$qcStmt->execute([$order_id]);
$qc = $qcStmt->fetch();

$rework = $pdo->prepare("SELECT * FROM rework_log WHERE order_id = ? ORDER BY created_at DESC");
$rework->execute([$order_id]);
$reworkLog = $rework->fetchAll();

$progress = getStageProgress($task['stage']);
$daysRemaining = $task['expected_completion'] ? max(0, (strtotime($task['expected_completion']) - time()) / 86400) : null;
$isOverdue = $task['expected_completion'] && strtotime($task['expected_completion']) < time();
$role = get_user_role();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Task — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="emp-viewtask-styles">
    .tl-dot-sm { width:12px;height:12px;border-radius:50%;flex-shrink:0;margin-top:3px }
    .tl-line-sm { width:2px;flex-shrink:0;margin-left:5px;min-height:24px;background:var(--border-color) }
    .tl-line-sm.active { background:var(--role-accent) }
    .item-tile { background:var(--bg-secondary);border-radius:8px;padding:12px 20px;text-align:center;min-width:70px }
    .item-tile .qty { font-size:16px;font-weight:700;color:var(--text-primary) }
    .item-tile .size { font-size:11px;color:var(--text-tertiary) }
    .design-thumb { display:block;width:120px;height:120px;border-radius:8px;overflow:hidden;background:var(--bg-secondary);border:1px solid var(--border-color);transition:border-color .2s }
    .design-thumb:hover { border-color:var(--role-accent) }
    .design-thumb img { width:100%;height:100%;object-fit:cover }
    .design-thumb .placeholder { display:flex;align-items:center;justify-content:center;height:100%;font-size:24px;color:var(--text-tertiary) }
    .media-item { width:140px }
    .media-item img { width:100%;height:100px;object-fit:cover;border-radius:6px;border:1px solid var(--border-color) }
    .media-item .caption { font-size:0.7rem;color:var(--text-tertiary);margin:4px 0 0 }
    .note-input-row { display:flex;gap:8px;margin-bottom:12px }
    .note-input-row input { flex:1;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;font-size:0.85rem;background:var(--bg-secondary);color:var(--text-primary);outline:none }
    .note-input-row input:focus { border-color:var(--role-accent) }
    .media-upload-row { display:flex;gap:8px;align-items:center;margin-bottom:12px;flex-wrap:wrap }
    .media-upload-row input[type="file"] { flex:1;padding:8px;border:1px solid var(--border-color);border-radius:8px;font-size:0.82rem;background:var(--bg-secondary) }
    .media-upload-row input[type="text"] { flex:1;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;font-size:0.82rem;background:var(--bg-secondary);color:var(--text-primary);outline:none;min-width:120px }
    .media-upload-row input[type="text"]:focus { border-color:var(--role-accent) }
  </style>
</head>
<body data-role="<?= htmlspecialchars($role) ?>">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
$breadcrumb = '<div style="font-size:0.78rem;color:var(--text-tertiary);margin-bottom:8px"><a href="dashboard.php" style="color:var(--role-accent);text-decoration:none">Dashboard</a> <span style="margin:0 4px">/</span> <a href="my_tasks.php" style="color:var(--role-accent);text-decoration:none">My Tasks</a> <span style="margin:0 4px">/</span> <span style="color:var(--text-primary)">#ORD-' . $order_id . '</span></div>';

$pVariant = ($task['priority']??'medium') === 'urgent' ? 'danger' : (($task['priority']??'medium') === 'high' ? 'warning' : 'neutral');
$priorityBadge = renderStatusBadge(ucfirst($task['priority'] ?? 'Medium'), $pVariant, 'sm');
$overdueBadge = '';
if ($isOverdue) $overdueBadge = renderStatusBadge('Overdue', 'danger', 'sm');
elseif ($daysRemaining !== null && $daysRemaining <= 2) $overdueBadge = renderStatusBadge('Due soon', 'warning', 'sm');

$stageColor = $STAGE_CONFIG[$task['stage']]['color'] ?? 'var(--role-accent)';

$headerCard = '<div class="panel-card" style="padding:20px 24px;margin-bottom:16px">';
$headerCard .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:12px">';
$headerCard .= '<div><h2 style="margin:0;font-size:1.15rem;font-weight:700;color:var(--text-primary)">#ORD-' . $order_id . ' — ' . htmlspecialchars($task['product_type'] ?? 'Garment') . '</h2>';
$headerCard .= '<p style="margin:4px 0 0;font-size:0.82rem;color:var(--text-tertiary)">' . htmlspecialchars($customer['full_name'] ?? 'N/A') . ' · Qty: ' . $totalQty . ' · ' . htmlspecialchars($task['service_name'] ?? 'Custom') . '</p></div>';
$headerCard .= '<div style="display:flex;gap:6px">' . $priorityBadge . $overdueBadge . '</div></div>';
$headerCard .= '<div style="display:flex;align-items:center;gap:12px"><div style="flex:1;height:8px;background:var(--border-color);border-radius:4px;overflow:hidden"><div style="width:' . $progress . '%;height:100%;background:' . $stageColor . ';border-radius:4px;transition:width .5s"></div></div><span style="font-size:0.82rem;font-weight:600;color:var(--text-secondary);white-space:nowrap">' . $progress . '%</span><span style="font-size:0.75rem;color:var(--text-tertiary);white-space:nowrap">Stage: ' . htmlspecialchars($task['stage']) . '</span></div>';
$headerCard .= '</div>';

// ── Main column content ──
$mainInner = '';

// Stage Timeline
$stages = [STAGE_ORDER_RECEIVED, STAGE_DESIGN_REVIEW, STAGE_MATERIAL_PREP, STAGE_CUTTING, STAGE_PRINTING, STAGE_SEWING, STAGE_QUALITY_INSPECTION, STAGE_PACKAGING, STAGE_READY_PICKUP];
$currentIdx = array_search($task['stage'], $stages);
ob_start();
?>
<div style="display:flex;flex-direction:column;gap:0">
  <?php foreach ($stages as $i => $s):
    $active = $i <= $currentIdx;
    $isCurrent = $s === $task['stage'];
  ?>
  <div style="display:flex;gap:12px;padding:6px 0">
    <div style="display:flex;flex-direction:column;align-items:center">
      <div class="tl-dot-sm" style="background:<?= $active ? ($isCurrent ? 'var(--role-accent)' : '#22c55e') : '#d1d5db' ?>;border:2px solid <?= $active ? ($isCurrent ? 'var(--role-accent)' : '#22c55e') : '#e5e7eb' ?>"></div>
      <?php if ($i < count($stages)-1): ?>
      <div class="tl-line-sm<?= $active && $i < $currentIdx ? ' active' : '' ?>"></div>
      <?php endif; ?>
    </div>
    <div style="font-size:0.82rem;<?= $active ? 'color:var(--text-primary);font-weight:500' : 'color:var(--text-tertiary)' ?>">
      <?= htmlspecialchars($s) ?>
      <?php if ($isCurrent): ?><?= renderStatusBadge('Current', 'accent', 'sm') ?><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php
$mainInner .= renderPageSection('Production Timeline', ob_get_clean());

// Order Items
ob_start();
?>
<div style="display:flex;flex-wrap:wrap;gap:8px">
  <?php foreach ($sizeItems as $s): ?>
  <div class="item-tile"><div class="qty"><?= (int)$s['quantity'] ?></div><div class="size">Size <?= htmlspecialchars($s['size']) ?></div></div>
  <?php endforeach; ?>
</div>
<?php
$mainInner .= renderPageSection('Order Items', ob_get_clean());

// Design Files
ob_start();
if (empty($designFiles)):
  echo '<p style="font-size:0.82rem;color:var(--text-tertiary);text-align:center;padding:8px 0;margin:0">No design files uploaded</p>';
else:
?>
<div style="display:flex;flex-wrap:wrap;gap:8px">
  <?php foreach ($designFiles as $f):
    $path = '/public/uploads/designs/' . $f['file_path'];
    $ext = strtolower(pathinfo($f['file_path'], PATHINFO_EXTENSION));
    $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']);
  ?>
  <a href="<?= $path ?>" target="_blank" class="design-thumb">
    <?php if ($isImg): ?><img src="<?= $path ?>" alt="Design"><?php else: ?><div class="placeholder"><i class="fas fa-file-alt"></i></div><?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>
<?php
endif;
$mainInner .= renderPageSection('Design Files', ob_get_clean());

// Production Notes
ob_start();
?>
<form id="noteForm" class="note-input-row">
  <input type="hidden" name="action" value="add_note">
  <input type="hidden" name="order_id" value="<?= $order_id ?>">
  <input type="text" name="content" placeholder="Add a note..." required>
  <button type="submit" class="dash-btn dash-btn-primary dash-btn-sm">Add</button>
</form>
<?php if (empty($prodNotes)): ?>
<p style="font-size:0.82rem;color:var(--text-tertiary);text-align:center;padding:8px 0;margin:0">No notes yet</p>
<?php else:
  $feedItems = [];
  foreach ($prodNotes as $n):
    $icon = $n['note_type']==='issue' ? 'fas fa-exclamation-triangle' : ($n['note_type']==='handoff' ? 'fas fa-check-double' : 'fas fa-comment');
    $accent = $n['note_type']==='issue' ? 'red' : ($n['note_type']==='handoff' ? 'green' : 'blue');
    $feedItems[] = ['icon' => $icon, 'text' => htmlspecialchars($n['content']), 'time' => htmlspecialchars($n['author_name'] ?? 'System') . ' · ' . date('M d, g:i A', strtotime($n['created_at'])), 'accent' => $accent];
  endforeach;
  echo renderActivityFeed($feedItems);
endif;
$mainInner .= renderPageSection('Production Notes', ob_get_clean());

// Progress Media
ob_start();
?>
<form id="mediaForm" enctype="multipart/form-data" class="media-upload-row">
  <input type="hidden" name="action" value="upload_media">
  <input type="hidden" name="order_id" value="<?= $order_id ?>">
  <input type="file" name="file" accept="image/*" required>
  <input type="text" name="caption" placeholder="Caption (optional)">
  <button type="submit" class="dash-btn dash-btn-primary dash-btn-sm">Upload</button>
</form>
<?php if (empty($taskMedia)): ?>
<p style="font-size:0.82rem;color:var(--text-tertiary);text-align:center;padding:8px 0;margin:0">No media uploaded</p>
<?php else: ?>
<div style="display:flex;flex-wrap:wrap;gap:8px">
  <?php foreach ($taskMedia as $m): ?>
  <div class="media-item">
    <img src="/public/<?= $m['file_path'] ?>" alt="Progress photo">
    <?php if ($m['caption']): ?><p class="caption"><?= htmlspecialchars($m['caption']) ?></p><?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif;
$mainInner .= renderPageSection('Progress Media', ob_get_clean());

// ── Sidebar content ──
$sidebarHtml = '';

// Update Stage
ob_start();
?>
<form method="post" action="my_tasks.php">
  <input type="hidden" name="order_id" value="<?= $order_id ?>">
  <div style="margin-bottom:8px">
    <select name="stage" style="width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:6px;font-size:0.82rem;background:var(--bg-secondary);color:var(--text-primary)">
      <option value="<?= STAGE_DESIGN_REVIEW ?>" <?= $task['stage']===STAGE_DESIGN_REVIEW?'selected':'' ?>>Design Review</option>
      <option value="<?= STAGE_MATERIAL_PREP ?>" <?= $task['stage']===STAGE_MATERIAL_PREP?'selected':'' ?>>Material Prep</option>
      <option value="<?= STAGE_CUTTING ?>" <?= $task['stage']===STAGE_CUTTING?'selected':'' ?>>Cutting</option>
      <option value="<?= STAGE_PRINTING ?>" <?= $task['stage']===STAGE_PRINTING?'selected':'' ?>>Print/Embroider</option>
      <option value="<?= STAGE_SEWING ?>" <?= $task['stage']===STAGE_SEWING?'selected':'' ?>>Sewing & Assembly</option>
    </select>
  </div>
  <div style="margin-bottom:8px">
    <input type="text" name="notes" placeholder="Notes (optional)" value="<?= htmlspecialchars($task['workflow_notes'] ?? '') ?>" style="width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:6px;font-size:0.82rem;background:var(--bg-secondary);color:var(--text-primary)">
  </div>
  <button type="submit" name="update_stage" class="dash-btn dash-btn-primary dash-btn-sm" style="width:100%;justify-content:center">Update Stage</button>
</form>
<?php if ($task['stage'] !== STAGE_QUALITY_INSPECTION): ?>
<form method="post" action="my_tasks.php" style="margin-top:8px">
  <button type="submit" name="submit_qc" value="<?= $order_id ?>" class="dash-btn dash-btn-success dash-btn-sm" style="width:100%;justify-content:center"><i class="fas fa-check"></i> Submit to QC</button>
</form>
<?php endif;
$sidebarHtml .= renderPageSection('Update Stage', ob_get_clean(), '', [], 'sidebar');

// QC Status
ob_start();
if ($qc):
  $qcVariant = $qc['result']==='Passed' ? 'success' : ($qc['result']==='Failed' ? 'danger' : 'warning');
  echo '<p style="margin:0 0 4px;font-size:0.82rem;color:var(--text-secondary)">Result: ' . renderStatusBadge($qc['result'] ?? 'Pending', $qcVariant, 'sm') . '</p>';
  if ($qc['inspected_at']):
    echo '<p style="font-size:0.75rem;color:var(--text-tertiary);margin:0 0 4px">Inspected: ' . date('M d, g:i A', strtotime($qc['inspected_at'])) . '</p>';
  endif;
  if ($qc['feedback']):
    echo '<p style="font-size:0.75rem;color:var(--text-tertiary);margin:0">Feedback: ' . htmlspecialchars($qc['feedback']) . '</p>';
  endif;
else:
  echo '<p style="font-size:0.82rem;color:var(--text-tertiary);text-align:center;padding:8px 0;margin:0">Not yet submitted to QC</p>';
endif;
$sidebarHtml .= renderPageSection('QC Status', ob_get_clean(), '', [], 'sidebar');

// Rework History
if (!empty($reworkLog)):
  ob_start();
  foreach ($reworkLog as $r):
?>
<div style="padding:8px 0;border-bottom:1px solid var(--border-color);font-size:0.75rem">
  <p style="margin:0;font-weight:500;color:var(--text-primary)"><?= htmlspecialchars($r['from_stage']) ?> → <?= htmlspecialchars($r['to_stage']) ?></p>
  <p style="margin:2px 0 0;color:var(--text-tertiary)"><?= htmlspecialchars($r['reason']) ?></p>
  <p style="margin:2px 0 0;color:var(--text-tertiary);opacity:.7"><?= date('M d, g:i A', strtotime($r['created_at'])) ?></p>
</div>
<?php
  endforeach;
  $sidebarHtml .= renderPageSection('Rework History', ob_get_clean(), '', [], 'sidebar');
endif;

// Garment Tracking
$sidebarHtml .= renderPageSection('Garment Tracking', '<p style="font-size:0.75rem;color:var(--text-tertiary);margin:0 0 8px">Per-item stage tracking for this order</p><a href="garment_tracking.php?order_id=' . $order_id . '" class="dash-btn dash-btn-outline dash-btn-sm" style="width:100%;justify-content:center"><i class="fas fa-table"></i> View Item Status</a>', '', [], 'sidebar');

// Customer Info
$sidebarHtml .= renderPageSection('Customer', '<p style="margin:0 0 4px;font-size:0.85rem;font-weight:600;color:var(--text-primary)">' . htmlspecialchars($customer['full_name'] ?? 'N/A') . '</p><p style="margin:0 0 2px;font-size:0.75rem;color:var(--text-tertiary)">' . htmlspecialchars($customer['email'] ?? '') . '</p><p style="margin:0;font-size:0.75rem;color:var(--text-tertiary)">' . htmlspecialchars($customer['phone_number'] ?? '') . '</p>', '', [], 'sidebar');

$workspace = $breadcrumb . $headerCard . renderTwoColumn($mainInner, $sidebarHtml);

echo renderDashboardShell('', '', $workspace);
?>
    </div>
  </div>
</div>

<script>
document.getElementById('noteForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  const res = await fetch('/app/Controllers/production_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) location.reload();
  else alert('Error: ' + (data.error || 'Unknown'));
});

document.getElementById('mediaForm')?.addEventListener('submit', async function(e) {
  e.preventDefault();
  const fd = new FormData(this);
  const res = await fetch('/app/Controllers/production_api.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) location.reload();
  else alert('Error: ' + (data.error || 'Unknown'));
});

document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>
