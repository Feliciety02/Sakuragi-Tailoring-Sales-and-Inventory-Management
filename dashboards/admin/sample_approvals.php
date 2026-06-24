<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/role_admin_only.php';

$user_id = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sample'])) {
    $order_id = (int)$_POST['order_id'];
    $notes = $_POST['notes'] ?? '';
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE order_workflow SET sample_status='submitted', sample_submitted_at=NOW(), stage=? WHERE order_id=?")->execute([STAGE_SAMPLE_REVIEW, $order_id]);
        $pdo->prepare("INSERT INTO sample_approvals (order_id, submitted_by, status, notes) VALUES (?, ?, 'pending', ?)")->execute([$order_id, $user_id, $notes]);
        $approvalId = $pdo->lastInsertId();
        $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, 'Sample submitted for customer approval', 'handoff')")->execute([$order_id, $user_id]);

        // Handle photo uploads
        $uploadDir = __DIR__ . '/../../public/uploads/samples/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $maxSize = 5 * 1024 * 1024;

        if (!empty($_FILES['photos'])) {
            $files = $_FILES['photos'];
            $fileCount = is_array($files['name']) ? count($files['name']) : 1;
            for ($i = 0; $i < $fileCount; $i++) {
                $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $type = is_array($files['type']) ? $files['type'][$i] : $files['type'];
                $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                if ($error !== UPLOAD_ERR_OK) continue;
                if (!in_array($type, $allowedTypes)) continue;
                if ($size > $maxSize) continue;
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $safeName = 'sample_' . $order_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $dest = $uploadDir . $safeName;
                if (move_uploaded_file($tmp, $dest)) {
                    $relPath = '/public/uploads/samples/' . $safeName;
                    $pdo->prepare("INSERT INTO sample_photos (approval_id, order_id, file_path, uploaded_by) VALUES (?, ?, ?, ?)")->execute([$approvalId, $order_id, $relPath, $user_id]);
                }
            }
        }

        $pdo->commit();
        $msg = 'Sample submitted for approval';
    } catch (Exception $e) { $pdo->rollBack(); $msg = 'Error: ' . $e->getMessage(); }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_sample'])) {
    $order_id = (int)$_POST['order_id'];
    try {
        $pdo->prepare("UPDATE order_workflow SET sample_status='not_required', stage=? WHERE order_id=?")->execute([STAGE_BULK_PRODUCTION, $order_id]);
        $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, 'Sample skipped — moving to bulk production', 'general')")->execute([$order_id, $user_id]);
        $msg = 'Sample skipped, order moved to bulk production';
    } catch (Exception $e) { $msg = 'Error: ' . $e->getMessage(); }
}

