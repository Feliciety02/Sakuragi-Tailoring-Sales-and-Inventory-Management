<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/app/Middleware/role_admin_only.php';

$pageTitle = 'Manage Orders';

$stmt = $pdo->query("
    SELECT o.order_id, u.full_name, o.order_date, o.total_price, o.status, o.payment_status,
           s.service_name
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    LEFT JOIN services s ON o.service_id = s.service_id
    ORDER BY o.order_date DESC
");
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Orders - Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="admin">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
$tableContent = '';
if (empty($orders)) {
    $tableContent = renderEmptyState('fas fa-shopping-bag', 'No orders found', 'Orders from customers will appear here.');
} else {
    $columns = [
        ['field' => 'order_id', 'label' => 'Order #'],
        ['field' => 'customer', 'label' => 'Customer'],
        ['field' => 'service', 'label' => 'Service'],
        ['field' => 'date', 'label' => 'Date'],
        ['field' => 'total', 'label' => 'Total'],
        ['field' => 'status', 'label' => 'Status', 'type' => 'badge'],
        ['field' => 'payment', 'label' => 'Payment', 'type' => 'badge'],
        ['field' => 'actions', 'label' => 'Actions', 'type' => 'actions'],
    ];

    $rows = [];
    foreach ($orders as $order) {
        $statusVariant = strtolower($order['status']) === 'completed'
            ? 'success'
            : (strtolower($order['status']) === 'cancelled' ? 'danger' : (strtolower($order['status']) === 'in progress' ? 'accent' : 'warning'));
        $paymentVariant = strtolower($order['payment_status']) === 'paid' ? 'success' : 'warning';

        $rows[] = [
            'order_id' => '#ORD-' . $order['order_id'],
            'customer' => $order['full_name'],
            'service' => $order['service_name'] ?? 'N/A',
            'date' => date('M d, Y', strtotime($order['order_date'])),
            'total' => '₱' . number_format((float) $order['total_price'], 2),
            'status' => ['text' => $order['status'], 'variant' => $statusVariant],
            'payment' => ['text' => $order['payment_status'], 'variant' => $paymentVariant],
            'actions' => [
                ['label' => 'View', 'href' => '/dashboards/customer/view_order.php?id=' . $order['order_id'], 'icon' => 'fas fa-eye', 'variant' => 'outline'],
                ['label' => 'Cancel', 'href' => '#', 'icon' => 'fas fa-times', 'variant' => 'outline', 'onclick' => 'deleteOrder(' . $order['order_id'] . ');return false'],
            ],
        ];
    }

    $tableContent = renderDataTable('orders-table', $columns, $rows, [
        'searchPlaceholder' => 'Search orders...',
        'emptyMessage' => 'No orders found',
        'emptyDetail' => 'Orders from customers will appear here.',
    ]);
}

$tableContent .= <<<HTML
<script>
function deleteOrder(id) {
    if (confirm('Cancel order #' + id + '?')) {
        fetch('/app/Controllers/update_order.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'order_id=' + id + '&action=cancel'
        }).then(function (response) { return response.json(); })
          .then(function (data) {
              if (data.success) {
                  location.reload();
              } else {
                  alert(data.error || 'Failed to cancel order.');
              }
          });
    }
}

document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
HTML;

echo renderDashboardShell(
    renderPageHeader('Manage Orders', 'View, filter, and manage all customer orders.'),
    '',
    $tableContent
);
?>
</div>
</div>
</body>
</html>
