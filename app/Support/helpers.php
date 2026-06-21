<?php

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

function render_role_sidebar(PDO $pdo, ?string $role = null, ?int $userId = null): void {
    require get_role_sidebar_view($pdo, $role, $userId);
}
?>
