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
    <title>Rejection Reports - Senior Tailor</title>
      <link rel="stylesheet" href="../public/assets/css/enhanced-sidebar.css">
    <script src="../public/assets/js/enhanced-sidebar.js"></script>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
   
</head>
<body>
    <div class="main-content">
        <div class="container">
            <h1 class="page-title">Rejection Reports</h1>
            <p class="page-subtitle">View and manage garments that failed quality inspection</p>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="search-bar">
                        <i class="bi bi-search"></i>
                        <input type="text" class="form-control search-input" placeholder="Search by ID, garment type, or reason...">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="filter-section">
                        <div class="filter-group">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="statusFilter" data-bs-toggle="dropdown" aria-expanded="false">
                                    All Statuses
                            </button>
                            <ul class="dropdown-menu" aria-labelledby="statusFilter">
                                <li><a class="dropdown-item" href="#">All Statuses</a></li>
                                <li><a class="dropdown-item" href="#">Rework Assigned</a></li>
                                <li><a class="dropdown-item" href="#">Rework Completed</a></li>
                                <li><a class="dropdown-item" href="#">Permanently Rejected</a></li>
                            </ul>
                        </div>
                        <button class="btn btn-outline-secondary d-flex align-items-center">
                            <i class="bi bi-calendar me-2"></i> Filter by date
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                        <i class="bi bi-funnel me-2"></i> Advanced Filters
                    </button>
                </div>
                <div class="col-md-6 text-end">
                    <button class="btn btn-outline-secondary btn-sm d-flex align-items-center ms-auto">
                        <i class="bi bi-download me-2"></i> Export Reports
                    </button>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Rejection Reports</h5>
                    <small class="text-muted">5 items that failed quality inspection</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Garment</th>
                                    <th>Rejection Reason</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>QC-5210</td>
                                    <td>
                                        Formal Suit
                                        <span class="order-number">Order #ORD-7345</span>
                                    </td>
                                    <td>Misaligned stitching on lapel</td>
                                    <td>May 15, 2025, 2:30 PM</td>
                                    <td>
                                        <span class="status-badge status-rework-assigned">
                                            <i class="bi bi-arrow-repeat me-1"></i> Rework Assigned
                                        </span>
                                    </td>
                                    <td>David Lee</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="action-btn" title="Refresh Status">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button class="action-btn" title="Download Report">
                                                <i class="bi bi-download"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>QC-5208</td>
                                    <td>
                                        Evening Dress
                                        <span class="order-number">Order #ORD-7342</span>
                                    </td>
                                    <td>Zipper installation defect</td>
                                    <td>May 15, 2025, 11:15 AM</td>
                                    <td>
                                        <span class="status-badge status-rework-assigned">
                                            <i class="bi bi-arrow-repeat me-1"></i> Rework Assigned
                                        </span>
                                    </td>
                                    <td>Elena Rodriguez</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="action-btn" title="Refresh Status">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button class="action-btn" title="Download Report">
                                                <i class="bi bi-download"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>QC-5205</td>
                                    <td>
                                        Wedding Gown
                                        <span class="order-number">Order #ORD-7338</span>
                                    </td>
                                    <td>Beading improperly secured</td>
                                    <td>May 14, 2025, 4:45 PM</td>
                                    <td>
                                        <span class="status-badge status-rework-completed">
                                            <i class="bi bi-check-circle me-1"></i> Rework Completed
                                        </span>
                                    </td>
                                    <td>Sarah Johnson</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="action-btn" title="Refresh Status">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button class="action-btn" title="Download Report">
                                                <i class="bi bi-download"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>QC-5200</td>
                                    <td>
                                        Silk Blouse
                                        <span class="order-number">Order #ORD-7330</span>
                                    </td>
                                    <td>Uneven hemline</td>
                                    <td>May 14, 2025, 9:20 AM</td>
                                    <td>
                                        <span class="status-badge status-rework-completed">
                                            <i class="bi bi-check-circle me-1"></i> Rework Completed
                                        </span>
                                    </td>
                                    <td>Maria Chen</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="action-btn" title="Refresh Status">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button class="action-btn" title="Download Report">
                                                <i class="bi bi-download"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>QC-5195</td>
                                    <td>
                                        Linen Trousers
                                        <span class="order-number">Order #ORD-7325</span>
                                    </td>
                                    <td>Incorrect size produced</td>
                                    <td>May 13, 2025, 3:10 PM</td>
                                    <td>
                                        <span class="status-badge status-rejected">
                                            <i class="bi bi-x-circle me-1"></i> Permanently Rejected
                                        </span>
                                    </td>
                                    <td>-</td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="action-btn" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <button class="action-btn" title="Refresh Status">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button class="action-btn" title="Download Report">
                                                <i class="bi bi-download"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<style>
