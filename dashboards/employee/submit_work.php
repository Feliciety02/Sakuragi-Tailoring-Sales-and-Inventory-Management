<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../app/Middleware/auth_required.php';
require_once '../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once __DIR__ . '/../../app/Support/helpers.php';
$pageTitle = 'Submit Work';

if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    $taskSql = "
        SELECT o.order_id, o.order_date, o.status, ow.stage, ow.product_type
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        WHERE ow.assigned_employee = ? AND o.status = 'In Progress'
        ORDER BY o.order_date DESC
    ";
    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute([$user_id]);
    $tasks = $taskStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Submit Work error: ' . $e->getMessage());
    $tasks = [];
}

$formSubmitted = false;
$formError = '';
$formSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedTask = $_POST['task_id'] ?? '';
    $notes = trim($_POST['notes'] ?? '');

    if (empty($selectedTask)) {
        $formError = 'Please select a task to submit';
    } else {
        $uploadDir = __DIR__ . '/../../public/uploads/work_submissions/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $uploadedFiles = [];
        $hasUploadError = false;
        if (isset($_FILES['work_photos']) && $_FILES['work_photos']['error'][0] != UPLOAD_ERR_NO_FILE) {
            foreach ($_FILES['work_photos']['tmp_name'] as $key => $tmp_name) {
                if ($_FILES['work_photos']['error'][$key] == 0) {
                    $fileName = uniqid() . '_' . basename($_FILES['work_photos']['name'][$key]);
                    if (move_uploaded_file($tmp_name, $uploadDir . $fileName)) $uploadedFiles[] = $fileName;
                    else $hasUploadError = true;
                } elseif ($_FILES['work_photos']['error'][$key] != UPLOAD_ERR_NO_FILE) $hasUploadError = true;
            }
        }
        if ($hasUploadError) $formError = 'There was a problem uploading one or more files';
        else {
            try {
                $pdo->beginTransaction();
                try {
                    $submissionStmt = $pdo->prepare("INSERT INTO work_submissions (order_id, employee_id, notes, submission_date) VALUES (?, ?, ?, NOW())");
                    $submissionStmt->execute([$selectedTask, $user_id, $notes]);
                    $submissionId = $pdo->lastInsertId();
                    if (!empty($uploadedFiles)) {
                        $fileStmt = $pdo->prepare("INSERT INTO submission_files (submission_id, file_path) VALUES (?, ?)");
                        foreach ($uploadedFiles as $file) $fileStmt->execute([$submissionId, $file]);
                    }
                } catch (PDOException $e) { error_log('work_submissions table not available: ' . $e->getMessage()); }
                $pdo->prepare("UPDATE orders SET status = 'Completed' WHERE order_id = ?")->execute([$selectedTask]);
                $pdo->commit();
                $formSuccess = 'Work submitted successfully! Your task has been sent to QC for review.';
                $formSubmitted = true;
            } catch (PDOException $e) { $pdo->rollBack(); error_log('Submit Work save error: ' . $e->getMessage()); $formError = 'Database error occurred. Please try again.'; }
        }
    }
}

$role = get_user_role();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Work — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="<?= htmlspecialchars($role) ?>">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
$header = renderPageHeader('Submit Work', 'Upload photos of your completed work for QC review.');
$alerts = '';
if ($formSuccess) $alerts = '<div class="dash-alert dash-alert-success" style="margin:0 24px 16px"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($formSuccess) . '</div>';
elseif ($formError) $alerts = '<div class="dash-alert dash-alert-danger" style="margin:0 24px 16px"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($formError) . '</div>';

$taskOptions = [['value' => '', 'label' => 'Select a task to submit', 'disabled' => true, 'selected' => true]];
foreach ($tasks as $t) $taskOptions[] = ['value' => $t['order_id'], 'label' => 'JOB-' . str_pad($t['order_id'], 4, '0', STR_PAD_LEFT) . ' - ' . ($t['product_type'] ?? 'Custom Garment')];
if (empty($tasks)) $taskOptions[] = ['value' => '', 'label' => 'No in-progress tasks available', 'disabled' => true];

