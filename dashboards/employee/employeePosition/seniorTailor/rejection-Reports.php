<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once __DIR__ . '/../../../../config/component_helpers.php';
require_once '../../../../app/Middleware/auth_required.php';
require_once '../../../../config/db_connect.php';

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

$pageTitle = 'Rejection Reports';

// Real DB query — failed inspections with rework info
$statusFilter = $_GET['status'] ?? 'all';
$where = 'WHERE qc.result = \'Failed\'';
$params = [];

// Check if there's a rework_log or equivalent table; fallback to just showing failed inspections
$reworkTableExists = $pdo->query("SHOW TABLES LIKE 'rework_log'")->fetchColumn();

$query = "
    SELECT qc.inspection_id, qc.feedback, qc.inspected_at,
           o.order_id, ow.product_type,
           u.full_name AS customer_name,
           e.full_name AS employee_name
    FROM qc_inspections qc
    JOIN orders o ON qc.order_id = o.order_id
    JOIN order_workflow ow ON o.order_id = ow.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN users e ON ow.assigned_employee = e.user_id
    {$where}
    ORDER BY qc.inspected_at DESC
";

$rejections = $pdo->prepare($query);
$rejections->execute($params);
$rejectionsList = $rejections->fetchAll();
$totalCount = count($rejectionsList);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Rejection Reports — Sakuragi</title>
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
  <?php require_once '../../../../app/Views/Shared/Sidebars/senior_tailor.php'; ?>
  <div class="dash-main">
<?php
// Build table
$columns = [
  ['field' => 'inspection_id', 'label' => 'QC ID'],
  ['field' => 'garment', 'label' => 'Garment'],
  ['field' => 'reason', 'label' => 'Rejection Reason'],
  ['field' => 'date', 'label' => 'Date'],
  ['field' => 'status', 'label' => 'Status', 'type' => 'badge'],
  ['field' => 'assigned_to', 'label' => 'Assigned To'],
];

$rows = [];
foreach ($rejectionsList as $r) {
    // Determine rework status from the order's current stage
    $stageStmt = $pdo->prepare("SELECT stage FROM order_workflow WHERE order_id = ?");
    $stageStmt->execute([$r['order_id']]);
    $currentStage = $stageStmt->fetchColumn();
    
    if ($currentStage === 'Rework') {
        $statusText = 'Rework In Progress';
        $statusVariant = 'warning';
    } elseif ($currentStage === 'Quality Inspection' || $currentStage === 'Ready for Release') {
        $statusText = 'Rework Completed';
        $statusVariant = 'success';
    } else {
        $statusText = 'Rework Assigned';
        $statusVariant = 'warning';
    }

    $rows[] = [
        'inspection_id' => 'QC-' . $r['inspection_id'],
        'garment' => htmlspecialchars($r['product_type'] ?? 'Garment') . '<br><span style="font-size:0.75rem;color:var(--text-tertiary)">Order #ORD-' . $r['order_id'] . '</span>',
        'reason' => htmlspecialchars(substr($r['feedback'] ?? 'No details', 0, 80)),
        'date' => $r['inspected_at'] ? date('M j, Y, g:i A', strtotime($r['inspected_at'])) : '—',
        'status' => ['text' => $statusText, 'type' => 'badge', 'variant' => $statusVariant],
        'assigned_to' => htmlspecialchars($r['employee_name'] ?? '—'),
    ];
}

echo renderDashboardShell(
  renderPageHeader(
    'Rejection Reports',
    'View and manage garments that failed quality inspection',
    ''),
  renderFilterBar([
    [
      'label' => 'Status',
      'options' => [
        ['label' => 'All Statuses', 'value' => 'all', 'active' => $statusFilter === 'all', 'onclick' => "window.location.href='?status=all'"],
        ['label' => 'Rework In Progress', 'value' => 'rework', 'active' => $statusFilter === 'rework', 'onclick' => "window.location.href='?status=rework'"],
        ['label' => 'Rework Completed', 'value' => 'completed', 'active' => $statusFilter === 'completed', 'onclick' => "window.location.href='?status=completed'"],
      ],
    ],
  ]),
  renderPageSection(
    "Rejection Reports ({$totalCount} items)",
    renderDataTable('rejection-table', $columns, $rows, [
      'emptyMessage' => 'No rejected items',
      'emptyIcon' => 'fas fa-check-circle',
      'searchable' => true,
      'searchPlaceholder' => 'Search by ID, garment, or reason...',
      'actions' => [
        ['label' => 'Export Reports', 'href' => '#', 'icon' => 'fas fa-download', 'variant' => 'outline'],
      ],
    ]),
    'fas fa-exclamation-triangle'
  )
);
?>
    </div>
  </div>
</div>
</body>
</html>
