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

// Check if task ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: my_tasks.php');
    exit();
}

$task_id = (int) $_GET['id'];

try {
    // Fetch task details
    $taskSql = "
        SELECT o.order_id, o.order_date, o.status, o.expected_completion, 
               o.total_price,
               ow.stage, ow.product_type, ow.workflow_notes, ow.assigned_employee,
               s.service_id, s.service_name, s.category AS service_category
        FROM orders o
        JOIN order_workflow ow ON o.order_id = ow.order_id
        LEFT JOIN services s ON o.service_id = s.service_id
        WHERE o.order_id = ?
        AND ow.assigned_employee = ?
    ";
    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute([$task_id, $user_id]);
    $task = $taskStmt->fetch();

    if (!$task) {
        // Task not found or doesn't belong to this employee
        header('Location: my_tasks.php');
        exit();
    }

    // Fetch customer information
    $customerSql = "
        SELECT u.*, COALESCE(u.first_name, u.name, u.full_name) AS display_name
        FROM users u
        JOIN orders o ON u.user_id = o.user_id
        WHERE o.order_id = ?
    ";
    $customerStmt = $pdo->prepare($customerSql);
    $customerStmt->execute([$task_id]);
    $customer = $customerStmt->fetch();

    // Fetch design files
    $filesSql = "
        SELECT file_id, file_path, file_type, upload_date
        FROM order_files
        WHERE order_id = ?
    ";
    $filesStmt = $pdo->prepare($filesSql);
    $filesStmt->execute([$task_id]);
    $designFiles = $filesStmt->fetchAll();

    // Count total items for this order
    $itemsSql = "
        SELECT SUM(quantity) as total_items
        FROM order_details
        WHERE order_id = ?
    ";
    $itemsStmt = $pdo->prepare($itemsSql);
    $itemsStmt->execute([$task_id]);
    $totalItems = $itemsStmt->fetchColumn() ?: 0;

    // Get size breakdown
    $sizesSql = "
        SELECT size, quantity
        FROM order_details
        WHERE order_id = ?
    ";
    $sizesStmt = $pdo->prepare($sizesSql);
    $sizesStmt->execute([$task_id]);
    $sizes = $sizesStmt->fetchAll();

    // Handle task status update
    $statusMessage = '';
    $statusError = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
        $newStatus = $_POST['task_status'] ?? '';
        $workNotes = $_POST['work_notes'] ?? '';

        if (!empty($newStatus)) {
            try {
                // Begin transaction
                $pdo->beginTransaction();

                // Update order workflow stage
                $updateStageSql = "
                    UPDATE order_workflow SET stage = ?, workflow_notes = ?
                    WHERE order_id = ? AND assigned_employee = ?
                ";
                $updateStageStmt = $pdo->prepare($updateStageSql);
                $updateStageStmt->execute([$newStatus, $workNotes, $task_id, $user_id]);

                // Commit transaction
                $pdo->commit();

                // Update local variable for display
                $task['stage'] = $newStatus;
                $task['workflow_notes'] = $workNotes;

                $statusMessage = 'Task status updated successfully';
            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                error_log('Task update error: ' . $e->getMessage());
                $statusError = 'Failed to update task status. Please try again.';
            }
        } else {
            $statusError = 'Please select a valid status';
        }
    }

    // Format the task ID for display
    $jobId = 'JOB-' . str_pad($task['order_id'], 4, '0', STR_PAD_LEFT);

    // Format dates
    $orderDate = date('M d, Y', strtotime($task['order_date']));
    $deadlineDate = !empty($task['expected_completion'])
        ? date('M d, Y', strtotime($task['expected_completion']))
        : 'Not specified';

    // Calculate days remaining until deadline
    $daysRemaining = 0;
    $deadlineClass = 'text-success';

    if (!empty($task['expected_completion'])) {
        $today = new DateTime();
        $deadline = new DateTime($task['expected_completion']);
        $daysRemaining = $today->diff($deadline)->days;

        if ($deadline < $today) {
            $daysRemaining = -$daysRemaining; // Make it negative to indicate overdue
            $deadlineClass = 'text-danger';
        } elseif ($daysRemaining <= 2) {
            $deadlineClass = 'text-warning';
        }
    }

    // Get progress percentage based on stage
    $progressPercentage = 0;
    switch ($task['stage']) {
        case STAGE_DESIGNING:
            $progressPercentage = 20;
            break;
        case STAGE_PRINTING:
            $progressPercentage = 40;
            break;
        case STAGE_EMBROIDERY:
            $progressPercentage = 60;
            break;
        case STAGE_QUALITY_CHECK:
            $progressPercentage = 80;
            break;
        case STAGE_PACKAGING:
        case STAGE_SHIPPED:
            $progressPercentage = 100;
            break;
        default:
            $progressPercentage = 10;
    }
} catch (PDOException $e) {
    // Log error and redirect to my tasks
    error_log('View Task error: ' . $e->getMessage());
    header('Location: my_tasks.php');
    exit();
}
?>

