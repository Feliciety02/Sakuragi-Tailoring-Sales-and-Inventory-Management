<?php
require_once __DIR__ . '/../Models/InventoryStockLog.php';

class InventoryDAO
{
    private $pdo;
    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    // Log stock in/out
    public function logStockChange($inventory_id, $change_type, $quantity, $supplier_id = null, $note = null)
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO inventory_stock_log (inventory_id, change_type, quantity, supplier_id, note, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$inventory_id, $change_type, $quantity, $supplier_id, $note]);
        return $this->pdo->lastInsertId();
    }

    // Update inventory quantity
    public function updateInventoryQuantity($inventory_id, $quantity, $is_in = true)
    {
        $op = $is_in ? '+' : '-';
        $stmt = $this->pdo->prepare("UPDATE inventory SET quantity = quantity $op ? WHERE inventory_id = ?");
        $stmt->execute([$quantity, $inventory_id]);
    }

    // Get inventory logs
    public function getInventoryLogs($inventory_id)
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM inventory_stock_log WHERE inventory_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$inventory_id]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($row) => new InventoryStockLog($row), $logs);
    }
}
