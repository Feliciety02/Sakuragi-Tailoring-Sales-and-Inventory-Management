<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../config/db_connect.php';
require_once '../../../../middleware/auth_required.php';

$user_id = $_SESSION['user_id'];

$pos = $pdo->prepare("SELECT p.position_name FROM employees e JOIN positions p ON e.position_id = p.position_id WHERE e.user_id = ?");
$pos->execute([$user_id]);
$posName = $pos->fetchColumn();
if ($posName !== 'Quality Control Inspector') {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

require_once '../../../../includes/header.php';
require_once '../../../../includes/sidebar_qc_inspector.php';

$order_id = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$message = '';

// Handle QC submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_qc'])) {
    $result = $_POST['result'] ?? 'Failed';
    $feedback = $_POST['feedback'] ?? '';
    $failure_reason = $_POST['failure_reason'] ?? '';
    $corrections = $_POST['required_corrections'] ?? '';

    $checklist = [
        'design_accuracy' => (int)($_POST['design_accuracy'] ?? 0),
        'print_alignment' => (int)($_POST['print_alignment'] ?? 0),
        'embroidery_quality' => (int)($_POST['embroidery_quality'] ?? 0),
        'stitching_quality' => (int)($_POST['stitching_quality'] ?? 0),
        'size_accuracy' => (int)($_POST['size_accuracy'] ?? 0),
        'fabric_condition' => (int)($_POST['fabric_condition'] ?? 0),
        'cleanliness' => (int)($_POST['cleanliness'] ?? 0),
        'packaging_readiness' => (int)($_POST['packaging_readiness'] ?? 0),
    ];

    try {
        $pdo->beginTransaction();

        $ins = $pdo->prepare("SELECT inspection_id FROM qc_inspections WHERE order_id = ?");
        $ins->execute([$order_id]);
        $existing = $ins->fetch();

        if ($existing) {
            $sql = "UPDATE qc_inspections SET result=?, inspector_id=?, inspected_at=NOW(), feedback=?, failure_reason=?, required_corrections=?, design_accuracy=?, print_alignment=?, embroidery_quality=?, stitching_quality=?, size_accuracy=?, fabric_condition=?, cleanliness=?, packaging_readiness=? WHERE order_id=?";
            $pdo->prepare($sql)->execute([$result, $user_id, $feedback, $failure_reason, $corrections, $checklist['design_accuracy'], $checklist['print_alignment'], $checklist['embroidery_quality'], $checklist['stitching_quality'], $checklist['size_accuracy'], $checklist['fabric_condition'], $checklist['cleanliness'], $checklist['packaging_readiness'], $order_id]);
        } else {
            $checklist['result'] = $result;
            $checklist['inspector_id'] = $user_id;
            $checklist['inspected_at'] = date('Y-m-d H:i:s');
            $checklist['feedback'] = $feedback;
            $checklist['failure_reason'] = $failure_reason;
            $checklist['required_corrections'] = $corrections;
            $checklist['order_id'] = $order_id;
            $cols = implode(',', array_keys($checklist));
            $vals = implode(',', array_fill(0, count($checklist), '?'));
            $pdo->prepare("INSERT INTO qc_inspections ({$cols}) VALUES ({$vals})")->execute(array_values($checklist));
        }

        if ($result === 'Passed') {
            $pdo->prepare("UPDATE order_workflow SET stage='Packaging', completed_at=NOW() WHERE order_id=?")->execute([$order_id]);
            $pdo->prepare("INSERT INTO garment_log (order_id, order_detail_id, from_stage, to_stage, employee_id, notes) SELECT ?, detail_id, 'Quality Inspection', 'Packaging', ?, 'QC Passed' FROM order_details WHERE order_id=?")->execute([$order_id, $user_id, $order_id]);
            $pdo->prepare("UPDATE garment_tracking SET stage='Packaging', employee_id=?, updated_at=NOW() WHERE order_id=?")->execute([$user_id, $order_id]);
        } else {
            $pdo->prepare("INSERT INTO rework_log (order_id, triggered_by, from_stage, to_stage, reason, notes) VALUES (?, ?, 'Quality Inspection', 'Rework', ?, ?)")->execute([$order_id, $user_id, $failure_reason, $corrections]);
            $pdo->prepare("UPDATE order_workflow SET stage='Rework' WHERE order_id=?")->execute([$order_id]);
            $pdo->prepare("INSERT INTO garment_log (order_id, order_detail_id, from_stage, to_stage, employee_id, notes) SELECT ?, detail_id, 'Quality Inspection', 'Rework', ?, ? FROM order_details WHERE order_id=?")->execute([$order_id, $user_id, $failure_reason ?: 'QC Failed', $order_id]);
            $pdo->prepare("UPDATE garment_tracking SET stage='Rework', employee_id=?, updated_at=NOW() WHERE order_id=?")->execute([$user_id, $order_id]);
        }

        $pdo->commit();
        $message = 'QC inspection submitted successfully';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error: ' . $e->getMessage();
    }
}