<main class="main-content">
    <div class="container-fluid mb-4">
        <div class="row align-items-center">
            <div class="col-12 mb-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="my_tasks.php">My Tasks</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($jobId) ?></li>
                    </ol>
                </nav>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <h1 class="fw-bold mb-0"><?= htmlspecialchars($jobId) ?></h1>
                <p class="text-muted"><?= htmlspecialchars($task['product_type'] ?? 'Custom Garment') ?></p>
            </div>
            <div class="col-md-6 text-md-end">
                <span class="badge rounded-pill <?= $task['status'] === 'In Progress'
                    ? 'bg-primary'
                    : 'bg-secondary' ?> px-3 py-2 mb-2">
                    <?= htmlspecialchars($task['status']) ?>
                </span>
                
                <?php if ($daysRemaining > 0): ?>
                <p class="<?= $deadlineClass ?> mb-0 mt-2">
                    <i class="fas fa-calendar-alt me-1"></i> 
                    <?= $daysRemaining ?> days remaining
                </p>
                <?php elseif ($daysRemaining < 0): ?>
                <p class="<?= $deadlineClass ?> mb-0 mt-2">
                    <i class="fas fa-exclamation-circle me-1"></i> 
                    <?= abs($daysRemaining) ?> days overdue
                </p>
                <?php else: ?>
                <p class="text-warning mb-0 mt-2">
                    <i class="fas fa-exclamation-circle me-1"></i> 
                    Due today
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Status Messages -->
    <?php if (!empty($statusMessage)): ?>
    <div class="container-fluid mb-4">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($statusMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($statusError)): ?>
    <div class="container-fluid mb-4">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($statusError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Task Details -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Task Overview</h5>
                        
                        <!-- Progress -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="mb-0">Progress</h6>
                                <span class="text-muted"><?= $progressPercentage ?>%</span>
                            </div>
                            <div class="progress" style="height: 10px;">
                                <div class="progress-bar bg-primary" role="progressbar" 
                                    style="width: <?= $progressPercentage ?>%;" 
                                    aria-valuenow="<?= $progressPercentage ?>" 
                                    aria-valuemin="0" 
                                    aria-valuemax="100"></div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">Order Date</h6>
                                    <p class="mb-0"><?= htmlspecialchars($orderDate) ?></p>
                                </div>
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">Deadline</h6>
                                    <p class="mb-0 <?= $deadlineClass ?>"><?= htmlspecialchars($deadlineDate) ?></p>
                                </div>
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">Current Stage</h6>
                                    <p class="mb-0"><?= htmlspecialchars($task['stage'] ?? 'Not started') ?></p>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">Service Type</h6>
                                    <p class="mb-0"><?= htmlspecialchars(
                                        $task['service_name'] ?? 'Custom Service'
                                    ) ?></p>
                                </div>
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">Total Items</h6>
                                    <p class="mb-0"><?= $totalItems ?> items</p>
                                </div>
                                <div class="mb-4">
                                    <h6 class="text-muted mb-2">Customer</h6>
                                    <p class="mb-0"><?= htmlspecialchars($customer['display_name'] ?? 'N/A') ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Size Breakdown -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-3">Size Breakdown</h6>
                            <div class="row">
                                <?php foreach ($sizes as $size): ?>
                                <div class="col-sm-3 col-6 mb-3">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body text-center p-3">
                                            <h5 class="mb-0"><?= htmlspecialchars($size['quantity']) ?></h5>
                                            <small class="text-muted">Size <?= htmlspecialchars(
                                                $size['size']
                                            ) ?></small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Workflow Notes -->
                        <div class="mb-0">
                            <h6 class="text-muted mb-2">Workflow Notes</h6>
                            <div class="p-3 bg-light rounded">
                                <?php if (!empty($task['workflow_notes'])): ?>
                                <p class="mb-0"><?= nl2br(htmlspecialchars($task['workflow_notes'])) ?></p>
                                <?php else: ?>
                                <p class="text-muted mb-0">No workflow notes added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Design Files -->
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Design Files</h5>
                        
                        <?php if (!empty($designFiles)): ?>
                        <div class="row">
                            <?php foreach ($designFiles as $file): ?>
                                <?php
                                $filePath = '/public/uploads/designs/' . $file['file_path'];
                                $fileExt = pathinfo($file['file_path'], PATHINFO_EXTENSION);
                                $isImage = in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                ?>
                                <div class="col-md-4 col-6 mb-3">
                                    <div class="card h-100">
                                        <?php if ($isImage): ?>
                                        <img src="<?= htmlspecialchars(
                                            $filePath
                                        ) ?>" class="card-img-top img-fluid" alt="Design File">
                                        <?php else: ?>
                                        <div class="card-img-top text-center py-5 bg-light">
                                            <i class="fas fa-file-alt fa-3x text-muted"></i>
                                        </div>
                                        <?php endif; ?>
                                        <div class="card-body p-2 text-center">
                                            <small class="text-muted"><?= htmlspecialchars(
                                                $file['file_type'] ?? 'File'
                                            ) ?></small>
                                        </div>
                                        <div class="card-footer p-2 text-center bg-white">
                                            <a href="<?= htmlspecialchars(
                                                $filePath
                                            ) ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-download me-1"></i> Download
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-file-image fs-1 text-muted mb-3"></i>
                            <p class="text-muted">No design files have been uploaded for this order.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Update Status -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm sticky-top" style="top: 20px;">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Update Status</h5>
                        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $task_id) ?>" method="post">
                            <div class="mb-3">
                                <label for="task_status" class="form-label">Current Stage</label>
                                <select class="form-select" id="task_status" name="task_status" required>
                                    <option value="<?= STAGE_DESIGNING ?>" <?= $task['stage'] === STAGE_DESIGNING
    ? 'selected'
    : '' ?>>
                                        Designing
                                    </option>
                                    <option value="<?= STAGE_PRINTING ?>" <?= $task['stage'] === STAGE_PRINTING
    ? 'selected'
    : '' ?>>
                                        Printing
                                    </option>
                                    <option value="<?= STAGE_EMBROIDERY ?>" <?= $task['stage'] === STAGE_EMBROIDERY
    ? 'selected'
    : '' ?>>
                                        Embroidery
                                    </option>
                                    <option value="<?= STAGE_QUALITY_CHECK ?>" <?= $task['stage'] ===
