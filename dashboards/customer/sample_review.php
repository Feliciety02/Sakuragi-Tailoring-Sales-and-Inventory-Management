<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/auth_required.php';

if (get_user_role() !== ROLE_CUSTOMER) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$msg = '';

// Handle approval/rejection
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

$reviewHistory = $pdo->prepare("
    SELECT sa.*, o.order_id, ow.product_type
    FROM sample_approvals sa
    JOIN orders o ON sa.order_id = o.order_id
    JOIN order_workflow ow ON o.order_id = ow.order_id
    WHERE sa.reviewed_by = ? AND sa.status IN ('approved','rejected')
    ORDER BY sa.reviewed_at DESC LIMIT 20
");
$reviewHistory->execute([$user_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sample Review — Sakuragi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:'Inter',sans-serif; background:#f8fafc; color:#0f172a; -webkit-font-smoothing:antialiased; }
    .container { max-width:800px; margin:0 auto; padding:24px; }
    .back-link { display:inline-flex; align-items:center; gap:6px; color:#94a3b8; font-size:.85rem; font-weight:500; text-decoration:none; margin-bottom:24px; }
    .back-link:hover { color:#475569; }
    h1 { font-size:1.5rem; font-weight:800; letter-spacing:-.02em; margin-bottom:4px; }
    .subtitle { font-size:.9rem; color:#64748b; margin-bottom:28px; }
    .msg-card { padding:12px 16px; border-radius:10px; font-size:.85rem; margin-bottom:20px; display:flex; align-items:center; gap:8px; }
    .msg-success { background:#d1fae5; border:1px solid #a7f3d0; color:#065f46; }
    .msg-error { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
    .review-card { background:#fff; border-radius:12px; border:1px solid #e2e8f0; padding:24px; margin-bottom:16px; }
    .review-card h3 { font-size:1rem; font-weight:700; margin-bottom:4px; }
    .review-card .meta { font-size:.8rem; color:#64748b; margin-bottom:16px; }
    .review-card .notes { font-size:.85rem; color:#475569; background:#f8fafc; padding:12px; border-radius:8px; margin-bottom:16px; }
    .form-group { margin-bottom:16px; }
    .form-group label { display:block; font-size:.8rem; font-weight:600; color:#475569; margin-bottom:6px; }
    .form-group textarea, .form-group input { width:100%; padding:10px 12px; border:1.5px solid #e2e8f0; border-radius:8px; font-size:.85rem; font-family:inherit; outline:none; transition:.2s; }
    .form-group textarea:focus, .form-group input:focus { border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .btn-row { display:flex; gap:8px; margin-top:16px; }
    .btn { padding:10px 24px; border-radius:8px; font-size:.85rem; font-weight:600; font-family:inherit; border:none; cursor:pointer; transition:.15s; display:inline-flex; align-items:center; gap:6px; }
    .btn-approve { background:#059669; color:#fff; }
    .btn-approve:hover { background:#10b981; transform:translateY(-1px); box-shadow:0 4px 12px rgba(5,150,105,.25); }
    .btn-reject { background:#dc2626; color:#fff; }
    .btn-reject:hover { background:#ef4444; transform:translateY(-1px); box-shadow:0 4px 12px rgba(220,38,38,.25); }
    .history-item { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f1f5f9; font-size:.8rem; }
    .badge { font-size:.7rem; font-weight:600; padding:2px 10px; border-radius:100px; }
    .badge-approved { background:#d1fae5; color:#065f46; }
    .badge-rejected { background:#fee2e2; color:#991b1b; }
    .badge-pending { background:#fef3c7; color:#92400e; }
    .empty { text-align:center; padding:32px 0; color:#94a3b8; font-size:.85rem; }
  </style>
</head>
<body>
  <div class="container">
    <a href="dashboard.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to dashboard</a>
    <h1>Sample Review</h1>
    <p class="subtitle">Review and approve samples before bulk production begins</p>

    <?php if ($msg): ?>
    <div class="msg-card <?= strpos($msg,'Error')!==false || strpos($msg,'rejected')!==false ? 'msg-error' : 'msg-success' ?>">
      <i class="fas <?= strpos($msg,'Error')!==false || strpos($msg,'rejected')!==false ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
      <?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>

    <?php if ($pendingReviews->rowCount() === 0): ?>
    <div class="review-card"><div class="empty">No samples awaiting your review</div></div>
    <?php else: foreach ($pendingReviews as $r): ?>
    <div class="review-card">
      <h3>#ORD-<?= $r['order_id'] ?> — <?= htmlspecialchars($r['product_type'] ?? 'Sample Garment') ?></h3>
      <div class="meta">Submitted <?= $r['submitted_at'] ? date('F d, Y g:i A', strtotime($r['submitted_at'])) : '—' ?></div>
      <?php if ($r['notes']): ?>
      <div class="notes"><strong>Production Notes:</strong><br><?= htmlspecialchars($r['notes']) ?></div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="order_id" value="<?= $r['order_id'] ?>">
        <div class="form-group">
          <label>Feedback</label>
          <textarea name="feedback" rows="2" placeholder="Any comments about the sample..."></textarea>
        </div>
        <div class="form-group" id="corrections-group-<?= $r['order_id'] ?>" style="display:none">
          <label>Required Corrections</label>
          <textarea name="corrections" rows="2" placeholder="What needs to be changed?"></textarea>
        </div>
        <div class="btn-row">
          <button type="submit" name="review_sample" value="1" class="btn btn-approve" onclick="this.form.action.value='approved'"><i class="fas fa-check"></i> Approve Sample</button>
          <button type="button" class="btn btn-reject" onclick="document.getElementById('corrections-group-<?= $r['order_id'] ?>').style.display='block'; this.form.action.value='rejected'; this.form.querySelector('[name=review_sample]').click()"><i class="fas fa-times"></i> Reject</button>
        </div>
        <input type="hidden" name="action" value="">
      </form>
    </div>
    <?php endforeach; endif; ?>

    <?php if ($reviewHistory->rowCount() > 0): ?>
    <h3 style="font-size:1rem;font-weight:700;margin:24px 0 12px">Your Review History</h3>
    <?php foreach ($reviewHistory as $h): ?>
    <div class="history-item">
      <div><strong>#ORD-<?= $h['order_id'] ?></strong> <span class="badge badge-<?= $h['status'] ?>"><?= $h['status'] ?></span></div>
      <div style="color:#64748b"><?= date('M d, Y', strtotime($h['reviewed_at'])) ?></div>
    </div>
    <?php endforeach; endif; ?>
  </div>

  <script>
  document.querySelectorAll('.btn-reject').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      var form = this.closest('form');
      form.querySelector('[name="action"]').value = 'rejected';
      form.submit();
    });
  });
  document.querySelectorAll('.btn-approve').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      var form = this.closest('form');
      form.querySelector('[name="action"]').value = 'approved';
    });
  });
  </script>
</body>
</html>
