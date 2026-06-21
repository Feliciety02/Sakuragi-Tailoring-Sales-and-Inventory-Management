<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../app/Middleware/auth_required.php';
require_once '../../app/Controllers/OrderController.php';

if (get_user_role() !== ROLE_CUSTOMER) {
    echo 'Unauthorized access';
    exit();
}

$order_id = 1; // Choose one of your order IDs
$user_id = $_SESSION['user_id'];

// Initialize the OrderController with the PDO connection
$orderController = new OrderController($pdo);

// Try to fetch the order
try {
    $order = $orderController->getOrderById($order_id, $user_id);
    echo '<pre>';
    print_r($order);
    echo '</pre>';
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
