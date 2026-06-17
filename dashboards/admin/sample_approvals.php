<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/role_admin_only.php';

$user_id = $_SESSION['user_id'];
$msg = '';

// Mark sample as submitted for approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sample'])) {
    $order_id = (int)$_POST['order_id'];
    $notes = $_POST['notes'] ?? '';
    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE order_workflow SET sample_status='submitted', sample_submitted_at=NOW(), stage=? WHERE order_id=?")
            ->execute([STAGE_SAMPLE_REVIEW, $order_id]);
        $pdo->prepare("INSERT INTO sample_approvals (order_id, submitted_by, status, notes) VALUES (?, ?, 'pending', ?)")
            ->execute([$order_id, $user_id, $notes]);
        $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, 'Sample submitted for customer approval', 'handoff')")
            ->execute([$order_id, $user_id]);
        $pdo->commit();
        $msg = 'Sample submitted for approval';
    } catch (Exception $e) { $pdo->rollBack(); $msg = 'Error: ' . $e->getMessage(); }
}

// Mark sample as not required
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_sample'])) {
    $order_id = (int)$_POST['order_id'];
    $pdo->prepare("UPDATE order_workflow SET sample_status='not_required', stage=? WHERE order_id=?")
        ->execute([STAGE_BULK_PRODUCTION, $order_id]);
    $pdo->prepare("INSERT INTO production_notes (order_id, author_id, content, note_type) VALUES (?, ?, 'Sample skipped — moving to bulk production', 'general')")
        ->execute([$order_id, $user_id]);
    $msg = 'Sample skipped, order moved to bulk production';
}