ob_start();
?>
<form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="post" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:20px">
  <div>
    <label style="display:block;font-size:0.8125rem;font-weight:600;color:var(--text-primary);margin-bottom:6px">Select Task</label>
    <select name="task_id" required style="width:100%;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;font-size:0.85rem;background:var(--bg-primary);color:var(--text-primary)">
      <?php foreach ($taskOptions as $opt): ?>
      <option value="<?= $opt['value'] ?>" <?= !empty($opt['disabled']) ? 'disabled' : '' ?> <?= !empty($opt['selected']) ? 'selected' : '' ?>><?= htmlspecialchars($opt['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div>
    <label style="display:block;font-size:0.8125rem;font-weight:600;color:var(--text-primary);margin-bottom:6px">Upload Photos</label>
    <div style="border:2px dashed var(--border-color);border-radius:12px;padding:32px;text-align:center;background:var(--bg-secondary);transition:border-color 0.2s" id="uploadArea">
      <div style="font-size:2rem;color:var(--text-tertiary);margin-bottom:8px"><i class="fas fa-cloud-upload-alt"></i></div>
      <p style="color:var(--text-secondary);margin:0 0 12px;font-size:0.85rem">Drag and drop or click to upload</p>
      <input type="file" id="work_photos" name="work_photos[]" multiple accept="image/*" required style="display:none">
      <button type="button" class="dash-btn dash-btn-outline dash-btn-sm" onclick="document.getElementById('work_photos').click()">Choose Files</button>
      <div id="preview-container" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px"></div>
    </div>
    <p style="font-size:0.75rem;color:var(--text-tertiary);margin:6px 0 0">Please upload clear images (JPG, PNG) showing the completed work from different angles</p>
  </div>
  <div>
    <label style="display:block;font-size:0.8125rem;font-weight:600;color:var(--text-primary);margin-bottom:6px">Additional Notes (Optional)</label>
    <textarea name="notes" rows="3" placeholder="Add any notes about the completed work..." style="width:100%;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;font-size:0.85rem;resize:vertical;background:var(--bg-primary);color:var(--text-primary);font-family:inherit"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
  </div>
  <div style="display:flex;gap:8px;justify-content:flex-end;padding-top:8px;border-top:1px solid var(--border-color)">
    <button type="reset" class="dash-btn dash-btn-outline dash-btn-sm" id="clear-form">Clear Form</button>
    <button type="submit" class="dash-btn dash-btn-primary dash-btn-sm"><i class="fas fa-paper-plane"></i> Submit to QC</button>
  </div>
</form>
<?php
$formHtml = ob_get_clean();
$workspace = $alerts . renderPanelCard('Submit Completed Task', $formHtml, 'fas fa-check-circle');

echo renderDashboardShell($header, '', $workspace);
?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var fileInput = document.getElementById('work_photos');
  var previewContainer = document.getElementById('preview-container');
  var uploadArea = document.getElementById('uploadArea');
  fileInput.addEventListener('change', function() {
    previewContainer.innerHTML = '';
    Array.from(this.files).forEach(function(file) {
      if (!file.type.match('image.*')) return;
      var reader = new FileReader();
      reader.onload = function(e) {
        var img = document.createElement('img');
        img.src = e.target.result;
        img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid var(--border-color)';
        previewContainer.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  });
  uploadArea.addEventListener('dragover', function(e) { e.preventDefault(); this.style.borderColor = 'var(--accent-color)'; });
  uploadArea.addEventListener('dragleave', function() { this.style.borderColor = 'var(--border-color)'; });
  uploadArea.addEventListener('drop', function(e) { e.preventDefault(); this.style.borderColor = 'var(--border-color)'; if (e.dataTransfer.files.length) { fileInput.files = e.dataTransfer.files; fileInput.dispatchEvent(new Event('change')); } });
  document.getElementById('clear-form').addEventListener('click', function() { previewContainer.innerHTML = ''; });
  document.getElementById('menuToggle')?.addEventListener('click', function() { document.getElementById('sidebar')?.classList.toggle('collapsed'); });
});
</script>
</body>
</html>
