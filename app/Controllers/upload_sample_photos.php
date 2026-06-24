<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
$approval_id = (int)($_POST['approval_id'] ?? 0);

if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Missing order_id']);
    exit;
}

$uploadDir = __DIR__ . '/../../public/uploads/samples/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
$maxSize = 5 * 1024 * 1024; // 5MB
$uploaded = [];

if (empty($_FILES['photos'])) {
    echo json_encode(['success' => false, 'error' => 'No files uploaded']);
    exit;
}

$files = $_FILES['photos'];
$fileCount = is_array($files['name']) ? count($files['name']) : 1;

for ($i = 0; $i < $fileCount; $i++) {
    $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
    $tmp = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
    $type = is_array($files['type']) ? $files['type'][$i] : $files['type'];
    $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
    $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];

    if ($error !== UPLOAD_ERR_OK) continue;

    if (!in_array($type, $allowedTypes)) {
        continue;
    }

    if ($size > $maxSize) {
        continue;
    }

    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $safeName = 'sample_' . $order_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $dest = $uploadDir . $safeName;

    if (move_uploaded_file($tmp, $dest)) {
        $relPath = '/public/uploads/samples/' . $safeName;
        $stmt = $pdo->prepare("INSERT INTO sample_photos (approval_id, order_id, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$approval_id, $order_id, $relPath, $user_id]);
        $uploaded[] = ['path' => $relPath, 'photo_id' => $pdo->lastInsertId()];
    }
}

if (empty($uploaded)) {
    echo json_encode(['success' => false, 'error' => 'No valid files were uploaded. Allowed: JPG, PNG, WEBP up to 5MB each.']);
    exit;
}

echo json_encode(['success' => true, 'photos' => $uploaded]);
