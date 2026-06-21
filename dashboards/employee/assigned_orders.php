<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../app/Middleware/auth_required.php'; // Any logged-in user
require_once '../../config/db_connect.php'; // Add database connection
$pageTitle = 'Assigned Orders';

// Check if $pdo is defined after including db_connect.php
if (!isset($pdo)) {
    die('Database connection failed. Please check the db_connect.php file.');
}

// Block customers
if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

// Get currently logged in user's ID
$user_id = $_SESSION['user_id'];

try {
    $sql = "
        SELECT DISTINCT o.order_id, o.order_date, o.status, o.total_price, ow.stage, ow.expected_completion,
               u.full_name AS customer_name
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        JOIN users u ON o.user_id = u.user_id
        WHERE ow.assigned_employee = ?
        ORDER BY o.order_date DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$user_id]);
    $result = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assigned Orders — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/employee.php'; ?>
  <div class="dash-main">
    <?php require_once '../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
    <h1>Assigned Orders</h1>
    <p>Here you can view orders assigned to you by the admin.</p>
    
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Date</th>
                    <th>Stage</th>
                    <th>Status</th>
                    <th>Expected Completion</th>
                    <th>Total Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($result)): ?>
                    <?php foreach ($result as $row): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['order_id']) ?></td>
                            <td><?= htmlspecialchars($row['customer_name'] ?? 'Unknown') ?></td>
                            <td><?= htmlspecialchars(date('M d, Y', strtotime($row['order_date']))) ?></td>
                            <td><?= htmlspecialchars($row['stage']) ?></td>
                            <td>
                                <span class="badge <?= get_status_badge_class($row['status']) ?>">
                                    <?= htmlspecialchars($row['status']) ?>
                                </span>
                            </td>
                            <td><?= $row['expected_completion']
                                ? htmlspecialchars(date('M d, Y', strtotime($row['expected_completion'])))
                                : 'Not set' ?></td>
                            <td>₱<?= htmlspecialchars(number_format($row['total_price'], 2)) ?></td>
                            <td>
                                <a href="view_order.php?id=<?= $row['order_id'] ?>" class="btn btn-sm btn-primary">
                                    View Details
                                </a>
                                <a href="update_order_status.php?id=<?= $row[
                                    'order_id'
                                ] ?>" class="btn btn-sm btn-secondary">
                                    Update Status
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center">No orders assigned to you yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
  </div>
</div>

<?php
// Helper function to get appropriate badge class based on status
function get_status_badge_class($status)
{
    switch ($status) {
        case 'Completed':
            return 'bg-success';
        case 'Cancelled':
            return 'bg-danger';
        case 'In Progress':
            return 'bg-primary';
        case 'Pending':
            return 'bg-warning';
        default:
            return 'bg-secondary';
    }
}
?>
<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>

