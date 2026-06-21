<?php
// Model for inventory stock in/out logs
class InventoryStockLog
{
    public $log_id;
    public $inventory_id;
    public $change_type; // 'in' or 'out'
    public $quantity;
    public $supplier_id; // nullable, for stock in
    public $note;
    public $created_at;

    public function __construct($data)
    {
        $this->log_id = $data['log_id'] ?? null;
        $this->inventory_id = $data['inventory_id'] ?? null;
        $this->change_type = $data['change_type'] ?? null;
        $this->quantity = $data['quantity'] ?? null;
        $this->supplier_id = $data['supplier_id'] ?? null;
        $this->note = $data['note'] ?? null;
        $this->created_at = $data['created_at'] ?? null;
    }
}
