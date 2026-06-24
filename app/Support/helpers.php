<?php

require_once __DIR__ . '/../../config/constants.php';

// Sanitize input (prevent XSS)
function sanitize_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Hash password
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Flash message (Optional: use for success/error messages)
function set_flash($key, $message) {
    $_SESSION['flash'][$key] = $message;
}

function get_flash($key) {
    if (isset($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }
    return null;
}

// CSRF Protection
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
}

function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!validate_csrf_token($token)) {
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
}

function normalize_user_role(string $role, ?string $positionName = null): string {
    $role = trim(strtolower($role));
    $positionName = trim(strtolower((string) $positionName));

    if (in_array($role, [
        ROLE_ADMIN,
        ROLE_CUSTOMER,
        ROLE_OPERATIONS_MANAGER,
        ROLE_PRODUCTION_STAFF,
        ROLE_INVENTORY_MANAGER,
        ROLE_QUALITY_CONTROL_INSPECTOR,
    ], true)) {
        return $role;
    }

    if ($role === ROLE_MANAGER) {
        return ROLE_OPERATIONS_MANAGER;
    }

    if ($role === ROLE_EMPLOYEE) {
        return match ($positionName) {
            'quality control inspector' => ROLE_QUALITY_CONTROL_INSPECTOR,
            'inventory clerk', 'inventory manager' => ROLE_INVENTORY_MANAGER,
            'operations manager', 'floor supervisor', 'shop assistant', 'admin assistant', 'hr staff', 'accountant' => ROLE_OPERATIONS_MANAGER,
            default => ROLE_PRODUCTION_STAFF,
        };
    }

    return ROLE_CUSTOMER;
}

function get_assignable_employee_positions(): array {
    return [
        'Operations Manager' => ROLE_OPERATIONS_MANAGER,
        'Tailor / Production Staff' => ROLE_PRODUCTION_STAFF,
        'Inventory Manager' => ROLE_INVENTORY_MANAGER,
        'Quality Control Inspector' => ROLE_QUALITY_CONTROL_INSPECTOR,
    ];
}

function get_assignable_position_names(): array {
    return array_keys(get_assignable_employee_positions());
}

function get_role_from_position_name(string $positionName): string {
    $positionRoleMap = get_assignable_employee_positions();
    return $positionRoleMap[$positionName] ?? normalize_user_role(ROLE_EMPLOYEE, $positionName);
}

function get_role_from_position_id(PDO $pdo, int $positionId): ?string {
    static $cache = [];
    if (isset($cache[$positionId])) {
        return $cache[$positionId];
    }

    $stmt = $pdo->prepare("SELECT position_name FROM positions WHERE position_id = ?");
    $stmt->execute([$positionId]);
    $positionName = $stmt->fetchColumn();
    if (!$positionName) {
        return $cache[$positionId] = null;
    }

    return $cache[$positionId] = get_role_from_position_name((string) $positionName);
}

