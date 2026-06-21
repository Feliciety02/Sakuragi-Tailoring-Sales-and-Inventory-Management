<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../Support/helpers.php';

$branchMap = [
    'Main' => 1,
    'Davao' => 2,
    'Kidapawan' => 3,
    'Tagum' => 4,
];

// DELETE EMPLOYEE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'delete') {
    $employeeId = $_POST['employee_id'];

    try {
        $pdo->beginTransaction();

        $pdo->prepare("DELETE FROM employees WHERE user_id = ?")->execute([$employeeId]);
        $pdo->prepare("UPDATE users SET role = 'customer' WHERE user_id = ?")->execute([$employeeId]);

        $pdo->commit();
        $_SESSION['success_message'] = 'Employee deleted and reverted to customer.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Error deleting employee: ' . $e->getMessage();
    }

    header('Location: ../../dashboards/admin/employees.php');
    exit();
}

// ADD EMPLOYEE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add') {
    $fullName       = $_POST['full_name'];
    $positionId     = (int) $_POST['position_id'];
    $departmentId   = $_POST['department_id']; // for form validation only
    $branchName     = $_POST['branch_name'];
    $shiftId        = $_POST['shift_id'];
    $statusId       = $_POST['status_id'];

    $branchId = $branchMap[$branchName] ?? null;
    $role = get_role_from_position_id($pdo, $positionId);
    $email    = strtolower(str_replace(' ', '', $fullName)) . rand(100, 999) . '@sakuragi.com';
    $password = password_hash('123456', PASSWORD_DEFAULT);
    $phone    = '09' . rand(100000000, 999999999);

    if (!$role || !in_array($role, [
        ROLE_OPERATIONS_MANAGER,
        ROLE_PRODUCTION_STAFF,
        ROLE_INVENTORY_MANAGER,
        ROLE_QUALITY_CONTROL_INSPECTOR,
    ], true)) {
        $_SESSION['error_message'] = 'Selected position is not allowed in the new role model.';
        header('Location: ../../dashboards/admin/employees.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO users (branch_id, full_name, email, password, phone_number, role, status)
                               VALUES (?, ?, ?, ?, ?, ?, 'Active')");
        $stmt->execute([$branchId, $fullName, $email, $password, $phone, $role]);
        $userId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO employees (user_id, branch_id, position_id, shift_id, status_id, hire_date, salary)
                               VALUES (?, ?, ?, ?, ?, CURDATE(), 0)");
        $stmt->execute([$userId, $branchId, $positionId, $shiftId, $statusId]);

        $pdo->commit();
        $_SESSION['success_message'] = 'Employee added successfully.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Error adding employee: ' . $e->getMessage();
    }

    header('Location: ../../dashboards/admin/employees.php');
    exit();
}

// EDIT EMPLOYEE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'edit') {
    $employeeId     = $_POST['employee_id'];
    $fullName       = $_POST['full_name'];
    $positionId     = (int) $_POST['position_id'];
    $departmentId   = $_POST['department_id']; // optional if needed later
    $branchName     = $_POST['branch_name'];
    $shiftId        = $_POST['shift_id'];
    $statusId       = $_POST['status_id'];

    $branchId = $branchMap[$branchName] ?? null;
    $role = get_role_from_position_id($pdo, $positionId);

    if (!$role || !in_array($role, [
        ROLE_OPERATIONS_MANAGER,
        ROLE_PRODUCTION_STAFF,
        ROLE_INVENTORY_MANAGER,
        ROLE_QUALITY_CONTROL_INSPECTOR,
    ], true)) {
        $_SESSION['error_message'] = 'Selected position is not allowed in the new role model.';
        header('Location: ../../dashboards/admin/employees.php');
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE employees
                               SET position_id = ?, shift_id = ?, status_id = ?, branch_id = ?
                               WHERE user_id = ?");
        $stmt->execute([$positionId, $shiftId, $statusId, $branchId, $employeeId]);

        $stmt = $pdo->prepare("UPDATE users SET full_name = ?, branch_id = ?, role = ? WHERE user_id = ?");
        $stmt->execute([$fullName, $branchId, $role, $employeeId]);

        $pdo->commit();
        $_SESSION['success_message'] = 'Employee updated successfully.';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Error updating employee: ' . $e->getMessage();
    }

    header('Location: ../../dashboards/admin/employees.php');
    exit();
}
