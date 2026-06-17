<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/role_admin_only.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar_admin.php';

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

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

<main class="main-content">
    <h1>Manage Orders</h1>

    <div class="table-controls">
        <div class="filters">
            <div class="input-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="orderSearch" placeholder="Search orders..." class="table-search"
                    onkeyup="filterTableBySearch('orderSearch', 'orderTable')">
            </div>
            <div class="select-wrapper">
                <i class="fas fa-filter"></i>
                <select id="orderStatusFilter" class="table-filter"
                    onchange="filterTableByStatus('orderStatusFilter', 'orderTable')">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="in progress">In Progress</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>

        <button onclick="exportTableToCSV('orderTable', 'orders.csv')" class="btn-export">
            <i class="fas fa-download"></i> Export CSV
        </button>
    </div>

    <div class="table-responsive">
        <table id="orderTable">
            <thead>
                <tr>
                    <th onclick="sortTableByColumn('orderTable', 0)">Order #</th>
                    <th onclick="sortTableByColumn('orderTable', 1)">Customer</th>
                    <th onclick="sortTableByColumn('orderTable', 2)">Service</th>
                    <th onclick="sortTableByColumn('orderTable', 3)">Date</th>
                    <th onclick="sortTableByColumn('orderTable', 4)">Total</th>
                    <th onclick="sortTableByColumn('orderTable', 5)">Status</th>
                    <th onclick="sortTableByColumn('orderTable', 6)">Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="text-center">No orders found.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?= $order['order_id'] ?></td>
                        <td><?= htmlspecialchars($order['full_name']) ?></td>
                        <td><?= htmlspecialchars($order['service_name'] ?? 'N/A') ?></td>
                        <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
                        <td>₱<?= number_format($order['total_price'], 2) ?></td>
                        <td><span class="badge <?= strtolower($order['status']) ?>"><?= $order['status'] ?></span></td>
                        <td><span class="badge <?= strtolower($order['payment_status']) ?>"><?= $order['payment_status'] ?></span></td>
                        <td class="action-buttons">
                            <button class="view" onclick="viewOrder(<?= $order['order_id'] ?>)"><i class="fas fa-eye"></i></button>
                            <button class="delete" onclick="deleteOrder(<?= $order['order_id'] ?>)"><i class="fas fa-times"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script src="/assets/js/tables.js"></script>
<script>
function viewOrder(id) {
    window.location.href = '/dashboards/customer/view_order.php?id=' + id;
}
function deleteOrder(id) {
    if (confirm('Cancel order #' + id + '?')) {
        fetch('/controller/update_order.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'order_id=' + id + '&action=cancel'
        }).then(r => r.json()).then(d => { if(d.success) location.reload(); else alert(d.error); });
    }
}
</script>

<?php require_once '../../includes/footer.php'; ?>
