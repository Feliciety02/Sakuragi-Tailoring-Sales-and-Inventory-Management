
<?php
require_once __DIR__ . '/../../../../config/session_handler.php';
require_once __DIR__ . '/../../../../config/constants.php';
require_once '../../../../middleware/auth_required.php';
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

require_once '../../../../includes/header.php';
require_once '../../../../includes/sidebar_senior_tailor.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QC Dashboard - Senior Tailor</title>
    <link rel="stylesheet" href="../public/assets/css/enhanced-sidebar.css">
    <script src="../public/assets/js/enhanced-sidebar.js"></script>
  
</head>
<body>
    <div class="main-content">
        <div class="container">
        <div class="header">
            <div>
                <h1>Welcome, Jennifer Chen</h1>
                <div class="date">Friday, May 16, 2025</div>
            </div>
            <div class="user-avatar">
                <!-- User avatar could go here -->
            </div>
        </div>
        
        <div class="status-cards">
            <div class="card">
                <div class="card-title">Items Passed Today</div>
                <div class="card-subtitle">Quality standards met</div>
                <div class="metric">
                    <div class="metric-icon icon-passed">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div class="metric-value">28 Items</div>
                        <div class="metric-label">Approved</div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-title">Items Failed</div>
                <div class="card-subtitle">Requiring attention</div>
                <div class="metric">
                    <div class="metric-icon icon-failed">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                    </div>
                    <div>
                        <div class="metric-value">7 Items</div>
                        <div class="metric-label">Not approved</div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-title">Pending Inspections</div>
                <div class="card-subtitle">Items in queue</div>
                <div class="metric">
                    <div class="metric-icon icon-pending">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <div>
                        <div class="metric-value">15 Items</div>
                        <div class="metric-label">Waiting for review</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="next-item">
            <div class="section-title">Next Item to Inspect</div>
            <div class="item-details">
                <div class="item-image">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                        <polyline points="21 15 16 10 5 21"></polyline>
                    </svg>
                </div>
                <div class="item-info">
                    <div class="item-id">
                        QC-5237
                        <span class="order-number">Order #ORD-7982</span>
                    </div>
                    <div class="item-name">Wool Suit Jacket</div>
                    <div class="item-meta">Crafted by: Marcus Wilson</div>
                    <div class="priority-tag">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        High priority item
                    </div>
                </div>
                <button class="action-button">
                    Start Inspection
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>
        </div>
        
        <div class="bottom-section">
            <div class="performance-card">
                <div class="performance-title">Today's Performance</div>
                <div class="performance-subtitle">Quality check efficiency</div>
                
                <div class="progress-item">
                    <div class="progress-label">
                        <span>Inspection Rate</span>
                        <span>75%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-value progress-inspection" style="width: 75%"></div>
                    </div>
                </div>
                
                <div class="progress-item">
                    <div class="progress-label">
                        <span>Pass Rate</span>
                        <span>80%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-value progress-pass" style="width: 80%"></div>
                    </div>
                </div>
                
                <div class="progress-item">
                    <div class="progress-label">
                        <span>Accuracy</span>
                        <span>95%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-value progress-accuracy" style="width: 95%"></div>
                    </div>
                </div>
            </div>
            
            <div class="activity-card">
                <div class="performance-title">Recent Activity</div>
                <div class="performance-subtitle">Latest inspection results</div>
                
                <div class="activity-list">
                    <div class="activity-item">
                        <div class="activity-info">
                            <div class="activity-icon icon-passed">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                            </div>
                            <div class="activity-details">
                                <div class="activity-id">QC-5236</div>
                                <div class="activity-name">Dress Shirt</div>
                            </div>
                        </div>
                        <div class="activity-time">10:02 AM</div>
                    </div>
                    
                    <div class="activity-item">
                        <div class="activity-info">
                            <div class="activity-icon icon-failed">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <line x1="15" y1="9" x2="9" y2="15"></line>
                                    <line x1="9" y1="9" x2="15" y2="15"></line>
                                </svg>
                            </div>
                            <div class="activity-details">
                                <div class="activity-id">QC-5235</div>
                                <div class="activity-name">Silk Blouse</div>
                            </div>
                        </div>
                        <div class="activity-time">10:15 AM</div>
                    </div>
                    
                    <div class="activity-item">
                        <div class="activity-info">
                            <div class="activity-icon icon-passed">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                            </div>
                            <div class="activity-details">
                                <div class="activity-id">QC-5234</div>
                                <div class="activity-name">Formal Trousers</div>
                            </div>
                        </div>
                        <div class="activity-time">9:58 AM</div>
                    </div>
                    
                    <div class="activity-item">
                        <div class="activity-info">
                            <div class="activity-icon icon-pending">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                            </div>
                            <div class="activity-details">
                                <div class="activity-id">QC-5233</div>
                                <div class="activity-name">Cashmere Sweater</div>
                            </div>
                        </div>
                        <div class="activity-time">9:30 AM</div>
                    </div>
                </div>            </div>
        </div>
    </div>
    </div>
