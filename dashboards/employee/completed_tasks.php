<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../middleware/auth_required.php';
require_once '../../config/db_connect.php';
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
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$valid_filters = ['all', 'passed', 'failed'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'all';
}

try {
    // Check if work_submissions table exists
    $hasWorkSubmissions = false;
    try {
        $checkWs = $pdo->query("SELECT 1 FROM work_submissions LIMIT 1");
        $hasWorkSubmissions = true;
    } catch (PDOException $e) {
        $hasWorkSubmissions = false;
    }

    if ($hasWorkSubmissions) {
        $taskSql = "
            SELECT o.order_id, o.order_date, o.status, o.completion_date, 
                   ws.notes, ws.status AS qc_status, ws.feedback,
                   ow.product_type
            FROM orders o
            JOIN order_workflow ow ON o.order_id = ow.order_id
            LEFT JOIN work_submissions ws ON o.order_id = ws.order_id AND ws.employee_id = ?
            WHERE ow.assigned_employee = ?
            AND o.status = 'Completed'
        ";
        $params = [$user_id, $user_id];
        if ($filter === 'passed') {
            $taskSql .= " AND (ws.status = 'Passed' OR ws.status IS NULL)";
        } elseif ($filter === 'failed') {
            $taskSql .= " AND ws.status = 'Failed'";
        }
    } else {
        $taskSql = "
            SELECT o.order_id, o.order_date, o.status, o.completion_date, 
                   NULL AS notes, NULL AS qc_status, NULL AS feedback,
                   ow.product_type
            FROM orders o
            JOIN order_workflow ow ON o.order_id = ow.order_id
            WHERE ow.assigned_employee = ?
            AND o.status = 'Completed'
        ";
        $params = [$user_id];
    }

    $taskSql .= ' ORDER BY o.completion_date DESC, o.order_date DESC';

    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute($params);
    $completedTasks = $taskStmt->fetchAll();
} catch (PDOException $e) {
    // Log the error, don't stop page from loading
    error_log('Completed Tasks error: ' . $e->getMessage());
    $completedTasks = [];
}

// Handle task reopen request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reopen_task'])) {
    $taskId = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;

    if ($taskId > 0) {
        try {
            // Begin transaction
            $pdo->beginTransaction();

            // Update order status
            $updateOrderSql = "
                UPDATE orders SET status = 'In Progress' WHERE order_id = ?
            ";
            $updateOrderStmt = $pdo->prepare($updateOrderSql);
            $updateOrderStmt->execute([$taskId]);

            // Update submission status (if table exists)
            try {
                $updateSubSql = "
                    UPDATE work_submissions SET status = 'Reopened' WHERE order_id = ? AND employee_id = ?
                ";
                $updateSubStmt = $pdo->prepare($updateSubSql);
                $updateSubStmt->execute([$taskId, $user_id]);
            } catch (PDOException $e) {
                error_log('work_submissions not available: ' . $e->getMessage());
            }

            // Commit transaction
            $pdo->commit();

            // Redirect to refresh the page
            header('Location: completed_tasks.php');
            exit();
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            error_log('Reopen task error: ' . $e->getMessage());
        }
    }
}

// Helper function to get appropriate badge class based on status
function get_qc_badge_class($status)
{
    switch ($status) {
        case 'Passed':
        case 'Passed QC':
            return 'bg-success';
        case 'Failed':
        case 'Failed QC':
            return 'bg-danger';
        default:
            return 'bg-warning';
    }
}
?>

