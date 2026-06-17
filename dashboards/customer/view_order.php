<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/auth_required.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar_customer.php';

if (get_user_role() !== ROLE_CUSTOMER) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: my_orders.php');
    exit();
}

$order_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch order
$stmt = $pdo->prepare("
    SELECT o.*, s.service_name,
           e.full_name AS employee_name,
           ow.stage, ow.expected_completion, ow.product_type, ow.priority
    FROM orders o
    LEFT JOIN services s ON o.service_id = s.service_id
    LEFT JOIN order_workflow ow ON o.order_id = ow.order_id
    LEFT JOIN users e ON ow.assigned_employee = e.user_id
    WHERE o.order_id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: my_orders.php');
    exit();
}

// Items
$items = $pdo->prepare("SELECT * FROM order_details WHERE order_id = ?");
$items->execute([$order_id]);
$orderItems = $items->fetchAll();

// Payment
$payStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ?");
$payStmt->execute([$order_id]);
$payment = $payStmt->fetch();

// Files
$files = $pdo->prepare("SELECT * FROM order_files WHERE order_id = ?");
$files->execute([$order_id]);
$designFiles = $files->fetchAll();

// Production notes
$notes = $pdo->prepare("
    SELECT pn.*, u.full_name AS author_name
    FROM production_notes pn
    LEFT JOIN users u ON pn.author_id = u.user_id
    WHERE pn.order_id = ?
    ORDER BY pn.created_at ASC
");
$notes->execute([$order_id]);
$prodNotes = $notes->fetchAll();

// Map internal stage to customer stage
$customerStage = $CUSTOMER_STAGE_MAP[$order['stage']] ?? 'Processing';
$progress = getStageProgress($order['stage']);

// Customer timeline stages
$customerTimeline = [
    CSTAGE_CONFIRMED,
    CSTAGE_PRODUCTION,
    CSTAGE_QUALITY,
    CSTAGE_PACKAGING_C,
    CSTAGE_READY,
];
$currentCustomerIdx = array_search($customerStage, $customerTimeline);
if ($customerStage === CSTAGE_DONE) $currentCustomerIdx = 5;
?>
<link rel="stylesheet" href="/public/assets/css/mes.css">
<style>
  body { background: #f5f5f5; }
  .main-content { margin-left: 220px; padding: 24px 32px; background: #f5f5f5; }
  .timeline-step { display:flex;flex-direction:column;align-items:center;position:relative;flex:1 }
  .timeline-step:not(:last-child)::after { content:'';position:absolute;top:20px;left:55%;width:90%;height:3px;background:#e5e7eb;z-index:0 }
  .timeline-step.completed:not(:last-child)::after { background:#10b981 }
  .timeline-step.active:not(:last-child)::after { background:linear-gradient(90deg,#10b981 50%,#e5e7eb 50%) }
  .timeline-dot { width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:16px;position:relative;z-index:1;border:3px solid #e5e7eb;background:#fff;color:#9ca3af;transition:all .3s }
  .timeline-step.completed .timeline-dot { background:#10b981;border-color:#10b981;color:#fff }
  .timeline-step.active .timeline-dot { border-color:var(--mes-primary);color:var(--mes-primary) }
  .timeline-label { font-size:11px;text-align:center;margin-top:6px;color:#9ca3af;font-weight:500;max-width:80px }
  .timeline-step.completed .timeline-label { color:#10b981 }
  .timeline-step.active .timeline-label { color:var(--mes-primary);font-weight:600 }
</style>

<div class="main-content">
  <div class="d-flex align-items-center gap-2 mb-3" style="font-size:12px;color:#6b7280">
    <a href="my_orders.php" style="color:var(--mes-primary)">My Orders</a>
    <span>/</span>
    <span style="color:#374151">#ORD-<?= $order_id ?></span>
  </div>

  <!-- Header -->
  <div class="mes-card mb-4">
    <div class="mes-card-body" style="padding:20px 24px">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <h2 style="font-size:18px;font-weight:700;margin:0">Order #ORD-<?= $order_id ?></h2>
          <p style="font-size:13px;color:#6b7280;margin:4px 0 0">Placed <?= date('F d, Y', strtotime($order['order_date'])) ?> · <?= htmlspecialchars($order['service_name'] ?? 'Custom') ?></p>
        </div>
        <div>
          <span class="mes-badge mes-badge-primary" style="font-size:13px"><?= htmlspecialchars($customerStage) ?></span>
        </div>
      </div>

      <!-- Progress -->
      <div style="display:flex;align-items:center;gap:12px;margin-top:16px">
        <div style="flex:1;height:8px;background:#e5e7eb;border-radius:4px;overflow:hidden">
          <div style="width:<?= $progress ?>%;height:100%;background:var(--mes-primary);border-radius:4px;transition:width .5s"></div>
        </div>
        <span style="font-size:13px;font-weight:600;color:var(--mes-primary)"><?= $progress ?>%</span>
      </div>
    </div>
  </div>

  <!-- Customer Timeline -->
  <div class="mes-card mb-4">
    <div class="mes-card-body" style="padding:24px">
      <div style="display:flex;justify-content:space-between;padding:0 10px">
        <?php foreach ($customerTimeline as $i => $stage):
          $completed = $i < $currentCustomerIdx;
          $active = $i === $currentCustomerIdx;
        ?>
        <div class="timeline-step <?= $completed ? 'completed' : ($active ? 'active' : '') ?>">
          <div class="timeline-dot">
            <?php if ($completed): ?><i class="fas fa-check"></i>
            <?php elseif ($active): ?><i class="fas fa-circle"></i>
            <?php else: ?><i class="fas fa-circle"></i><?php endif; ?>
          </div>
          <div class="timeline-label"><?= htmlspecialchars($stage) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="mes-layout">
    <div class="mes-main">
      <!-- Production Updates -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Production Updates</h3></div>
        <div class="mes-card-body">
          <?php if (empty($prodNotes)): ?>
          <p style="font-size:13px;color:#6b7280;margin:0;text-align:center;padding:12px 0">No updates yet. We'll post progress here as your order moves through production.</p>
          <?php else: ?>
          <div class="mes-feed">
            <?php foreach ($prodNotes as $n): ?>
            <div class="mes-feed-item">
              <div class="mes-feed-icon" style="background:#dbeafe;color:#2563eb">
                <i class="fas fa-<?= $n['note_type']==='handoff'?'check-double':'comment' ?>"></i>
              </div>
              <div class="mes-feed-content">
                <p><?= htmlspecialchars($n['content']) ?></p>
                <div class="mes-feed-time"><?= date('M d, g:i A', strtotime($n['created_at'])) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Order Items -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Order Items</h3></div>
        <div class="mes-card-body">
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($orderItems as $item): ?>
            <div style="background:#f3f4f6;border-radius:8px;padding:12px 20px;text-align:center;min-width:80px">
              <div style="font-size:16px;font-weight:700;color:#374151"><?= (int)$item['quantity'] ?></div>
              <div style="font-size:11px;color:#6b7280">Size <?= htmlspecialchars($item['size']) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Design Files -->
      <?php if (!empty($designFiles)): ?>
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Design Files</h3></div>
        <div class="mes-card-body">
          <div style="display:flex;flex-wrap:wrap;gap:8px">
            <?php foreach ($designFiles as $f):
              $path = '/public/uploads/designs/' . $f['file_path'];
              $ext = strtolower(pathinfo($f['file_path'], PATHINFO_EXTENSION));
              $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']);
            ?>
            <a href="<?= $path ?>" target="_blank" style="display:block;width:100px;height:100px;border-radius:8px;overflow:hidden;background:#f3f4f6;border:1px solid #e5e7eb">
              <?php if ($isImg): ?>
              <img src="<?= $path ?>" style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
              <div style="display:flex;align-items:center;justify-content:center;height:100%;font-size:20px;color:#9ca3af"><i class="fas fa-file-alt"></i></div>
              <?php endif; ?>
            </a>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div class="mes-sidebar-right">
      <!-- Order Info -->
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Order Details</h3></div>
        <div class="mes-card-body" style="font-size:13px">
          <p style="margin:0 0 6px"><strong>Status</strong><br><span class="mes-badge mes-badge-primary"><?= htmlspecialchars($customerStage) ?></span></p>
          <p style="margin:0 0 6px"><strong>Total</strong><br>₱<?= number_format($order['total_price'], 2) ?></p>
          <?php if ($order['employee_name']): ?>
          <p style="margin:0 0 6px"><strong>Assigned Staff</strong><br><?= htmlspecialchars($order['employee_name']) ?></p>
          <?php endif; ?>
          <?php if ($order['expected_completion']): ?>
          <p style="margin:0 0 6px"><strong>Expected Completion</strong><br><?= date('F d, Y', strtotime($order['expected_completion'])) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Payment -->
      <?php if ($payment): ?>
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Payment</h3></div>
        <div class="mes-card-body" style="font-size:13px">
          <p style="margin:0 0 6px"><strong>Amount</strong><br>₱<?= number_format($payment['amount'], 2) ?></p>
          <p style="margin:0 0 6px"><strong>Status</strong><br><span class="mes-badge <?= $payment['status']==='Paid'?'mes-badge-success':'mes-badge-warning' ?>"><?= htmlspecialchars($payment['status']) ?></span></p>
          <?php if ($payment['reference_number']): ?>
          <p style="margin:0"><strong>Reference</strong><br><?= htmlspecialchars($payment['reference_number']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
