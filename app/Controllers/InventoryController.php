<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../DataAccess/InventoryDAO.php';

function getInventory($pdo) {
  $stmt = $pdo->prepare("
    SELECT 
      i.inventory_id,
      i.item_name,
      i.quantity,
      i.reorder_level,
      i.last_updated,
      s.supplier_name,
      s.supplier_id,
      t.name AS supply_type
    FROM inventory i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    LEFT JOIN supply_types t ON i.supply_type_id = t.supply_type_id
    WHERE i.branch_id = 2
    ORDER BY i.last_updated DESC
  ");
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSuppliers($pdo) {
  $stmt = $pdo->prepare("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name");
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSupplyTypes($pdo) {
  $stmt = $pdo->prepare("SELECT supply_type_id, name FROM supply_types ORDER BY name");
  $stmt->execute();
  return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? null;

  if ($action === 'add') {
    $stmt = $pdo->prepare("
      INSERT INTO inventory (branch_id, item_name, supplier_id, supply_type_id, quantity, reorder_level)
      VALUES (2, :item_name, :supplier_id, :supply_type_id, :quantity, :reorder_level)
    ");
    $stmt->execute([
      'item_name' => $_POST['item_name'],
      'supplier_id' => $_POST['supplier_id'],
      'supply_type_id' => $_POST['supply_type_id'],
      'quantity' => $_POST['quantity'],
      'reorder_level' => $_POST['reorder_level']
    ]);
  }

  if ($action === 'edit') {
    $stmt = $pdo->prepare("
      UPDATE inventory
      SET item_name = :item_name,
          supplier_id = :supplier_id,
          supply_type_id = :supply_type_id,
          reorder_level = :reorder_level
      WHERE inventory_id = :id
    ");
    $stmt->execute([
      'item_name' => $_POST['item_name'],
      'supplier_id' => $_POST['supplier_id'],
      'supply_type_id' => $_POST['supply_type_id'],
      'reorder_level' => $_POST['reorder_level'],
      'id' => $_POST['inventory_id']
    ]);
  }

  if ($action === 'delete') {
    $stmt = $pdo->prepare("DELETE FROM inventory WHERE inventory_id = :id");
    $stmt->execute(['id' => $_POST['inventory_id']]);
  }

  if ($action === 'stock_in' || $action === 'stock_out') {
    $id = $_POST['inventory_id'];
    $quantity = (int) $_POST['quantity'];
    $supplier_id = !empty($_POST['stock_supplier_id']) ? (int)$_POST['stock_supplier_id'] : null;

    $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE inventory_id = :id");
    $stmt->execute(['id' => $id]);
    $currentQty = (int) $stmt->fetchColumn();

    $newQty = $action === 'stock_in' ? $currentQty + $quantity : max(0, $currentQty - $quantity);

    $stmt = $pdo->prepare("UPDATE inventory SET quantity = :qty WHERE inventory_id = :id");
    $stmt->execute(['qty' => $newQty, 'id' => $id]);

    $dao = new InventoryDAO($pdo);
    $dao->logStockChange($id, $action === 'stock_in' ? 'in' : 'out', $quantity, $supplier_id);
  }

  header('Location: ../../dashboards/admin/inventory.php');
  exit();
}
?>
