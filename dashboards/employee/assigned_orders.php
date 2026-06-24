<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/auth_required.php';

$pageTitle = 'Assigned Orders';

if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT o.order_id, o.order_date, o.status, o.total_price, ow.stage, ow.expected_completion,
               u.full_name AS customer_name
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        JOIN users u ON o.user_id = u.user_id
        WHERE ow.assigned_employee = ?
        ORDER BY o.order_date DESC
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetchAll();
} catch (PDOException $e) {
    $result = [];
    error_log('Assigned orders error: ' . $e->getMessage());
}

$role = get_user_role();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assigned Orders — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="<?= htmlspecialchars($role) ?>">
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/employee.php'; ?>
  <div class="dash-main">
<?php
if (empty($result)):
  $tableContent = renderEmptyState('fas fa-clipboard-list', 'No orders assigned', 'Orders assigned to you by the admin will appear here.');
else:
  $cols = [
    ['field' => 'order_id', 'label' => 'Order #'],
    ['field' => 'customer', 'label' => 'Customer'],
    ['field' => 'date', 'label' => 'Date'],
    ['field' => 'stage', 'label' => 'Stage'],
    ['field' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ['field' => 'completion', 'label' => 'Expected'],
    ['field' => 'total', 'label' => 'Total'],
    ['field' => 'actions', 'label' => 'Actions', 'type' => 'actions'],
  ];
  $data = [];
  foreach ($result as $row):
    $sVariant = strtolower($row['status']) === 'completed' ? 'success' : (strtolower($row['status']) === 'cancelled' ? 'danger' : (strtolower($row['status']) === 'in progress' ? 'accent' : 'warning'));
    $data[] = [
      'order_id' => '#ORD-' . $row['order_id'],
      'customer' => htmlspecialchars($row['customer_name'] ?? 'Unknown'),
      'date' => date('M d, Y', strtotime($row['order_date'])),
      'stage' => htmlspecialchars($row['stage']),
      'status' => $row['status'],
      'completion' => $row['expected_completion'] ? date('M d, Y', strtotime($row['expected_completion'])) : '—',
      'total' => '₱' . number_format($row['total_price'], 2),
      'actions' => [
        ['label' => 'View', 'href' => 'view_order.php?id=' . $row['order_id'], 'icon' => 'fas fa-eye', 'variant' => 'accent'],
        ['label' => 'Update', 'href' => 'update_order_status.php?id=' . $row['order_id'], 'icon' => 'fas fa-edit', 'variant' => 'outline'],
      ],
    ];
  endforeach;
  $tableContent = renderDataTable('assigned-orders', $cols, $data, ['searchable' => true, 'searchPlaceholder' => 'Search orders...']);
endif;

echo renderDashboardShell(
  renderPageHeader('Assigned Orders', 'Orders assigned to you by the admin.'),
  '',
  $tableContent
);
?>
    </div>
  </div>
</div>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>
