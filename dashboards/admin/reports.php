<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/role_admin_only.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar_admin.php';

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
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />

<main class="main-content">
    <h1>Reports & Analytics</h1>

    <div class="summary-cards" style="display:flex;gap:1rem;margin-bottom:2rem;flex-wrap:wrap;">
        <div style="flex:1;min-width:150px;background:#007bff;color:white;padding:1rem;border-radius:10px;text-align:center;">
            <h3 style="margin:0;font-size:0.9rem;">Total Sales</h3>
            <p style="margin:0;font-size:1.5rem;font-weight:bold;">₱<?= number_format($totalSales, 2) ?></p>
        </div>
        <div style="flex:1;min-width:150px;background:#28a745;color:white;padding:1rem;border-radius:10px;text-align:center;">
            <h3 style="margin:0;font-size:0.9rem;">Total Orders</h3>
            <p style="margin:0;font-size:1.5rem;font-weight:bold;"><?= $totalOrders ?></p>
        </div>
        <div style="flex:1;min-width:150px;background:#ffc107;color:#212529;padding:1rem;border-radius:10px;text-align:center;">
            <h3 style="margin:0;font-size:0.9rem;">Pending</h3>
            <p style="margin:0;font-size:1.5rem;font-weight:bold;"><?= $pendingOrders ?></p>
        </div>
    </div>

    <div class="table-controls">
        <div class="filters">
            <div class="input-wrapper">
                <i class="fas fa-search"></i>
                <input type="text" id="reportSearch" placeholder="Search reports..." class="table-search"
                    onkeyup="filterTableBySearch('reportSearch', 'reportsTable')">
            </div>
        </div>

        <button onclick="exportTableToCSV('reportsTable', 'monthly_reports.csv')" class="btn-export">
            <i class="fas fa-download"></i> Export CSV
        </button>
    </div>

    <div class="table-responsive">
        <table id="reportsTable">
            <thead>
                <tr>
                    <th onclick="sortTableByColumn('reportsTable', 0)">Month</th>
                    <th onclick="sortTableByColumn('reportsTable', 1)">Total Sales</th>
                    <th onclick="sortTableByColumn('reportsTable', 2)">Orders</th>
                    <th onclick="sortTableByColumn('reportsTable', 3)">Completed</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($monthlyReports)): ?>
                    <tr><td colspan="4" class="text-center">No data yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($monthlyReports as $report): ?>
                    <tr>
                        <td><?= htmlspecialchars($report['month_label']) ?></td>
                        <td>₱<?= number_format($report['total_sales'], 2) ?></td>
                        <td><?= $report['total_orders'] ?></td>
                        <td><?= $report['completed_orders'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script src="/public/assets/js/tables.js"></script>

<?php require_once '../../includes/footer.php'; ?>
