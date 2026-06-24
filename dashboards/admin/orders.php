<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/role_admin_only.php';

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
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Orders — Sakuragi</title>
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
if (empty($orders)):
  $tableContent = renderEmptyState('fas fa-shopping-bag', 'No orders found', 'Orders from customers will appear here.');
else:
  ob_start();
?>
<div class="data-table-wrapper" id="orders-wrapper">
  <div class="data-table-toolbar">
    <div class="search-bar">
      <i class="fas fa-search search-bar-icon"></i>
      <input type="text" class="search-bar-input" id="orders-search" placeholder="Search orders..." data-table="orders-table">
    </div>
    <div class="data-table-actions">
      <div class="filter-bar">
        <select class="filter-bar-select" id="orders-status-filter" data-table="orders-table">
          <option value="">All Status</option>
          <option value="pending">Pending</option>
          <option value="in progress">In Progress</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>
      <button onclick="exportTableToCSV('orders-table', 'orders.csv')" class="dash-btn dash-btn-outline dash-btn-sm">
        <i class="fas fa-download"></i> Export CSV
      </button>
    </div>
  </div>
  <div class="data-table-scroll">
    <table class="data-table" id="orders-table" data-sortable="true">
      <thead>
        <tr>
          <th class="data-table-th" data-field="order_id">Order # <i class="fas fa-sort sort-icon"></i></th>
          <th class="data-table-th" data-field="customer">Customer <i class="fas fa-sort sort-icon"></i></th>
          <th class="data-table-th" data-field="service">Service <i class="fas fa-sort sort-icon"></i></th>
          <th class="data-table-th" data-field="date">Date <i class="fas fa-sort sort-icon"></i></th>
          <th class="data-table-th" data-field="total">Total <i class="fas fa-sort sort-icon"></i></th>
          <th class="data-table-th" data-field="status">Status <i class="fas fa-sort sort-icon"></i></th>
          <th class="data-table-th" data-field="payment">Payment <i class="fas fa-sort sort-icon"></i></th>
          <th class="data-table-th">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($orders as $order):
          $sVariant = strtolower($order['status']) === 'completed' ? 'success' : (strtolower($order['status']) === 'cancelled' ? 'danger' : (strtolower($order['status']) === 'in progress' ? 'accent' : 'warning'));
          $pVariant = strtolower($order['payment_status']) === 'paid' ? 'success' : 'warning';
        ?>
        <tr class="data-table-row">
          <td class="data-table-cell">#<?= $order['order_id'] ?></td>
          <td class="data-table-cell"><?= htmlspecialchars($order['full_name']) ?></td>
          <td class="data-table-cell"><?= htmlspecialchars($order['service_name'] ?? 'N/A') ?></td>
          <td class="data-table-cell"><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
          <td class="data-table-cell">₱<?= number_format($order['total_price'], 2) ?></td>
          <td class="data-table-cell"><?= renderStatusBadge(htmlspecialchars($order['status']), $sVariant, 'sm') ?></td>
          <td class="data-table-cell"><?= renderStatusBadge(htmlspecialchars($order['payment_status']), $pVariant, 'sm') ?></td>
          <td class="data-table-cell actions-cell">
            <a href="/dashboards/customer/view_order.php?id=<?= $order['order_id'] ?>" class="dash-btn dash-btn-outline dash-btn-sm" title="View"><i class="fas fa-eye"></i></a>
            <a href="#" class="dash-btn dash-btn-outline dash-btn-sm" title="Cancel" onclick="deleteOrder(<?= $order['order_id'] ?>);return false"><i class="fas fa-times"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php
  $tableContent = ob_get_clean();
endif;

$tableContent .= '<script src="/public/assets/js/tables.js"></script>
<script>
function deleteOrder(id) {
    if (confirm(\'Cancel order #\' + id + \'?\')) {
        fetch(\'/app/Controllers/update_order.php\', {
            method: \'POST\',
            headers: {\'Content-Type\': \'application/x-www-form-urlencoded\'},
            body: \'order_id=\' + id + \'&action=cancel\'
        }).then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.error); });
    }
}
</script>
<script>
document.getElementById(\'menuToggle\')?.addEventListener(\'click\', function() {
  document.getElementById(\'sidebar\')?.classList.toggle(\'collapsed\');
});
</script>';

echo renderDashboardShell(
  renderPageHeader('Manage Orders', 'View, filter, and manage all customer orders.'),
  '',
  $tableContent
);
