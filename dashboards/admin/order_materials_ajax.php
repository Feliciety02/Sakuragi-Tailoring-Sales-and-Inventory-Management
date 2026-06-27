<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/app/Middleware/role_admin_only.php';

$order_id = (int) ($_GET['order_id'] ?? 0);
if (!$order_id) {
    echo '<div style="padding:12px;background:#fee2e2;color:#991b1b;border-radius:8px">Invalid order.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT o.*, u.full_name AS customer_name FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.order_id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) {
    echo '<div style="padding:12px;background:#fee2e2;color:#991b1b;border-radius:8px">Order not found.</div>';
    exit;
}

$stmt = $pdo->prepare("SELECT om.*, i.item_name, i.quantity AS stock_qty, st.name AS supply_type FROM order_materials om JOIN inventory i ON om.inventory_id = i.inventory_id LEFT JOIN supply_types st ON i.supply_type_id = st.supply_type_id WHERE om.order_id = ? ORDER BY st.name, i.item_name");
$stmt->execute([$order_id]);
$allocated = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT mcl.*, i.item_name, u.full_name AS consumed_by_name FROM material_consumption_log mcl JOIN inventory i ON mcl.inventory_id = i.inventory_id LEFT JOIN users u ON mcl.consumed_by = u.user_id WHERE mcl.order_id = ? ORDER BY mcl.created_at DESC LIMIT 20");
$stmt->execute([$order_id]);
$consumptions = $stmt->fetchAll();

$inv_items = $pdo->query("SELECT i.inventory_id, i.item_name, i.quantity, st.name AS supply_type FROM inventory i LEFT JOIN supply_types st ON i.supply_type_id = st.supply_type_id WHERE i.supply_type_id IN (1, 2, 5, 8, 9) ORDER BY st.name, i.item_name")->fetchAll();
?>
<div style="display:flex;gap:24px;margin-bottom:16px;flex-wrap:wrap">
  <div><strong style="color:var(--text-primary);font-size:0.85rem">Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></div>
  <div><strong style="color:var(--text-primary);font-size:0.85rem">Status:</strong> <?= renderStatusBadge((string) $order['status'], 'neutral', 'sm') ?></div>
  <div><strong style="color:var(--text-primary);font-size:0.85rem">Order Date:</strong> <?= date('M j, Y', strtotime($order['order_date'])) ?></div>
  <div><strong style="color:var(--text-primary);font-size:0.85rem">Total:</strong> ₱<?= number_format((float) $order['total_price'], 2) ?></div>
</div>

