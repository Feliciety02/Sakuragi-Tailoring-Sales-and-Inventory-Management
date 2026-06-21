<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once '../../../../app/Middleware/auth_required.php';
require_once '../../../../config/db_connect.php';
// Get user position
$user_id = $_SESSION['user_id'];
try {
    $userSql = "
        SELECT e.position_id 
        FROM employees e
        WHERE e.user_id = ?
    ";
    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();

    $positionSql = 'SELECT position_name FROM positions WHERE position_id = ?';
    $positionStmt = $pdo->prepare($positionSql);
    $positionStmt->execute([$user['position_id'] ?? 0]);
    $position = $positionStmt->fetch();
    $positionName = $position ? $position['position_name'] : '';

    // Restrict access to Senior Tailors only
    if ($positionName !== 'Senior Tailor') {
        header('Location: /dashboards/employee/dashboard.php');
        exit();
    }
} catch (PDOException $e) {
    // Handle error
    error_log('Error: ' . $e->getMessage());
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$pageTitle = 'Items to Inspect';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Items to Inspect — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <style>
        :root {
            --primary-color: #4a6fdc;
            --primary-light: #e8f0ff;
            --accent-color: #f8a100;
            --text-dark: #333;
            --text-light: #666;
            --background-light: #f9f9f9;
            --shadow: 0 2px 8px rgba(0,0,0,0.08);
            --radius: 8px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
            background-color: #f5f7fa;
            line-height: 1.6;
        }
        
        h1 {
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-dark);
        }
        
        h2 {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--text-dark);
        }
        
        p {
            color: var(--text-light);
            margin-bottom: 24px;
        }
        
        .card {
            background-color: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 24px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: var(--radius);
            overflow: hidden;
            background: white;
            box-shadow: var(--shadow);
        }
        
        table, th, td {
            border: none;
        }
        
        th {
            background-color: var(--primary-light);
            color: var(--text-dark);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            padding: 14px 16px;
        }
        
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #eee;
            color: var(--text-dark);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background-color: var(--primary-light);
        }
        
        .priority-high {
            background-color: #ffecec;
            color: #d32f2f;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .priority-medium {
            background-color: #fff8e1;
            color: #ff8f00;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .priority-low {
            background-color: #e8f5e9;
            color: #388e3c;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .inspect-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius);
            padding: 8px 16px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
        }
        
        .inspect-btn:hover {
            background-color: #3658b3;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(74, 111, 220, 0.2);
        }
        
        .filter-container {
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .filter-btn {
            padding: 8px 16px;
            margin-right: 8px;
            cursor: pointer;
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: var(--radius);
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .filter-btn:hover {
            background-color: #f5f5f5;
        }
        
        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .more-filters-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background-color: white;
            border: 1px solid #e0e0e0;
            border-radius: var(--radius);
            cursor: pointer;
        }
        
        .more-filters-btn:hover {
            background-color: #f5f5f5;
        }
        
        .item-meta {
            display: flex;
            flex-direction: column;
        }
        
        .timestamp {
            font-size: 14px;
            font-weight: 500;
        }
        
        .time-ago {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 4px;
        }
        
        .garment-type {
            font-weight: 500;
        }
    </style>
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../../../app/Views/Shared/Sidebars/senior_tailor.php'; ?>
  <div class="dash-main">
    <?php require_once '../../../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
        <h1>Items to Inspect</h1>
        <p>Review and quality check items submitted for inspection</p>
        
        <div class="filter-container">
            <div>
                <button class="filter-btn active" data-filter="all">All Items</button>
                <button class="filter-btn" data-filter="high">High Priority</button>
                <button class="filter-btn" data-filter="medium">Medium Priority</button>
                <button class="filter-btn" data-filter="low">Low Priority</button>
            </div>
            <div>
                <button class="more-filters-btn">
                    More Filters
                </button>
            </div>
        </div>
        
        <div>
            <div>
                <h2>All Pending Inspections</h2>
                <small>5 items waiting for quality control</small>
            </div>
            <div>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Garment Type</th>
                            <th>Tailor</th>
                            <th>Submitted</th>
                            <th>Priority</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="item" data-priority="high">
                            <td>QC-5237</td>
                            <td>
                                <div>
                                    Wool Suit Jacket
                                </div>
                            </td>
                            <td>Marcus Wilson</td>
                            <td>
                                <div>
                                    May 16, 10:30 AM
                                    <div>
                                        ~432 minutes ago
                                    </div>
                                </div>
                            </td>
                            <td><span class="priority-high">High</span></td>
                            <td>
                                <button class="inspect-btn" onclick="window.location.href='inspection-detail.php?id=QC-5237'">
                                    Inspect
                                </button>
                            </td>
                        </tr>
                        <tr class="item" data-priority="medium">
                            <td>QC-5238</td>
                            <td>
                                <div>
                                    Silk Blouse
                                </div>
                            </td>
                            <td>Sarah Johnson</td>
                            <td>
                                <div>
                                    May 16, 9:45 AM
                                    <div>
                                        ~467 minutes ago
                                    </div>
                                </div>
                            </td>
                            <td><span class="priority-medium">Medium</span></td>
                            <td>
                                <button class="inspect-btn" onclick="window.location.href='inspection-detail.php?id=QC-5238'">
                                    Inspect
                                </button>
                            </td>
                        </tr>
                        <tr class="item" data-priority="high">
                            <td>QC-5239</td>
                            <td>
                                <div>
                                    Wedding Dress
                                </div>
                            </td>
                            <td>Elena Rodriguez</td>
                            <td>
                                <div>
                                    May 16, 9:15 AM
                                    <div>
                                        ~497 minutes ago
                                    </div>
                                </div>
                            </td>
                            <td><span class="priority-high">High</span></td>
                            <td>
                                <button class="inspect-btn" onclick="window.location.href='inspection-detail.php?id=QC-5239'">
                                    Inspect
                                </button>
                            </td>
                        </tr>
                        <tr class="item" data-priority="low">
                            <td>QC-5240</td>
                            <td>
                                <div>
                                    Formal Trousers
                                </div>
                            </td>
                            <td>David Lee</td>
                            <td>
                                <div>
                                    May 16, 8:50 AM
                                    <div>
                                        ~512 minutes ago
                                    </div>
                                </div>
                            </td>
                            <td><span class="priority-low">Low</span></td>
                            <td>
                                <button class="inspect-btn" onclick="window.location.href='inspection-detail.php?id=QC-5240'">
                                    Inspect
                                </button>
                            </td>
                        </tr>
                        <tr class="item" data-priority="medium">
                            <td>QC-5241</td>
                            <td>
                                <div>
                                    Evening Gown
                                </div>
                            </td>
                            <td>Maria Chen</td>
                            <td>
                                <div>
                                    May 15, 4:20 PM
                                    <div>
                                        ~17 hours ago
                                    </div>
                                </div>
                            </td>
                            <td><span class="priority-medium">Medium</span></td>
                            <td>
                                <button class="inspect-btn" onclick="window.location.href='inspection-detail.php?id=QC-5241'">
                                    Inspect
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
  </div>
</div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter functionality
            const filterButtons = document.querySelectorAll('.filter-btn');
            const items = document.querySelectorAll('.item');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    
                    // Add active class to clicked button
                    this.classList.add('active');
                    
                    // Get filter value
                    const filterValue = this.getAttribute('data-filter');
                    
                    // Show/hide items based on filter
                    items.forEach(item => {
                        if (filterValue === 'all' || item.getAttribute('data-priority') === filterValue) {
                            item.style.display = '';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                });
            });
            
            // Adjust layout based on sidebar
            function adjustLayout() {
                const sidebar = document.getElementById('sidebar');
                const mainContent = document.querySelector('.dash-content');
                
                if (sidebar && mainContent) {
                    if (sidebar.classList.contains('collapsed')) {
                        mainContent.style.marginLeft = '70px';
                    } else {
                        mainContent.style.marginLeft = '250px';
                    }
                }
            }
            
            // Listen for sidebar toggle events
            const sidebarToggle = document.getElementById('sidebarToggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', adjustLayout);
            }
            
            // Initial layout adjustment
            adjustLayout();
            
            // Adjust on window resize
            window.addEventListener('resize', adjustLayout);
        });
    </script>
<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>

