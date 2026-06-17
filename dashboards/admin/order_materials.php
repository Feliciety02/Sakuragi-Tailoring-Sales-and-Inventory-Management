<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/role_admin_only.php';

$pageTitle = 'Order Materials';

// Handle allocate material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate_material'])) {
    $order_id = (int)$_POST['order_id'];
    $inventory_id = (int)$_POST['inventory_id'];
    $allocated_qty = (float)$_POST['allocated_qty'];
    $unit = $_POST['unit'] ?? 'piece';
    $notes = $_POST['notes'] ?? '';

    try {
        $pdo->prepare("
            INSERT INTO order_materials (order_id, inventory_id, allocated_qty, unit, notes)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE allocated_qty = allocated_qty + ?, unit = VALUES(unit), notes = VALUES(notes)
        ")->execute([$order_id, $inventory_id, $allocated_qty, $unit, $notes, $allocated_qty]);

        $success = 'Material allocated successfully.';
    } catch (Exception $e) {
        $error = 'Failed to allocate: ' . $e->getMessage();
    }
}

// Handle log consumption
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_consumption'])) {
    $allocation_id = (int)$_POST['allocation_id'];
    $quantity = (float)$_POST['quantity'];
    $notes = $_POST['notes'] ?? '';

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM order_materials WHERE allocation_id = ?");
        $stmt->execute([$allocation_id]);
        $alloc = $stmt->fetch();

        if ($alloc) {
            $new_consumed = $alloc['consumed_qty'] + $quantity;
            $pdo->prepare("UPDATE order_materials SET consumed_qty = ? WHERE allocation_id = ?")
                ->execute([$new_consumed, $allocation_id]);

            $pdo->prepare("
                INSERT INTO material_consumption_log (order_id, inventory_id, allocation_id, quantity, consumed_by, notes)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$alloc['order_id'], $alloc['inventory_id'], $allocation_id, $quantity, $_SESSION['user_id'], $notes]);

            // Deduct from inventory stock
            $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE inventory_id = ?");
            $stmt->execute([$alloc['inventory_id']]);
            $inv = $stmt->fetch();
            if ($inv) {
                $new_qty = max(0, $inv['quantity'] - $quantity);
                $pdo->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?")
                    ->execute([$new_qty, $alloc['inventory_id']]);
                $pdo->prepare("INSERT INTO inventory_stock_log (inventory_id, change_type, quantity, note) VALUES (?, 'out', ?, ?)")
                    ->execute([$alloc['inventory_id'], $quantity, "Consumed for Order #{$alloc['order_id']}"]);
            }
        }

        $pdo->commit();
        $success = 'Consumption logged and inventory updated.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to log consumption: ' . $e->getMessage();
    }
}

// Handle return material
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_material'])) {
    $allocation_id = (int)$_POST['allocation_id'];
    $quantity = (float)$_POST['quantity'];
    $notes = $_POST['notes'] ?? '';

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM order_materials WHERE allocation_id = ?");
        $stmt->execute([$allocation_id]);
        $alloc = $stmt->fetch();

        if ($alloc) {
            $new_consumed = max(0, $alloc['consumed_qty'] - $quantity);
            $pdo->prepare("UPDATE order_materials SET consumed_qty = ? WHERE allocation_id = ?")
                ->execute([$new_consumed, $allocation_id]);

            $pdo->prepare("
                INSERT INTO material_consumption_log (order_id, inventory_id, allocation_id, quantity, consumed_by, consumption_type, notes)
                VALUES (?, ?, ?, ?, ?, 'returned', ?)
            ")->execute([$alloc['order_id'], $alloc['inventory_id'], $allocation_id, $quantity, $_SESSION['user_id'], $notes]);

            // Return to inventory stock
            $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE inventory_id = ?")
                ->execute([$quantity, $alloc['inventory_id']]);
            $pdo->prepare("INSERT INTO inventory_stock_log (inventory_id, change_type, quantity, note) VALUES (?, 'in', ?, ?)")
                ->execute([$alloc['inventory_id'], $quantity, "Returned from Order #{$alloc['order_id']}"]);
        }

        $pdo->commit();
        $success = 'Material returned to inventory.';
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Failed to return material: ' . $e->getMessage();
    }
}

