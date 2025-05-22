<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../middleware/role_admin_only.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar_admin.php';

// Total Orders
$totalOrders = $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();

// Orders Per Day (Last 7 Days)
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('M d', strtotime($date));
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE DATE(order_date) = ?');
    $stmt->execute([$date]);
    $chartData[] = $stmt->fetchColumn();
}

// Low Stock Items
$lowStockItems = $pdo
    ->query(
        "
    SELECT item_name, quantity, reorder_level 
    FROM inventory 
    WHERE quantity <= reorder_level 
    ORDER BY quantity ASC 
    LIMIT 5
"
    )
    ->fetchAll(PDO::FETCH_ASSOC);

// Status Breakdown
$statusLabels = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
$statusCounts = [];
foreach ($statusLabels as $status) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM orders WHERE status = ?');
    $stmt->execute([$status]);
    $statusCounts[] = $stmt->fetchColumn();
}

// Top Services
$topServices = $pdo
    ->query(
        "
    SELECT s.service_name, COUNT(s.service_id) as total_orders
    FROM services s 
    LEFT JOIN (
        SELECT service_id, COUNT(*) as usage_count
        FROM services
        GROUP BY service_id
    ) as service_usage ON s.service_id = service_usage.service_id
    GROUP BY s.service_name
    ORDER BY total_orders DESC
    LIMIT 5
"
    )
    ->fetchAll(PDO::FETCH_ASSOC);

// Orders per Branch
$branchOrders = $pdo
    ->query(
        "
    SELECT b.branch_name, COUNT(o.order_id) AS total_orders
    FROM branches b
    LEFT JOIN orders o ON o.branch_id = b.branch_id
    GROUP BY b.branch_id
"
    )
    ->fetchAll(PDO::FETCH_ASSOC);

// Recent Orders
$recentOrders = $pdo
    ->query(
        "
    SELECT o.order_id, u.full_name, o.total_price, o.status, o.order_date
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    ORDER BY o.order_date DESC
    LIMIT 5
"
    )
    ->fetchAll(PDO::FETCH_ASSOC);

// Order Timeline
$orderTimelines = $pdo
    ->query(
        "
    SELECT o.order_id, u.full_name, o.order_date, o.expected_completion
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    ORDER BY o.order_date DESC
    LIMIT 5
"
    )
    ->fetchAll(PDO::FETCH_ASSOC);

// Totals
$totalEmployees = $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
$totalInventory = $pdo->query('SELECT COUNT(*) FROM inventory')->fetchColumn();
$totalSales = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status = 'Completed'")->fetchColumn() ?: 0;
?>

<link rel="stylesheet" href="/../public/assets/css/admin_dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="main-content admin-dashboard">
  <div class="dashboard-container">
    <h1>Welcome, <?= $_SESSION['full_name'] ?> (Admin)</h1>

    <section class="dashboard-cards">
      <div class="card card-blue"><div class="card-content"><div class="card-text"><h3>Total Orders</h3><p><?= $totalOrders ?></p></div><div class="card-icon"><i class="fa-solid fa-cart-shopping"></i></div></div></div>
      <div class="card card-green"><div class="card-content"><div class="card-text"><h3>Inventory Items</h3><p><?= $totalInventory ?></p></div><div class="card-icon"><i class="fa-solid fa-boxes-stacked"></i></div></div></div>
      <div class="card card-yellow"><div class="card-content"><div class="card-text"><h3>Total Sales</h3><p>₱<?= number_format(
          $totalSales,
          2
      ) ?></p></div><div class="card-icon"><i class="fa-solid fa-peso-sign"></i></div></div></div>
      <div class="card card-red"><div class="card-content"><div class="card-text"><h3>Active Employees</h3><p><?= $totalEmployees ?></p></div><div class="card-icon"><i class="fa-solid fa-users"></i></div></div></div>
    </section>

    <section class="chart-grid-2x2">
      <div class="chart-card"><h2>📈 Orders (7 Days)</h2><canvas id="ordersChart"></canvas></div>
      <div class="chart-card"><h2>📊 Status</h2><canvas id="statusChart"></canvas></div>
      <div class="chart-card"><h2>🏆 Top Services</h2><canvas id="topServicesChart"></canvas></div>
      <div class="chart-card"><h2>📍 By Branch</h2><canvas id="branchChart"></canvas></div>
    </section>

    <section class="dashboard-bottom-section">
      <div class="low-stock">
        <h2>⚠️ Low Stock</h2>
        <ul>
          <?php foreach ($lowStockItems as $item): ?>
            <li><?= htmlspecialchars($item['item_name']) ?> — <strong><?= $item[
     'quantity'
 ] ?></strong> left <span style="color:red;">🔴</span></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="timeline-chart">
        <h2>🗓️ Order Timelines</h2>
        <ul class="timeline-list">
          <?php foreach ($orderTimelines as $row): ?>
            <li>
              <div class="timeline-header">
                <strong>#<?= $row['order_id'] ?> - <?= $row['full_name'] ?></strong>
                <span><?= date('M d', strtotime($row['order_date'])) ?> → <?= date(
     'M d',
     strtotime($row['expected_completion'])
 ) ?></span>
              </div>
              <div class="timeline-bar"><div class="timeline-fill" style="width: 100%;"></div></div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </section>

    <section class="recent-orders">
      <h2>📝 Recent Orders</h2>
      <table>
        <thead><tr><th>#</th><th>Customer</th><th>Status</th><th>Total</th><th>Date</th></tr></thead>
        <tbody>
          <?php foreach ($recentOrders as $order): ?>
            <tr>
              <td>#<?= $order['order_id'] ?></td>
              <td><?= htmlspecialchars($order['full_name']) ?></td>
              <td><span class="badge status-<?= strtolower($order['status']) ?>"><?= $order['status'] ?></span></td>
              <td>₱<?= number_format($order['total_price'], 2) ?></td>
              <td><?= date('M d, Y', strtotime($order['order_date'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>
  </div>
</main>

<script>
new Chart(document.getElementById('ordersChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($chartLabels) ?>,
    datasets: [{
      label: 'Orders',
      data: <?= json_encode($chartData) ?>,
      backgroundColor: 'rgba(0, 123, 255, 0.2)',
      borderColor: '#007bff',
      fill: true,
      tension: 0.3
    }]
  }
});

new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($statusLabels) ?>,
    datasets: [{
      data: <?= json_encode($statusCounts) ?>,
      backgroundColor: ['#f1c40f', '#3498db', '#2ecc71', '#e74c3c']
    }]
  }
});

new Chart(document.getElementById('topServicesChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_column($topServices, 'service_name')) ?>,
    datasets: [{
      data: <?= json_encode(array_column($topServices, 'total_orders')) ?>,
      backgroundColor: '#2980b9'
    }]
  }
});

new Chart(document.getElementById('branchChart'), {
  type: 'pie',
  data: {
    labels: <?= json_encode(array_column($branchOrders, 'branch_name')) ?>,
    datasets: [{
      data: <?= json_encode(array_column($branchOrders, 'total_orders')) ?>,
      backgroundColor: ['#007bff', '#00c6ff', '#76b5c5', '#95a5a6']
    }]
  }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
