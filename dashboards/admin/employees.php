<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../app/Middleware/role_admin_only.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../app/Support/helpers.php';
require_once __DIR__ . '/../../app/Controllers/EmployeesController.php';
require_once __DIR__ . '/../../config/component_helpers.php';

try {
$stmt = $pdo->prepare("
    SELECT e.user_id AS employee_id, u.full_name, p.position_name, d.department_name,
           s.shift_name, st.status_name, e.hire_date, b.branch_name
    FROM employees e
    JOIN users u ON e.user_id = u.user_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN positions p ON e.position_id = p.position_id
    LEFT JOIN departments d ON p.department_id = d.department_id
    LEFT JOIN shifts s ON e.shift_id = s.shift_id
    LEFT JOIN statuses st ON e.status_id = st.status_id
    ORDER BY u.full_name ASC
");

$assignablePositionNames = get_assignable_position_names();
$positionPlaceholders = implode(',', array_fill(0, count($assignablePositionNames), '?'));
$positionsStmt = $pdo->prepare("SELECT position_id, position_name FROM positions WHERE position_name IN ($positionPlaceholders) ORDER BY position_name");
$positionsStmt->execute($assignablePositionNames);
$positions = $positionsStmt->fetchAll(PDO::FETCH_ASSOC);
$shifts = $pdo->query("SELECT shift_id, shift_name FROM shifts")->fetchAll(PDO::FETCH_ASSOC);
$statuses = $pdo->query("SELECT status_id, status_name FROM statuses")->fetchAll(PDO::FETCH_ASSOC);
$departments = $pdo->query("SELECT department_id, department_name FROM departments")->fetchAll(PDO::FETCH_ASSOC);

    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $result = [];
    error_log('DB error: ' . $e->getMessage());
}
$pageTitle = 'Manage Employees';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Employees — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <link rel="stylesheet" href="/public/assets/css/adminEmployee.css" />
</head>
<body data-role="admin">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
if (empty($result)):
  $tableContent = renderEmptyState('fas fa-users', 'No employees found', 'Add employees to manage the tailoring team.');
else:
  $cols = [
    ['field' => 'name', 'label' => 'Name'],
    ['field' => 'position', 'label' => 'Position'],
    ['field' => 'department', 'label' => 'Department'],
    ['field' => 'branch', 'label' => 'Branch'],
    ['field' => 'hire_date', 'label' => 'Hire Date'],
    ['field' => 'shift', 'label' => 'Shift'],
    ['field' => 'status', 'label' => 'Status', 'type' => 'badge'],
    ['field' => 'actions', 'label' => 'Actions', 'type' => 'actions'],
  ];
  $data = [];
  foreach ($result as $row):
    $sVariant = strtolower($row['status_name'] ?? 'active') === 'active' ? 'success' : 'neutral';
    $data[] = [
      'name' => htmlspecialchars($row['full_name']),
      'position' => htmlspecialchars($row['position_name'] ?? '—'),
      'department' => htmlspecialchars($row['department_name'] ?? '—'),
      'branch' => htmlspecialchars($row['branch_name'] ?? '—'),
      'hire_date' => $row['hire_date'] ? date('M d, Y', strtotime($row['hire_date'])) : '—',
      'shift' => htmlspecialchars($row['shift_name'] ?? '—'),
      'status' => $row['status_name'] ?? 'Active',
      'actions' => [
        ['label' => 'Edit', 'icon' => 'fas fa-pen', 'href' => '#', 'variant' => 'accent', 'onclick' => 'showEditEmployeeModal(' . $row['employee_id'] . ');return false'],
        ['label' => 'Delete', 'icon' => 'fas fa-trash', 'href' => '#', 'variant' => 'outline', 'onclick' => 'showDeleteEmployeeModal(' . $row['employee_id'] . ');return false'],
      ],
    ];
  endforeach;
  $tableContent = renderDataTable('employee-table', $cols, $data, [
    'searchable' => true, 'searchPlaceholder' => 'Search employee...',
    'actions' => [
      ['label' => 'Export CSV', 'icon' => 'fas fa-download', 'href' => '#', 'variant' => 'outline', 'onclick' => 'downloadCSV();return false'],
      ['label' => 'Add Employee', 'icon' => 'fas fa-user-plus', 'href' => '#', 'variant' => 'primary', 'onclick' => 'showAddEmployeeModal();return false'],
    ],
  ]);
endif;

$scriptsHtml = '';
ob_start(); ?>
<div id="addEmployeeModal" class="modal add">
  <div class="modal-content">
    <span class="close-btn" onclick="closeAddEmployeeModal()">×</span>
    <h2 class="modal-title">Add New Employee</h2>
    <p class="modal-subtext">Fill in the fields to create a new employee profile.</p>
    <form method="POST" action="../../app/Controllers/EmployeesController.php">
      <input type="hidden" name="action" value="add">
      <label>Full Name</label>
      <input type="text" name="full_name" placeholder="e.g. Jane Doe" required>
      <label>Position</label>
      <select name="position_id" required>
        <option value="">Select Position</option>
        <?php foreach ($positions as $pos): ?>
        <option value="<?= $pos['position_id'] ?>"><?= htmlspecialchars($pos['position_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Department</label>
      <select name="department_id" required>
        <option value="">Select Department</option>
        <?php foreach ($departments as $dept): ?>
        <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Branch</label>
      <select name="branch_name" required>
        <option value="">Select</option>
        <option value="Main">Main</option>
        <option value="Davao">Davao</option>
        <option value="Kidapawan">Kidapawan</option>
        <option value="Tagum">Tagum</option>
      </select>
      <label>Shift</label>
      <select name="shift_id" required>
        <option value="">Select Shift</option>
        <?php foreach ($shifts as $shift): ?>
        <option value="<?= $shift['shift_id'] ?>"><?= htmlspecialchars($shift['shift_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Status</label>
      <select name="status_id" required>
        <option value="">Select Status</option>
        <?php foreach ($statuses as $st): ?>
        <option value="<?= $st['status_id'] ?>"><?= htmlspecialchars($st['status_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Email</label>
      <input type="email" name="email" placeholder="e.g. jane@sakuragi.com" required>
      <label>Temporary Password</label>
      <input type="password" name="password" placeholder="Min. 8 characters" required minlength="8">
      <div class="modal-actions">
        <button type="button" class="dash-btn dash-btn-outline dash-btn-sm" onclick="closeAddEmployeeModal()">Cancel</button>
        <button type="submit" class="dash-btn dash-btn-primary dash-btn-sm">Add Employee</button>
      </div>
    </form>
  </div>
</div>

<div id="editEmployeeModal" class="modal edit">
  <div class="modal-content">
    <span class="close-btn" onclick="closeEditEmployeeModal()">×</span>
    <h2 class="modal-title">Edit Employee</h2>
    <p class="modal-subtext">Update employee information.</p>
    <form method="POST" action="../../app/Controllers/EmployeesController.php">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="employee_id" id="editEmployeeId">
      <label>Position</label>
      <select name="position_id" id="editPositionId">
        <?php foreach ($positions as $pos): ?>
        <option value="<?= $pos['position_id'] ?>"><?= htmlspecialchars($pos['position_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Shift</label>
      <select name="shift_id" id="editShiftId">
        <?php foreach ($shifts as $shift): ?>
        <option value="<?= $shift['shift_id'] ?>"><?= htmlspecialchars($shift['shift_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Status</label>
      <select name="status_id" id="editStatusId">
        <?php foreach ($statuses as $st): ?>
        <option value="<?= $st['status_id'] ?>"><?= htmlspecialchars($st['status_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="modal-actions">
        <button type="button" class="dash-btn dash-btn-outline dash-btn-sm" onclick="closeEditEmployeeModal()">Cancel</button>
        <button type="submit" class="dash-btn dash-btn-primary dash-btn-sm">Update Employee</button>
      </div>
    </form>
  </div>
</div>

<div id="deleteEmployeeModal" class="modal delete">
  <div class="modal-content">
    <span class="close-btn" onclick="closeDeleteEmployeeModal()">×</span>
    <h2 class="modal-title">Delete Employee</h2>
    <p class="modal-subtext" id="deleteEmployeeName"></p>
    <form method="POST" action="../../app/Controllers/EmployeesController.php">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="employee_id" id="deleteEmployeeId">
      <div class="modal-actions">
        <button type="button" class="dash-btn dash-btn-outline dash-btn-sm" onclick="closeDeleteEmployeeModal()">Cancel</button>
        <button type="submit" class="dash-btn dash-btn-danger dash-btn-sm">Delete</button>
      </div>
    </form>
  </div>
</div>

<script src="/public/assets/js/adminEmployee.js"></script>
<script>
document.getElementById('menuToggle')?.addEventListener('click', function() { document.getElementById('sidebar')?.classList.toggle('collapsed'); });
</script>
<?php $scriptsHtml = ob_get_clean();

echo renderDashboardShell(
  renderPageHeader('Manage Employees', 'Assignable operational roles: Operations Manager, Tailor / Production Staff, Inventory Manager, and Quality Control Inspector.'),
  '',
  $tableContent . $scriptsHtml
);
?>
</div>
</div>
</body>
</html>
