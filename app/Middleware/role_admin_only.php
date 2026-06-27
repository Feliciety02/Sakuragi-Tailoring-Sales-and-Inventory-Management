<?php

require_once __DIR__ . '/../bootstrap.php';
// Check if user is logged in first
if (!is_logged_in()) {
    header('Location: /auth/login.php');
    exit();
}

$role = get_user_role();
if ($role === ROLE_ADMIN) {
    return;
}

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$operationsManagerPages = [
    'production_board.php',
    'orders.php',
    'sample_approvals.php',
    'production_schedule.php',
    'workload.php',
    'production_analytics.php',
    'reports.php',
];
$inventoryManagerPages = [
    'inventory.php',
    'order_materials.php',
    'order_materials_ajax.php',
];

$isAllowed = ($role === ROLE_OPERATIONS_MANAGER && in_array($currentScript, $operationsManagerPages, true))
    || ($role === ROLE_INVENTORY_MANAGER && in_array($currentScript, $inventoryManagerPages, true));

if (!$isAllowed) {
    header('Location: ' . get_role_dashboard_home($pdo, $role, (int) ($_SESSION['user_id'] ?? 0)));
    exit();
}
?>
