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

$pageTitle = 'Inspection Records';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inspection Records — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../../../app/Views/Shared/Sidebars/senior_tailor.php'; ?>
  <div class="dash-main">
    <?php require_once '../../../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
        <div class="container">
        <h1 class="page-title">Inspection History</h1>
        <p class="page-subtitle">View your complete quality inspection history</p>
        
        <div class="row mb-4">
            <div class="col-md-5">
                <div class="search-bar">
                    <i class="bi bi-search"></i>
                    <input type="text" class="form-control search-input" placeholder="Search by ID, order number, or garment type...">
                </div>
            </div>
            <div class="col-md-7">
                <div class="d-flex justify-content-end align-items-center gap-2">
                    <div class="filter-section me-2">
                        <button type="button" class="btn btn-outline-secondary filter-btn active">All Time</button>
                        <button type="button" class="btn btn-outline-secondary filter-btn">Today</button>
                        <button type="button" class="btn btn-outline-secondary filter-btn">This Week</button>
                        <button type="button" class="btn btn-outline-secondary date-picker-btn">
                            <i class="bi bi-calendar"></i>
                            Pick a date
                        </button>
                    </div>
                    
                    <div class="btn-group me-2" role="group" aria-label="View toggle">
                        <button type="button" class="view-toggle-btn">
                            <i class="bi bi-grid-fill"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary">
                            <i class="bi bi-list"></i>
                        </button>
                    </div>
                    
                    <button class="btn btn-outline-secondary export-btn">
                        <i class="bi bi-download"></i>
                        Export History
                    </button>
                </div>
            </div>
        </div>
        
        <div class="section-header">
            <h2 class="section-title">Inspection Records</h2>
            <p class="section-subtitle">8 inspection records found</p>
        </div>
        
        <div class="row g-4">
            <!-- Card 1 -->
            <div class="col-md-6 col-lg-3">
                <div class="inspection-card">
                    <div class="card-image">
                        <div class="card-image-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                    </div>
                    <div class="status-badge status-passed">
                        <i class="bi bi-check-circle-fill"></i>
                        Passed
                    </div>
                    <div class="card-content">
                        <div class="card-id">
                            <span>QC-5236</span>
                            <i class="bi bi-eye view-icon"></i>
                        </div>
                        <div class="card-garment">Dress Shirt</div>
                        <div class="card-date">May 16, 2025, 10:42 AM</div>
                    </div>
                </div>
            </div>
            
            <!-- Card 2 -->
            <div class="col-md-6 col-lg-3">
                <div class="inspection-card">
                    <div class="card-image">
                        <div class="card-image-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                    </div>
                    <div class="status-badge status-failed">
                        <i class="bi bi-x-circle-fill"></i>
                        Failed
                    </div>
                    <div class="card-content">
                        <div class="card-id">
                            <span>QC-5235</span>
                            <i class="bi bi-eye view-icon"></i>
                        </div>
                        <div class="card-garment">Silk Blouse</div>
                        <div class="card-date">May 16, 2025, 10:15 AM</div>
                    </div>
                </div>
            </div>
            
            <!-- Card 3 -->
            <div class="col-md-6 col-lg-3">
                <div class="inspection-card">
                    <div class="card-image">
                        <div class="card-image-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                    </div>
                    <div class="status-badge status-passed">
                        <i class="bi bi-check-circle-fill"></i>
                        Passed
                    </div>
                    <div class="card-content">
                        <div class="card-id">
                            <span>QC-5234</span>
                            <i class="bi bi-eye view-icon"></i>
                        </div>
                        <div class="card-garment">Formal Trousers</div>
                        <div class="card-date">May 16, 2025, 9:58 AM</div>
                    </div>
                </div>
            </div>
            
            <!-- Card 4 -->
            <div class="col-md-6 col-lg-3">
                <div class="inspection-card">
                    <div class="card-image">
                        <div class="card-image-icon">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                    </div>
                    <div class="status-badge status-rework">
                        <i class="bi bi-arrow-repeat"></i>
                        Rework
                    </div>
                    <div class="card-content">
                        <div class="card-id">
                            <span>QC-5233</span>
                            <i class="bi bi-eye view-icon"></i>
                        </div>
                        <div class="card-garment">Evening Gown</div>
                        <div class="card-date">May 16, 2025, 9:30 AM</div>
                    </div>
                </div>
            </div>
        </div>
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
<style>/* Main Layout */
/* Page Header */
.page-title {
    font-size: 1.8rem;
    font-weight: 600;
    color: #212529;
    margin-bottom: 0.5rem;
}

