<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/session_handler.php';
require_once __DIR__ . '/../config/constants.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_logged_in() || get_user_role() !== ROLE_ADMIN) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$order_id = (int)($_POST['order_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$order_id || !$action) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit();
}

try {
    if ($action === 'cancel') {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'Cancelled' WHERE order_id = ?");
        $stmt->execute([$order_id]);
        echo json_encode(['success' => true]);
    } elseif ($action === 'update_status') {
        $status = $_POST['status'] ?? '';
        $allowed = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
        if (!in_array($status, $allowed)) {
            throw new Exception('Invalid status');
        }
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt->execute([$status, $order_id]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
