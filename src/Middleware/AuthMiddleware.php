<?php
namespace Sakuragi\Middleware;

class AuthMiddleware
{
    public static function requireAuth(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            header('Location: /auth/login.php');
            exit();
        }
    }

    public static function requireRole(string ...$roles): void
    {
        self::requireAuth();

        $userRole = $_SESSION['role'] ?? '';
        if (!in_array($userRole, $roles, true)) {
            http_response_code(403);
            die('Access denied: insufficient permissions');
        }
    }

    public static function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
}
