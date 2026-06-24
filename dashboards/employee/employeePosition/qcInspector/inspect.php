<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../config/db_connect.php';
require_once __DIR__ . '/../../../../config/component_helpers.php';
require_once '../../../../app/Middleware/auth_required.php';

$user_id = $_SESSION['user_id'];

$pos = $pdo->prepare("SELECT p.position_name FROM employees e JOIN positions p ON e.position_id = p.position_id WHERE e.user_id = ?");
$pos->execute([$user_id]);
$posName = $pos->fetchColumn();
if ($posName !== 'Quality Control Inspector') {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$pageTitle = 'Inspect Item';

$order_id = (int)($_GET['order_id'] ?? $_POST['order_id'] ?? 0);
$message = '';

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
            $pdo->prepare("INSERT INTO garment_log (order_id, order_detail_id, from_stage, to_stage, employee_id, notes) SELECT ?, order_detail_id, 'Quality Inspection', 'Packaging', ?, 'QC Passed' FROM order_details WHERE order_id=?")->execute([$order_id, $user_id, $order_id]);
            $pdo->prepare("UPDATE garment_tracking SET stage='Packaging', employee_id=?, updated_at=NOW() WHERE order_id=?")->execute([$user_id, $order_id]);
        } else {
            $pdo->prepare("INSERT INTO rework_log (order_id, triggered_by, from_stage, to_stage, reason, notes) VALUES (?, ?, 'Quality Inspection', 'Rework', ?, ?)")->execute([$order_id, $user_id, $failure_reason, $corrections]);
            $pdo->prepare("UPDATE order_workflow SET stage='Rework' WHERE order_id=?")->execute([$order_id]);
            $pdo->prepare("INSERT INTO garment_log (order_id, order_detail_id, from_stage, to_stage, employee_id, notes) SELECT ?, order_detail_id, 'Quality Inspection', 'Rework', ?, ? FROM order_details WHERE order_id=?")->execute([$order_id, $user_id, $failure_reason ?: 'QC Failed', $order_id]);
            $pdo->prepare("UPDATE garment_tracking SET stage='Rework', employee_id=?, updated_at=NOW() WHERE order_id=?")->execute([$user_id, $order_id]);
        }

        $pdo->commit();
        $message = 'QC inspection submitted successfully';
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = 'Error: ' . $e->getMessage();
    }
}

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
$order_not_found = !$ord;
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inspect Item — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="qc-inspect-styles">
    .inspect-grid { display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start }
    .inspect-main { min-width:0 }
    .inspect-sidebar { display:flex;flex-direction:column;gap:12px }

    .order-header { background:var(--bg-primary);border:1px solid var(--border-color);border-radius:12px;padding:20px 24px;margin-bottom:16px }
    .order-header-top { display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px }
    .order-header-title { font-size:1.15rem;font-weight:700;color:var(--text-primary);margin:0 }
    .order-header-meta { display:flex;flex-wrap:wrap;gap:16px;margin-top:8px;font-size:0.82rem;color:var(--text-tertiary) }
    .order-header-meta span { display:flex;align-items:center;gap:4px }
    .order-header-meta i { width:14px;text-align:center;font-size:0.75rem }

    .qc-card { background:var(--bg-primary);border:1px solid var(--border-color);border-radius:12px;padding:20px;margin-bottom:16px }
    .qc-card-title { font-size:0.95rem;font-weight:700;color:var(--text-primary);margin:0 0 16px;display:flex;align-items:center;gap:6px }
    .qc-card-title i { color:var(--role-accent) }

    .result-select { width:100%;padding:12px 14px;border:2px solid var(--border-color);border-radius:10px;font-size:0.9rem;font-weight:600;background:var(--bg-secondary);color:var(--text-primary);outline:none;margin-bottom:16px;cursor:pointer;transition:border-color .15s }
    .result-select:focus { border-color:var(--role-accent) }
    .result-select option[value="Passed"] { color:#16a34a }
    .result-select option[value="Failed"] { color:#dc2626 }

    .checklist-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:8px;margin-bottom:16px }
    .checklist-item { display:flex;align-items:center;gap:10px;padding:10px 14px;background:var(--bg-secondary);border-radius:8px;border:1px solid var(--border-color);cursor:pointer;transition:all .15s;font-size:0.82rem;color:var(--text-primary) }
    .checklist-item:hover { border-color:var(--role-accent);background:var(--bg-primary) }
    .checklist-item.checked { border-color:var(--role-accent);background:rgba(214,40,40,0.04) }
    .checklist-item input[type="checkbox"] { width:18px;height:18px;accent-color:var(--role-accent);flex-shrink:0;margin:0;cursor:pointer }
    .checklist-item .check-label { user-select:none;line-height:1.3 }
    .checklist-item .check-icon { width:18px;text-align:center;flex-shrink:0;color:var(--text-tertiary);font-size:0.75rem }
    .checklist-item.checked .check-icon { color:#16a34a }

    .form-field { margin-bottom:14px }
    .form-field label { display:block;font-size:0.78rem;font-weight:600;color:var(--text-secondary);margin-bottom:4px }
    .form-field textarea, .form-field input[type="text"] { width:100%;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;font-size:0.85rem;background:var(--bg-secondary);color:var(--text-primary);outline:none;transition:border-color .15s;font-family:inherit;box-sizing:border-box }
    .form-field textarea:focus, .form-field input:focus { border-color:var(--role-accent) }
    .form-field textarea { resize:vertical;min-height:60px }

    .failure-section { background:rgba(239,68,68,0.04);border:1px solid rgba(239,68,68,0.15);border-radius:10px;padding:16px;margin-bottom:16px;display:none }
    .failure-section.visible { display:block }
    .failure-section-title { font-size:0.82rem;font-weight:700;color:#dc2626;margin:0 0 12px;display:flex;align-items:center;gap:6px }

    .sidebar-card { background:var(--bg-primary);border:1px solid var(--border-color);border-radius:10px;padding:16px }
    .sidebar-card-title { font-size:0.82rem;font-weight:700;color:var(--text-primary);margin:0 0 10px;display:flex;align-items:center;gap:6px }
    .sidebar-card-title i { color:var(--role-accent) }
    .sidebar-card ol { margin:0;padding-left:18px;font-size:0.82rem;color:var(--text-secondary);line-height:2 }
    .sidebar-card li { padding-left:4px }

    .submit-row { display:flex;gap:8px;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border-color);margin-top:16px }
  </style>
</head>
<body data-role="quality_control_inspector">
<div class="dash-layout">
  <?php require_once '../../../../app/Views/Shared/Sidebars/qc_inspector.php'; ?>
  <div class="dash-main">
<?php
if ($order_not_found):
  echo renderDashboardShell(
    'Order not found.',
    '',
    renderEmptyState('fas fa-exclamation-triangle', 'Order not found', 'The requested order could not be found.', ['label' => 'Back to Dashboard', 'href' => 'dashboard.php', 'icon' => 'fas fa-arrow-left'])
  );
else:
  $breadcrumb = '<div style="font-size:0.78rem;color:var(--text-tertiary);margin-bottom:8px"><a href="dashboard.php" style="color:var(--role-accent);text-decoration:none">QC Dashboard</a> <span style="margin:0 4px">/</span> <span style="color:var(--text-primary)">Inspect #ORD-' . $order_id . '</span></div>';

  // Alert message
  $alerts = '';
  if ($message):
    $isErr = strpos($message, 'Error') !== false;
    $alerts .= '<div class="panel-card" style="padding:10px 14px;margin-bottom:12px;display:flex;align-items:center;gap:8px;font-size:0.85rem;background:' . ($isErr ? 'rgba(239,68,68,0.08)' : 'rgba(34,197,94,0.08)') . ';border:1px solid ' . ($isErr ? 'rgba(239,68,68,0.2)' : 'rgba(34,197,94,0.2)') . ';color:' . ($isErr ? '#ef4444' : '#22c55e') . '"><i class="fas ' . ($isErr ? 'fa-exclamation-circle' : 'fa-check-circle') . '"></i> ' . htmlspecialchars($message) . '</div>';
  endif;
  if ($ord['qc_result'] && $ord['qc_result'] !== 'Pending'):
    $alerts .= '<div class="panel-card" style="padding:10px 14px;margin-bottom:12px;font-size:0.85rem;background:rgba(234,179,8,0.08);border:1px solid rgba(234,179,8,0.2);color:#92400e"><i class="fas fa-exclamation-triangle"></i> This order was already inspected — result: <strong>' . htmlspecialchars($ord['qc_result']) . '</strong>. Submitting again will overwrite.</div>';
  endif;

  $headerCard = '<div class="order-header"><div class="order-header-top"><div><h2 class="order-header-title">#ORD-' . $ord['order_id'] . ' — ' . htmlspecialchars($ord['product_type'] ?? 'Garment') . '</h2>' . renderStatusBadge(htmlspecialchars($ord['stage']), 'info', 'sm') . '</div></div><div class="order-header-meta"><span><i class="fas fa-user"></i> ' . htmlspecialchars($ord['customer_name']) . '</span><span><i class="fas fa-user-tag"></i> ' . htmlspecialchars($ord['employee_name'] ?? 'Unassigned') . '</span><span><i class="fas fa-layer-group"></i> ' . htmlspecialchars($ord['stage']) . '</span></div></div>';

  // ── QC Form ──
  $checklistItems = [
    'design_accuracy' => 'Design Accuracy',
    'print_alignment' => 'Print / Embroidery Alignment',
    'embroidery_quality' => 'Embroidery Quality',
    'stitching_quality' => 'Stitching Quality',
    'size_accuracy' => 'Size Accuracy',
    'fabric_condition' => 'Fabric Condition',
    'cleanliness' => 'Cleanliness',
    'packaging_readiness' => 'Packaging Readiness',
  ];

  ob_start();
?>
<div class="inspect-grid">
  <div class="inspect-main">
    <div class="qc-card">
      <h5 class="qc-card-title"><i class="fas fa-clipboard-check"></i> QC Inspection Form</h5>
      <form method="post">
        <input type="hidden" name="order_id" value="<?= $order_id ?>">

        <select name="result" id="resultSelect" class="result-select" required>
          <option value="Passed">✅ Passed</option>
          <option value="Failed">❌ Failed</option>
        </select>

        <label style="font-size:0.8rem;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:8px">Inspection Checklist</label>
        <div class="checklist-grid">
          <?php foreach ($checklistItems as $key => $label): ?>
          <label class="checklist-item">
            <span class="check-icon"><i class="far fa-square"></i></span>
            <input type="checkbox" name="<?= $key ?>" value="1" style="display:none">
            <span class="check-label"><?= $label ?></span>
          </label>
          <?php endforeach; ?>
        </div>

        <div class="form-field">
          <label>Feedback</label>
          <textarea name="feedback" rows="2" placeholder="Overall quality feedback..."></textarea>
        </div>

        <div class="failure-section" id="failureSection">
          <div class="failure-section-title"><i class="fas fa-exclamation-triangle"></i> Failure Details</div>
          <div class="form-field">
            <label>Failure Reason</label>
            <input type="text" name="failure_reason" placeholder="e.g. Stitching misalignment">
          </div>
          <div class="form-field">
            <label>Required Corrections</label>
            <textarea name="required_corrections" rows="2" placeholder="Describe what needs to be fixed..."></textarea>
          </div>
        </div>

        <div class="submit-row">
          <button type="submit" name="submit_qc" class="dash-btn dash-btn-primary"><i class="fas fa-paper-plane"></i> Submit Inspection</button>
        </div>
      </form>
    </div>
  </div>

  <div class="inspect-sidebar">
    <div class="sidebar-card">
      <div class="sidebar-card-title"><i class="fas fa-list"></i> Checklist Reference</div>
      <p style="font-size:0.75rem;color:var(--text-tertiary);margin:0 0 8px">Check each item that passes:</p>
      <ol>
        <li>Design Accuracy</li>
        <li>Print / Embroidery Alignment</li>
        <li>Embroidery Quality</li>
        <li>Stitching Quality</li>
        <li>Size Accuracy</li>
        <li>Fabric Condition</li>
        <li>Cleanliness</li>
        <li>Packaging Readiness</li>
      </ol>
    </div>
    <div class="sidebar-card">
      <div class="sidebar-card-title"><i class="fas fa-info-circle"></i> Guidelines</div>
      <ul style="margin:0;padding-left:14px;font-size:0.78rem;color:var(--text-tertiary);line-height:1.8">
        <li>All items marked = Passed</li>
        <li>Any unchecked = review & fail</li>
        <li>Provide clear failure reason</li>
        <li>Specify exact corrections needed</li>
      </ul>
    </div>
  </div>
</div>
<?php
  $formHtml = ob_get_clean();

  $workspace = $breadcrumb . $alerts . $headerCard . $formHtml;

  echo renderDashboardShell('', '', $workspace . '<script>
document.getElementById(\'resultSelect\')?.addEventListener(\'change\', function() {
  document.getElementById(\'failureFields\').style.display = this.value === \'Failed\' ? \'block\' : \'none\';
});
document.getElementById(\'menuToggle\')?.addEventListener(\'click\', function() {
  document.getElementById(\'sidebar\')?.classList.toggle(\'collapsed\');
});
</script>');
endif;
?>
