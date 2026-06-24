<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../app/Middleware/role_admin_only.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) { echo '<div style="padding:12px;background:#fee2e2;color:#991b1b;border-radius:8px">Invalid order.</div>'; exit; }

$stmt = $pdo->prepare("SELECT o.*, u.full_name AS customer_name FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.order_id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) { echo '<div style="padding:12px;background:#fee2e2;color:#991b1b;border-radius:8px">Order not found.</div>'; exit; }

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
<div><strong style="color:var(--text-primary);font-size:0.85rem">Status:</strong> <span class="dash-badge dash-badge-outline"><?= $order['status'] ?></span></div>
<div><strong style="color:var(--text-primary);font-size:0.85rem">Order Date:</strong> <?= date('M j, Y', strtotime($order['order_date'])) ?></div>
<div><strong style="color:var(--text-primary);font-size:0.85rem">Total:</strong> ₱<?= number_format($order['total_price'], 2) ?></div>
</div>

<div style="background:var(--bg-secondary);border-radius:12px;padding:16px;margin-bottom:16px">
<h5 style="margin:0 0 12px;font-size:0.9rem;font-weight:600;color:var(--text-primary)"><i class="fas fa-plus-circle" style="color:var(--accent-color);margin-right:6px"></i>Allocate Material</h5>
<form method="POST" action="order_materials.php" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
<input type="hidden" name="order_id" value="<?= $order_id ?>">
<div style="flex:2;min-width:180px">
<select name="inventory_id" required style="width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:8px;font-size:0.8rem;background:var(--bg-primary);color:var(--text-primary)">
<option value="">Select material...</option>
<?php foreach ($inv_items as $item): ?>
<option value="<?= $item['inventory_id'] ?>"><?= htmlspecialchars($item['item_name']) ?> (<?= $item['supply_type'] ?>) — Stock: <?= $item['quantity'] ?></option>
<?php endforeach; ?>
</select>
</div>
<div style="flex:1;min-width:80px">
<input type="number" name="allocated_qty" placeholder="Qty" step="0.01" min="0.01" required style="width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:8px;font-size:0.8rem;background:var(--bg-primary);color:var(--text-primary)">
</div>
<div style="flex:1;min-width:80px">
<select name="unit" style="width:100%;padding:8px 10px;border:1px solid var(--border-color);border-radius:8px;font-size:0.8rem;background:var(--bg-primary);color:var(--text-primary)">
<option value="piece">piece</option><option value="meter">meter</option><option value="yard">yard</option><option value="roll">roll</option><option value="pack">pack</option>
</select>
</div>
<button type="submit" name="allocate_material" class="dash-btn dash-btn-primary dash-btn-sm"><i class="fas fa-check"></i> Allocate</button>
</form>
</div>

<h5 style="margin:0 0 12px;font-size:0.85rem;font-weight:600;color:var(--text-primary)">Allocated Materials</h5>
<?php if (count($allocated) === 0): ?>
<p style="color:var(--text-tertiary);font-size:0.85rem;text-align:center;padding:16px">No materials allocated yet.</p>
<?php else: ?>
<div style="overflow-x:auto;margin-bottom:16px">
<table style="width:100%;border-collapse:collapse;font-size:0.8rem">
<thead><tr style="border-bottom:2px solid var(--border-color)">
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Material</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Type</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Allocated</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Consumed</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Remaining</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Unit</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Stock</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Actions</th>
</tr></thead>
<tbody>
<?php foreach ($allocated as $a): $remaining = $a['allocated_qty'] - $a['consumed_qty']; ?>
<tr style="border-bottom:1px solid var(--border-color)">
<td style="padding:8px"><?= htmlspecialchars($a['item_name']) ?></td>
<td style="padding:8px"><span class="dash-badge dash-badge-outline"><?= $a['supply_type'] ?></span></td>
<td style="padding:8px"><?= number_format($a['allocated_qty'], 1) ?></td>
<td style="padding:8px"><?= number_format($a['consumed_qty'], 1) ?></td>
<td style="padding:8px"><span style="font-weight:600;color:<?= $remaining > 0 ? '#f59e0b' : '#10b981' ?>"><?= number_format($remaining, 1) ?></span></td>
<td style="padding:8px"><?= $a['unit'] ?></td>
<td style="padding:8px;color:<?= $a['stock_qty'] <= 0 ? '#ef4444' : 'var(--text-primary)' ?>"><?= $a['stock_qty'] ?></td>
<td style="padding:8px">
<div style="display:flex;gap:4px">
<?php if ($remaining > 0): ?>
<button class="dash-btn dash-btn-outline dash-btn-sm" onclick="showConsume(<?= $a['allocation_id'] ?>,'<?= htmlspecialchars($a['item_name']) ?>',<?= $remaining ?>)" title="Log consumption"><i class="fas fa-arrow-down"></i></button>
<?php endif; ?>
<?php if ($a['consumed_qty'] > 0): ?>
<button class="dash-btn dash-btn-outline dash-btn-sm" onclick="showReturn(<?= $a['allocation_id'] ?>,'<?= htmlspecialchars($a['item_name']) ?>',<?= $a['consumed_qty'] ?>)" title="Return to inventory"><i class="fas fa-undo"></i></button>
<?php endif; ?>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

<h5 style="margin:0 0 12px;font-size:0.85rem;font-weight:600;color:var(--text-primary)">Recent Consumption Log</h5>
<?php if (count($consumptions) === 0): ?>
<p style="color:var(--text-tertiary);font-size:0.85rem;text-align:center;padding:16px">No consumption logged yet.</p>
<?php else: ?>
<div style="overflow-x:auto;margin-bottom:16px">
<table style="width:100%;border-collapse:collapse;font-size:0.8rem">
<thead><tr style="border-bottom:2px solid var(--border-color)">
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Material</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Qty</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Type</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">By</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Notes</th>
<th style="padding:8px;text-align:left;color:var(--text-tertiary);font-weight:600">Date</th>
</tr></thead>
<tbody>
<?php foreach ($consumptions as $c): ?>
<tr style="border-bottom:1px solid var(--border-color)">
<td style="padding:8px"><?= htmlspecialchars($c['item_name']) ?></td>
<td style="padding:8px;font-weight:600"><?= number_format($c['quantity'], 1) ?></td>
<td style="padding:8px"><?php if ($c['consumption_type'] === 'returned'): ?><span class="dash-badge dash-badge-warning">Returned</span><?php else: ?><span class="dash-badge dash-badge-outline"><?= ucfirst($c['consumption_type']) ?></span><?php endif; ?></td>
<td style="padding:8px"><?= htmlspecialchars($c['consumed_by_name'] ?? '—') ?></td>
<td style="padding:8px;color:var(--text-tertiary)"><?= htmlspecialchars($c['notes'] ?? '—') ?></td>
<td style="padding:8px;color:var(--text-tertiary)"><?= date('M j, g:i A', strtotime($c['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>

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
var qty = prompt('Log consumption for "' + name + '"\\nRemaining: ' + maxQty + '\\nEnter quantity:', '1');
if (qty !== null && !isNaN(qty) && parseFloat(qty) > 0 && parseFloat(qty) <= maxQty) {
var notes = prompt('Notes (optional):');
document.getElementById('consumeAllocationId').value = id;
document.getElementById('consumeQuantity').value = qty;
document.getElementById('consumeNotes').value = notes || '';
document.getElementById('consumeForm').submit();
} else if (qty !== null) { alert('Invalid quantity. Must be between 1 and ' + maxQty); }
}
function showReturn(id, name, maxQty) {
var qty = prompt('Return to inventory for "' + name + '"\\nConsumed: ' + maxQty + '\\nEnter quantity to return:', '1');
if (qty !== null && !isNaN(qty) && parseFloat(qty) > 0 && parseFloat(qty) <= maxQty) {
var notes = prompt('Return reason (optional):');
document.getElementById('returnAllocationId').value = id;
document.getElementById('returnQuantity').value = qty;
document.getElementById('returnNotes').value = notes || '';
document.getElementById('returnForm').submit();
} else if (qty !== null) { alert('Invalid quantity.'); }
}
</script>
