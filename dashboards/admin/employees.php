<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../app/Middleware/role_admin_only.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../app/Support/helpers.php';

require_once __DIR__ . '/../../app/Controllers/EmployeesController.php';


// Fetch employee list
try {
$stmt = $pdo->prepare("
    SELECT 
        e.user_id AS employee_id,
        u.full_name,
        p.position_name,
        d.department_name,
        s.shift_name,
        st.status_name,
        e.hire_date,
        b.branch_name
    FROM employees e
    JOIN users u ON e.user_id = u.user_id
    LEFT JOIN branches b ON e.branch_id = b.branch_id
    LEFT JOIN positions p ON e.position_id = p.position_id
    LEFT JOIN departments d ON p.department_id = d.department_id
    LEFT JOIN shifts s ON e.shift_id = s.shift_id
    LEFT JOIN statuses st ON e.status_id = st.status_id
    ORDER BY u.full_name ASC
");

// Load dropdown data from DB
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Employees — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/adminEmployee.css" />
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/admin.php'; ?>
  <div class="dash-main">
    <?php require_once '../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
  <h1>Manage Employees</h1>
  <p class="text-muted" style="margin:0 0 18px;">Assignable operational roles: Operations Manager, Tailor / Production Staff, Inventory Manager, and Quality Control Inspector.</p>
  
  <div class="table-controls">
<div class="search-wrapper">
  <input type="text" id="employeeSearch" placeholder="Search employee..." onkeyup="filterEmployeeTable()" />
</div>

<button onclick="downloadCSV()" class="btn-export">
  <i class="fas fa-download"></i> Export CSV
</button>

    <button onclick="showAddEmployeeModal()" class="btn-export">
      <i class="fas fa-user-plus"></i> Add Employee
    </button>
  </div>

  <div class="table-responsive">
    <table id="employeeTable">
      <thead>
        <tr>
          <th>Name</th><th>Position</th><th>Department</th><th>Branch</th>
          <th>Hire Date</th><th>Shift</th><th>Status</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($result):
            foreach ($result as $row): ?>
         <tr data-employee-id="<?= $row['employee_id'] ?>">
          <td><?= htmlspecialchars($row['full_name']) ?></td>
          <td><?= htmlspecialchars($row['position_name']) ?></td>
          <td><?= htmlspecialchars($row['department_name']) ?></td>
          <td><?= htmlspecialchars($row['branch_name']) ?></td>
          <td><?= htmlspecialchars($row['hire_date']) ?></td>
          <td><?= htmlspecialchars($row['shift_name']) ?></td>
          <td><span class="status <?= strtolower($row['status_name']) ?>"><?= $row['status_name'] ?></span></td>
          <td class="action-buttons">
            <button class="edit" onclick="showEditEmployeeModal(<?= $row['employee_id'] ?>)">
              <i class="fas fa-pen"></i>
            </button>
            <button class="delete" onclick="showDeleteEmployeeModal(<?= $row['employee_id'] ?>)">
              <i class="fas fa-trash"></i>
            </button>
          </td>
        </tr>

        <?php endforeach;
        else:
             ?>
          <tr><td colspan="8" style="text-align:center;">No employees found.</td></tr>
        <?php
        endif; ?>
      </tbody>
    </table>
  </div>
</div>


<!-- Add Modal -->
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
  <?php foreach ($statuses as $status): ?>
    <option value="<?= $status['status_id'] ?>"><?= htmlspecialchars($status['status_name']) ?></option>
  <?php endforeach; ?>
</select>

      <div class="modal-button-group">
        <button type="submit" class="btn-primary">Save</button>
        <button type="button" onclick="closeAddEmployeeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editEmployeeModal" class="modal edit">
  <div class="modal-content">
    <span class="close-btn" onclick="closeEditEmployeeModal()">×</span>
    <h2 class="modal-title">Edit Employee</h2>
    <form method="POST" action="../../app/Controllers/EmployeesController.php">
  <input type="hidden" name="action" value="edit">
  <input type="hidden" id="editEmployeeId" name="employee_id">

  <label>Full Name</label>
  <input type="text" id="editFullName" name="full_name" required>

  <label>Position</label>
  <select id="editPositionId" name="position_id" required>
    <option value="">Select Position</option>
    <?php foreach ($positions as $pos): ?>
      <option value="<?= $pos['position_id'] ?>"><?= htmlspecialchars($pos['position_name']) ?></option>
    <?php endforeach; ?>
  </select>

  <label>Department</label>
  <select id="editDepartmentId" name="department_id" required>
    <option value="">Select Department</option>
    <?php foreach ($departments as $dept): ?>
      <option value="<?= $dept['department_id'] ?>"><?= htmlspecialchars($dept['department_name']) ?></option>
    <?php endforeach; ?>
  </select>

  <label>Branch</label>
  <select id="editBranch" name="branch_name" required>
    <option value="Main">Main</option>
    <option value="Davao">Davao</option>
    <option value="Kidapawan">Kidapawan</option>
    <option value="Tagum">Tagum</option>
  </select>

  <label>Shift</label>
  <select id="editShiftId" name="shift_id" required>
    <option value="">Select Shift</option>
    <?php foreach ($shifts as $shift): ?>
      <option value="<?= $shift['shift_id'] ?>"><?= htmlspecialchars($shift['shift_name']) ?></option>
    <?php endforeach; ?>
  </select>

  <label>Status</label>
  <select id="editStatusId" name="status_id" required>
    <option value="">Select Status</option>
    <?php foreach ($statuses as $status): ?>
      <option value="<?= $status['status_id'] ?>"><?= htmlspecialchars($status['status_name']) ?></option>
    <?php endforeach; ?>
  </select>

  <div class="modal-button-group">
    <button type="submit" class="btn-primary">Update</button>
    <button type="button" onclick="closeEditEmployeeModal()">Cancel</button>
  </div>
</form>

  </div>
</div>

<!-- Delete Modal -->
<div id="deleteEmployeeModal" class="modal delete">
  <div class="modal-content">
    <span class="close-btn" onclick="closeDeleteEmployeeModal()">×</span>
    <h2 class="modal-title">Confirm Deletion</h2>
    <form method="POST" action="../../app/Controllers/EmployeesController.php">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" id="deleteEmployeeId" name="employee_id">
      <p>This will remove the employee and revert their account to customer. Proceed?</p>
      <div class="modal-button-group">
        <button type="submit" class="btn-primary">Delete</button>
        <button type="button" onclick="closeDeleteEmployeeModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

  </div>
</div>

<script>
function showAddEmployeeModal() {
  document.getElementById('addEmployeeModal').style.display = 'flex';
}
function closeAddEmployeeModal() {
  document.getElementById('addEmployeeModal').style.display = 'none';
}
function showEditEmployeeModal(id) {
  const row = document.querySelector(`tr[data-employee-id="${id}"]`);

  document.getElementById('editEmployeeId').value = id;
  document.getElementById('editFullName').value = row.cells[0].textContent.trim();

  // Match by displayed text, then select corresponding <option>
  setSelectByText('editPositionId', row.cells[1].textContent.trim());
  setSelectByText('editDepartmentId', row.cells[2].textContent.trim());

  setSelectByText('editShiftId', row.cells[5].textContent.trim());
  setSelectByText('editStatusId', row.cells[6].textContent.trim());

  document.getElementById('editEmployeeModal').style.display = 'flex';
}

function setSelectByText(selectId, text) {
  const select = document.getElementById(selectId);
  for (let i = 0; i < select.options.length; i++) {
    if (select.options[i].text.trim() === text) {
      select.selectedIndex = i;
      break;
    }
  }
}


function closeEditEmployeeModal() {
  document.getElementById('editEmployeeModal').style.display = 'none';
}
function showDeleteEmployeeModal(id) {
  document.getElementById('deleteEmployeeId').value = id;
  document.getElementById('deleteEmployeeModal').style.display = 'flex';
}
function closeDeleteEmployeeModal() {
  document.getElementById('deleteEmployeeModal').style.display = 'none';
}

function filterEmployeeTable() {
  const input = document.getElementById("employeeSearch").value.toLowerCase();
  const rows = document.querySelectorAll("#employeeTable tbody tr");
  rows.forEach(row => {
    row.style.display = row.innerText.toLowerCase().includes(input) ? "" : "none";
  });
}
function sortTableByColumn(index) {
  const table = document.getElementById("employeeTable");
  const rows = Array.from(table.rows).slice(1); // skip header
  const asc = table.dataset.sortDir !== "asc";
  rows.sort((a, b) => {
    return asc
      ? a.cells[index].innerText.localeCompare(b.cells[index].innerText)
      : b.cells[index].innerText.localeCompare(a.cells[index].innerText);
  });
  rows.forEach(row => table.tBodies[0].appendChild(row));
  table.dataset.sortDir = asc ? "asc" : "desc";
}

function downloadCSV() {
const table = document.querySelector("#employeeTable"); // ✅ Correct for employees
  const rows = Array.from(table.querySelectorAll("tbody tr"));

  let csvContent = "data:text/csv;charset=utf-8,";

  const headers = Array.from(table.querySelectorAll("thead th"))
    .map(th => `"${th.textContent.trim()}"`).slice(0, 6);
  csvContent += headers.join(",") + "\\r\\n";

  rows.forEach(row => {
    const cols = Array.from(row.querySelectorAll("td")).slice(0, 6);
    const line = cols.map(td => `"${td.textContent.trim()}"`).join(",");
    csvContent += line + "\\r\\n";
  });

  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "employee_data.csv");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}


</script>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>
