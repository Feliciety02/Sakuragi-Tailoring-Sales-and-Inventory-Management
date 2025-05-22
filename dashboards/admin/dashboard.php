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
<<<<<<< HEAD
$topServices = $pdo->query("
    SELECT p.product_name AS service_name, COUNT(*) AS total_orders
    FROM order_details od
    JOIN products p ON od.product_id = p.product_id
    GROUP BY p.product_id
=======
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
>>>>>>> 260fd405dc3f873dd238096f8d52098d11255520
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
<<<<<<< HEAD
$totalEmployees = $pdo->query("
    SELECT COUNT(*) 
    FROM employees e
    JOIN statuses s ON e.status_id = s.status_id
    WHERE s.status_name = 'Active'
")->fetchColumn();
$totalInventory = $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
=======
$totalEmployees = $pdo->query('SELECT COUNT(*) FROM employees')->fetchColumn();
$totalInventory = $pdo->query('SELECT COUNT(*) FROM inventory')->fetchColumn();
>>>>>>> 260fd405dc3f873dd238096f8d52098d11255520
$totalSales = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status = 'Completed'")->fetchColumn() ?: 0;
?>

<link rel="stylesheet" href="/../public/assets/css/admin_dashboard.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<main class="main-content admin-dashboard">
  <div class="dashboard-container">
    <h1 class="dashboard-heading">Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?> <span class="dashboard-role">(Admin)</span></h1>

    <section class="dashboard-cards">
<<<<<<< HEAD
      <div class="card card-blue"><div class="card-content"><h3>Total Orders</h3><p><?= $totalOrders ?></p></div></div>
      <div class="card card-green"><div class="card-content"><h3>Inventory Items</h3><p><?= $totalInventory ?></p></div></div>
      <div class="card card-yellow"><div class="card-content"><h3>Total Sales</h3><p>₱<?= number_format($totalSales, 2) ?></p></div></div>
      <div class="card card-red"><div class="card-content"><h3>Active Employees</h3><p><?= $totalEmployees ?></p></div></div>
=======
      <div class="card card-blue"><div class="card-content"><div class="card-text"><h3>Total Orders</h3><p><?= $totalOrders ?></p></div><div class="card-icon"><i class="fa-solid fa-cart-shopping"></i></div></div></div>
      <div class="card card-green"><div class="card-content"><div class="card-text"><h3>Inventory Items</h3><p><?= $totalInventory ?></p></div><div class="card-icon"><i class="fa-solid fa-boxes-stacked"></i></div></div></div>
      <div class="card card-yellow"><div class="card-content"><div class="card-text"><h3>Total Sales</h3><p>₱<?= number_format(
          $totalSales,
          2
      ) ?></p></div><div class="card-icon"><i class="fa-solid fa-peso-sign"></i></div></div></div>
      <div class="card card-red"><div class="card-content"><div class="card-text"><h3>Active Employees</h3><p><?= $totalEmployees ?></p></div><div class="card-icon"><i class="fa-solid fa-users"></i></div></div></div>
>>>>>>> 260fd405dc3f873dd238096f8d52098d11255520
    </section>

    <section class="chart-grid">
      <div class="chart-card"><h2>📈 Orders (7 Days)</h2><canvas id="ordersChart"></canvas></div>
      <div class="chart-card"><h2>📊 Order Status</h2><canvas id="statusChart"></canvas></div>
      <div class="chart-card"><h2>🏆 Top Services</h2><canvas id="topServicesChart"></canvas></div>
      <div class="chart-card"><h2>📍 Orders by Branch</h2><canvas id="branchChart"></canvas></div>
    </section>

    <section class="dashboard-bottom">
      <div class="low-stock">
        <h2>⚠️ Low Stock Items</h2>
        <ul>
          <?php foreach ($lowStockItems as $item): ?>
<<<<<<< HEAD
            <li><?= htmlspecialchars($item['item_name']) ?> — <strong><?= $item['quantity'] ?></strong> (Limit: <?= $item['reorder_level'] ?>)</li>
=======
            <li><?= htmlspecialchars($item['item_name']) ?> — <strong><?= $item[
     'quantity'
 ] ?></strong> left <span style="color:red;">🔴</span></li>
>>>>>>> 260fd405dc3f873dd238096f8d52098d11255520
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="order-timeline">
        <h2>🗓️ Order Timeline</h2>
        <ul>
          <?php foreach ($orderTimelines as $row): ?>
            <li>
<<<<<<< HEAD
              <strong>#<?= $row['order_id'] ?> <?= htmlspecialchars($row['full_name']) ?></strong><br>
              <small><?= date('M d', strtotime($row['order_date'])) ?> → <?= date('M d', strtotime($row['expected_completion'])) ?></small>
=======
              <div class="timeline-header">
                <strong>#<?= $row['order_id'] ?> - <?= $row['full_name'] ?></strong>
                <span><?= date('M d', strtotime($row['order_date'])) ?> → <?= date(
     'M d',
     strtotime($row['expected_completion'])
 ) ?></span>
              </div>
              <div class="timeline-bar"><div class="timeline-fill" style="width: 100%;"></div></div>
>>>>>>> 260fd405dc3f873dd238096f8d52098d11255520
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
              <td><span class="badge <?= strtolower($order['status']) ?>"><?= $order['status'] ?></span></td>
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
      tension: 0.4
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
<<<<<<< HEAD

<style>
/* Base Container Styling */
.admin-dashboard {
  padding: 2rem;
  font-family: 'Segoe UI', sans-serif;
  background-color: #f5f7fa;
}

/* Page Title */
.dashboard-heading {
  font-size: 1.75rem;
  font-weight: 600;
  margin-bottom: 1.5rem;
}
.dashboard-role {
  font-size: 0.9rem;
  color: #6c757d;
}

/* Dashboard Cards */
.dashboard-cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}
.card {
  border-radius: 10px;
  padding: 1rem;
  color: white;
  display: flex;
  flex-direction: column;
  justify-content: center;
  text-align: center;
  transition: transform 0.2s ease;
}
.card:hover {
  transform: translateY(-3px);
}
.card h3 {
  font-size: 1rem;
  margin-bottom: 0.5rem;
}
.card p {
  font-size: 1.6rem;
  font-weight: bold;
  margin: 0;
}
.card-blue { background: #007bff; }
.card-green { background: #28a745; }
.card-yellow { background: #ffc107; color: #212529; }
.card-red { background: #dc3545; }

/* Chart Grid */
.chart-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}
.chart-card {
  background: white;
  padding: 1.2rem;
  border-radius: 10px;
  box-shadow: 0 1px 6px rgba(0, 0, 0, 0.05);
  display: flex;
  flex-direction: column;
  height: 320px;
}
.chart-card h2 {
  font-size: 1rem;
  font-weight: 600;
  margin-bottom: 0.5rem;
}
.chart-card canvas {
  flex-grow: 1;
  max-height: 260px;
}

/* Dashboard Sections */
.dashboard-bottom,
.recent-orders {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 1.5rem;
  margin-top: 2rem;
}
.low-stock, .order-timeline {
  background: white;
  padding: 1.25rem;
  border-radius: 10px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
.low-stock h2, .order-timeline h2 {
  font-size: 1.1rem;
  margin-bottom: 1rem;
}
.low-stock ul, .order-timeline ul {
  list-style: none;
  margin: 0;
  padding-left: 0;
}
.low-stock li, .order-timeline li {
  font-size: 0.9rem;
  margin-bottom: 0.5rem;
}

/* Table Styles */
.recent-orders h2 {
  font-size: 1.1rem;
  margin-bottom: 1rem;
}
table {
  width: 100%;
  border-collapse: collapse;
  background: white;
  border-radius: 10px;
  overflow: hidden;
  box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}
thead {
  background: #f1f1f1;
}
th, td {
  padding: 0.75rem 1rem;
  text-align: left;
  font-size: 0.9rem;
}
tbody tr:nth-child(even) {
  background: #fafafa;
}

/* Status Badges */
.badge {
  display: inline-block;
  padding: 0.3rem 0.6rem;
  font-size: 0.75rem;
  font-weight: 600;
  border-radius: 5px;
  color: #fff;
  text-transform: capitalize;
}
.badge.pending { background-color: #f1c40f; color: #212529; }
.badge.in-progress { background-color: #3498db; }
.badge.completed { background-color: #2ecc71; }
.badge.cancelled { background-color: #e74c3c; }
</style>

</style>
=======
>>>>>>> 260fd405dc3f873dd238096f8d52098d11255520
