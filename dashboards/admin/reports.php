<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/app/Middleware/role_admin_only.php';

$monthlyReports = $pdo->query("
    SELECT
        DATE_FORMAT(order_date, '%Y-%m') as month,
        DATE_FORMAT(order_date, '%M %Y') as month_label,
        SUM(total_price) as total_sales,
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_orders
    FROM orders
    GROUP BY DATE_FORMAT(order_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
")->fetchAll(PDO::FETCH_ASSOC);

$totalSales = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status = 'Completed'")->fetchColumn() ?: 0;
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn();

$pageTitle = 'Reports & Analytics';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports & Analytics — Sakuragi</title>
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
$metricsRow = renderKPIRow([
  ['label' => 'Total Sales', 'value' => '₱' . number_format($totalSales, 2), 'icon' => 'fas fa-dollar-sign', 'accent' => 'red'],
  ['label' => 'Total Orders', 'value' => (string)$totalOrders, 'icon' => 'fas fa-shopping-cart', 'accent' => 'blue'],
  ['label' => 'Pending', 'value' => (string)$pendingOrders, 'icon' => 'fas fa-clock', 'accent' => 'amber'],
]);

if (empty($monthlyReports)):
  $tableContent = renderEmptyState('fas fa-chart-bar', 'No data yet', 'Monthly reports will appear here once orders are placed.');
else:
  $cols = [
    ['field' => 'month', 'label' => 'Month'],
    ['field' => 'sales', 'label' => 'Total Sales'],
    ['field' => 'orders', 'label' => 'Orders'],
    ['field' => 'completed', 'label' => 'Completed'],
  ];
  $data = [];
  foreach ($monthlyReports as $r):
    $data[] = [
      'month' => htmlspecialchars($r['month_label']),
      'sales' => '₱' . number_format($r['total_sales'], 2),
      'orders' => (string)$r['total_orders'],
      'completed' => (string)$r['completed_orders'],
    ];
  endforeach;
  $tableContent = renderDataTable('reports-table', $cols, $data, ['searchable' => true, 'searchPlaceholder' => 'Search reports...']);
endif;

$tableContent .= '<script>
document.getElementById(\'menuToggle\')?.addEventListener(\'click\', function() {
  document.getElementById(\'sidebar\')?.classList.toggle(\'collapsed\');
});
</script>';

echo renderDashboardShell(
  renderPageHeader('Reports & Analytics', 'Monthly sales, orders, and performance data.'),
  $metricsRow,
  $tableContent
);
?>
</div>
</div>
</body>
</html>
