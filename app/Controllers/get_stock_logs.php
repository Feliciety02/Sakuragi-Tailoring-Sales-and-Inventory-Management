<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../DataAccess/InventoryDAO.php';

header('Content-Type: application/json');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { echo json_encode([]); exit; }

$dao = new InventoryDAO($pdo);
$logs = $dao->getInventoryLogs($id);

$result = array_map(function($log) {
    return [
        'log_id' => $log->log_id,
        'change_type' => $log->change_type,
        'quantity' => (int)$log->quantity,
        'note' => $log->note,
        'created_at' => $log->created_at,
    ];
}, $logs);

echo json_encode($result);
