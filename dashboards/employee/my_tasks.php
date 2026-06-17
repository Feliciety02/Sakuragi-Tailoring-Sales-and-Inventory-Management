<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../middleware/auth_required.php'; // Any logged-in user
require_once '../../config/db_connect.php'; // Add database connection
require_once '../../includes/header.php';
require_once '../../includes/sidebar_employee.php';

// Protect: If customer somehow reaches employee pages
if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

// Get currently logged in user's ID
$user_id = $_SESSION['user_id'];

// Handle filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$valid_statuses = ['all', 'pending', 'in_progress', 'paused'];
if (!in_array(strtolower($status_filter), $valid_statuses)) {
    $status_filter = 'all';
}

try {
    // Base query with DISTINCT to avoid dupes from order_workflow having multiple rows per order
    $taskSql = "
        SELECT DISTINCT o.order_id, o.order_date, o.status, o.total_price, 
               ow.stage, ow.expected_completion, ow.product_type,
               u.full_name AS customer_name
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        JOIN users u ON o.user_id = u.user_id
        WHERE ow.assigned_employee = ?
    ";

    // Apply status filter if not 'all'
    $params = [$user_id];
    if ($status_filter !== 'all') {
        if ($status_filter === 'pending') {
            $taskSql .= " AND o.status = 'Pending'";
        } elseif ($status_filter === 'in_progress') {
            $taskSql .= " AND o.status = 'In Progress'";
        } elseif ($status_filter === 'paused') {
            $taskSql .= " AND o.status = 'Paused'";
        }
    } else {
        // If 'all', only show active tasks (not completed or cancelled)
        $taskSql .= " AND o.status NOT IN ('Completed', 'Cancelled')";
    }

    $taskSql .= ' ORDER BY ow.expected_completion ASC, o.order_date DESC';

    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute($params);
    $tasks = $taskStmt->fetchAll();
} catch (PDOException $e) {
    // Log the error, don't stop page from loading
    error_log('My Tasks error: ' . $e->getMessage());
    $tasks = [];
}
?>

