<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../app/Middleware/auth_required.php';
require_once __DIR__ . '/../../config/component_helpers.php';
$pageTitle = 'Garment Tracking';

$role = get_user_role();
if ($role === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) { header('Location: my_tasks.php'); exit(); }

$order = $pdo->prepare("SELECT o.*, ow.stage, ow.product_type, ow.expected_completion, ow.priority, u.full_name AS customer_name FROM orders o JOIN order_workflow ow ON o.order_id = ow.order_id JOIN users u ON o.user_id = u.user_id WHERE o.order_id = ?");
$order->execute([$order_id]);
$ord = $order->fetch();
if (!$ord) { header('Location: my_tasks.php'); exit(); }

$garments = $pdo->prepare("SELECT od.*, gt.stage AS current_stage, gt.updated_at, gt.employee_id, u.full_name AS last_employee FROM order_details od LEFT JOIN garment_tracking gt ON od.order_detail_id = gt.order_detail_id LEFT JOIN users u ON gt.employee_id = u.user_id WHERE od.order_id = ? ORDER BY od.size");
$garments->execute([$order_id]);

$history = $pdo->prepare("SELECT gl.*, od.size, od.quantity, u.full_name AS employee_name FROM garment_log gl JOIN order_details od ON gl.order_detail_id = od.order_detail_id LEFT JOIN users u ON gl.employee_id = u.user_id WHERE gl.order_id = ? ORDER BY gl.created_at DESC LIMIT 50");
$history->execute([$order_id]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Garment Tracking — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="<?= htmlspecialchars($role) ?>">
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/employee.php'; ?>
  <div class="dash-main">
<?php
$header = renderPageHeader('Garment Tracking', htmlspecialchars($ord['customer_name']) . ' &middot; ' . htmlspecialchars($ord['product_type'] ?? 'Garment') . ' &middot; ' . htmlspecialchars($ord['stage']), '', [
  ['label' => 'Back to Task', 'href' => 'view_task.php?id=' . $order_id, 'icon' => 'fas fa-arrow-left', 'variant' => 'outline'],
]);

// Garment table
$garmentRows = [];
$totalItems = 0;
$completedItems = 0;
foreach ($garments as $g) {
    $totalItems += (int)$g['quantity'];
    $cs = $g['current_stage'] ?? $ord['stage'];
    $pct = getStageProgress($cs);
    if ($cs === STAGE_COMPLETED || $cs === STAGE_READY_PICKUP || $cs === STAGE_PACKAGING) $completedItems += (int)$g['quantity'];
    $dotColor = $cs === STAGE_COMPLETED ? '#10b981' : ($cs === STAGE_REWORK ? '#ef4444' : ($pct > 50 ? '#b91c1c' : '#f59e0b'));
    $garmentRows[] = [
        ['html' => '<strong>' . htmlspecialchars($g['size']) . '</strong>'],
        ['text' => (string)(int)$g['quantity']],
        ['html' => '<span style="display:inline-flex;align-items:center;gap:6px"><span style="width:8px;height:8px;border-radius:50%;background:' . $dotColor . ';display:inline-block"></span>' . htmlspecialchars($cs) . '</span>'],
        ['text' => $g['updated_at'] ? date('M d, g:i A', strtotime($g['updated_at'])) : '—'],
        ['text' => htmlspecialchars($g['last_employee'] ?? '—')],
    ];
}
$garmentTable = renderDataTable('garment-table', [
    ['label' => 'Size'], ['label' => 'Qty'], ['label' => 'Current Stage'], ['label' => 'Last Updated'], ['label' => 'Last Employee'],
], $garmentRows, ['searchable' => true, 'searchPlaceholder' => 'Search items by size or stage...']);

// Timeline
ob_start();
if ($history->rowCount() === 0): ?>
<p style="text-align:center;padding:16px 0;color:var(--text-tertiary);font-size:0.85rem">No stage transitions recorded yet</p>
<?php else: ?>
<div style="display:flex;flex-direction:column;gap:12px">
<?php foreach ($history as $h): ?>
<div style="display:flex;gap:12px">
<div style="width:32px;height:32px;border-radius:50%;background:var(--accent-bg);color:var(--accent-color);display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-arrows-alt-h" style="font-size:0.75rem"></i></div>
<div style="flex:1;min-width:0">
<p style="margin:0 0 2px;font-size:0.8125rem;color:var(--text-primary)"><strong>Size <?= htmlspecialchars($h['size']) ?></strong>: <?= htmlspecialchars($h['from_stage'] ?? '—') ?> &rarr; <?= htmlspecialchars($h['to_stage']) ?><?php if ($h['notes']): ?><br><span style="color:var(--text-tertiary);font-size:0.75rem"><?= htmlspecialchars($h['notes']) ?></span><?php endif; ?></p>
<p style="margin:0;font-size:0.75rem;color:var(--text-tertiary)"><?= htmlspecialchars($h['employee_name'] ?? 'System') ?> &middot; <?= date('M d, g:i A', strtotime($h['created_at'])) ?></p>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif;
$timelineHtml = ob_get_clean();

$mainContent = '<div style="display:flex;flex-direction:column;gap:16px">' . $garmentTable . renderPanelCard('Stage History', $timelineHtml, 'fas fa-history') . '</div>';

// Sidebar
$progressPct = $totalItems > 0 ? round($completedItems / $totalItems * 100) : 0;
ob_start(); ?>
<div style="display:flex;flex-direction:column;gap:16px">
<div style="background:var(--bg-secondary);border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:8px">
<div><span style="font-size:0.75rem;color:var(--text-tertiary);display:block">Total Items</span><span style="font-size:1rem;font-weight:600;color:var(--text-primary)"><?= $totalItems ?></span></div>
<div><span style="font-size:0.75rem;color:var(--text-tertiary);display:block">Completed / Ready</span><span style="font-size:1rem;font-weight:600;color:var(--text-primary)"><?= $completedItems ?></span></div>
<div>
<span style="font-size:0.75rem;color:var(--text-tertiary);display:block;margin-bottom:4px">Progress</span>
<div style="height:8px;background:var(--border-color);border-radius:4px;overflow:hidden"><div style="width:<?= $progressPct ?>%;height:100%;background:var(--accent-color);border-radius:4px;transition:width 0.4s"></div></div>
</div>
</div>
<a href="view_task.php?id=<?= $order_id ?>" class="dash-btn dash-btn-outline" style="width:100%"><i class="fas fa-arrow-left"></i> Back to Task</a>
</div>
<?php $sidebarHtml = ob_get_clean();

$workspace = renderTwoColumn($mainContent, $sidebarHtml);

echo renderDashboardShell($header, '', $workspace);
?>
    </div>
  </div>
</div>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() { document.getElementById('sidebar')?.classList.toggle('collapsed'); });
</script>
</body>
</html>