function get_user_position_context(PDO $pdo, ?int $userId = null): array {
    $userId = $userId ?? (int) ($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) {
        return [
            'position_id' => 0,
            'position_name' => '',
            'role' => ROLE_PRODUCTION_STAFF,
            'sidebar' => 'employee',
            'dashboard' => '/dashboards/employee/dashboard.php',
        ];
    }

    static $cache = [];
    if (isset($cache[$userId])) {
        return $cache[$userId];
    }

    $stmt = $pdo->prepare("
        SELECT e.position_id, p.position_name
        FROM employees e
        LEFT JOIN positions p ON e.position_id = p.position_id
        WHERE e.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $position = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['position_id' => 0, 'position_name' => ''];

    $positionName = (string) ($position['position_name'] ?? '');
    $normalizedRole = normalize_user_role((string) ($_SESSION['base_role'] ?? $_SESSION['role'] ?? ROLE_EMPLOYEE), $positionName);

    $sidebar = match ($normalizedRole) {
        ROLE_QUALITY_CONTROL_INSPECTOR => 'qc_inspector',
        ROLE_OPERATIONS_MANAGER => 'operations_manager',
        ROLE_INVENTORY_MANAGER => 'inventory_manager',
        default => 'employee',
    };

    $dashboard = match ($normalizedRole) {
        ROLE_QUALITY_CONTROL_INSPECTOR => '/dashboards/employee/employeePosition/qcInspector/dashboard.php',
        ROLE_INVENTORY_MANAGER => '/dashboards/admin/inventory.php',
        ROLE_OPERATIONS_MANAGER => '/dashboards/admin/production_board.php',
        default => '/dashboards/employee/dashboard.php',
    };

    return $cache[$userId] = [
        'position_id' => (int) ($position['position_id'] ?? 0),
        'position_name' => $positionName,
        'role' => $normalizedRole,
        'sidebar' => $sidebar,
        'dashboard' => $dashboard,
    ];
}

function get_role_dashboard_home(PDO $pdo, ?string $role = null, ?int $userId = null): string {
    $role = $role ?? (string) ($_SESSION['role'] ?? '');

    switch ($role) {
        case ROLE_ADMIN:
            return '/dashboards/admin/dashboard.php';
        case ROLE_CUSTOMER:
            return '/dashboards/customer/dashboard.php';
        case ROLE_OPERATIONS_MANAGER:
            return '/dashboards/admin/production_board.php';
        case ROLE_PRODUCTION_STAFF:
            return '/dashboards/employee/dashboard.php';
        case ROLE_INVENTORY_MANAGER:
            return '/dashboards/admin/inventory.php';
        case ROLE_QUALITY_CONTROL_INSPECTOR:
            return '/dashboards/employee/employeePosition/qcInspector/dashboard.php';
        case ROLE_MANAGER:
        case ROLE_EMPLOYEE:
            return get_user_position_context($pdo, $userId)['dashboard'];
        default:
            return '/auth/login.php';
    }
}

function get_role_sidebar_view(PDO $pdo, ?string $role = null, ?int $userId = null): string {
    $role = $role ?? (string) ($_SESSION['role'] ?? '');

    switch ($role) {
        case ROLE_ADMIN:
            return __DIR__ . '/../Views/Shared/Sidebars/admin.php';
        case ROLE_CUSTOMER:
            return __DIR__ . '/../Views/Shared/Sidebars/customer.php';
        case ROLE_OPERATIONS_MANAGER:
            return __DIR__ . '/../Views/Shared/Sidebars/operations_manager.php';
        case ROLE_INVENTORY_MANAGER:
            return __DIR__ . '/../Views/Shared/Sidebars/inventory_manager.php';
        case ROLE_QUALITY_CONTROL_INSPECTOR:
            return __DIR__ . '/../Views/Shared/Sidebars/qc_inspector.php';
        case ROLE_PRODUCTION_STAFF:
            return __DIR__ . '/../Views/Shared/Sidebars/employee.php';
        case ROLE_MANAGER:
        case ROLE_EMPLOYEE:
            $sidebar = get_user_position_context($pdo, $userId)['sidebar'];
            return __DIR__ . '/../Views/Shared/Sidebars/' . $sidebar . '.php';
        default:
            return __DIR__ . '/../Views/Shared/Sidebars/customer.php';
    }
}

function get_nav_items_for_role(PDO $pdo, ?string $role = null, ?int $userId = null): array {
    $role = $role ?? (string) ($_SESSION['role'] ?? '');
    $userId = $userId ?? (int) ($_SESSION['user_id'] ?? 0);

    // Normalize legacy roles
    if (in_array($role, [ROLE_MANAGER, ROLE_EMPLOYEE], true)) {
        $ctx = get_user_position_context($pdo, $userId);
        $role = $ctx['role'];
    }

    $dashAdmin = fn(string $p) => '/dashboards/admin/' . $p;
    $dashEmp = fn(string $p) => '/dashboards/employee/' . $p;

    $groups = [
        ROLE_ADMIN => [
            ['type' => 'link', 'href' => $dashAdmin('dashboard.php'), 'icon' => 'fas fa-th-large', 'label' => 'Dashboard'],
            ['type' => 'group', 'id' => 'admin-production', 'icon' => 'fas fa-columns', 'label' => 'Production', 'children' => [
                ['href' => $dashAdmin('production_board.php'), 'icon' => 'fas fa-columns', 'label' => 'Production Board'],
                ['href' => $dashAdmin('orders.php'), 'icon' => 'fas fa-shopping-bag', 'label' => 'Orders Queue'],
                ['href' => $dashAdmin('sample_approvals.php'), 'icon' => 'fas fa-flask', 'label' => 'Sample Approvals'],
                ['href' => $dashAdmin('production_schedule.php'), 'icon' => 'fas fa-calendar-alt', 'label' => 'Schedule'],
                ['href' => $dashAdmin('workload.php'), 'icon' => 'fas fa-tasks', 'label' => 'Workload'],
            ]],
            ['type' => 'group', 'id' => 'admin-quality', 'icon' => 'fas fa-clipboard-check', 'label' => 'Quality Control', 'children' => [
                ['href' => $dashAdmin('quality_control.php'), 'icon' => 'fas fa-clipboard-check', 'label' => 'QC Dashboard'],
                ['href' => $dashAdmin('aql_qc.php'), 'icon' => 'fas fa-chart-pie', 'label' => 'AQL Sampling QC'],
            ]],
            ['type' => 'group', 'id' => 'admin-analytics', 'icon' => 'fas fa-chart-bar', 'label' => 'Reports', 'children' => [
                ['href' => $dashAdmin('reports.php'), 'icon' => 'fas fa-chart-bar', 'label' => 'Reports &amp; Analytics'],
                ['href' => $dashAdmin('production_analytics.php'), 'icon' => 'fas fa-chart-line', 'label' => 'Analytics'],
            ]],
            ['type' => 'group', 'id' => 'admin-resources', 'icon' => 'fas fa-box', 'label' => 'Inventory &amp; Materials', 'children' => [
                ['href' => $dashAdmin('inventory.php'), 'icon' => 'fas fa-box', 'label' => 'Inventory'],
                ['href' => $dashAdmin('order_materials.php'), 'icon' => 'fas fa-roll', 'label' => 'Order Materials'],
            ]],
            ['type' => 'group', 'id' => 'admin-team', 'icon' => 'fas fa-users', 'label' => 'Team &amp; Settings', 'children' => [
                ['href' => $dashAdmin('employees.php'), 'icon' => 'fas fa-users', 'label' => 'Employees'],
                ['href' => $dashAdmin('settings.php'), 'icon' => 'fas fa-cog', 'label' => 'Settings'],
            ]],
        ],
        ROLE_CUSTOMER => [
            ['type' => 'link', 'href' => '/dashboards/customer/dashboard.php', 'icon' => 'fas fa-home', 'label' => 'Home'],
            ['type' => 'group', 'id' => 'cust-orders', 'icon' => 'fas fa-shopping-bag', 'label' => 'My Orders', 'children' => [
                ['href' => '/dashboards/customer/place_order.php', 'icon' => 'fas fa-plus-circle', 'label' => 'Place New Order'],
                ['href' => '/dashboards/customer/my_orders.php', 'icon' => 'fas fa-folder-open', 'label' => 'My Orders'],
                ['href' => '/dashboards/customer/sample_review.php', 'icon' => 'fas fa-flask', 'label' => 'Sample Approvals'],
            ]],
            ['type' => 'group', 'id' => 'cust-settings', 'icon' => 'fas fa-cog', 'label' => 'Settings', 'children' => [
                ['href' => '/dashboards/customer/notifications.php', 'icon' => 'fas fa-bell', 'label' => 'Notifications'],
                ['href' => '/dashboards/customer/account.php', 'icon' => 'fas fa-user-cog', 'label' => 'My Account'],
            ]],
        ],
        ROLE_PRODUCTION_STAFF => [
            ['type' => 'link', 'href' => $dashEmp('dashboard.php'), 'icon' => 'fas fa-th-large', 'label' => 'Dashboard'],
            ['type' => 'group', 'id' => 'emp-tasks', 'icon' => 'fas fa-tasks', 'label' => 'My Work', 'children' => [
                ['href' => $dashEmp('my_tasks.php'), 'icon' => 'fas fa-tasks', 'label' => 'My Tasks'],
                ['href' => $dashEmp('kanban.php'), 'icon' => 'fas fa-columns', 'label' => 'Kanban Board'],
                ['href' => $dashEmp('assigned_orders.php'), 'icon' => 'fas fa-clipboard-list', 'label' => 'Assigned Orders'],
                ['href' => $dashEmp('completed_tasks.php'), 'icon' => 'fas fa-check-circle', 'label' => 'Completed Tasks'],
            ]],
            ['type' => 'group', 'id' => 'emp-tools', 'icon' => 'fas fa-toolbox', 'label' => 'Tools', 'children' => [
                ['href' => $dashAdmin('inventory.php'), 'icon' => 'fas fa-box', 'label' => 'Inventory'],
                ['href' => $dashEmp('garment_tracking.php'), 'icon' => 'fas fa-shirt', 'label' => 'Garment Tracking'],
                ['href' => $dashEmp('profile.php'), 'icon' => 'fas fa-user', 'label' => 'Profile'],
            ]],
        ],
        ROLE_OPERATIONS_MANAGER => [
            ['type' => 'link', 'href' => $dashAdmin('dashboard.php'), 'icon' => 'fas fa-th-large', 'label' => 'Dashboard'],
            ['type' => 'group', 'id' => 'ops-production', 'icon' => 'fas fa-columns', 'label' => 'Production', 'children' => [
                ['href' => $dashAdmin('production_board.php'), 'icon' => 'fas fa-columns', 'label' => 'Production Board'],
                ['href' => $dashAdmin('orders.php'), 'icon' => 'fas fa-shopping-bag', 'label' => 'Orders Queue'],
                ['href' => $dashAdmin('sample_approvals.php'), 'icon' => 'fas fa-flask', 'label' => 'Sample Approvals'],
                ['href' => $dashAdmin('production_schedule.php'), 'icon' => 'fas fa-calendar-alt', 'label' => 'Scheduling'],
            ]],
            ['type' => 'group', 'id' => 'ops-workforce', 'icon' => 'fas fa-users', 'label' => 'Workforce', 'children' => [
                ['href' => $dashAdmin('workload.php'), 'icon' => 'fas fa-tasks', 'label' => 'Workload'],
                ['href' => $dashAdmin('production_analytics.php'), 'icon' => 'fas fa-chart-line', 'label' => 'Team Performance'],
                ['href' => $dashAdmin('reports.php'), 'icon' => 'fas fa-chart-bar', 'label' => 'Reports &amp; Analytics'],
            ]],
            ['type' => 'link', 'href' => $dashEmp('profile.php'), 'icon' => 'fas fa-user', 'label' => 'Profile'],
        ],
        ROLE_INVENTORY_MANAGER => [
            ['type' => 'link', 'href' => $dashAdmin('dashboard.php'), 'icon' => 'fas fa-th-large', 'label' => 'Dashboard'],
            ['type' => 'group', 'id' => 'inv-stock', 'icon' => 'fas fa-box', 'label' => 'Stock Management', 'children' => [
                ['href' => $dashAdmin('inventory.php'), 'icon' => 'fas fa-box', 'label' => 'Stock Levels'],
                ['href' => $dashAdmin('order_materials.php'), 'icon' => 'fas fa-roll', 'label' => 'Material Reservations'],
            ]],
            ['type' => 'link', 'href' => $dashEmp('profile.php'), 'icon' => 'fas fa-user', 'label' => 'Profile'],
        ],
        ROLE_QUALITY_CONTROL_INSPECTOR => [
            ['type' => 'link', 'href' => '/dashboards/employee/employeePosition/qcInspector/dashboard.php', 'icon' => 'fas fa-clipboard-check', 'label' => 'QC Dashboard'],
            ['type' => 'link', 'href' => '/dashboards/employee/employeePosition/qcInspector/aql_dashboard.php', 'icon' => 'fas fa-chart-pie', 'label' => 'AQL Lot Inspection'],
            ['type' => 'group', 'id' => 'qc-inspections', 'icon' => 'fas fa-search', 'label' => 'Inspections', 'children' => [
                ['href' => '/dashboards/employee/employeePosition/qcInspector/inspect.php', 'icon' => 'fas fa-search', 'label' => 'Inspect Items'],
                ['href' => '/dashboards/employee/employeePosition/qcInspector/history.php', 'icon' => 'fas fa-history', 'label' => 'Inspection History'],
            ]],
            ['type' => 'link', 'href' => $dashEmp('my_tasks.php'), 'icon' => 'fas fa-tasks', 'label' => 'My Tasks'],
            ['type' => 'link', 'href' => $dashEmp('profile.php'), 'icon' => 'fas fa-user', 'label' => 'Profile'],
        ],
    ];

    // Senior tailor shares employee nav but override Dashboard
    if (isset($_SESSION['position_name']) && strtolower($_SESSION['position_name']) === 'senior tailor') {
        return [
            ['type' => 'link', 'href' => '/dashboards/employee/employeePosition/seniorTailor/dashboard.php', 'icon' => 'fas fa-home', 'label' => 'Dashboard'],
            ['type' => 'group', 'id' => 'st-records', 'icon' => 'fas fa-clipboard-check', 'label' => 'Records', 'children' => [
                ['href' => '/dashboards/employee/employeePosition/seniorTailor/inspectRecords.php', 'icon' => 'fas fa-clipboard-check', 'label' => 'Inspection Records'],
                ['href' => '/dashboards/employee/employeePosition/seniorTailor/item-to-inspect.php', 'icon' => 'fas fa-search', 'label' => 'Items to Inspect'],
                ['href' => '/dashboards/employee/employeePosition/seniorTailor/rejection-Reports.php', 'icon' => 'fas fa-exclamation-triangle', 'label' => 'Rejection Reports'],
            ]],
        ];
    }

    return $groups[$role] ?? $groups[ROLE_CUSTOMER];
}

function render_role_sidebar(PDO $pdo, ?string $role = null, ?int $userId = null): void {
    $role = $role ?? ($_SESSION['role'] ?? '');
    $userId = $userId ?? ($_SESSION['user_id'] ?? 0);
    $navItems = get_nav_items_for_role($pdo, $role, $userId);

    $userName = $_SESSION['full_name'] ?? 'User';
    $roleLabel = $_SESSION['role_name'] ?? $role;
    $branch = $_SESSION['branch_name'] ?? '';
    $initials = strtoupper(substr($userName, 0, 2));

    require __DIR__ . '/../Views/Shared/sidebar.php';
}
?>