$pendingSamples = $pdo->query("
    SELECT o.order_id, o.order_date, u.full_name AS customer_name,
           ow.sample_status, ow.product_type, ow.expected_completion, ow.priority,
           sa.approval_id, sa.notes AS sample_notes, sa.submitted_at
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN sample_approvals sa ON o.order_id = sa.order_id AND sa.status = 'pending'
    WHERE ow.sample_status IN ('pending','submitted')
    ORDER BY sa.submitted_at DESC
");

$sampleHistory = $pdo->query("
    SELECT sa.*, o.order_id, u.full_name AS customer_name, sub.full_name AS submitted_by_name
    FROM sample_approvals sa
    JOIN orders o ON sa.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN users sub ON sa.submitted_by = sub.user_id
    WHERE sa.status IN ('approved','rejected')
    ORDER BY sa.reviewed_at DESC LIMIT 20
");

$approvableOrders = $pdo->query("
    SELECT o.order_id, u.full_name AS customer_name, ow.product_type
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    WHERE ow.sample_status = 'not_required' AND ow.stage IN ('Design Review','Material Preparation')
");

// KPI calculations
$totalPending = $pendingSamples->rowCount();
$recentApprovals = $pdo->query("SELECT COUNT(*) AS c FROM sample_approvals WHERE status='approved' AND reviewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch()['c'];
$recentRejections = $pdo->query("SELECT COUNT(*) AS c FROM sample_approvals WHERE status='rejected' AND reviewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch()['c'];
$totalReviewed = $pdo->query("SELECT COUNT(*) AS c FROM sample_approvals WHERE status IN ('approved','rejected')")->fetch()['c'];
$approvalRate = $totalReviewed > 0 ? round(($pdo->query("SELECT COUNT(*) AS c FROM sample_approvals WHERE status='approved'")->fetch()['c'] / $totalReviewed) * 100) : 0;

$pageTitle = 'Sample Approvals';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sample Approvals — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="admin">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
$alertHtml = '';
if ($msg):
  $isErr = strpos($msg, 'Error') !== false;
  $alertHtml = '<div class="alert-banner" style="background:' . ($isErr ? 'rgba(239,68,68,0.08)' : 'rgba(34,197,94,0.08)') . ';border:1px solid ' . ($isErr ? 'rgba(239,68,68,0.2)' : 'rgba(34,197,94,0.2)') . ';color:' . ($isErr ? '#ef4444' : '#22c55e') . ';border-radius:12px;padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px;font-size:0.85rem"><i class="fas ' . ($isErr ? 'fa-exclamation-circle' : 'fa-check-circle') . '"></i> ' . htmlspecialchars($msg) . '</div>';
endif;

// ── KPI Row ──
$kpiRow = renderKPIRow([
  ['icon' => 'fas fa-hourglass-half', 'label' => 'Pending Review', 'value' => $totalPending, 'accent' => 'amber'],
  ['icon' => 'fas fa-check-circle', 'label' => 'Approved (7d)', 'value' => $recentApprovals, 'accent' => 'green'],
  ['icon' => 'fas fa-times-circle', 'label' => 'Rejected (7d)', 'value' => $recentRejections, 'accent' => 'red'],
  ['icon' => 'fas fa-percentage', 'label' => 'Approval Rate', 'value' => $approvalRate . '%', 'accent' => 'blue'],
]);

// ── Submit Sample Section ──
$submitHtml = '';
if ($approvableOrders->rowCount() === 0):
  $submitHtml = renderEmptyState('fas fa-check-double', 'No Orders Ready', 'All orders either have samples submitted, approved, or are already past the sample stage.', []);
else:
  ob_start();
  foreach ($approvableOrders as $ao):
?>
<div class="sample-card">
  <div class="sample-card-body">
    <div class="sample-card-header">
      <div class="sample-card-title">#ORD-<?= $ao['order_id'] ?> — <?= htmlspecialchars($ao['customer_name']) ?></div>
      <span class="status-badge status-badge-neutral status-badge-sm"><?= htmlspecialchars($ao['product_type'] ?? 'Garment') ?></span>
    </div>
    <div class="sample-card-meta">
      <span><i class="fas fa-user"></i> <?= htmlspecialchars($ao['customer_name']) ?></span>
      <span><i class="fas fa-tag"></i> <?= htmlspecialchars($ao['product_type'] ?? 'Standard Garment') ?></span>
    </div>
    <div class="sample-card-footer">
      <button class="dash-btn dash-btn-primary dash-btn-sm" onclick="openSubmit(<?= $ao['order_id'] ?>)"><i class="fas fa-paper-plane"></i> Submit Sample</button>
      <form method="post" style="display:inline">
        <input type="hidden" name="order_id" value="<?= $ao['order_id'] ?>">
        <button type="submit" name="skip_sample" class="dash-btn dash-btn-outline dash-btn-sm" onclick="return confirm('Skip sample for this order and proceed to bulk production?')"><i class="fas fa-forward"></i> Skip Sample</button>
      </form>
    </div>
  </div>
</div>
<?php
  endforeach;
  $submitHtml = ob_get_clean();
endif;

// ── Pending Customer Review Section ──
$pendingHtml = '';
if ($totalPending === 0):
  $pendingHtml = renderEmptyState('fas fa-inbox', 'No Pending Reviews', 'All submitted samples have been reviewed. When customers review samples, results will appear here.', []);
else:
  ob_start();
  foreach ($pendingSamples as $s):
    $daysWaiting = $s['submitted_at'] ? floor((time() - strtotime($s['submitted_at'])) / 86400) : 0;
    $urgencyClass = $daysWaiting >= 3 ? 'urgent-overdue' : ($daysWaiting >= 1 ? 'urgent-soon' : '');
    $urgencyLabel = $daysWaiting >= 3 ? $daysWaiting . ' days overdue' : ($daysWaiting >= 1 ? $daysWaiting . ' day wait' : 'Just submitted');
?>
<div class="sample-card pending-highlight">
  <div class="sample-card-body">
    <div class="sample-card-header">
      <div>
        <div class="sample-card-title">#ORD-<?= $s['order_id'] ?></div>
        <div style="margin-top:4px;display:flex;gap:6px;flex-wrap:wrap;align-items:center">
          <?= renderStatusBadge('Pending', 'warning', 'sm') ?>
          <?php if ($urgencyClass): ?>
          <span class="sample-card-urgency <?= $urgencyClass ?>"><i class="fas fa-clock"></i> <?= $urgencyLabel ?></span>
          <?php endif; ?>
        </div>
      </div>
      <span style="font-size:0.7rem;color:var(--text-tertiary);white-space:nowrap">Submitted <?= $s['submitted_at'] ? date('M d, g:i A', strtotime($s['submitted_at'])) : '—' ?></span>
    </div>
    <div class="sample-card-meta">
      <span><i class="fas fa-user"></i> <?= htmlspecialchars($s['customer_name']) ?></span>
      <span><i class="fas fa-tag"></i> <?= htmlspecialchars($s['product_type'] ?? 'Garment') ?></span>
    </div>
    <?php if ($s['sample_notes']): ?>
    <div class="sample-card-notes">
      <strong>Production Notes:</strong><br>
      <?= nl2br(htmlspecialchars($s['sample_notes'])) ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
  endforeach;
  $pendingHtml = ob_get_clean();
endif;

// ── History Section ──
$historyHtml = '';
if ($sampleHistory->rowCount() === 0):
  $historyHtml = renderEmptyState('fas fa-clock', 'No Review History', 'Completed sample reviews will appear here.', []);
else:
  ob_start();
  foreach ($sampleHistory as $h):
    $v = strtolower($h['status']) === 'approved' ? 'success' : 'danger';
?>
<div class="history-item" style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border-light);font-size:.82rem">
  <div>
    <strong>#ORD-<?= $h['order_id'] ?></strong>
    <?= renderStatusBadge(htmlspecialchars($h['status']), $v, 'sm') ?>
    <span style="color:var(--text-tertiary);margin-left:8px">by <?= htmlspecialchars($h['customer_name'] ?? 'N/A') ?></span>
  </div>
  <div style="color:var(--text-tertiary);font-size:.75rem"><?= date('M d, Y', strtotime($h['reviewed_at'])) ?></div>
</div>
<?php
  endforeach;
  $historyHtml = ob_get_clean();
endif;

$mainCol = '';
$mainCol .= renderPageSection('Submit Sample for Approval', $submitHtml, 'fas fa-paper-plane');
$mainCol .= renderPageSection('Awaiting Customer Approval', $pendingHtml, 'fas fa-hourglass-half');
$mainCol .= renderPageSection('Approval History', $historyHtml, 'fas fa-history');

$scriptsHtml = '';
ob_start(); ?>
<!-- Submit Sample Modal -->
<div class="modern-modal-overlay" id="submitModal" style="display:none" onclick="if(event.target===this)closeSubmit()">
  <div class="modern-modal" style="max-width:520px">
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="order_id" id="submitOrderId">
      <h3 style="margin:0 0 16px;font-size:1.05rem;font-weight:700;color:var(--text-primary)">Submit Sample for Approval</h3>
      <p style="font-size:0.8rem;color:var(--text-tertiary);margin:-8px 0 16px">Attach clear photos of the sample garment for customer review.</p>
      <div class="form-group">
        <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px">Production Notes</label>
        <textarea name="notes" rows="3" placeholder="Fabric used, measurements, any notes for the customer..." style="width:100%;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;font-size:0.85rem;background:var(--bg-secondary);color:var(--text-primary);resize:vertical;font-family:inherit"></textarea>
      </div>
      <div class="form-group">
        <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px">Sample Photos <span style="font-weight:400;color:var(--text-tertiary)">(optional)</span></label>
        <div class="photo-upload-area" onclick="document.getElementById('photoInput').click()">
          <div class="upload-icon"><i class="fas fa-camera"></i></div>
          <div class="upload-text">Click to upload sample photos</div>
          <div class="upload-hint">JPG, PNG, WEBP · Max 5MB each · Multiple allowed</div>
        </div>
        <input type="file" name="photos[]" id="photoInput" accept="image/jpeg,image/png,image/webp" multiple style="display:none">
        <div class="photo-preview-grid" id="photoPreview"></div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid var(--border-light)">
        <button type="button" class="dash-btn dash-btn-outline" onclick="closeSubmit()">Cancel</button>
        <button type="submit" name="submit_sample" class="dash-btn dash-btn-primary"><i class="fas fa-paper-plane"></i> Submit Sample</button>
      </div>
    </form>
  </div>
</div>

<!-- Photo Preview Modal -->
<div class="photo-modal" id="photoModal" onclick="if(event.target===this)closePhotoModal()">
  <button class="photo-modal-close" onclick="closePhotoModal()">&times;</button>
  <img id="photoModalImg" src="" alt="Sample photo">
</div>

<script>
function openSubmit(id) {
  document.getElementById('submitOrderId').value = id;
  document.getElementById('submitModal').style.display = 'flex';
  document.getElementById('photoPreview').innerHTML = '';
}

function closeSubmit() {
  document.getElementById('submitModal').style.display = 'none';
}

document.getElementById('photoInput')?.addEventListener('change', function(e) {
  const files = Array.from(e.target.files);
  const grid = document.getElementById('photoPreview');
  grid.innerHTML = '';
  files.forEach(f => {
    const reader = new FileReader();
    reader.onload = function(ev) {
      const div = document.createElement('div');
      div.className = 'preview-item';
      div.innerHTML = '<img src="' + ev.target.result + '" alt="Preview">';
      grid.appendChild(div);
    };
    reader.readAsDataURL(f);
  });
});

function openPhotoModal(src) {
  document.getElementById('photoModalImg').src = src;
  document.getElementById('photoModal').style.display = 'flex';
}

function closePhotoModal() {
  document.getElementById('photoModal').style.display = 'none';
}

document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
<?php $scriptsHtml = ob_get_clean();

echo renderDashboardShell(
  renderPageHeader('Sample Approval Workflow', 'Submit samples for customer approval before bulk production begins.', date('M d, Y')),
  $kpiRow,
  $alertHtml . $mainCol . $scriptsHtml
);
?>
