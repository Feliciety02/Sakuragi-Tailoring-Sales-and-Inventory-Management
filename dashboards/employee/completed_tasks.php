<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../app/Middleware/auth_required.php';
require_once '../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once __DIR__ . '/../../app/Support/helpers.php';

$pageTitle = 'Completed Tasks';

if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$valid_filters = ['all', 'passed', 'failed'];
if (!in_array($filter, $valid_filters)) $filter = 'all';

try {
    $hasWorkSubmissions = false;
    try {
        $pdo->query("SELECT 1 FROM work_submissions LIMIT 1");
        $hasWorkSubmissions = true;
    } catch (PDOException $e) { $hasWorkSubmissions = false; }

    if ($hasWorkSubmissions) {
        $taskSql = "
            SELECT o.order_id, o.order_date, o.status, o.completion_date,
                   ws.notes, ws.status AS qc_status, ws.feedback, ow.product_type
            FROM orders o
            JOIN order_workflow ow ON o.order_id = ow.order_id
            LEFT JOIN work_submissions ws ON o.order_id = ws.order_id AND ws.employee_id = ?
            WHERE ow.assigned_employee = ? AND o.status = 'Completed'
        ";
        $params = [$user_id, $user_id];
        if ($filter === 'passed') $taskSql .= " AND (ws.status = 'Passed' OR ws.status IS NULL)";
        elseif ($filter === 'failed') $taskSql .= " AND ws.status = 'Failed'";
    } else {
        $taskSql = "
            SELECT o.order_id, o.order_date, o.status, o.completion_date,
                   NULL AS notes, NULL AS qc_status, NULL AS feedback, ow.product_type
            FROM orders o
            JOIN order_workflow ow ON o.order_id = ow.order_id
            WHERE ow.assigned_employee = ? AND o.status = 'Completed'
        ";
        $params = [$user_id];
    }

    $taskSql .= ' ORDER BY o.completion_date DESC, o.order_date DESC';
    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->execute($params);
    $completedTasks = $taskStmt->fetchAll();
} catch (PDOException $e) {
    error_log('Completed Tasks error: ' . $e->getMessage());
    $completedTasks = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reopen_task'])) {
    $taskId = (int)($_POST['task_id'] ?? 0);
    if ($taskId > 0) {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE orders SET status = 'In Progress' WHERE order_id = ?")->execute([$taskId]);
            try { $pdo->prepare("UPDATE work_submissions SET status = 'Reopened' WHERE order_id = ? AND employee_id = ?")->execute([$taskId, $user_id]); }
            catch (PDOException $e) { error_log('work_submissions not available: ' . $e->getMessage()); }
            $pdo->commit();
            header('Location: completed_tasks.php');
            exit();
        } catch (PDOException $e) { $pdo->rollBack(); error_log('Reopen task error: ' . $e->getMessage()); }
    }
}

$role = get_user_role();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Completed Tasks — Sakuragi</title>
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
if (empty($completedTasks)):
  $tableContent = renderEmptyState('fas fa-check-circle', 'No completed tasks found', 'Tasks you complete will appear here.', $filter !== 'all' ? ['label' => 'Show all tasks', 'href' => '?filter=all', 'icon' => 'fas fa-list'] : []);
else:
  $cols = [
    ['field' => 'job_id', 'label' => 'Job ID'],
    ['field' => 'garment', 'label' => 'Garment Type'],
    ['field' => 'completed', 'label' => 'Completed Date'],
    ['field' => 'qc_status', 'label' => 'QC Result', 'type' => 'badge'],
    ['field' => 'actions', 'label' => 'Actions', 'type' => 'actions'],
  ];
  $data = [];
  foreach ($completedTasks as $task):
    $jobId = 'JOB-' . str_pad($task['order_id'], 4, '0', STR_PAD_LEFT);
    $garmentType = $task['product_type'] ?? 'Custom Garment';
    $completedDate = !empty($task['completion_date']) ? date('M d, Y', strtotime($task['completion_date'])) : date('M d, Y', strtotime($task['order_date']));
    $qcStatus = $task['qc_status'] ?? 'Pending QC';
    $qcVariant = $qcStatus === 'Passed' || $qcStatus === 'Passed QC' ? 'success' : ($qcStatus === 'Failed' || $qcStatus === 'Failed QC' ? 'danger' : 'warning');
    $actions = [['label' => 'View Results', 'icon' => 'fas fa-eye', 'href' => '#', 'variant' => 'accent', 'onclick' => "viewResults('{$jobId}','" . htmlspecialchars($garmentType, ENT_QUOTES) . "','" . date('M d, Y', strtotime($task['completion_date'])) . "','{$qcStatus}','" . htmlspecialchars($task['feedback'] ?? 'No feedback provided yet.', ENT_QUOTES) . "');return false"]];
    if ($qcStatus === 'Failed' || $qcStatus === 'Failed QC'):
      $actions[] = ['label' => 'Reopen', 'icon' => 'fas fa-undo', 'href' => '#', 'variant' => 'outline', 'onclick' => "document.getElementById('reopenForm-{$task['order_id']}').submit();return false"];
    endif;
    $data[] = [
      'job_id' => $jobId,
      'garment' => $garmentType,
      'completed' => $completedDate,
      'qc_status' => $qcStatus,
      'actions' => $actions,
    ];
  endforeach;

  ob_start();
  foreach ($completedTasks as $task):
    if ($task['qc_status'] === 'Failed' || $task['qc_status'] === 'Failed QC'):
