<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/auth_required.php';

if (get_user_role() !== ROLE_CUSTOMER) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_sample'])) {
    $order_id = (int)$_POST['order_id'];
    $action = $_POST['action'] ?? '';
    $feedback = $_POST['feedback'] ?? '';
    $corrections = $_POST['corrections'] ?? '';

    if (!in_array($action, ['approved','rejected'])) {
        $msg = 'Invalid action';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE sample_approvals SET status=?, reviewed_by=?, rejection_reason=?, corrections_needed=?, reviewed_at=NOW() WHERE order_id=? AND status='pending'")
                ->execute([$action, $user_id, $action === 'rejected' ? $feedback : null, $action === 'rejected' ? $corrections : null, $order_id]);
            $pdo->prepare("UPDATE order_workflow SET sample_status=?, stage=? WHERE order_id=?")
                ->execute([$action, $action === 'approved' ? STAGE_BULK_PRODUCTION : STAGE_DESIGN_REVIEW, $order_id]);
            $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, ?, 'handoff')")
                ->execute([$order_id, $user_id, $action === 'approved' ? 'Sample approved by customer — starting bulk production' : 'Sample rejected by customer: ' . $feedback]);
            $pdo->commit();
            $msg = $action === 'approved' ? 'Sample approved! Production will begin shortly.' : 'Sample rejected. We will review your feedback.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = 'Error: ' . $e->getMessage();
        }
    }
}