// Fetch all orders
$orders = $pdo->query("
    SELECT o.order_id, o.order_date, o.status, u.full_name AS customer_name,
           (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) AS total_qty,
           (SELECT COUNT(*) FROM order_materials WHERE order_id = o.order_id) AS material_count,
           (SELECT COALESCE(SUM(allocated_qty), 0) FROM order_materials WHERE order_id = o.order_id) AS total_allocated,
           (SELECT COALESCE(SUM(consumed_qty), 0) FROM order_materials WHERE order_id = o.order_id) AS total_consumed
    FROM orders o
    JOIN users u ON o.user_id = u.user_id
    ORDER BY o.order_date DESC
")->fetchAll();

// Fetch inventory for modal (only fabrics, threads, trims)
$inv_items = $pdo->query("
    SELECT i.*, st.name AS supply_type_name, s.supplier_name
    FROM inventory i
    LEFT JOIN supply_types st ON i.supply_type_id = st.supply_type_id
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    WHERE i.supply_type_id IN (1, 2, 5, 8, 9)
    ORDER BY st.name, i.item_name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Sakuragi Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
    <style>
        .mat-badge { font-size: 0.75rem; padding: 2px 8px; border-radius: 10px; }
        .mat-ok { background: #d1fae5; color: #065f46; }
        .mat-partial { background: #fef3c7; color: #92400e; }
        .mat-none { background: #fee2e2; color: #991b1b; }
        .progress-thin { height: 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <?php include __DIR__ . '/../../includes/sidebar_admin.php'; ?>
    <div class="main-content">
        <?php include __DIR__ . '/../../includes/topnav.php'; ?>
        <div class="content-container">
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            <?php endif; ?>

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="fw-bold mb-0"><i class="fas fa-roll me-2" style="color: #3b82f6;"></i><?= $pageTitle ?></h4>
            </div>

            <!-- Orders Table -->
            <div class="card border-0 shadow-sm" style="border-radius: 16px;">
                <div class="card-header bg-transparent border-bottom-0 pt-3 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-semibold mb-0">All Orders</h5>
                    <div class="input-group input-group-sm" style="max-width: 300px;">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0" id="orderSearch" placeholder="Search orders..." oninput="filterOrders()">
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="orderTable">
                            <thead class="text-muted small">
                                <tr>
                                    <th>Order</th>
                                    <th>Customer</th>
                                    <th>Qty</th>
                                    <th>Materials</th>
                                    <th>Allocated</th>
                                    <th>Consumed</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <?php
                                    $allocated = (float)$order['total_allocated'];
                                    $consumed = (float)$order['total_consumed'];
                                    $mat_count = (int)$order['material_count'];
                                    $statusClass = $mat_count === 0 ? 'mat-none' : ($consumed < $allocated ? 'mat-partial' : 'mat-ok');
                                    $statusLabel = $mat_count === 0 ? 'Not Allocated' : ($consumed < $allocated ? 'Partial' : 'Fulfilled');
                                    $pct = $allocated > 0 ? min(100, round(($consumed / $allocated) * 100)) : 0;
                                    ?>
                                    <tr>
                                        <td><a href="#" class="fw-medium text-decoration-none" onclick="showOrderDetails(<?= $order['order_id'] ?>); return false;">#<?= $order['order_id'] ?></a></td>
                                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                        <td><?= $order['total_qty'] ?? 0 ?></td>
                                        <td><span class="mat-badge <?= $statusClass ?>"><?= $mat_count ?> items</span></td>
                                        <td><?= $allocated > 0 ? $allocated : '—' ?></td>
                                        <td>
                                            <?php if ($allocated > 0): ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span><?= $consumed ?></span>
                                                    <div class="flex-grow-1 progress progress-thin">
                                                        <div class="progress-bar bg-<?= $pct >= 100 ? 'success' : ($pct > 0 ? 'warning' : 'secondary') ?>" style="width: <?= $pct ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-light text-dark"><?= $order['status'] ?></span></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary" onclick="showOrderDetails(<?= $order['order_id'] ?>)">
                                                <i class="fas fa-eye"></i> Manage
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Materials Modal -->
    <div class="modal fade" id="orderMaterialsModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content" style="border-radius: 16px;">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalTitle">Order Materials</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading...</p></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function filterOrders() {
            const q = document.getElementById('orderSearch').value.toLowerCase();
            document.querySelectorAll('#orderTable tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        }

        function showOrderDetails(orderId) {
            const modal = new bootstrap.Modal(document.getElementById('orderMaterialsModal'));
            document.getElementById('orderMaterialsModal').querySelector('.modal-title').textContent = `Order #${orderId} - Materials`;
            document.getElementById('modalBody').innerHTML = '<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading...</p></div>';
            modal.show();

            fetch(`/dashboards/admin/order_materials_ajax.php?order_id=${orderId}`)
                .then(r => r.text())
                .then(html => {
                    document.getElementById('modalBody').innerHTML = html;
                })
                .catch(() => {
                    document.getElementById('modalBody').innerHTML = '<div class="alert alert-danger">Failed to load order details.</div>';
                });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
