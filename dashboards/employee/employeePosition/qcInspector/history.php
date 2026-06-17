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

$history = $pdo->prepare("
    SELECT qc.*, o.order_id, o.total_price, u.full_name AS inspector_name, ow.product_type
    FROM qc_inspections qc
    JOIN orders o ON qc.order_id = o.order_id
    JOIN users u ON qc.inspector_id = u.user_id
    LEFT JOIN order_workflow ow ON o.order_id = ow.order_id
    WHERE qc.inspector_id = ?
    ORDER BY qc.inspected_at DESC
");
$history->execute([$user_id]);

$search = $_GET['search'] ?? '';
?>
<link rel="stylesheet" href="/public/assets/css/mes.css">
<style>
  body { background: #f5f5f5; }
  .main-content { margin-left: 220px; padding: 24px 32px; background: #f5f5f5; min-height: 100vh; }
  @media (max-width: 768px) { .main-content { margin-left: 0; padding: 16px; } }
</style>

<div class="main-content">
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h1 style="font-size:20px;font-weight:700;margin:0">Inspection History</h1>
      <p style="font-size:13px;color:#6b7280;margin-top:4px">All inspections you've performed</p>
    </div>
    <a href="dashboard.php" class="mes-btn mes-btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
  </div>

  <div class="mes-card">
    <div class="mes-card-body">
      <div style="display:flex;gap:8px;margin-bottom:16px">
        <input type="text" id="searchInput" class="mes-form-input" placeholder="Search by order # or product..." value="<?= htmlspecialchars($search) ?>" style="max-width:300px">
        <button class="mes-btn mes-btn-primary mes-btn-sm" onclick="doSearch()"><i class="fas fa-search"></i> Search</button>
      </div>

      <?php if ($history->rowCount() === 0): ?>
      <p style="font-size:13px;color:#6b7280;margin:0;text-align:center;padding:24px 0">No inspections found</p>
      <?php else: ?>
      <div class="mes-table-wrap">
        <table class="mes-table" id="historyTable">
          <thead>
            <tr>
              <th>Order</th>
              <th>Product</th>
              <th>Result</th>
              <th>Feedback</th>
              <th>Inspected At</th>
              <th>Passed Items</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h):
              $passedCount = (int)$h['design_accuracy'] + (int)$h['print_alignment'] + (int)$h['embroidery_quality'] + (int)$h['stitching_quality'] + (int)$h['size_accuracy'] + (int)$h['fabric_condition'] + (int)$h['cleanliness'] + (int)$h['packaging_readiness'];
            ?>
            <tr>
              <td><strong>#ORD-<?= $h['order_id'] ?></strong></td>
              <td><?= htmlspecialchars($h['product_type'] ?? 'Garment') ?></td>
              <td><span class="mes-badge <?= $h['result'] === 'Passed' ? 'mes-badge-success' : 'mes-badge-danger' ?>"><?= $h['result'] ?></span></td>
              <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($h['feedback'] ?: '—') ?></td>
              <td style="font-size:12px;color:#6b7280"><?= $h['inspected_at'] ? date('M d, g:i A', strtotime($h['inspected_at'])) : '—' ?></td>
              <td><?= $passedCount ?>/8</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
function doSearch() {
  var q = document.getElementById('searchInput').value.toLowerCase();
  var rows = document.querySelectorAll('#historyTable tbody tr');
  rows.forEach(function(r) {
    r.style.display = r.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}
document.getElementById('searchInput').addEventListener('keyup', function(e) { if (e.key === 'Enter') doSearch(); });
</script>

<?php require_once '../../../../includes/footer.php'; ?>