// Fetch order for inspection
$order = $pdo->prepare("
    SELECT o.*, ow.stage, ow.product_type, ow.priority, ow.assigned_employee,
           u.full_name AS customer_name,
           e.full_name AS employee_name,
           qc.result AS qc_result
    FROM orders o
    JOIN order_workflow ow ON o.order_id = ow.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN users e ON ow.assigned_employee = e.user_id
    LEFT JOIN qc_inspections qc ON o.order_id = qc.order_id
    WHERE o.order_id = ?
");
$order->execute([$order_id]);
$ord = $order->fetch();

if (!$ord) {
    echo "<div class='main-content'><p style='padding:24px;color:#6b7280'>Order not found. <a href='dashboard.php'>Back to dashboard</a></p></div>";
    require_once '../../../../includes/footer.php';
    exit();
}
?>
<link rel="stylesheet" href="/public/assets/css/mes.css">
<style>
  body { background: #f5f5f5; }
  .main-content { margin-left: 220px; padding: 24px 32px; background: #f5f5f5; min-height: 100vh; }
  @media (max-width: 768px) { .main-content { margin-left: 0; padding: 16px; } }
</style>

<div class="main-content">
  <div class="d-flex align-items-center gap-2 mb-3" style="font-size:12px;color:#6b7280">
    <a href="dashboard.php" style="color:var(--mes-primary)">QC Dashboard</a>
    <span>/</span>
    <span style="color:#374151">Inspect #ORD-<?= $order_id ?></span>
  </div>

  <?php if ($message): ?>
  <div class="mes-card mb-3" style="padding:12px 20px;background:<?= strpos($message,'Error')!==false ? '#fef2f2' : '#d1fae5' ?>;border-color:<?= strpos($message,'Error')!==false ? '#fecaca' : '#a7f3d0' ?>">
    <p style="margin:0;font-size:13px;color:<?= strpos($message,'Error')!==false ? '#991b1b' : '#065f46' ?>"><?= htmlspecialchars($message) ?></p>
  </div>
  <?php endif; ?>

  <?php if ($ord['qc_result'] && $ord['qc_result'] !== 'Pending'): ?>
  <div class="mes-card mb-3" style="padding:12px 20px;background:#fef3c7;border-color:#fde68a">
    <p style="margin:0;font-size:13px;color:#92400e">This order was already inspected — result: <strong><?= $ord['qc_result'] ?></strong>. Submitting again will overwrite.</p>
  </div>
  <?php endif; ?>

  <div class="mes-layout">
    <div class="mes-main">
      <div class="mes-card mb-3">
        <div class="mes-card-body" style="padding:20px 24px">
          <h2 style="font-size:18px;font-weight:700;margin:0">#ORD-<?= $ord['order_id'] ?> — <?= htmlspecialchars($ord['product_type'] ?? 'Garment') ?></h2>
          <p style="font-size:13px;color:#6b7280;margin:4px 0 0">
            <?= htmlspecialchars($ord['customer_name']) ?> · 
            Assigned: <?= htmlspecialchars($ord['employee_name'] ?? 'Unassigned') ?> · 
            Stage: <?= htmlspecialchars($ord['stage']) ?>
          </p>
        </div>
      </div>

      <div class="mes-card">
        <div class="mes-card-header"><h3 class="mes-card-title">QC Inspection Form</h3></div>
        <div class="mes-card-body">
          <form method="post">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">

            <div class="mes-form-group">
              <label class="mes-form-label">Result</label>
              <select name="result" class="mes-form-select" id="resultSelect" required>
                <option value="Passed">Passed</option>
                <option value="Failed">Failed</option>
              </select>
            </div>

            <div class="mes-form-group">
              <label class="mes-form-label">Inspection Checklist</label>
              <div class="mes-checklist">
                <?php $items = ['design_accuracy'=>'Design Accuracy','print_alignment'=>'Print / Embroidery Alignment','embroidery_quality'=>'Embroidery Quality','stitching_quality'=>'Stitching Quality','size_accuracy'=>'Size Accuracy','fabric_condition'=>'Fabric Condition','cleanliness'=>'Cleanliness','packaging_readiness'=>'Packaging Readiness'];
                foreach ($items as $key => $label): ?>
                <label class="mes-checklist-item">
                  <input type="checkbox" name="<?= $key ?>" value="1">
                  <span><?= $label ?></span>
                </label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="mes-form-group">
              <label class="mes-form-label">Feedback</label>
              <textarea name="feedback" class="mes-form-textarea" rows="2" placeholder="Overall feedback..."></textarea>
            </div>

            <div class="mes-form-group" id="failureFields" style="display:none">
              <label class="mes-form-label">Failure Reason</label>
              <input type="text" name="failure_reason" class="mes-form-input" placeholder="e.g. Stitching issue">
              <label class="mes-form-label mt-2">Required Corrections</label>
              <textarea name="required_corrections" class="mes-form-textarea" rows="2" placeholder="What needs to be fixed?"></textarea>
            </div>

            <button type="submit" name="submit_qc" class="mes-btn mes-btn-primary mt-2"><i class="fas fa-check"></i> Submit Inspection</button>
          </form>
        </div>
      </div>
    </div>

    <div class="mes-sidebar-right">
      <div class="mes-card mb-3">
        <div class="mes-card-header"><h3 class="mes-card-title">Checklist Items</h3></div>
        <div class="mes-card-body" style="font-size:13px">
          <p style="margin-bottom:8px;color:#6b7280">Check each item that passes inspection:</p>
          <div style="display:flex;flex-direction:column;gap:6px">
            <div>1. Design Accuracy</div>
            <div>2. Print / Embroidery Alignment</div>
            <div>3. Embroidery Quality</div>
            <div>4. Stitching Quality</div>
            <div>5. Size Accuracy</div>
            <div>6. Fabric Condition</div>
            <div>7. Cleanliness</div>
            <div>8. Packaging Readiness</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('resultSelect')?.addEventListener('change', function() {
  document.getElementById('failureFields').style.display = this.value === 'Failed' ? 'block' : 'none';
});
</script>

<?php require_once '../../../../includes/footer.php'; ?>
