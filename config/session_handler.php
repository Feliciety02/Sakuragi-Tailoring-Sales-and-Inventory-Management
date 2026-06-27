<?php
// ✅ Set session configurations BEFORE session_start
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    session_start();
}

// ✅ Helper functions
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function get_user_role() {
    return $_SESSION['role_context'] ?? $_SESSION['role'] ?? null;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: /auth/login.php');
        exit();
    }
}

function redirect_if_logged_in() {
    if (is_logged_in()) {
        switch (get_user_role()) {
            case 'admin':
                header('Location: /dashboards/admin/dashboard.php');
                break;
            case 'manager':
            case 'employee':
            case 'operations_manager':
            case 'production_staff':
            case 'inventory_manager':
            case 'quality_control_inspector':
                $target = '/dashboards/employee/dashboard.php';
                $bootstrapPath = __DIR__ . '/../app/bootstrap.php';
                if (file_exists($bootstrapPath)) {
                    require_once $bootstrapPath;
                    if (isset($pdo) && function_exists('get_role_dashboard_home')) {
                        $target = get_role_dashboard_home($pdo, get_user_role(), (int) ($_SESSION['user_id'] ?? 0));
                    }
                }
                header('Location: ' . $target);
                break;
            case 'customer':
            default:
                header('Location: /dashboards/customer/dashboard.php');
                break;
        }
        exit();
    }
}
?>
