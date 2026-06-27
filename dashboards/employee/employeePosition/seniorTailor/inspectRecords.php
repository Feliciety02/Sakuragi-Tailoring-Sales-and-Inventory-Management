<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../config/component_helpers.php';
require_once '../../../../app/Middleware/auth_required.php';
require_once '../../../../config/db_connect.php';
require_once __DIR__ . '/../../../../app/Support/helpers.php';

$user_id = $_SESSION['user_id'];
try {
    $userStmt = $pdo->prepare("SELECT e.position_id FROM employees e WHERE e.user_id = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();
    $positionStmt = $pdo->prepare('SELECT position_name FROM positions WHERE position_id = ?');
    $positionStmt->execute([$user['position_id'] ?? 0]);
    $position = $positionStmt->fetch();
    $positionName = $position ? $position['position_name'] : '';
    if ($positionName !== 'Senior Tailor') {
        header('Location: /dashboards/employee/dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    error_log('Error: ' . $e->getMessage());
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$pageTitle = 'Inspection Records';

// Real DB queries
$filter = $_GET['period'] ?? 'all';
$search = $_GET['search'] ?? '';
$where = 'WHERE qc.inspector_id = ?';
$params = [$user_id];

if ($filter === 'today') {
    $where .= ' AND DATE(qc.inspected_at) = CURDATE()';
} elseif ($filter === 'week') {
    $where .= ' AND YEARWEEK(qc.inspected_at) = YEARWEEK(CURDATE())';
}

if ($search) {
    $where .= ' AND (qc.inspection_id LIKE ? OR o.order_id LIKE ? OR ow.product_type LIKE ?)';
    $s = "%{$search}%";
    $params = array_merge($params, [$s, $s, $s]);
}

$inspections = $pdo->prepare("
    SELECT qc.inspection_id, qc.result, qc.inspected_at, qc.feedback,
           o.order_id, ow.product_type,
           u.full_name AS customer_name
    FROM qc_inspections qc
    JOIN orders o ON qc.order_id = o.order_id
    JOIN order_workflow ow ON o.order_id = ow.order_id
    JOIN users u ON o.user_id = u.user_id
    {$where}
    ORDER BY qc.inspected_at DESC
");
$inspections->execute($params);
$inspectionsList = $inspections->fetchAll();
$totalCount = count($inspectionsList);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inspection Records — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="senior_tailor">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
// Build card grid
$cardsHtml = '';
if (empty($inspectionsList)):
  $cardsHtml = renderEmptyState('fas fa-clipboard-check', 'No inspection records found', 'Inspections you perform will appear here.');
else:
  ob_start();
  echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">';
  foreach ($inspectionsList as $row):
    $variant = $row['result'] === 'Passed' ? 'success' : ($row['result'] === 'Failed' ? 'danger' : 'warning');
    $dateStr = $row['inspected_at'] ? date('M j, Y, g:i A', strtotime($row['inspected_at'])) : '—';
?>
<div class="task-card" style="position:relative">
  <div style="position:absolute;top:16px;right:16px"><?= renderStatusBadge(htmlspecialchars($row['result'] ?? 'Pending'), $variant, 'sm') ?></div>
  <div style="margin-bottom:12px">
    <div style="font-size:0.95rem;font-weight:700;color:var(--text-primary)">QC-<?= $row['inspection_id'] ?></div>
    <div style="font-size:0.78rem;color:var(--text-tertiary)">Order #ORD-<?= $row['order_id'] ?></div>
  </div>
  <div style="font-size:0.85rem;font-weight:600;margin-bottom:4px"><?= htmlspecialchars($row['product_type'] ?? 'Garment') ?></div>
  <div style="font-size:0.78rem;color:var(--text-tertiary)"><?= $dateStr ?></div>
  <?php if ($row['feedback']): ?>
  <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:8px;font-style:italic">"<?= htmlspecialchars(substr($row['feedback'], 0, 60)) ?>"</div>
  <?php endif; ?>
</div>
<?php
  endforeach;
  echo '</div>';
  $cardsHtml = ob_get_clean();
endif;

echo renderDashboardShell(
  renderPageHeader(
    'Inspection Records',
    'View your complete quality inspection history',
    '',
    [['label' => 'Export', 'href' => '#', 'icon' => 'fas fa-download', 'variant' => 'outline', 'size' => 'sm']]
  ),
  renderFilterBar([
    [
      'options' => [
        ['label' => 'All Time', 'value' => 'all', 'active' => $filter === 'all', 'onclick' => "window.location.href='?period=all'"],
        ['label' => 'Today', 'value' => 'today', 'active' => $filter === 'today', 'onclick' => "window.location.href='?period=today'"],
        ['label' => 'This Week', 'value' => 'week', 'active' => $filter === 'week', 'onclick' => "window.location.href='?period=week'"],
      ],
    ],
  ]) . renderPageSection("Inspection Records ({$totalCount} found)", $cardsHtml, 'fas fa-clipboard-check')
);
?>
    </div>
  </div>
</div>
</body>
</html>