</body>
</html>
 <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Poppins', sans-serif;
        }
        
        .main-content {
            padding: 1.5rem;
            transition: margin-left 0.3s ease;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .header h1 {
            margin: 0;
            font-size: 1.8rem;
            color: #333;
        }
        
        .date {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.3rem;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            background-color: #4e73df;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .status-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .card-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.2rem;
            color: #333;
        }
        
        .card-subtitle {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 1.2rem;
        }
        
        .metric {
            display: flex;
            align-items: center;
        }
        
        .metric-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .icon-passed {
            background-color: rgba(40, 167, 69, 0.15);
            color: #28a745;
        }
        
        .icon-failed {
            background-color: rgba(220, 53, 69, 0.15);
            color: #dc3545;
        }
        
        .icon-pending {
            background-color: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }
        
        .metric-value {
            font-weight: 700;
            font-size: 1.4rem;
            color: #333;
        }
        
        .metric-label {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .section-title {
            margin-bottom: 1rem;
            font-size: 1.2rem;
            font-weight: 600;
            color: #333;
        }
        
        .next-item {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }
        
        .item-details {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .item-image {
            width: 64px;
            height: 64px;
            background-color: #f1f1f1;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: #6c757d;
        }
        
        .item-info {
            flex-grow: 1;
            padding-right: 1rem;
        }
        
        .item-id {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.3rem;
            display: flex;
            align-items: center;
        }
        
        .order-number {
            font-weight: normal;
            color: #6c757d;
            font-size: 0.85rem;
            margin-left: 0.8rem;
        }
        
        .item-name {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 0.3rem;
            color: #333;
        }
        
        .item-meta {
            font-size: 0.85rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        .priority-tag {
            display: inline-flex;
            align-items: center;
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            padding: 0.3rem 0.6rem;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
        .priority-tag svg {
            margin-right: 0.3rem;
        }
        
        .action-button {
            background: #4e73df;
            color: white;
            border: none;
            padding: 0.7rem 1.2rem;
            border-radius: 8px;
            font-weight: 500;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .action-button:hover {
            background: #375bcc;
        }
        
        .action-button svg {
            margin-left: 0.5rem;
        }
        
        .bottom-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }
        
        .performance-card, .activity-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }
        
        .performance-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.2rem;
            color: #333;
        }
        
        .performance-subtitle {
            color: #6c757d;
            font-size: 0.85rem;
            margin-bottom: 1.5rem;
        }
        
        .progress-item {
            margin-bottom: 1.2rem;
        }
        
        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .progress-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-value {
            height: 100%;
            border-radius: 4px;
        }
        
        .progress-inspection {
            background: #4e73df;
        }
        
        .progress-pass {
            background: #28a745;
        }
        
        .progress-accuracy {
            background: #17a2b8;
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid #f1f1f1;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-info {
            display: flex;
            align-items: center;
        }
        
        .activity-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .activity-details {
            display: flex;
            flex-direction: column;
        }
        
        .activity-id {
            font-weight: 600;
            font-size: 0.9rem;
            color: #333;
        }
        
        .activity-name {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .activity-time {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .chart-container {
            height: 180px;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .item-details {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .item-info {
                margin-bottom: 1rem;
                padding-right: 0;
            }
            
            .action-button {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Animation effects */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .card, .next-item, .performance-card, .activity-card {
            animation: fadeIn 0.5s ease forwards;
        }
        
        .status-cards .card:nth-child(1) { animation-delay: 0.1s; }
        .status-cards .card:nth-child(2) { animation-delay: 0.2s; }
        .status-cards .card:nth-child(3) { animation-delay: 0.3s; }
        
        .next-item { animation-delay: 0.4s; }
        .performance-card { animation-delay: 0.5s; }
        .activity-card { animation-delay: 0.6s; }
        
        .skeleton {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: loading 1.5s infinite;
            border-radius: 4px;
        }
        
        @keyframes loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
  