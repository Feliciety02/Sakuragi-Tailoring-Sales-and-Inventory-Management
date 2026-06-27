<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../config/db_connect.php';
require_once __DIR__ . '/../../../../config/component_helpers.php';
require_once '../../../../app/Middleware/auth_required.php';
require_once __DIR__ . '/../../../../app/Support/helpers.php';

$user_id = $_SESSION['user_id'];

$pos = $pdo->prepare("SELECT p.position_name FROM employees e JOIN positions p ON e.position_id = p.position_id WHERE e.user_id = ?");
$pos->execute([$user_id]);
$posName = $pos->fetchColumn();
if ($posName !== 'Quality Control Inspector') {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$pageTitle = 'Inspection History';

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
$rows = $history->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inspection History — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="quality_control_inspector">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
if (empty($rows)):
  $tableContent = renderEmptyState('fas fa-clipboard-list', 'No inspections found', 'Your inspection history will appear here.');
else:
  $cols = [
    ['field' => 'order_id', 'label' => 'Order'],
    ['field' => 'product', 'label' => 'Product'],
    ['field' => 'result', 'label' => 'Result', 'type' => 'badge'],
    ['field' => 'feedback', 'label' => 'Feedback'],
    ['field' => 'inspected_at', 'label' => 'Inspected At'],
    ['field' => 'passed', 'label' => 'Passed Items'],
  ];
  $data = [];
  foreach ($rows as $h):
    $passedCount = (int)$h['design_accuracy'] + (int)$h['print_alignment'] + (int)$h['embroidery_quality'] + (int)$h['stitching_quality'] + (int)$h['size_accuracy'] + (int)$h['fabric_condition'] + (int)$h['cleanliness'] + (int)$h['packaging_readiness'];
    $data[] = [
      'order_id' => '#ORD-' . $h['order_id'],
      'product' => htmlspecialchars($h['product_type'] ?? 'Garment'),
      'result' => $h['result'],
      'feedback' => $h['feedback'] ?: '—',
      'inspected_at' => $h['inspected_at'] ? date('M d, g:i A', strtotime($h['inspected_at'])) : '—',
      'passed' => $passedCount . '/8',
    ];
  endforeach;
  $tableContent = renderDataTable('history-table', $cols, $data, ['searchable' => true, 'searchPlaceholder' => 'Search by order or product...']);
endif;

echo renderDashboardShell(
  renderPageHeader('Inspection History', 'All inspections you\'ve performed.', '', [['label' => 'Back to Dashboard', 'href' => 'dashboard.php', 'icon' => 'fas fa-arrow-left', 'variant' => 'outline', 'size' => 'sm']]),
  '',
  $tableContent . '<script>document.getElementById(\'menuToggle\')?.addEventListener(\'click\', function() { document.getElementById(\'sidebar\')?.classList.toggle(\'collapsed\'); });</script>'
);
?>
</div>
</div>
</body>
</html>
