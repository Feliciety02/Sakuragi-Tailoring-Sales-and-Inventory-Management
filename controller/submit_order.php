<?php
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/../config/session_handler.php';
require_once __DIR__ . '/../config/constants.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_logged_in()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = get_user_role();

$service_id = (int)($_POST['service_id'] ?? 0);
$items_json = $_POST['items'] ?? '[]';
$items = json_decode($items_json, true);
$reference_number = trim($_POST['reference_number'] ?? '');
$branch_id = (int)($_POST['branch_id'] ?? 2);

if (!$service_id || empty($items)) {
    echo json_encode(['success' => false, 'error' => 'Missing required order data']);
    exit();
}

$upload_dir = __DIR__ . '/../public/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

try {
    $pdo->beginTransaction();

    // 1. Handle design file upload (from step 2)
    $design_file_id = null;
    if (isset($_FILES['design_file']) && $_FILES['design_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['design_file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['psd', 'zip'])) {
            throw new Exception('Invalid design file type. Only PSD and ZIP are allowed.');
        }
        $design_filename = 'design_' . $user_id . '_' . time() . '.' . $ext;
        $design_path = $upload_dir . $design_filename;
        if (!move_uploaded_file($_FILES['design_file']['tmp_name'], $design_path)) {
            throw new Exception('Failed to upload design file.');
        }
        $insert_file = $pdo->prepare("INSERT INTO order_files (file_path, file_type) VALUES (?, ?)");
        $insert_file->execute(['uploads/' . $design_filename, $ext]);
        $design_file_id = $pdo->lastInsertId();
    }

    // 2. Handle Excel file upload (from step 3 customizable)
    $excel_file_id = null;
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['excel_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xlsx') {
            throw new Exception('Invalid Excel file type.');
        }
        $excel_filename = 'excel_' . $user_id . '_' . time() . '.' . $ext;
        $excel_path = $upload_dir . $excel_filename;
        if (!move_uploaded_file($_FILES['excel_file']['tmp_name'], $excel_path)) {
            throw new Exception('Failed to upload Excel file.');
        }
        $insert_file = $pdo->prepare("INSERT INTO order_files (file_path, file_type) VALUES (?, ?)");
        $insert_file->execute(['uploads/' . $excel_filename, 'excel']);
        $excel_file_id = $pdo->lastInsertId();
    }

    // 3. Calculate total price
    $total_price = 0;
    foreach ($items as $item) {
        $total_price += (float)($item['cost'] ?? 0);
    }

    // 4. Insert order
    $insert_order = $pdo->prepare("
        INSERT INTO orders (branch_id, user_id, service_id, design_file_id, total_price, status, payment_status, order_date)
        VALUES (?, ?, ?, ?, ?, 'Pending', 'Pending', NOW())
    ");
    $insert_order->execute([$branch_id, $user_id, $service_id, $design_file_id ?: $excel_file_id, $total_price]);
    $order_id = $pdo->lastInsertId();

    // 5. Insert order details
    $insert_detail = $pdo->prepare("
        INSERT INTO order_details (order_id, service_id, quantity, unit_price, subtotal, size)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($items as $item) {
        $qty = (int)($item['quantity'] ?? 0);
        $price_per_unit = (float)($item['price_per_unit'] ?? 0);
        $subtotal = $qty * $price_per_unit;
        $size = $item['size'] ?? '';
        $insert_detail->execute([$order_id, $service_id, $qty, $price_per_unit, $subtotal, $size]);
    }

    // 6. Create workflow entry
    $insert_workflow = $pdo->prepare("
        INSERT INTO order_workflow (order_id, stage) VALUES (?, 'Designing')
    ");
    $insert_workflow->execute([$order_id]);

    // 7. Handle payment proof upload
    $proof_path = null;
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['payment_proof']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
            throw new Exception('Invalid payment proof file type.');
        }
        $proof_filename = 'proof_' . $user_id . '_' . time() . '.' . $ext;
        $proof_path_abs = $upload_dir . $proof_filename;
        if (!move_uploaded_file($_FILES['payment_proof']['tmp_name'], $proof_path_abs)) {
            throw new Exception('Failed to upload payment proof.');
        }
        $proof_path = 'uploads/' . $proof_filename;
    }

    // 8. Insert payment record
    $insert_payment = $pdo->prepare("
        INSERT INTO payments (order_id, amount, payment_method, status, reference_number, proof_file_path, payment_date)
        VALUES (?, ?, 'GCash', 'Pending', ?, ?, NOW())
    ");
    $insert_payment->execute([$order_id, $total_price, $reference_number, $proof_path]);

    // 9. Loyalty: update free shirts earned (1 free per 12 items)
    $total_qty = array_sum(array_column($items, 'quantity'));
    $free_shirts = intdiv($total_qty, 12);
    if ($free_shirts > 0) {
        $loyalty_stmt = $pdo->prepare("
            INSERT INTO loyalty (user_id, total_orders, free_shirts_earned)
            VALUES (?, 1, ?)
            ON DUPLICATE KEY UPDATE
                total_orders = total_orders + 1,
                free_shirts_earned = free_shirts_earned + ?
        ");
        $loyalty_stmt->execute([$user_id, $free_shirts, $free_shirts]);
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_id' => $order_id,
        'message' => 'Order placed successfully!'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Order submission error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
