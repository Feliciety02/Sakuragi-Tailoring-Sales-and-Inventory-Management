<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../app/Middleware/auth_required.php';

require_once '../../app/Controllers/OrderController.php';

if (get_user_role() !== ROLE_CUSTOMER) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

// Initialize the OrderController with the PDO connection
$orderController = new OrderController($pdo);

// Fetch customer's orders using the OrderController
try {
    $orders = $orderController->getCustomerOrders($_SESSION['user_id']);
} catch (PDOException $e) {
    // Log error
    error_log('Error fetching orders: ' . $e->getMessage());
    $orders = [];
}
<?php
$pageTitle = 'My Orders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Orders — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/order.css">
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/customer.php'; ?>
  <div class="dash-main">
    <?php require_once '../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
    <h5 class="page-title">My Orders</h5>
    <p class="page-subtext">Track your orders, design type, status, and staff handling them.</p>

    <div class="my-orders-container">
        <div class="table-wrapper">
            <table class="my-orders-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Service</th>
                        <th>Design Type</th>
                        <th>Total Items</th>
                        <th>Assigned Staff</th>
                        <th>Expected Completion</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Check</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="10" class="text-center">No orders found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#ORD-<?= $order['order_id'] ?></td>
                                <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                                <td><?= htmlspecialchars($order['service_name']) ?></td>
                                <td><?= $order['service_category'] === 'Embroidery' ||
                                $order['service_category'] === 'Screen Printing'
                                    ? 'Standard'
                                    : 'N/A' ?></td>
                                <td><?= $order['total_quantity'] ?? 0 ?></td>
                                <td><?= $order['employee_name']
                                    ? htmlspecialchars($order['employee_name'])
                                    : '<span class="text-muted">Not yet assigned</span>' ?></td>
                                <td><?= $order['expected_completion']
                                    ? date('M d, Y', strtotime($order['expected_completion']))
                                    : 'To be determined' ?></td>
                                <td><span class="badge status <?= strtolower($order['status']) ?>"><?= $order[
    'status'
] ?></span></td>
                                <td><span class="badge status <?= strtolower($order['payment_status']) ?>"><?= $order[
    'payment_status'
] ?></span></td>
                                <td>
                                    <button type="button" class="btn-view" onclick="viewOrder(<?= $order[
                                        'order_id'
                                    ] ?>)">View</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Order Details Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Order #<span id="modalOrderId"></span></h5>
            <button type="button" class="close-modal" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="orderModalContent">
            <!-- Content will be loaded dynamically -->
            <div class="loading-indicator">
                <div class="spinner"></div>
                <p>Loading order details...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="modal-btn" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<script src="/public/assets/js/order.js"></script>

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