?>
<form method="post" id="reopenForm-<?= $task['order_id'] ?>" style="display:none">
  <input type="hidden" name="task_id" value="<?= $task['order_id'] ?>">
  <input type="hidden" name="reopen_task" value="1">
</form>
<?php endif; endforeach;
  $hiddenForms = ob_get_clean();

  $tableContent = renderDataTable('completed-tasks', $cols, $data, [
    'searchable' => true, 'searchPlaceholder' => 'Search by ID or garment type...',
    'actions' => [
      ['label' => 'All Results', 'href' => '?filter=all', 'variant' => $filter === 'all' ? 'primary' : 'outline', 'size' => 'sm'],
      ['label' => 'Passed QC', 'href' => '?filter=passed', 'variant' => $filter === 'passed' ? 'primary' : 'outline', 'size' => 'sm'],
      ['label' => 'Failed QC', 'href' => '?filter=failed', 'variant' => $filter === 'failed' ? 'primary' : 'outline', 'size' => 'sm'],
    ],
  ]);
  $tableContent .= $hiddenForms;
endif;

echo renderDashboardShell(
  renderPageHeader('Completed Tasks', 'Review your completed work and QC results.'),
  '',
  $tableContent
);
?>
    </div>
  </div>

<div class="modern-modal-overlay" id="resultsModal" style="display:none" onclick="if(event.target===this)closeResults()">
  <div class="modern-modal" style="max-width:480px">
    <h3 style="margin:0 0 16px;font-size:1.05rem;font-weight:700;color:var(--text-primary)">QC Results</h3>
    <div style="display:grid;gap:12px;font-size:0.85rem">
      <div><strong style="color:var(--text-secondary)">Job:</strong><br><span id="rm-job" style="color:var(--text-primary);font-weight:600"></span></div>
      <div><strong style="color:var(--text-secondary)">Garment:</strong><br><span id="rm-garment" style="color:var(--text-primary)"></span></div>
      <div><strong style="color:var(--text-secondary)">Completed On:</strong><br><span id="rm-completed" style="color:var(--text-primary)"></span></div>
      <div><strong style="color:var(--text-secondary)">Status:</strong><br><span id="rm-status"></span></div>
      <div><strong style="color:var(--text-secondary)">QC Feedback:</strong><br><div style="background:var(--bg-secondary);padding:12px;border-radius:8px;margin-top:4px"><span id="rm-feedback" style="color:var(--text-primary)"></span></div></div>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:20px">
      <button class="dash-btn dash-btn-outline dash-btn-sm" onclick="closeResults()">Close</button>
    </div>
  </div>
</div>

<script>
function viewResults(jobId, garment, completed, status, feedback) {
  document.getElementById('rm-job').textContent = jobId;
  document.getElementById('rm-garment').textContent = garment;
  document.getElementById('rm-completed').textContent = completed;
  var statusEl = document.getElementById('rm-status');
  statusEl.innerHTML = '<span class="dash-badge dash-badge-' + (status.includes('Passed') ? 'success' : status.includes('Failed') ? 'danger' : 'warning') + '">' + status + '</span>';
  document.getElementById('rm-feedback').textContent = feedback;
  document.getElementById('resultsModal').style.display = 'flex';
}
function closeResults() { document.getElementById('resultsModal').style.display = 'none'; }
document.getElementById('menuToggle')?.addEventListener('click', function() { document.getElementById('sidebar')?.classList.toggle('collapsed'); });
</script>
</body>
</html>
