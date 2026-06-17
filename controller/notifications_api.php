<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/session_handler.php';
require_once __DIR__ . '/NotificationController.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? 'list';
$notif = new NotificationController($pdo);
$user_id = $_SESSION['user_id'];

switch ($action) {
    case 'count':
        echo json_encode(['count' => $notif->getUnreadCount($user_id)]);
        break;

    case 'list':
        $notifications = $notif->getUnread($user_id, 10);
        echo json_encode(['notifications' => $notifications]);
        break;

    case 'read':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $notif->markAsRead($id, $user_id);
        }
        echo json_encode(['success' => true]);
        break;

    case 'read_all':
        $notif->markAllAsRead($user_id);
        echo json_encode(['success' => true]);
        break;

    case 'delete':
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $notif->delete($id, $user_id);
        }
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Unknown action']);
}