<style>
    body {
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        color: #333;
        background-color: #fff;
    }
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .header {
        margin-bottom: 20px;
    }
    
    h1 {
        font-size: 28px;
        font-weight: 600;
        margin: 0;
        color: #333;
    }
    
    .subtitle {
        color: #666;
        font-size: 16px;
        margin-top: 5px;
    }
    
    .search-bar {
        margin-bottom: 15px;
    }
    
    .search-input {
        width: 100%;
        max-width: 300px;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .filters {
        display: flex;
        margin-bottom: 15px;
        gap: 10px;
        justify-content: flex-end;
    }
    
    .filter-button {
        padding: 8px 16px;
        background-color: #f5f7fa;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        cursor: pointer;
        color: #666;
        text-decoration: none;
    }
    
    .filter-button.active {
        background-color: #e5e7eb;
        color: #333;
        font-weight: 500;
    }
    
    .filter-button:hover {
        background-color: #e5e7eb;
    }
    
    .tasks-table {
        width: 100%;
        border-collapse: collapse;
        border: 1px solid #eaeaea;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .tasks-table th {
        text-align: left;
        padding: 12px 16px;
        background-color: #f9fafb;
        font-weight: 500;
        color: #666;
        border-bottom: 1px solid #eaeaea;
    }
    
    .tasks-table td {
        padding: 16px;
        border-bottom: 1px solid #eaeaea;
    }
    
    .tasks-table tr:hover {
        background-color: #f1f5f9;
    }
    
    .badge {
        font-size: 14px;
        padding: 6px 12px;
        border-radius: 12px;
    }
    
    .btn {
        display: inline-block;
        padding: 10px 20px;
        border: none;
        border-radius: 4px;
        font-size: 14px;
        font-weight: 500;
        text-align: center;
        cursor: pointer;
        transition: background-color 0.3s;
    }
    
    .btn-outline-dark {
        background-color: transparent;
        color: #333;
        border: 1px solid #333;
    }
    
    .btn-outline-dark:hover {
        background-color: #333;
        color: #fff;
    }
    
    .btn-link {
        color: #007bff;
        text-decoration: none;
    }
    
    .btn-link:hover {
        text-decoration: underline;
    }
</style>

<main class="main-content">
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-12">
                <h1 class="fw-bold">My Tasks</h1>
                <p class="text-muted">Manage and track your assigned work</p>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <div class="card shadow-sm">
            <div class="card-body">
                <!-- Search and filters -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search by ID or garment type..." id="task-search">
                            <button class="btn btn-outline-secondary" type="button">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <div class="btn-group" role="group">
                            <a href="?status=all" class="btn btn-outline-secondary <?= $status_filter === 'all'
                                ? 'active'
                                : '' ?>">All</a>
                            <a href="?status=pending" class="btn btn-outline-secondary <?= $status_filter === 'pending'
                                ? 'active'
                                : '' ?>">Pending</a>
                            <a href="?status=in_progress" class="btn btn-outline-secondary <?= $status_filter ===
                            'in_progress'
                                ? 'active'
                                : '' ?>">In Progress</a>
                            <a href="?status=paused" class="btn btn-outline-secondary <?= $status_filter === 'paused'
                                ? 'active'
                                : '' ?>">Paused</a>
                        </div>
                    </div>
                </div>

                <!-- Tasks table -->
                <div class="table-responsive">
                    <table class="table table-hover tasks-table" id="tasks-table">
                        <thead>
                            <tr>
                                <th>Job ID</th>
                                <th>Garment Type</th>
                                <th>Deadline</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($tasks)): ?>
                                <?php foreach ($tasks as $task):

                                    // Convert order_id to job format
                                    $jobId = 'JOB-' . str_pad($task['order_id'], 4, '0', STR_PAD_LEFT);

                                    // Determine garment type
                                    $garmentType = $task['product_type'] ?? 'Custom Garment';

                                    // Use expected_completion as deadline
                                    $deadline = $task['expected_completion']
                                        ? date('M d, Y', strtotime($task['expected_completion']))
                                        : date('M d, Y', strtotime('+7 days', strtotime($task['order_date'])));
                                    ?>
                                <tr>
                                    <td><?= htmlspecialchars($jobId) ?></td>
                                    <td><?= htmlspecialchars($garmentType) ?></td>
                                    <td><?= htmlspecialchars($deadline) ?></td>
                                    <td>
                                        <?php
                                        $statusClass = '';
                                        $iconClass = '';
                                        switch ($task['status']) {
                                            case 'Pending':
                                                $statusClass = 'bg-light text-dark border';
                                                $iconClass = 'far fa-clock';
                                                break;
                                            case 'In Progress':
                                                $statusClass = 'bg-primary text-white';
                                                $iconClass = 'fas fa-spinner fa-spin';
                                                break;
                                            case 'Paused':
                                                $statusClass = 'bg-danger text-white';
                                                $iconClass = 'fas fa-pause';
                                                break;
                                            default:
                                                $statusClass = 'bg-secondary text-white';
                                                $iconClass = 'far fa-question-circle';
                                        }
                                        ?>
                                        <span class="badge rounded-pill <?= $statusClass ?> px-3 py-2">
                                            <i class="<?= $iconClass ?> me-1"></i> <?= htmlspecialchars(
     $task['status']
 ) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view_task.php?id=<?= $task[
                                            'order_id'
                                        ] ?>" class="btn btn-sm btn-outline-dark">
                                            <i class="fas fa-play me-1"></i> Start Task
                                        </a>
                                        <a href="view_order.php?id=<?= $task[
                                            'order_id'
                                        ] ?>" class="btn btn-sm btn-link text-decoration-none">
                                            <i class="fas fa-file-alt"></i> Files
                                        </a>
                                    </td>
                                </tr>
                                <?php
                                endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <div class="d-flex flex-column align-items-center">
                                            <i class="fas fa-tasks fs-1 text-muted mb-3"></i>
                                            <p class="text-muted mb-0">No tasks found</p>
                                            <?php if ($status_filter !== 'all'): ?>
                                                <a href="?status=all" class="btn btn-sm btn-outline-primary mt-2">Show all tasks</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('task-search');
    const tasksTable = document.getElementById('tasks-table');
    
    if (searchInput && tasksTable) {
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = tasksTable.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>