$pendingSamples = $pdo->query("
    SELECT o.order_id, o.order_date, u.full_name AS customer_name,
           ow.sample_status, ow.product_type,
           ow.expected_completion, ow.priority,
           sa.approval_id, sa.notes AS sample_notes, sa.submitted_at
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN sample_approvals sa ON o.order_id = sa.order_id AND sa.status = 'pending'
    WHERE ow.sample_status IN ('pending','submitted')
    ORDER BY sa.submitted_at DESC
");

$sampleHistory = $pdo->query("
    SELECT sa.*, o.order_id, u.full_name AS customer_name,
           sub.full_name AS submitted_by_name
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sample Approvals — Sakuragi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
</head>
<body>
  <div class="dash-layout">
    <aside class="sidebar-modern" id="sidebar">
      <div class="sidebar-brand"><svg viewBox="0 0 28 28" fill="none" style="width:24px;height:24px"><rect width="28" height="28" rx="6" fill="#1e3a5f"/><path d="M7 10h14l-3 8H10L7 10z" fill="#fff" opacity=".9"/></svg><span>Sakuragi</span></div>
      <nav class="sidebar-nav">
        <div class="section-label">Main</div>
        <a href="dashboard.php" class="sidebar-item"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="production_board.php" class="sidebar-item"><i class="fas fa-columns"></i> Production</a>
        <a href="sample_approvals.php" class="sidebar-item active"><i class="fas fa-flask"></i> Sample Approvals</a>
        <a href="orders.php" class="sidebar-item"><i class="fas fa-shopping-bag"></i> Orders</a>
        <a href="employees.php" class="sidebar-item"><i class="fas fa-users"></i> Employees</a>
        <div class="section-label">Operations</div>
        <a href="quality_control.php" class="sidebar-item"><i class="fas fa-clipboard-check"></i> Quality Control</a>
        <a href="inventory.php" class="sidebar-item"><i class="fas fa-box"></i> Inventory</a>
        <div class="sidebar-footer"><a href="/auth/logout.php" class="sidebar-item" style="color:var(--accent-red)"><i class="fas fa-sign-out-alt"></i> Sign Out</a></div>
      </nav>
    </aside>
    <div class="dash-main">
      <header class="top-nav">
        <div class="top-nav-left">
          <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
          <div style="font-size:.9rem;font-weight:600;color:var(--text-secondary)">Sample Approvals</div>
        </div>
        <div class="top-nav-right"><div class="avatar"><?= htmlspecialchars(substr($_SESSION['full_name']??'A',0,2)) ?></div></div>
      </header>
      <div class="dash-content">
        <div class="page-header">
          <h1>Sample Approval Workflow</h1>
          <p>Submit samples for customer approval before bulk production begins</p>
        </div>

        <?php if ($msg): ?>
        <div class="panel-card" style="margin-bottom:16px;padding:12px 20px;background:<?= strpos($msg,'Error')!==false?'#fee2e2':'#d1fae5' ?>">
          <p style="margin:0;font-size:.85rem;color:<?= strpos($msg,'Error')!==false?'#991b1b':'#065f46' ?>"><?= htmlspecialchars($msg) ?></p>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="panel-card" style="margin-bottom:20px">
          <h3><i class="fas fa-paper-plane" style="color:var(--accent-blue)"></i> Submit Sample for Approval</h3>
          <p style="font-size:.8rem;color:var(--text-secondary);margin-bottom:12px">Orders in Design Review / Material Prep that need a sample first article</p>
          <?php if ($approvableOrders->rowCount() === 0): ?>
          <p style="font-size:.85rem;color:var(--text-tertiary);text-align:center;padding:12px 0">No orders ready for sample submission</p>
          <?php else: ?>
          <div style="display:flex;flex-direction:column;gap:8px">
            <?php foreach ($approvableOrders as $ao): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;background:#f8fafc;border-radius:8px;border:1px solid var(--border-light)">
              <div><strong>#ORD-<?= $ao['order_id'] ?></strong> — <?= htmlspecialchars($ao['customer_name']) ?> · <?= htmlspecialchars($ao['product_type'] ?? 'Garment') ?></div>
              <div style="display:flex;gap:6px">
                <form method="post" style="display:inline">
                  <input type="hidden" name="order_id" value="<?= $ao['order_id'] ?>">
                  <button type="submit" name="skip_sample" class="dash-btn dash-btn-outline dash-btn-sm" onclick="return confirm('Skip sample for this order?')">Skip Sample</button>
                </form>
                <button class="dash-btn dash-btn-primary dash-btn-sm" onclick="openSubmit(<?= $ao['order_id'] ?>)"><i class="fas fa-paper-plane"></i> Submit Sample</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>

        <div class="dash-two-col">
          <div>
            <!-- Pending Customer Review -->
            <div class="panel-card">
              <h3><i class="fas fa-hourglass-half" style="color:var(--accent-amber)"></i> Awaiting Customer Approval</h3>
              <?php if ($pendingSamples->rowCount() === 0): ?>
              <p style="font-size:.85rem;color:var(--text-tertiary);text-align:center;padding:24px 0">No samples pending customer review</p>
              <?php else: foreach ($pendingSamples as $s): ?>
              <div class="task-card" style="margin-bottom:8px">
                <div style="display:flex;justify-content:space-between;align-items:start">
                  <div>
                    <span style="font-size:.85rem;font-weight:700">#ORD-<?= $s['order_id'] ?></span>
                    <span class="qc-status pending" style="margin-left:8px;font-size:.7rem">Pending</span>
                    <p style="font-size:.8rem;color:var(--text-secondary);margin:4px 0 0"><?= htmlspecialchars($s['customer_name']) ?> · <?= htmlspecialchars($s['product_type'] ?? 'Garment') ?></p>
                    <?php if ($s['sample_notes']): ?><p style="font-size:.75rem;color:var(--text-tertiary);margin-top:4px">Notes: <?= htmlspecialchars($s['sample_notes']) ?></p><?php endif; ?>
                  </div>
                  <span style="font-size:.7rem;color:var(--text-tertiary)">Submitted <?= $s['submitted_at'] ? date('M d, g:i A', strtotime($s['submitted_at'])) : '—' ?></span>
                </div>
              </div>
              <?php endforeach; endif; ?>
            </div>

            <!-- History -->
            <div class="panel-card" style="margin-top:16px">
              <h3><i class="fas fa-history" style="color:var(--accent-purple)"></i> Approval History</h3>
              <?php if ($sampleHistory->rowCount() === 0): ?>
              <p style="font-size:.85rem;color:var(--text-tertiary);text-align:center;padding:16px 0">No completed reviews</p>
              <?php else: ?>
              <div style="display:flex;flex-direction:column;gap:6px;font-size:.8rem">
                <?php foreach ($sampleHistory as $h): ?>
                <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border-light)">
                  <div><strong>#ORD-<?= $h['order_id'] ?></strong> <span class="qc-status <?= strtolower($h['status']) ?>"><?= $h['status'] ?></span></div>
                  <div style="color:var(--text-tertiary)">by <?= htmlspecialchars($h['customer_name'] ?? 'N/A') ?> · <?= date('M d', strtotime($h['reviewed_at'])) ?></div>
                </div>
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Instructions -->
          <div class="side-panel">
            <div class="panel-card">
              <h3><i class="fas fa-info-circle" style="color:var(--accent-blue)"></i> How It Works</h3>
              <div style="font-size:.8rem;color:var(--text-secondary);line-height:1.6">
                <p style="margin-bottom:12px"><strong>1.</strong> After design review, produce a sample garment.</p>
                <p style="margin-bottom:12px"><strong>2.</strong> Submit the sample here for customer approval.</p>
                <p style="margin-bottom:12px"><strong>3.</strong> Customer reviews the sample online.</p>
                <p style="margin-bottom:12px"><strong>4.</strong> <span style="color:var(--accent-emerald);font-weight:600">Approved</span> → bulk production begins.</p>
                <p style="margin-bottom:0"><strong>5.</strong> <span style="color:var(--accent-red);font-weight:600">Rejected</span> → rework sample based on feedback.</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Submit Modal -->
  <div class="modern-modal-overlay" id="submitModal" style="display:none" onclick="if(event.target===this)closeSubmit()">
    <div class="modern-modal">
      <form method="post">
        <input type="hidden" name="order_id" id="submitOrderId">
        <h3>Submit Sample for Approval</h3>
        <div class="form-group">
          <label>Production Notes</label>
          <textarea name="notes" rows="3" placeholder="Fabric used, measurements, any notes for the customer..."></textarea>
        </div>
        <div class="modal-footer">
          <button type="button" class="dash-btn dash-btn-outline" onclick="closeSubmit()">Cancel</button>
          <button type="submit" name="submit_sample" class="dash-btn dash-btn-primary"><i class="fas fa-paper-plane"></i> Submit Sample</button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function openSubmit(id) { document.getElementById('submitOrderId').value = id; document.getElementById('submitModal').style.display = 'flex'; }
  function closeSubmit() { document.getElementById('submitModal').style.display = 'none'; }
  document.getElementById('menuToggle')?.addEventListener('click', function() { document.getElementById('sidebar')?.classList.toggle('collapsed'); });
  </script>
</body>
</html>