STAGE_QUALITY_CHECK
    ? 'selected'
    : '' ?>>
                                        Quality Check
                                    </option>
                                    <option value="<?= STAGE_PACKAGING ?>" <?= $task['stage'] === STAGE_PACKAGING
    ? 'selected'
    : '' ?>>
                                        Packaging
                                    </option>
                                </select>
                                <div class="form-text">Update the current stage of the task</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="work_notes" class="form-label">Work Notes</label>
                                <textarea class="form-control" id="work_notes" name="work_notes" rows="5"><?= htmlspecialchars(
                                    $task['workflow_notes'] ?? ''
                                ) ?></textarea>
                                <div class="form-text">Add notes about your progress or any issues</div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="update_status" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Update Status
                                </button>
                                
                                <?php if (
                                    $task['stage'] !== STAGE_DESIGNING &&
                                    $task['stage'] !== STAGE_PRINTING &&
                                    $task['stage'] !== STAGE_EMBROIDERY
                                ): ?>
                                <a href="submit_work.php" class="btn btn-success">
                                    <i class="fas fa-upload me-1"></i> Submit for QC
                                </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Contact Info -->
                <div class="card shadow-sm mt-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Customer Information</h5>
                        
                        <div class="mb-3">
                            <p class="mb-1"><strong>Name:</strong> <?= htmlspecialchars(
                                $customer['display_name'] ?? 'N/A'
                            ) ?></p>
                            <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars(
                                $customer['email'] ?? 'N/A'
                            ) ?></p>
                            <p class="mb-0"><strong>Phone:</strong> <?= htmlspecialchars(
                                $customer['phone'] ?? ($customer['phone_number'] ?? 'N/A')
                            ) ?></p>
                        </div>
                        
                        <?php if (!empty($customer['notes'])): ?>
                        <div class="mt-4">
                            <h6 class="text-muted mb-2">Customer Notes</h6>
                            <div class="p-3 bg-light rounded">
                                <p class="mb-0"><?= nl2br(htmlspecialchars($customer['notes'])) ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.sticky-top {
    z-index: 100;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
