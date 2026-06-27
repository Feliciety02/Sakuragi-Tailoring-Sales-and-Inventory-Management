<?php
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once __DIR__ . '/../../app/Support/helpers.php';
require_once __DIR__ . '/../../app/Middleware/role_admin_only.php';

$pageTitle = 'Order Materials';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['allocate_material'])) {
    $order_id = (int)$_POST['order_id']; $inventory_id = (int)$_POST['inventory_id']; $allocated_qty = (float)$_POST['allocated_qty'];
    $unit = $_POST['unit'] ?? 'piece'; $notes = $_POST['notes'] ?? '';
    try { $pdo->prepare("INSERT INTO order_materials (order_id, inventory_id, allocated_qty, unit, notes) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE allocated_qty = allocated_qty + ?, unit = VALUES(unit), notes = VALUES(notes)")->execute([$order_id, $inventory_id, $allocated_qty, $unit, $notes, $allocated_qty]); $success = 'Material allocated successfully.'; }
    catch (Exception $e) { $error = 'Failed to allocate: ' . $e->getMessage(); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_consumption'])) {
    $allocation_id = (int)$_POST['allocation_id']; $quantity = (float)$_POST['quantity']; $notes = $_POST['notes'] ?? '';
    try {
        $pdo->beginTransaction();
        $allocStmt = $pdo->prepare("SELECT * FROM order_materials WHERE allocation_id = ?");
        $allocStmt->execute([$allocation_id]);
        $alloc = $allocStmt->fetch();
        if ($alloc) {
            $new_consumed = $alloc['consumed_qty'] + $quantity;
            $pdo->prepare("UPDATE order_materials SET consumed_qty = ? WHERE allocation_id = ?")->execute([$new_consumed, $allocation_id]);
            $pdo->prepare("INSERT INTO material_consumption_log (order_id, inventory_id, allocation_id, quantity, consumed_by, notes) VALUES (?, ?, ?, ?, ?, ?)")->execute([$alloc['order_id'], $alloc['inventory_id'], $allocation_id, $quantity, $_SESSION['user_id'], $notes]);
            $invStmt = $pdo->prepare("SELECT quantity FROM inventory WHERE inventory_id = ?");
            $invStmt->execute([$alloc['inventory_id']]);
            $inv = $invStmt->fetch();
            if ($inv) {
                $new_qty = max(0, $inv['quantity'] - $quantity);
                $pdo->prepare("UPDATE inventory SET quantity = ? WHERE inventory_id = ?")->execute([$new_qty, $alloc['inventory_id']]);
                $pdo->prepare("INSERT INTO inventory_stock_log (inventory_id, change_type, quantity, note) VALUES (?, 'out', ?, ?)")->execute([$alloc['inventory_id'], $quantity, "Consumed for Order #{$alloc['order_id']}"]);
            }
        }
        $pdo->commit(); $success = 'Consumption logged and inventory updated.';
    } catch (Exception $e) { $pdo->rollBack(); $error = 'Failed to log consumption: ' . $e->getMessage(); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_material'])) {
    $allocation_id = (int)$_POST['allocation_id']; $quantity = (float)$_POST['quantity']; $notes = $_POST['notes'] ?? '';
    try {
        $pdo->beginTransaction();
        $allocStmt2 = $pdo->prepare("SELECT * FROM order_materials WHERE allocation_id = ?");
        $allocStmt2->execute([$allocation_id]);
        $alloc = $allocStmt2->fetch();
        if ($alloc) {
            $new_consumed = max(0, $alloc['consumed_qty'] - $quantity);
            $pdo->prepare("UPDATE order_materials SET consumed_qty = ? WHERE allocation_id = ?")->execute([$new_consumed, $allocation_id]);
            $pdo->prepare("INSERT INTO material_consumption_log (order_id, inventory_id, allocation_id, quantity, consumed_by, consumption_type, notes) VALUES (?, ?, ?, ?, ?, 'returned', ?)")->execute([$alloc['order_id'], $alloc['inventory_id'], $allocation_id, $quantity, $_SESSION['user_id'], $notes]);
            $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE inventory_id = ?")->execute([$quantity, $alloc['inventory_id']]);
            $pdo->prepare("INSERT INTO inventory_stock_log (inventory_id, change_type, quantity, note) VALUES (?, 'in', ?, ?)")->execute([$alloc['inventory_id'], $quantity, "Returned from Order #{$alloc['order_id']}"]);
        }
        $pdo->commit(); $success = 'Material returned to inventory.';
    } catch (Exception $e) { $pdo->rollBack(); $error = 'Failed to return material: ' . $e->getMessage(); }
}

$orders = $pdo->query("SELECT o.order_id, o.order_date, o.status, u.full_name AS customer_name, (SELECT SUM(quantity) FROM order_details WHERE order_id = o.order_id) AS total_qty, (SELECT COUNT(*) FROM order_materials WHERE order_id = o.order_id) AS material_count, (SELECT COALESCE(SUM(allocated_qty), 0) FROM order_materials WHERE order_id = o.order_id) AS total_allocated, (SELECT COALESCE(SUM(consumed_qty), 0) FROM order_materials WHERE order_id = o.order_id) AS total_consumed FROM orders o JOIN users u ON o.user_id = u.user_id ORDER BY o.order_date DESC")->fetchAll();

$inv_items = $pdo->query("SELECT i.*, st.name AS supply_type_name, s.supplier_name FROM inventory i LEFT JOIN supply_types st ON i.supply_type_id = st.supply_type_id LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id WHERE i.supply_type_id IN (1, 2, 5, 8, 9) ORDER BY st.name, i.item_name")->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Sakuragi Admin</title>
    <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
    <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
    <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
    <link rel="manifest" href="/public/manifest.json" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css">
    <link rel="stylesheet" href="/public/assets/css/components.css">
</head>
<body data-role="admin">
<div class="dash-layout">
    <?php render_role_sidebar($pdo); ?>
    <div class="dash-main">

<?php
$alerts = '';
if (isset($success)) $alerts = '<div class="dash-alert dash-alert-success" style="margin:0 24px 16px"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($success) . '</div>';
elseif (isset($error)) $alerts = '<div class="dash-alert dash-alert-danger" style="margin:0 24px 16px"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($error) . '</div>';

ob_start();
$orderCols = [
    ['label' => 'Order', 'field' => 'order_num'],
    ['label' => 'Customer', 'field' => 'customer'],
    ['label' => 'Qty', 'field' => 'qty'],
    ['label' => 'Materials', 'field' => 'materials'],
    ['label' => 'Allocated', 'field' => 'allocated'],
    ['label' => 'Consumed', 'field' => 'consumed', 'safeHtml' => true],
    ['label' => 'Status', 'field' => 'status', 'type' => 'badge'],
    ['label' => 'Actions', 'field' => 'actions', 'type' => 'actions'],
];
$orderData = [];
foreach ($orders as $order):
    $allocated = (float)$order['total_allocated'];
    $consumed = (float)$order['total_consumed'];
    $mat_count = (int)$order['material_count'];
    $statusClass = $mat_count === 0 ? 'none' : ($consumed < $allocated ? 'partial' : 'ok');
    $statusLabel = $mat_count === 0 ? 'Not Allocated' : ($consumed < $allocated ? 'Partial' : 'Fulfilled');
    $pct = $allocated > 0 ? min(100, round(($consumed / $allocated) * 100)) : 0;
    $statusVariant = $statusClass === 'ok' ? 'success' : ($statusClass === 'partial' ? 'warning' : 'outline');
    $orderData[] = [
        'order_num' => ['html' => '<a href="#" onclick="showOrderDetails(' . $order['order_id'] . ');return false" style="font-weight:600;text-decoration:none;color:var(--accent-color)">#' . $order['order_id'] . '</a>'],
        'customer' => $order['customer_name'],
        'qty' => $order['total_qty'] ?? 0,
        'materials' => ['html' => '<span style="padding:2px 8px;border-radius:10px;font-size:0.75rem;background:' . ($statusClass === 'ok' ? '#d1fae5' : ($statusClass === 'partial' ? '#fef3c7' : '#fee2e2')) . ';color:' . ($statusClass === 'ok' ? '#065f46' : ($statusClass === 'partial' ? '#92400e' : '#991b1b')) . '">' . $mat_count . ' items</span>'],
        'allocated' => $allocated > 0 ? number_format($allocated, 1) : '—',
        'consumed' => $allocated > 0 ? '<div style="display:flex;align-items:center;gap:8px"><span>' . number_format($consumed, 1) . '</span><div style="flex:1;height:6px;background:var(--border-color);border-radius:3px;max-width:80px"><div style="width:' . $pct . '%;height:100%;background:' . ($pct >= 100 ? 'var(--accent-color)' : ($pct > 0 ? '#f59e0b' : 'var(--text-tertiary)')) . ';border-radius:3px"></div></div></div>' : '—',
        'status' => ['text' => $order['status']],
        'actions' => [['label' => 'Manage', 'icon' => 'fas fa-eye', 'variant' => 'outline', 'onclick' => 'showOrderDetails(' . $order['order_id'] . ');return false']],
    ];
endforeach;
echo renderDataTable('orderTable', $orderCols, $orderData, ['searchable' => true, 'searchPlaceholder' => 'Search orders...']);
$tableHtml = ob_get_clean();

$scriptsHtml = '';
ob_start(); ?>
<!-- Order Materials Modal -->
<div class="modern-modal-overlay" id="orderMaterialsModal" style="display:none" onclick="if(event.target===this)closeOrderModal()">
  <div class="modern-modal" style="max-width:720px;width:90%">
    <h3 style="margin:0 0 16px;font-size:1.05rem;font-weight:700;color:var(--text-primary)" id="modalTitle">Order Materials</h3>
    <div id="modalBody" style="min-height:100px;display:flex;align-items:center;justify-content:center">
      <i class="fas fa-spinner fa-spin fa-2x" style="color:var(--text-tertiary)"></i>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid var(--border-color)">
      <button type="button" class="dash-btn dash-btn-outline dash-btn-sm" onclick="closeOrderModal()">Close</button>
    </div>
  </div>
</div>
<script>
function showOrderDetails(orderId) {
    document.getElementById('modalTitle').textContent = 'Order #' + orderId + ' - Materials';
    document.getElementById('modalBody').innerHTML = '<i class="fas fa-spinner fa-spin fa-2x" style="color:var(--text-tertiary)"></i>';
    document.getElementById('orderMaterialsModal').style.display = 'flex';
    fetch('/dashboards/admin/order_materials_ajax.php?order_id=' + orderId)
        .then(function(r) { return r.text(); })
        .then(function(html) {
            document.getElementById('modalBody').innerHTML = html;
            if (window.SakuragiDataTable) {
                SakuragiDataTable.initAll();
            }
        })
        .catch(function() { document.getElementById('modalBody').innerHTML = '<div class="dash-alert dash-alert-danger">Failed to load order details.</div>'; });
}
function closeOrderModal() { document.getElementById('orderMaterialsModal').style.display = 'none'; }
document.getElementById('menuToggle')?.addEventListener('click', function() { document.getElementById('sidebar')?.classList.toggle('collapsed'); });
</script>
<?php $scriptsHtml = ob_get_clean();

$workspace = $alerts . renderPanelCard('All Orders', $tableHtml, 'fas fa-roll') . $scriptsHtml;

echo renderDashboardShell(renderPageHeader($pageTitle, 'Manage material allocation and consumption across orders.'), '', $workspace);
?>
</div>
</div>
</body>
</html>