/* General Styles */
body {
    background-color: #f8f9fa;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.container {
    max-width: 1320px;
    padding: 1.5rem;
}

/* Page Header Styles */
.page-title {
    font-size: 1.75rem;
    font-weight: 700;
    color: #212529;
    margin-bottom: 0.5rem;
}

.page-subtitle {
    color: #6c757d;
    font-size: 1rem;
    margin-bottom: 2rem;
}

/* Search Bar Styles */
.search-bar {
    position: relative;
    margin-bottom: 1rem;
}

.search-bar i {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    z-index: 10;
}

.search-input {
    padding-left: 35px;
    height: 42px;
    border-radius: 6px;
    border: 1px solid #dee2e6;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

.search-input:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

/* Filter Section Styles */
.filter-section {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    margin-bottom: 1rem;
}

.filter-group {
    display: flex;
    gap: 0.75rem;
}

.dropdown-toggle, .btn-outline-secondary {
    border-color: #dee2e6;
    color: #495057;
    background-color: white;
    font-size: 0.9rem;
    height: 42px;
    border-radius: 6px;
    transition: all 0.2s ease;
}

.dropdown-toggle:hover, .btn-outline-secondary:hover {
    background-color: #f1f3f5;
    border-color: #ced4da;
}

.dropdown-menu {
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border-radius: 6px;
    border: 1px solid #dee2e6;
}

.dropdown-item {
    padding: 0.65rem 1.25rem;
    font-size: 0.9rem;
}

.dropdown-item:active, .dropdown-item:hover {
    background-color: #f1f3f5;
    color: #212529;
}

/* Button Styles */
.btn-sm {
    padding: 0.4rem 0.85rem;
    font-size: 0.85rem;
    border-radius: 5px;
}

/* Card Styles */
.card {
    border: none;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    margin-bottom: 2rem;
}

.card-header {
    border-bottom: 1px solid #f1f3f5;
}

/* Table Styles */
.table {
    font-size: 0.9rem;
    color: #212529;
}

.table thead th {
    background-color: #f9fafb;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    color: #6c757d;
    border-bottom: none;
    padding: 1rem;
}

.table tbody td {
    padding: 1rem;
    vertical-align: middle;
}

.table tbody tr:hover {
    background-color: #f8f9fa;
}

/* Status Badge Styles */
.order-number {
    display: block;
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 0.2rem;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.status-rework-assigned {
    background-color: #fff8e6;
    color: #b07d1a;
}

.status-rework-completed {
    background-color:rgb(119, 111, 231);
    color:rgb(130, 238, 155);
}

.status-rejected {
    background-color:rgb(240, 23, 73);
    color:rgb(243, 229, 236);
}

/* Action Button Styles */
.action-buttons {
    display: flex;
    gap: 6px;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: 1px solid rgb(33, 96, 160);
    background-color: rgb(21, 96, 161);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    cursor: pointer;
}

.action-btn:hover {
    background-color: white;
    color: rgb(21, 96, 161);
    border-color: rgb(21, 96, 161);
    box-shadow: 0 2px 4px rgba(33, 96, 160, 0.2);
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .filter-section {
        margin-top: 1rem;
        justify-content: flex-start;
    }
    
    .filter-group {
        flex-wrap: wrap;
    }
    
    .table-responsive {
        border-radius: 8px;
    }
}
</style>