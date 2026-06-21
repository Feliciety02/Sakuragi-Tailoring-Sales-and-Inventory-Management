<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../app/Middleware/auth_required.php'; // Any logged-in user
require_once '../../config/db_connect.php'; // Add database connection
$pageTitle = 'Submit Work';

// Protect: If customer somehow reaches employee pages
if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

// Get currently logged in user's ID
$user_id = $_SESSION['user_id'];

// Get in-progress tasks for this user
try {
    $taskSql = "
        SELECT o.order_id, o.order_date, o.status, ow.stage, ow.product_type
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        WHERE ow.assigned_employee = ?
        AND o.status = 'In Progress'
        ORDER BY o.order_date DESC
    ";
    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute([$user_id]);
    $tasks = $taskStmt->fetchAll();
} catch (PDOException $e) {
    // Log the error, don't stop page from loading
    error_log('Submit Work error: ' . $e->getMessage());
    $tasks = [];
}

// Handle form submission
$formSubmitted = false;
$formError = '';
$formSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedTask = isset($_POST['task_id']) ? $_POST['task_id'] : '';
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    // Validate submission
    if (empty($selectedTask)) {
        $formError = 'Please select a task to submit';
    } else {
        // Handle file uploads
        $uploadDir = __DIR__ . '/../../public/uploads/work_submissions/';

        // Ensure directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $uploadedFiles = [];
        $hasUploadError = false;

        if (isset($_FILES['work_photos']) && $_FILES['work_photos']['error'][0] != UPLOAD_ERR_NO_FILE) {
            foreach ($_FILES['work_photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['work_photos']['error'][$key] == 0) {
                    $fileName = uniqid() . '_' . basename($_FILES['work_photos']['name'][$key]);
                    $targetPath = $uploadDir . $fileName;

                    if (move_uploaded_file($tmp_name, $targetPath)) {
                        $uploadedFiles[] = $fileName;
                    } else {
                        $hasUploadError = true;
                    }
                } elseif ($_FILES['work_photos']['error'][$key] != UPLOAD_ERR_NO_FILE) {
                    $hasUploadError = true;
                }
            }
        }

        if ($hasUploadError) {
            $formError = 'There was a problem uploading one or more files';
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();

                // Save submission record (if work_submissions table exists, try it)
                try {
                    $submissionSql = "
                        INSERT INTO work_submissions (order_id, employee_id, notes, submission_date)
                        VALUES (?, ?, ?, NOW())
                    ";
                    $submissionStmt = $pdo->prepare($submissionSql);
                    $submissionStmt->execute([$selectedTask, $user_id, $notes]);
                    $submissionId = $pdo->lastInsertId();

                    // Save uploaded files
                    if (!empty($uploadedFiles)) {
                        $fileSql = "
                            INSERT INTO submission_files (submission_id, file_path)
                            VALUES (?, ?)
                        ";
                        $fileStmt = $pdo->prepare($fileSql);

                        foreach ($uploadedFiles as $file) {
                            $fileStmt->execute([$submissionId, $file]);
                        }
                    }
                } catch (PDOException $e) {
                    error_log('work_submissions table not available: ' . $e->getMessage());
                }

                // Update order status to 'Completed'
                $updateOrderSql = "
                    UPDATE orders SET status = 'Completed' WHERE order_id = ?
                ";
                $updateOrderStmt = $pdo->prepare($updateOrderSql);
                $updateOrderStmt->execute([$selectedTask]);

                // Commit transaction
                $pdo->commit();

                $formSuccess = 'Work submitted successfully! Your task has been sent to QC for review.';
                $formSubmitted = true;
            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                error_log('Submit Work save error: ' . $e->getMessage());
                $formError = 'Database error occurred. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Work — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/employee.php'; ?>
  <div class="dash-main">
    <?php require_once '../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-12">
                <h1 class="fw-bold">Submit Work</h1>
                <p class="text-muted">Upload photos of your completed work for QC review</p>
            </div>
        </div>
    </div>
    
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($formSuccess): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?= htmlspecialchars($formSuccess) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($formError): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i> <?= htmlspecialchars($formError) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="card-title mb-4">Submit Completed Task</h5>
                        <p class="card-subtitle text-muted mb-4">Take clear photos of your completed work from multiple angles</p>
                        
                        <form action="<?= htmlspecialchars(
                            $_SERVER['PHP_SELF']
                        ) ?>" method="post" enctype="multipart/form-data">
                            <!-- Task Selection -->
                            <div class="mb-4">
                                <label for="task_id" class="form-label">Select Task</label>
                                <select class="form-select" id="task_id" name="task_id" required>
                                    <option value="" selected disabled>Select a task to submit</option>
                                    <?php foreach ($tasks as $task): ?>
                                        <?php $jobId = 'JOB-' . str_pad($task['order_id'], 4, '0', STR_PAD_LEFT); ?>
                                        <option value="<?= $task['order_id'] ?>">
                                            <?= htmlspecialchars($jobId) ?> - <?= htmlspecialchars(
     $task['product_type'] ?? 'Custom Garment'
 ) ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <?php if (empty($tasks)): ?>
                                        <option value="" disabled>No in-progress tasks available</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <!-- File Upload -->
                            <div class="mb-4">
                                <label class="form-label">Upload Photos</label>
                                <div class="upload-area p-4 border rounded bg-light text-center">
                                    <div class="mb-3">
                                        <i class="fas fa-cloud-upload-alt fs-1 text-secondary"></i>
                                    </div>
                                    <p class="mb-3">Drag and drop or click to upload</p>
                                    <input type="file" id="work_photos" name="work_photos[]" class="form-control" multiple accept="image/*" required>
                                    <div id="preview-container" class="mt-3 d-flex flex-wrap gap-2"></div>
                                </div>
                                <div class="form-text">Please upload clear images (JPG, PNG) showing the completed work from different angles</div>
                            </div>
                            
                            <!-- Additional Notes -->
                            <div class="mb-4">
                                <label for="notes" class="form-label">Additional Notes (Optional)</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any notes about the completed work..."></textarea>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="d-flex justify-content-between">
                                <button type="reset" class="btn btn-light" id="clear-form">Clear Form</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i>Submit to QC
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preview uploaded images
    const fileInput = document.getElementById('work_photos');
    const previewContainer = document.getElementById('preview-container');
    
    fileInput.addEventListener('change', function() {
        previewContainer.innerHTML = '';
        const files = this.files;
        
        if (files) {
            Array.from(files).forEach(file => {
                if (!file.type.match('image.*')) {
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imgWrapper = document.createElement('div');
                    imgWrapper.className = 'position-relative';
                    
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.className = 'img-thumbnail';
                    img.style.width = '100px';
                    img.style.height = '100px';
                    img.style.objectFit = 'cover';
                    
                    imgWrapper.appendChild(img);
                    previewContainer.appendChild(imgWrapper);
                };
                
                reader.readAsDataURL(file);
            });
        }
    });
    
    // Clear preview on form reset
    document.getElementById('clear-form').addEventListener('click', function() {
        previewContainer.innerHTML = '';
    });
});
</script>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>