.page-subtitle {
    font-size: 1rem;
    color: #6c757d;
    margin-bottom: 2rem;
}

/* Search and Filters */
.search-bar {
    position: relative;
    margin-bottom: 1rem;
}

.search-bar i {
    position: absolute;
    left: 15px;
    top: 12px;
    color: #6c757d;
}

.search-input {
    padding-left: 40px;
    border-radius: 10px;
    border: 1px solid #dee2e6;
    height: 45px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.search-input:focus {
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
    border-color: #86b7fe;
}

.filter-section {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.filter-btn, .date-picker-btn {
    border-radius: 8px;
    font-size: 0.875rem;
    padding: 0.375rem 0.75rem;
    background-color: white;
    border: 1px solid #dee2e6;
    transition: all 0.2s;
}

.filter-btn.active {
    background-color: #0d6efd;
    color: white;
    border-color: #0d6efd;
}

.filter-btn:hover, .date-picker-btn:hover {
    background-color: #f1f3f5;
}

.view-toggle-btn {
    border-radius: 8px 0 0 8px;
    background-color: #0d6efd;
    color: white;
    border: 1px solid #0d6efd;
    padding: 0.375rem 0.75rem;
}

.export-btn {
    border-radius: 8px;
    padding: 0.375rem 1rem;
    display: flex;
    align-items: center;
    gap: 8px;
    background-color: white;
    transition: all 0.2s;
}

.export-btn:hover {
    background-color: #f1f3f5;
}

/* Section Header */
.section-header {
    margin: 1.5rem 0;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.section-subtitle {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 1.25rem;
}

/* Inspection Cards */
.inspection-card {
    background-color: white;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    overflow: hidden;
    transition: transform 0.2s, box-shadow 0.2s;
    position: relative;
    border: 1px solid #f0f0f0;
    height: 100%;
}

.inspection-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 20px rgba(0,0,0,0.1);
}

.card-image {
    background-color: #f8f9fa;
    height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid #f0f0f0;
}

.card-image-icon {
    font-size: 2rem;
    color: #6c757d;
}

.status-badge {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
}

.status-passed {
    background-color: #d1e7dd;
    color: #0f5132;
}

.status-failed {
    background-color: #f8d7da;
    color: #842029;
}

.status-rework {
    background-color: #fff3cd;
    color: #664d03;
}

.card-content {
    padding: 1rem;
}

.card-id {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.card-id span {
    font-weight: 600;
    color: #495057;
}

.view-icon {
    color: #6c757d;
    cursor: pointer;
    transition: color 0.2s;
}

.view-icon:hover {
    color: #0d6efd;
}

.card-garment {
    font-size: 1.1rem;
    font-weight: 500;
    color: #212529;
    margin-bottom: 8px;
}

.card-date {
    font-size: 0.875rem;
    color: #6c757d;
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .filter-section {
        margin-bottom: 1rem;
    }
    
    .inspection-card {
        margin-bottom: 1.5rem;
    }
}

@media (max-width: 768px) {
    
    .filter-section, .btn-group, .export-btn {
        width: 100%;
        justify-content: center;
        margin-bottom: 0.5rem;
    }
    
    .card-image {
        height: 80px;
    }
}
</style>
