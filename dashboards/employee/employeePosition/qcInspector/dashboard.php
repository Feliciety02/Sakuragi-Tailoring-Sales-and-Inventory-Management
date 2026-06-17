<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../config/db_connect.php';
require_once '../../../../middleware/auth_required.php';

$user_id = $_SESSION['user_id'];

// Restrict to QC Inspectors
$pos = $pdo->prepare("SELECT p.position_name FROM employees e JOIN positions p ON e.position_id = p.position_id WHERE e.user_id = ?");
$pos->execute([$user_id]);
$posName = $pos->fetchColumn();
if ($posName !== 'Quality Control Inspector') {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

require_once '../../../../includes/header.php';
require_once '../../../../includes/sidebar_qc_inspector.php';

// Stats
$pendingCount = $pdo->query("SELECT COUNT(*) FROM order_workflow WHERE stage = 'Quality Inspection'")->fetchColumn();
$inspectedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = $user_id AND DATE(inspected_at) = CURDATE() AND result != 'Pending'")->fetchColumn();
$passedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = $user_id AND DATE(inspected_at) = CURDATE() AND result = 'Passed'")->fetchColumn();
$failedToday = $pdo->query("SELECT COUNT(*) FROM qc_inspections WHERE inspector_id = $user_id AND DATE(inspected_at) = CURDATE() AND result = 'Failed'")->fetchColumn();

// Orders awaiting QC
$pendingQC = $pdo->query("
    SELECT o.order_id, o.order_date, o.total_price,
           ow.product_type, ow.expected_completion, ow.priority, ow.assigned_employee,
           u.full_name AS customer_name,
           e.full_name AS employee_name,
           qc.result AS qc_result
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN users e ON ow.assigned_employee = e.user_id
    LEFT JOIN qc_inspections qc ON o.order_id = qc.order_id
    WHERE ow.stage = 'Quality Inspection' AND (qc.result IS NULL OR qc.result = 'Pending')
    ORDER BY ow.expected_completion ASC
");
?>
<link rel="stylesheet" href="/public/assets/css/mes.css">
<style>
  body { background: #f5f5f5; }
  .main-content { margin-left: 220px; padding: 24px 32px; background: #f5f5f5; min-height: 100vh; }
  @media (max-width: 768px) { .main-content { margin-left: 0; padding: 16px; } }
</style>

<div class="main-content">
  <div class="mb-4">
    <h1 style="font-size:20px;font-weight:700;margin:0">QC Dashboard</h1>
    <p style="font-size:13px;color:#6b7280;margin-top:4px">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></p>
  </div>

  <div class="mes-stat-row">
    <div class="mes-stat"><div class="mes-stat-label">Pending Inspection</div><div class="mes-stat-value"><?= $pendingCount ?></div></div>
    <div class="mes-stat"><div class="mes-stat-label">Inspected Today</div><div class="mes-stat-value" style="color:var(--mes-primary)"><?= $inspectedToday ?></div></div>
    <div class="mes-stat"><div class="mes-stat-label">Passed</div><div class="mes-stat-value" style="color:var(--mes-success)"><?= $passedToday ?></div></div>
    <div class="mes-stat"><div class="mes-stat-label">Failed</div><div class="mes-stat-value" style="color:var(--mes-danger)"><?= $failedToday ?></div></div>
  </div>

  <div class="mes-card">
    <div class="mes-card-header"><h3 class="mes-card-title">Awaiting Inspection</h3></div>
    <div class="mes-card-body">
      <?php if ($pendingQC->rowCount() === 0): ?>
      <p style="font-size:13px;color:#6b7280;margin:0;text-align:center;padding:24px 0">No orders pending inspection</p>
      <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:12px">
        <?php foreach ($pendingQC as $o): ?>
        <div class="mes-card" style="border-left:3px solid <?= ($o['priority']??'medium') === 'urgent' ? 'var(--mes-danger)' : (($o['priority']??'medium') === 'high' ? 'var(--mes-warning)' : 'var(--mes-info)') ?>">
          <div class="mes-card-body" style="padding:16px">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <strong style="font-size:14px">#ORD-<?= $o['order_id'] ?></strong>
              <?php if ($o['priority']): ?>
              <span class="mes-badge <?= $o['priority'] === 'urgent' ? 'mes-badge-danger' : ($o['priority'] === 'high' ? 'mes-badge-warning' : 'mes-badge-gray') ?>"><?= ucfirst($o['priority']) ?></span>
              <?php endif; ?>
            </div>
            <p style="font-size:13px;margin:0;color:#374151"><?= htmlspecialchars($o['product_type'] ?? 'Garment') ?></p>
            <p style="font-size:12px;color:#6b7280;margin:2px 0 8px"><?= htmlspecialchars($o['customer_name']) ?> · by <?= htmlspecialchars($o['employee_name'] ?? 'Unassigned') ?></p>
            <a href="inspect.php?order_id=<?= $o['order_id'] ?>" class="mes-btn mes-btn-primary mes-btn-sm"><i class="fas fa-search"></i> Inspect</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once '../../../../includes/footer.php'; ?>
