<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../app/Middleware/role_admin_only.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) { echo '<div class="alert alert-danger">Invalid order.</div>'; exit; }

// Fetch order
$stmt = $pdo->prepare("SELECT o.*, u.full_name AS customer_name FROM orders o JOIN users u ON o.user_id = u.user_id WHERE o.order_id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) { echo '<div class="alert alert-danger">Order not found.</div>'; exit; }

// Fetch allocated materials
$stmt = $pdo->prepare("
    SELECT om.*, i.item_name, i.quantity AS stock_qty, st.name AS supply_type
    FROM order_materials om
    JOIN inventory i ON om.inventory_id = i.inventory_id
    LEFT JOIN supply_types st ON i.supply_type_id = st.supply_type_id
    WHERE om.order_id = ?
    ORDER BY st.name, i.item_name
");
$stmt->execute([$order_id]);
$allocated = $stmt->fetchAll();

// Fetch consumption log
$stmt = $pdo->prepare("
    SELECT mcl.*, i.item_name, u.full_name AS consumed_by_name
    FROM material_consumption_log mcl
    JOIN inventory i ON mcl.inventory_id = i.inventory_id
    LEFT JOIN users u ON mcl.consumed_by = u.user_id
    WHERE mcl.order_id = ?
    ORDER BY mcl.created_at DESC
    LIMIT 20
");
$stmt->execute([$order_id]);
$consumptions = $stmt->fetchAll();

// Fetch inventory items for allocation (only supply types relevant: fabric, thread, trims)
$inv_items = $pdo->query("
    SELECT i.inventory_id, i.item_name, i.quantity, st.name AS supply_type
    FROM inventory i
    LEFT JOIN supply_types st ON i.supply_type_id = st.supply_type_id
    WHERE i.supply_type_id IN (1, 2, 5, 8, 9)
    ORDER BY st.name, i.item_name
")->fetchAll();
?>
<div class="row mb-3">
    <div class="col-md-6">
        <p class="mb-1"><strong>Customer:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
        <p class="mb-1"><strong>Status:</strong> <span class="badge bg-light text-dark"><?= $order['status'] ?></span></p>
    </div>
    <div class="col-md-6 text-md-end">
        <p class="mb-1"><strong>Order Date:</strong> <?= date('M j, Y', strtotime($order['order_date'])) ?></p>
        <p class="mb-1"><strong>Total:</strong> ₱<?= number_format($order['total_price'], 2) ?></p>
    </div>
</div>

<!-- Allocate Material Form -->
<div class="card border mb-3" style="border-radius: 12px;">
    <div class="card-header bg-transparent py-2 px-3">
        <h6 class="fw-semibold mb-0"><i class="fas fa-plus-circle me-1 text-primary"></i>Allocate Material</h6>
    </div>
    <div class="card-body px-3 py-2">
        <form method="POST" action="order_materials.php" class="row g-2 align-items-end">
            <input type="hidden" name="order_id" value="<?= $order_id ?>">
            <div class="col-md-5">
                <select name="inventory_id" class="form-select form-select-sm" required>
                    <option value="">Select material...</option>
                    <?php foreach ($inv_items as $item): ?>
                        <option value="<?= $item['inventory_id'] ?>">
                            <?= htmlspecialchars($item['item_name']) ?> (<?= $item['supply_type'] ?>) — Stock: <?= $item['quantity'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" name="allocated_qty" class="form-control form-control-sm" placeholder="Qty" step="0.01" min="0.01" required>
            </div>
            <div class="col-md-2">
                <select name="unit" class="form-select form-select-sm">
                    <option value="piece">piece</option>
                    <option value="meter">meter</option>
                    <option value="yard">yard</option>
                    <option value="roll">roll</option>
                    <option value="pack">pack</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" name="allocate_material" class="btn btn-primary btn-sm w-100"><i class="fas fa-check me-1"></i>Allocate</button>
            </div>
        </form>
    </div>
</div>

<!-- Allocated Materials -->
<h6 class="fw-semibold mb-2">Allocated Materials</h6>
<?php if (count($allocated) === 0): ?>
    <p class="text-muted small">No materials allocated yet.</p>
<?php else: ?>
    <div class="table-responsive mb-3">
        <table class="table table-sm table-hover align-middle">
            <thead class="text-muted small">
                <tr>
                    <th>Material</th>
                    <th>Type</th>
                    <th>Allocated</th>
                    <th>Consumed</th>
                    <th>Remaining</th>
                    <th>Unit</th>
                    <th>Stock</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allocated as $a): ?>
                    <?php $remaining = $a['allocated_qty'] - $a['consumed_qty']; ?>
                    <tr>
                        <td class="small"><?= htmlspecialchars($a['item_name']) ?></td>
                        <td><span class="badge bg-light text-dark"><?= $a['supply_type'] ?></span></td>
                        <td><?= number_format($a['allocated_qty'], 1) ?></td>
                        <td><?= number_format($a['consumed_qty'], 1) ?></td>
                        <td>
                            <span class="fw-medium <?= $remaining > 0 ? 'text-warning' : 'text-success' ?>">
                                <?= number_format($remaining, 1) ?>
                            </span>
                        </td>
                        <td><?= $a['unit'] ?></td>
                        <td class="small <?= $a['stock_qty'] <= 0 ? 'text-danger' : '' ?>"><?= $a['stock_qty'] ?></td>
                        <td>
                            <div class="d-flex gap-1">
                                <?php if ($remaining > 0): ?>
                                <button class="btn btn-sm btn-outline-success py-0 px-1" onclick="showConsume(<?= $a['allocation_id'] ?>, '<?= htmlspecialchars($a['item_name']) ?>', <?= $remaining ?>)" title="Log consumption">
                                    <i class="fas fa-arrow-down"></i>
                                </button>
                                <?php endif; ?>
                                <?php if ($a['consumed_qty'] > 0): ?>
                                <button class="btn btn-sm btn-outline-warning py-0 px-1" onclick="showReturn(<?= $a['allocation_id'] ?>, '<?= htmlspecialchars($a['item_name']) ?>', <?= $a['consumed_qty'] ?>)" title="Return to inventory">
                                    <i class="fas fa-undo"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Consumption History -->
<h6 class="fw-semibold mb-2">Recent Consumption Log</h6>
<?php if (count($consumptions) === 0): ?>
    <p class="text-muted small">No consumption logged yet.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle">
            <thead class="text-muted small">
                <tr><th>Material</th><th>Qty</th><th>Type</th><th>By</th><th>Notes</th><th>Date</th></tr>
            </thead>
            <tbody>
                <?php foreach ($consumptions as $c): ?>
                    <tr>
                        <td class="small"><?= htmlspecialchars($c['item_name']) ?></td>
                        <td class="fw-medium"><?= number_format($c['quantity'], 1) ?></td>
                        <td>
                            <?php if ($c['consumption_type'] === 'returned'): ?>
                                <span class="badge bg-warning text-dark">Returned</span>
                            <?php else: ?>
                                <span class="badge bg-info text-white"><?= ucfirst($c['consumption_type']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= htmlspecialchars($c['consumed_by_name'] ?? '—') ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($c['notes'] ?? '—') ?></td>
                        <td class="small text-muted"><?= date('M j, g:i A', strtotime($c['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Hidden forms for consume/return -->
<form method="POST" action="order_materials.php" id="consumeForm" style="display:none;">
    <input type="hidden" name="allocation_id" id="consumeAllocationId">
    <input type="hidden" name="quantity" id="consumeQuantity">
    <input type="hidden" name="notes" id="consumeNotes">
    <input type="hidden" name="log_consumption" value="1">
</form>
<form method="POST" action="order_materials.php" id="returnForm" style="display:none;">
    <input type="hidden" name="allocation_id" id="returnAllocationId">
    <input type="hidden" name="quantity" id="returnQuantity">
    <input type="hidden" name="notes" id="returnNotes">
    <input type="hidden" name="return_material" value="1">
</form>

<script>
function showConsume(id, name, maxQty) {
    const qty = prompt(`Log consumption for "${name}"\nRemaining: ${maxQty}\nEnter quantity:`, '1');
    if (qty !== null && !isNaN(qty) && parseFloat(qty) > 0 && parseFloat(qty) <= maxQty) {
        const notes = prompt('Notes (optional):');
        document.getElementById('consumeAllocationId').value = id;
        document.getElementById('consumeQuantity').value = qty;
        document.getElementById('consumeNotes').value = notes || '';
        document.getElementById('consumeForm').submit();
    } else if (qty !== null) {
        alert('Invalid quantity. Must be between 1 and ' + maxQty);
    }
}

function showReturn(id, name, maxQty) {
    const qty = prompt(`Return to inventory for "${name}"\nConsumed: ${maxQty}\nEnter quantity to return:`, '1');
    if (qty !== null && !isNaN(qty) && parseFloat(qty) > 0 && parseFloat(qty) <= maxQty) {
        const notes = prompt('Return reason (optional):');
        document.getElementById('returnAllocationId').value = id;
        document.getElementById('returnQuantity').value = qty;
        document.getElementById('returnNotes').value = notes || '';
        document.getElementById('returnForm').submit();
    } else if (qty !== null) {
        alert('Invalid quantity.');
    }
}
</script>
