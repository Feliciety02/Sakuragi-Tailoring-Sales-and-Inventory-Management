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

$pageTitle = 'Items to Inspect';

// Real DB query — orders at QC stage not yet inspected
$priorityFilter = $_GET['priority'] ?? 'all';
$where = 'WHERE ow.stage = ? AND (qc.result IS NULL OR qc.result = \'Pending\' OR qc.result = \'\')';
$params = ['Quality Inspection'];

if ($priorityFilter !== 'all') {
    $where .= ' AND ow.priority = ?';
    $params[] = $priorityFilter;
}

$items = $pdo->prepare("
    SELECT o.order_id, ow.product_type, ow.priority,
           u.full_name AS customer_name,
           e.full_name AS employee_name,
           ow.expected_completion
    FROM order_workflow ow
    JOIN orders o ON ow.order_id = o.order_id
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN users e ON ow.assigned_employee = e.user_id
    LEFT JOIN qc_inspections qc ON o.order_id = qc.order_id
    {$where}
    ORDER BY FIELD(ow.priority, 'urgent', 'high', 'medium', 'low'),
             ow.expected_completion ASC
");
$items->execute($params);
$itemsList = $items->fetchAll();
$totalCount = count($itemsList);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Items to Inspect — Sakuragi</title>
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
// Build table columns
$columns = [
  ['field' => 'order_id', 'label' => 'Order'],
  ['field' => 'product', 'label' => 'Garment Type'],
  ['field' => 'tailor', 'label' => 'Tailor'],
  ['field' => 'priority', 'label' => 'Priority'],
  ['field' => 'actions', 'label' => 'Action'],
];

$rows = [];
foreach ($itemsList as $item) {
    $pVariant = $item['priority'] === 'urgent' ? 'danger' : ($item['priority'] === 'high' ? 'warning' : ($item['priority'] === 'low' ? 'info' : 'neutral'));
    $rows[] = [
        'order_id' => '#ORD-' . $item['order_id'],
        'product' => htmlspecialchars($item['product_type'] ?? 'Garment'),
        'tailor' => htmlspecialchars($item['employee_name'] ?? 'Unassigned'),
        'priority' => ['text' => ucfirst($item['priority'] ?? 'medium'), 'type' => 'badge', 'variant' => $pVariant],
        'actions' => [
            ['type' => 'actions', 'value' => [
                ['label' => 'Inspect', 'href' => '../qcInspector/inspect.php?order_id=' . $item['order_id'], 'icon' => 'fas fa-search', 'variant' => 'primary'],
            ]],
        ],
    ];
}

echo renderDashboardShell(
  renderPageHeader(
    'Items to Inspect',
    'Review and quality check items submitted for inspection',
    ''),
  renderFilterBar([
    [
      'label' => 'Priority',
      'options' => [
        ['label' => 'All Items', 'value' => 'all', 'active' => $priorityFilter === 'all', 'onclick' => "window.location.href='?priority=all'"],
        ['label' => 'High', 'value' => 'high', 'active' => $priorityFilter === 'high', 'onclick' => "window.location.href='?priority=high'"],
        ['label' => 'Medium', 'value' => 'medium', 'active' => $priorityFilter === 'medium', 'onclick' => "window.location.href='?priority=medium'"],
        ['label' => 'Low', 'value' => 'low', 'active' => $priorityFilter === 'low', 'onclick' => "window.location.href='?priority=low'"],
      ],
    ],
  ]),
  renderPageSection(
    "Pending Inspections ({$totalCount} items waiting)",
    renderDataTable('items-table', $columns, $rows, [
      'emptyMessage' => 'No items pending inspection',
      'emptyIcon' => 'fas fa-check-circle',
      'searchable' => true,
      'searchPlaceholder' => 'Search by order or garment...',
    ]),
    'fas fa-search'
  )
);
?>
    </div>
  </div>
</div>
</body>
</html>
