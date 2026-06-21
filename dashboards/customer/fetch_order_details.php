<?php
// Prevent PHP errors from being displayed in the output
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../app/Middleware/auth_required.php';
require_once '../../app/Controllers/OrderController.php';

// Clear any previous output that might have been sent
ob_clean();

// Set proper content type for JSON response
header('Content-Type: application/json');

// Check if user is authorized
if (get_user_role() !== ROLE_CUSTOMER) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit();
}

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid order ID']);
    exit();
}

$order_id = (int) $_GET['id'];
$user_id = $_SESSION['user_id'];

// Initialize the OrderController with the PDO connection
$orderController = new OrderController($pdo);

// Fetch order details using the controller
try {
    $order = $orderController->getOrderById($order_id, $user_id);

    if (!$order) {
        // Order not found or doesn't belong to this user
        echo json_encode(['success' => false, 'error' => 'Order not found']);
        exit();
    }

    // Return order data as JSON
    echo json_encode(['success' => true, 'order' => $order]);
} catch (Exception $e) {
    // Log error
    error_log('Error fetching order details: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Unable to retrieve order details: ' . $e->getMessage()]);
}

?>