<div style="background:var(--bg-secondary);border-radius:12px;padding:16px;margin-bottom:16px">
  <h5 style="margin:0 0 12px;font-size:0.9rem;font-weight:600;color:var(--text-primary)"><i class="fas fa-plus-circle" style="color:var(--accent-color);margin-right:6px"></i>Allocate Material</h5>
  <form method="POST" action="order_materials.php" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
    <input type="hidden" name="order_id" value="<?= $order_id ?>">
    <div style="flex:2;min-width:180px">
      <select name="inventory_id" required style="width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:8px;font-size:0.8rem;background:var(--bg-primary);color:var(--text-primary)">
        <option value="">Select material...</option>
        <?php foreach ($inv_items as $item): ?>
        <option value="<?= (int) $item['inventory_id'] ?>"><?= htmlspecialchars($item['item_name']) ?> (<?= htmlspecialchars($item['supply_type']) ?>) - Stock: <?= (float) $item['quantity'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="flex:1;min-width:80px">
      <input type="number" name="allocated_qty" placeholder="Qty" step="0.01" min="0.01" required style="width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:8px;font-size:0.8rem;background:var(--bg-primary);color:var(--text-primary)">
    </div>
    <div style="flex:1;min-width:80px">
      <select name="unit" style="width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:8px;font-size:0.8rem;background:var(--bg-primary);color:var(--text-primary)">
        <option value="piece">piece</option>
        <option value="meter">meter</option>
        <option value="yard">yard</option>
        <option value="roll">roll</option>
        <option value="pack">pack</option>
      </select>
    </div>
    <button type="submit" name="allocate_material" class="dash-btn dash-btn-primary dash-btn-sm"><i class="fas fa-check"></i> Allocate</button>
  </form>
</div>

<h5 style="margin:0 0 12px;font-size:0.85rem;font-weight:600;color:var(--text-primary)">Allocated Materials</h5>
<?php
if (count($allocated) === 0) {
    echo renderEmptyState('fas fa-box-open', 'No materials allocated yet', 'Allocate the first material for this order.');
} else {
    $allocatedColumns = [
        ['field' => 'material', 'label' => 'Material'],
        ['field' => 'type', 'label' => 'Type'],
        ['field' => 'allocated', 'label' => 'Allocated'],
        ['field' => 'consumed', 'label' => 'Consumed'],
        ['field' => 'remaining', 'label' => 'Remaining'],
        ['field' => 'unit', 'label' => 'Unit'],
        ['field' => 'stock', 'label' => 'Stock'],
        ['field' => 'actions', 'label' => 'Actions', 'type' => 'actions'],
    ];
    $allocatedRows = [];
    foreach ($allocated as $allocation) {
        $remaining = (float) $allocation['allocated_qty'] - (float) $allocation['consumed_qty'];
        $remainingColor = $remaining > 0 ? '#f59e0b' : '#10b981';
        $allocatedRows[] = [
            'material' => $allocation['item_name'],
            'type' => ['html' => '<span class="table-link" style="color:var(--text-secondary);font-weight:600;text-decoration:none">' . htmlspecialchars((string) $allocation['supply_type']) . '</span>'],
            'allocated' => number_format((float) $allocation['allocated_qty'], 1),
            'consumed' => number_format((float) $allocation['consumed_qty'], 1),
            'remaining' => ['html' => '<span style="font-weight:600;color:' . $remainingColor . '">' . number_format($remaining, 1) . '</span>', 'sort' => (string) $remaining, 'filter' => number_format($remaining, 1)],
            'unit' => $allocation['unit'],
            'stock' => ['html' => '<span style="color:' . ((float) $allocation['stock_qty'] <= 0 ? '#ef4444' : 'var(--text-primary)') . '">' . htmlspecialchars((string) $allocation['stock_qty']) . '</span>', 'sort' => (string) $allocation['stock_qty'], 'filter' => (string) $allocation['stock_qty']],
            'actions' => array_values(array_filter([
                $remaining > 0 ? ['label' => 'Consume', 'icon' => 'fas fa-arrow-down', 'variant' => 'outline', 'tag' => 'button', 'onclick' => "showConsume(" . (int) $allocation['allocation_id'] . ", '" . htmlspecialchars($allocation['item_name'], ENT_QUOTES) . "', " . $remaining . ")"] : null,
                (float) $allocation['consumed_qty'] > 0 ? ['label' => 'Return', 'icon' => 'fas fa-undo', 'variant' => 'outline', 'tag' => 'button', 'onclick' => "showReturn(" . (int) $allocation['allocation_id'] . ", '" . htmlspecialchars($allocation['item_name'], ENT_QUOTES) . "', " . (float) $allocation['consumed_qty'] . ")"] : null,
            ])),
        ];
    }
    echo renderDataTable('allocated-materials-table', $allocatedColumns, $allocatedRows, [
        'searchPlaceholder' => 'Search allocated materials...',
        'pageSize' => 5,
        'emptyMessage' => 'No materials allocated yet',
    ]);
}
?>

<h5 style="margin:20px 0 12px;font-size:0.85rem;font-weight:600;color:var(--text-primary)">Recent Consumption Log</h5>
<?php
if (count($consumptions) === 0) {
    echo renderEmptyState('fas fa-history', 'No consumption logged yet', 'Consumption activity will appear here.');
} else {
    $logColumns = [
        ['field' => 'material', 'label' => 'Material'],
        ['field' => 'qty', 'label' => 'Qty'],
        ['field' => 'type', 'label' => 'Type', 'type' => 'badge'],
        ['field' => 'by', 'label' => 'By'],
        ['field' => 'notes', 'label' => 'Notes'],
        ['field' => 'date', 'label' => 'Date'],
    ];
    $logRows = [];
    foreach ($consumptions as $consumption) {
        $typeText = $consumption['consumption_type'] === 'returned' ? 'Returned' : ucfirst((string) $consumption['consumption_type']);
        $typeVariant = $consumption['consumption_type'] === 'returned' ? 'warning' : 'neutral';
        $logRows[] = [
            'material' => $consumption['item_name'],
            'qty' => number_format((float) $consumption['quantity'], 1),
            'type' => ['text' => $typeText, 'variant' => $typeVariant],
            'by' => $consumption['consumed_by_name'] ?: '—',
            'notes' => $consumption['notes'] ?: '—',
            'date' => date('M j, g:i A', strtotime($consumption['created_at'])),
        ];
    }
    echo renderDataTable('consumption-log-table', $logColumns, $logRows, [
        'searchPlaceholder' => 'Search consumption log...',
        'pageSize' => 5,
        'emptyMessage' => 'No consumption logged yet',
    ]);
}
?>

<form method="POST" action="order_materials.php" id="consumeForm" style="display:none">
  <input type="hidden" name="allocation_id" id="consumeAllocationId">
  <input type="hidden" name="quantity" id="consumeQuantity">
  <input type="hidden" name="notes" id="consumeNotes">
  <input type="hidden" name="log_consumption" value="1">
</form>
<form method="POST" action="order_materials.php" id="returnForm" style="display:none">
  <input type="hidden" name="allocation_id" id="returnAllocationId">
  <input type="hidden" name="quantity" id="returnQuantity">
  <input type="hidden" name="notes" id="returnNotes">
  <input type="hidden" name="return_material" value="1">
</form>

<script>
function showConsume(id, name, maxQty) {
  var qty = prompt('Log consumption for "' + name + '"\nRemaining: ' + maxQty + '\nEnter quantity:', '1');
  if (qty !== null && !isNaN(qty) && parseFloat(qty) > 0 && parseFloat(qty) <= maxQty) {
    var notes = prompt('Notes (optional):');
    document.getElementById('consumeAllocationId').value = id;
    document.getElementById('consumeQuantity').value = qty;
    document.getElementById('consumeNotes').value = notes || '';
    document.getElementById('consumeForm').submit();
  } else if (qty !== null) {
    alert('Invalid quantity. Must be between 1 and ' + maxQty);
  }
}

function showReturn(id, name, maxQty) {
  var qty = prompt('Return to inventory for "' + name + '"\nConsumed: ' + maxQty + '\nEnter quantity to return:', '1');
  if (qty !== null && !isNaN(qty) && parseFloat(qty) > 0 && parseFloat(qty) <= maxQty) {
    var notes = prompt('Return reason (optional):');
    document.getElementById('returnAllocationId').value = id;
    document.getElementById('returnQuantity').value = qty;
    document.getElementById('returnNotes').value = notes || '';
    document.getElementById('returnForm').submit();
  } else if (qty !== null) {
    alert('Invalid quantity.');
  }
}
</script>