$pendingReviews = $pdo->prepare("
    SELECT o.order_id, o.order_date, o.total_price,
           ow.sample_status, ow.product_type, ow.sample_submitted_at,
           sa.approval_id, sa.notes, sa.submitted_at
    FROM orders o
    JOIN order_workflow ow ON o.order_id = ow.order_id
    LEFT JOIN sample_approvals sa ON o.order_id = sa.order_id AND sa.status = 'pending'
    WHERE o.user_id = ? AND ow.sample_status = 'submitted'
    ORDER BY sa.submitted_at DESC
");
$pendingReviews->execute([$user_id]);

$pendingOrders = [];
foreach ($pendingReviews as $r) {
    $pendingOrders[$r['order_id']] = $r;
}
if (!empty($pendingOrders)) {
    $orderIds = implode(',', array_keys($pendingOrders));
    $photos = $pdo->query("SELECT * FROM sample_photos WHERE order_id IN ($orderIds) ORDER BY uploaded_at ASC")->fetchAll();
    $photosByOrder = [];
    foreach ($photos as $p) {
        $photosByOrder[$p['order_id']][] = $p;
    }
} else {
    $photosByOrder = [];
}

$reviewHistory = $pdo->prepare("
    SELECT sa.*, o.order_id, ow.product_type
    FROM sample_approvals sa
    JOIN orders o ON sa.order_id = o.order_id
    JOIN order_workflow ow ON o.order_id = ow.order_id
    WHERE sa.reviewed_by = ? AND sa.status IN ('approved','rejected')
    ORDER BY sa.reviewed_at DESC LIMIT 20
");
$reviewHistory->execute([$user_id]);

$totalPending = count($pendingOrders);
$totalApproved = $pdo->prepare("SELECT COUNT(*) AS c FROM sample_approvals WHERE reviewed_by=? AND status='approved'");
$totalApproved->execute([$user_id]);
$totalApproved = $totalApproved->fetch()['c'];
$totalRejected = $pdo->prepare("SELECT COUNT(*) AS c FROM sample_approvals WHERE reviewed_by=? AND status='rejected'");
$totalRejected->execute([$user_id]);
$totalRejected = $totalRejected->fetch()['c'];

$pageTitle = 'Sample Review';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sample Review — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="sample-review-css">
    .review-card { background:var(--surface);border-radius:var(--radius-lg);border:1px solid var(--border);overflow:hidden;margin-bottom:20px;transition:box-shadow .2s ease }
    .review-card:hover { box-shadow:var(--shadow-md) }
    .review-card.pending { border-left:4px solid var(--role-accent) }
    .review-card .review-body { padding:24px }
    .review-card .review-header { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px;gap:12px }
    .review-card .review-title { font-size:1rem;font-weight:700;color:var(--text-primary) }
    .review-card .review-meta { font-size:.78rem;color:var(--text-tertiary);margin-bottom:14px;display:flex;gap:12px;flex-wrap:wrap }
    .review-card .review-notes { font-size:.82rem;color:var(--text-secondary);background:var(--surface-secondary);padding:14px 18px;border-radius:var(--radius-sm);margin-bottom:16px;line-height:1.6 }
    .review-card .review-notes strong { color:var(--text-primary) }
    .review-card .review-actions { display:flex;gap:10px;margin-top:18px;padding-top:18px;border-top:1px solid var(--border-light) }
    .review-card .review-actions .dash-btn { flex:1;justify-content:center;padding:12px 20px;font-size:.85rem }
    .review-card .photo-gallery { display:flex;gap:8px;overflow-x:auto;padding:16px 24px 10px;background:var(--surface-secondary);scrollbar-width:thin;border-bottom:1px solid var(--border-light) }
    .review-card .photo-gallery img { width:100px;height:100px;object-fit:cover;border-radius:var(--radius-sm);border:1px solid var(--border);cursor:pointer;transition:transform .15s ease;flex-shrink:0 }
    .review-card .photo-gallery img:hover { transform:scale(1.06);box-shadow:var(--shadow-md) }
    .review-card .photo-gallery .no-photos { font-size:.75rem;color:var(--text-tertiary);padding:8px 0 }
    .reject-card textarea { width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.82rem;font-family:inherit;outline:none;transition:.2s;background:var(--surface-secondary);color:var(--text-primary);resize:vertical }
    .reject-card textarea:focus { border-color:var(--role-accent);box-shadow:0 0 0 3px var(--role-accent-soft) }
    .approve-modal-feedback { margin-top:12px }
    .approve-modal-feedback textarea { width:100%;padding:10px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.82rem;font-family:inherit;outline:none;transition:.2s;background:var(--surface-secondary);color:var(--text-primary);resize:vertical }
    .approve-modal-feedback textarea:focus { border-color:var(--role-accent);box-shadow:0 0 0 3px var(--role-accent-soft) }
  </style>
</head>
<body data-role="customer">
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/customer.php'; ?>
  <div class="dash-main">
<?php
$alertHtml = '';
if ($msg):
  $isError = stripos($msg, 'Error') !== false || stripos($msg, 'rejected') !== false;
  $alertHtml = '<div style="background:' . ($isError ? 'rgba(239,68,68,0.08)' : 'rgba(34,197,94,0.08)') . ';border:1px solid ' . ($isError ? 'rgba(239,68,68,0.2)' : 'rgba(34,197,94,0.2)') . ';color:' . ($isError ? 'var(--color-danger)' : 'var(--color-success)') . ';border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:8px;font-size:.85rem"><i class="fas ' . ($isError ? 'fa-exclamation-circle' : 'fa-check-circle') . '"></i> ' . htmlspecialchars($msg) . '</div>';
endif;

$kpiRow = renderKPIRow([
  ['icon' => 'fas fa-hourglass-half', 'label' => 'Awaiting Review', 'value' => $totalPending, 'accent' => 'amber'],
  ['icon' => 'fas fa-check-circle', 'label' => 'Approved', 'value' => $totalApproved, 'accent' => 'green'],
  ['icon' => 'fas fa-times-circle', 'label' => 'Rejected', 'value' => $totalRejected, 'accent' => 'red'],
]);

$pendingHtml = '';
if ($totalPending === 0):
  $pendingHtml = '<div class="panel-card" style="padding:32px;text-align:center;margin-bottom:20px">' . renderEmptyState('fas fa-shirt', 'No Samples to Review', 'When a sample is ready for your review, it will appear here with photos and production notes.') . '</div>';
else:
  ob_start();
  foreach ($pendingOrders as $oid => $r):
    $orderPhotos = $photosByOrder[$oid] ?? [];
?>
<div class="review-card pending">
  <?php if (!empty($orderPhotos)): ?>
  <div class="photo-gallery">
    <?php foreach ($orderPhotos as $p): ?>
    <img src="<?= htmlspecialchars($p['file_path']) ?>" alt="Sample photo" onclick="openPhotoModal(this.src)">
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="review-body">
    <div class="review-header">
      <div>
        <div class="review-title"><?= htmlspecialchars($r['product_type'] ?? 'Sample Garment') ?></div>
        <div style="font-size:.78rem;color:var(--text-tertiary);margin-top:2px">Order #ORD-<?= $r['order_id'] ?></div>
      </div>
      <?php if ($r['submitted_at']): ?>
      <span style="font-size:.72rem;color:var(--text-tertiary);white-space:nowrap;flex-shrink:0"><i class="far fa-clock"></i> <?= date('M d, g:i A', strtotime($r['submitted_at'])) ?></span>
      <?php endif; ?>
    </div>
    <?php if ($r['notes']): ?>
    <div class="review-notes"><strong>Production Notes:</strong><br><?= nl2br(htmlspecialchars($r['notes'])) ?></div>
    <?php endif; ?>
    <div class="review-actions">
      <button class="dash-btn dash-btn-accent" onclick="openApproveModal(<?= $r['order_id'] ?>)"><i class="fas fa-check"></i> Approve</button>
      <button class="dash-btn dash-btn-danger" onclick="openRejectForm(<?= $r['order_id'] ?>)"><i class="fas fa-times"></i> Reject</button>
    </div>
    <div class="reject-card" id="rejectForm-<?= $r['order_id'] ?>" style="display:none;margin-top:16px">
      <form method="post">
        <input type="hidden" name="order_id" value="<?= $r['order_id'] ?>">
        <input type="hidden" name="action" value="rejected">
        <div style="margin-bottom:12px">
          <label style="font-size:.78rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px">Feedback <span style="font-weight:400;color:var(--text-tertiary)">(required)</span></label>
          <textarea name="feedback" rows="2" placeholder="What needs to change?" required></textarea>
        </div>
        <div style="margin-bottom:12px">
          <label style="font-size:.78rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px">Required Corrections <span style="font-weight:400;color:var(--text-tertiary)">(optional)</span></label>
          <textarea name="corrections" rows="2" placeholder="Specific measurements or changes needed..."></textarea>
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" name="review_sample" value="1" class="dash-btn dash-btn-danger">Submit Rejection</button>
          <button type="button" class="dash-btn dash-btn-outline" onclick="closeRejectForm(<?= $r['order_id'] ?>)">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php
  endforeach;
  $pendingHtml = ob_get_clean();
endif;

$historyHtml = '';
if ($reviewHistory->rowCount() > 0):
  $cols = [
    ['field' => 'order_link', 'label' => 'Order #', 'safeHtml' => true],
    ['field' => 'product', 'label' => 'Product'],
    ['field' => 'status', 'label' => 'Status', 'safeHtml' => true],
    ['field' => 'date', 'label' => 'Reviewed'],
  ];
  $dataRows = [];
  foreach ($reviewHistory as $h):
    $hVariant = $h['status'] === 'approved' ? 'success' : 'danger';
    $dataRows[] = [
      'order_link' => '<a href="view_order.php?id=' . $h['order_id'] . '" style="color:var(--role-accent);text-decoration:none;font-weight:600">#ORD-' . $h['order_id'] . '</a>',
      'product' => htmlspecialchars($h['product_type'] ?? 'Garment'),
      'status' => renderStatusBadge(htmlspecialchars($h['status']), $hVariant, 'sm'),
      'date' => date('M d, Y', strtotime($h['reviewed_at'])),
    ];
  endforeach;
  $historyHtml = renderPageSection('Your Review History', renderDataTable('review-history', $cols, $dataRows), 'fas fa-history');
endif;

$mainWorkspace = $alertHtml . $kpiRow . $pendingHtml . $historyHtml;

echo renderDashboardShell(
  renderPageHeader('Sample Review', 'Review sample garments and approve or request changes before bulk production begins.'),
  '',
  $mainWorkspace
);
?>
    </div>
  </div>
</div>

<!-- Approve Confirmation Modal -->
<div class="modern-modal-overlay" id="approveModal" style="display:none" onclick="if(event.target===this)closeApproveModal()">
  <div class="modern-modal" style="max-width:460px">
    <form method="post">
      <input type="hidden" name="order_id" id="approveOrderId">
      <input type="hidden" name="action" value="approved">
      <h3 style="margin:0 0 6px;font-size:1.05rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-check-circle" style="color:#16a34a"></i> Approve Sample?</h3>
      <p style="font-size:.85rem;color:var(--text-secondary);margin:0 0 16px">Approve this sample and move it to bulk production?</p>
      <div class="approve-modal-feedback">
        <label style="font-size:.78rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:4px">Add a note <span style="font-weight:400;color:var(--text-tertiary)">(optional)</span></label>
        <textarea name="feedback" rows="2" placeholder="Any comments about the sample..."></textarea>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:18px;padding-top:16px;border-top:1px solid var(--border-light)">
        <button type="button" class="dash-btn dash-btn-outline" onclick="closeApproveModal()">Cancel</button>
        <button type="submit" name="review_sample" value="1" class="dash-btn dash-btn-accent"><i class="fas fa-check"></i> Approve Sample</button>
      </div>
    </form>
  </div>
</div>

<!-- Photo Preview Modal -->
<div class="photo-modal" id="photoModal" onclick="if(event.target===this)closePhotoModal()">
  <button class="photo-modal-close" onclick="closePhotoModal()">&times;</button>
  <img id="photoModalImg" src="" alt="Sample photo">
</div>

<style>
.photo-modal { display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.85);z-index:9999;align-items:center;justify-content:center;cursor:pointer }
.photo-modal img { max-width:90vw;max-height:90vh;border-radius:8px;object-fit:contain }
.photo-modal-close { position:absolute;top:16px;right:24px;font-size:2.2rem;color:#fff;background:none;border:none;cursor:pointer;opacity:.7;transition:opacity .2s }
.photo-modal-close:hover { opacity:1 }
</style>

<script>
function openApproveModal(orderId) {
  document.getElementById('approveOrderId').value = orderId;
  document.getElementById('approveModal').style.display = 'flex';
}
function closeApproveModal() {
  document.getElementById('approveModal').style.display = 'none';
}
function openRejectForm(orderId) {
  const form = document.getElementById('rejectForm-' + orderId);
  if (form) form.style.display = 'block';
}
function closeRejectForm(orderId) {
  const form = document.getElementById('rejectForm-' + orderId);
  if (form) form.style.display = 'none';
}
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
</body>
</html>