<main class="main-content">
    <div class="container-fluid">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="fw-bold fs-4 mb-1">Completed Tasks</h1>
                <p class="text-secondary">Review your completed work and QC results</p>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="Search by ID or garment type..." id="task-search">
                </div>
            </div>
            <div class="col-md-6 text-md-end">
                <div class="dropdown d-inline-block">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="filterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if ($filter === 'passed') {
                            echo 'Passed QC';
                        } elseif ($filter === 'failed') {
                            echo 'Failed QC';
                        } else {
                            echo 'All Results';
                        } ?>
                    </button>
                    <ul class="dropdown-menu" aria-labelledby="filterDropdown">
                        <li><a class="dropdown-item <?= $filter === 'all'
                            ? 'active'
                            : '' ?>" href="?filter=all">All Results</a></li>
                        <li><a class="dropdown-item <?= $filter === 'passed'
                            ? 'active'
                            : '' ?>" href="?filter=passed">Passed QC</a></li>
                        <li><a class="dropdown-item <?= $filter === 'failed'
                            ? 'active'
                            : '' ?>" href="?filter=failed">Failed QC</a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-striped table-hover border" id="completed-tasks-table">
                <thead class="bg-primary text-white">
                    <tr>
                        <th>Job ID</th>
                        <th>Garment Type</th>
                        <th>Completed Date</th>
                        <th>QC Result</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($completedTasks)): ?>
                        <?php foreach ($completedTasks as $task):

                            // Convert order_id to job format
                            $jobId = 'JOB-' . str_pad($task['order_id'], 4, '0', STR_PAD_LEFT);

                            // Determine garment type
                            $garmentType = $task['product_type'] ?? 'Custom Garment';

                            // Format completed date
                            $completedDate = !empty($task['completion_date'])
                                ? date('M d, Y', strtotime($task['completion_date']))
                                : date('M d, Y', strtotime($task['order_date']));

                            // Determine QC status
                            $qcStatus = '';
                            $badgeClass = '';

                            if (!empty($task['qc_status'])) {
                                if ($task['qc_status'] === 'Passed') {
                                    $qcStatus = 'Passed QC';
                                    $badgeClass = 'bg-success';
                                } elseif ($task['qc_status'] === 'Failed') {
                                    $qcStatus = 'Failed QC';
                                    $badgeClass = 'bg-danger';
                                } else {
                                    $qcStatus = 'Pending QC';
                                    $badgeClass = 'bg-warning';
                                }
                            } else {
                                $qcStatus = 'Pending QC';
                                $badgeClass = 'bg-warning';
                            }
                            ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($jobId) ?></td>
                            <td><?= htmlspecialchars($garmentType) ?></td>
                            <td><?= htmlspecialchars($completedDate) ?></td>
                            <td><span class="badge rounded-pill <?= $badgeClass ?>"><?= htmlspecialchars(
    $qcStatus
) ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary view-results" 
                                       data-job-id="<?= htmlspecialchars($jobId) ?>"
                                       data-garment="<?= htmlspecialchars($garmentType) ?>"
                                       data-feedback="<?= htmlspecialchars(
                                           $task['feedback'] ?? 'No feedback provided yet.'
                                       ) ?>"
                                       data-status="<?= htmlspecialchars($qcStatus) ?>"
                                       data-completed="<?= htmlspecialchars($completedDate) ?>"
                                       data-bs-toggle="modal" 
                                       data-bs-target="#viewResultsModal">
                                    <i class="bi bi-eye"></i> View Results
                                </button>
                                
                                <?php if ($task['qc_status'] === 'Failed'): ?>
                                <form method="post" class="d-inline-block" onsubmit="return confirm('Are you sure you want to reopen this task?');">
                                    <input type="hidden" name="task_id" value="<?= $task['order_id'] ?>">
                                    <button type="submit" name="reopen_task" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reopen
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php
                        endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5">
                                <div class="py-4">
                                    <i class="bi bi-file-earmark-text fs-1 text-muted mb-3"></i>
                                    <p class="text-muted mb-3">No completed tasks found</p>
                                    <?php if ($filter !== 'all'): ?>
                                        <a href="?filter=all" class="btn btn-outline-primary">Show all tasks</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- View Results Modal -->
    <div class="modal fade" id="viewResultsModal" tabindex="-1" aria-labelledby="viewResultsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="viewResultsModalLabel">QC Results</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-light border-start border-4 border-primary">
                    <div class="mb-3">
                        <h6 class="text-primary fw-bold mb-1 small">Job</h6>
                        <p id="modal-job-id" class="fw-bold mb-0"></p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-primary fw-bold mb-1 small">Garment</h6>
                        <p id="modal-garment" class="mb-0"></p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-primary fw-bold mb-1 small">Completed On</h6>
                        <p id="modal-completed" class="mb-0"></p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-primary fw-bold mb-1 small">Status</h6>
                        <p id="modal-status" class="fw-bold mb-0"></p>
                    </div>
                    <div>
                        <h6 class="text-primary fw-bold mb-1 small">QC Feedback</h6>
                        <div class="p-3 bg-white border border-1 rounded">
                            <p id="modal-feedback" class="mb-0"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Search functionality
    const searchInput = document.getElementById('task-search');
    const tasksTable = document.getElementById('completed-tasks-table');
    
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
    
    // View results modal functionality
    const viewResultsBtns = document.querySelectorAll('.view-results');
    
    viewResultsBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const jobId = this.getAttribute('data-job-id');
            const garment = this.getAttribute('data-garment');
            const feedback = this.getAttribute('data-feedback');
            const status = this.getAttribute('data-status');
            const completed = this.getAttribute('data-completed');
            
            document.getElementById('modal-job-id').textContent = jobId;
            document.getElementById('modal-garment').textContent = garment;
            document.getElementById('modal-feedback').textContent = feedback;
            document.getElementById('modal-status').textContent = status;
            document.getElementById('modal-completed').textContent = completed;
            
            // Add color to status based on result
            if (status.includes('Passed')) {
                document.getElementById('modal-status').className = 'fw-bold mb-0 text-success';
            } else if (status.includes('Failed')) {
                document.getElementById('modal-status').className = 'fw-bold mb-0 text-danger';
            } else {
                document.getElementById('modal-status').className = 'fw-bold mb-0 text-warning';
            }
        });
    });
});
</script>

<?php require_once '../../includes/footer.php'; ?>
