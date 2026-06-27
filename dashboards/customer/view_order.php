<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/auth_required.php';
require_once __DIR__ . '/../../app/Support/helpers.php';

if (get_user_role() !== ROLE_CUSTOMER) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: my_orders.php');
    exit();
}

$order_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT o.*, s.service_name,
           e.full_name AS employee_name,
           ow.stage, ow.expected_completion, ow.product_type, ow.priority
    FROM orders o
    LEFT JOIN services s ON o.service_id = s.service_id
    LEFT JOIN order_workflow ow ON o.order_id = ow.order_id
    LEFT JOIN users e ON ow.assigned_employee = e.user_id
    WHERE o.order_id = ? AND o.user_id = ?
");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: my_orders.php');
    exit();
}

$items = $pdo->prepare("SELECT * FROM order_details WHERE order_id = ?");
$items->execute([$order_id]);
$orderItems = $items->fetchAll();

$payStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ?");
$payStmt->execute([$order_id]);
$payment = $payStmt->fetch();

$files = $pdo->prepare("SELECT * FROM order_files WHERE order_id = ?");
$files->execute([$order_id]);
$designFiles = $files->fetchAll();

$notes = $pdo->prepare("
    SELECT pn.*, u.full_name AS author_name
    FROM production_notes pn
    LEFT JOIN users u ON pn.author_id = u.user_id
    WHERE pn.order_id = ?
    ORDER BY pn.created_at ASC
");
$notes->execute([$order_id]);
$prodNotes = $notes->fetchAll();

$customerStage = $CUSTOMER_STAGE_MAP[$order['stage']] ?? 'Processing';
$progress = getStageProgress($order['stage']);

$customerTimeline = [
    CSTAGE_CONFIRMED,
    CSTAGE_PRODUCTION,
    CSTAGE_QUALITY,
    CSTAGE_PACKAGING_C,
    CSTAGE_READY,
];
$currentCustomerIdx = array_search($customerStage, $customerTimeline);
if ($customerStage === CSTAGE_DONE) $currentCustomerIdx = 5;

$pageTitle = 'Order Details';
$stageColor = $STAGE_CONFIG[$order['stage']]['color'] ?? 'var(--role-accent)';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Details — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="cust-vieworder-styles">
    .tl-step { display:flex;flex-direction:column;align-items:center;position:relative;flex:1 }
    .tl-step:not(:last-child)::after { content:'';position:absolute;top:20px;left:55%;width:90%;height:3px;background:var(--border-color,rgba(0,0,0,0.06));z-index:0 }
    .tl-step.completed:not(:last-child)::after { background:#22c55e }
    .tl-step.active:not(:last-child)::after { background:linear-gradient(90deg,#22c55e 50%,var(--border-color,rgba(0,0,0,0.06)) 50%) }
    .tl-dot { width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;position:relative;z-index:1;border:3px solid var(--border-color,rgba(0,0,0,0.06));background:var(--bg-primary,#fff);color:var(--text-tertiary);transition:all .3s }
    .tl-step.completed .tl-dot { background:#22c55e;border-color:#22c55e;color:#fff }
    .tl-step.active .tl-dot { border-color:var(--role-accent);color:var(--role-accent) }
    .tl-label { font-size:11px;text-align:center;margin-top:6px;color:var(--text-tertiary);font-weight:500;max-width:80px }
    .tl-step.completed .tl-label { color:#22c55e }
    .tl-step.active .tl-label { color:var(--role-accent);font-weight:600 }

    .item-tile { background:var(--bg-secondary);border-radius:8px;padding:12px 20px;text-align:center;min-width:70px }
    .item-tile .qty { font-size:16px;font-weight:700;color:var(--text-primary) }
    .item-tile .size { font-size:11px;color:var(--text-tertiary) }

    .design-thumb { display:block;width:100px;height:100px;border-radius:8px;overflow:hidden;background:var(--bg-secondary);border:1px solid var(--border-color);transition:border-color .2s }
    .design-thumb:hover { border-color:var(--role-accent) }
    .design-thumb img { width:100%;height:100%;object-fit:cover }
    .design-thumb .placeholder { display:flex;align-items:center;justify-content:center;height:100%;font-size:20px;color:var(--text-tertiary) }
  </style>
</head>
<body data-role="customer">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
$breadcrumb = '<div style="font-size:0.78rem;color:var(--text-tertiary);margin-bottom:8px"><a href="my_orders.php" style="color:var(--role-accent);text-decoration:none">My Orders</a> <span style="margin:0 4px">/</span> <span style="color:var(--text-primary)">#ORD-' . $order_id . '</span></div>';

// ── Header card with progress ──
$stageBadge = renderStatusBadge(htmlspecialchars($customerStage), 'accent', 'sm');
$headerCard = '<div class="panel-card" style="padding:20px 24px;margin-bottom:16px">';
$headerCard .= '<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:12px">';
$headerCard .= '<div><h2 style="margin:0;font-size:1.2rem;font-weight:700;color:var(--text-primary)">Order #ORD-' . $order_id . '</h2>';
$headerCard .= '<p style="margin:4px 0 0;font-size:0.82rem;color:var(--text-tertiary)">Placed ' . date('F d, Y', strtotime($order['order_date'])) . ' · ' . htmlspecialchars($order['service_name'] ?? 'Custom') . '</p></div>';
$headerCard .= '<div>' . $stageBadge . '</div></div>';
$headerCard .= '<div style="display:flex;align-items:center;gap:12px"><div style="flex:1;height:8px;background:var(--border-color);border-radius:4px;overflow:hidden"><div style="width:' . $progress . '%;height:100%;background:' . $stageColor . ';border-radius:4px;transition:width .5s"></div></div><span style="font-size:0.82rem;font-weight:600;color:var(--text-secondary)">' . $progress . '%</span></div>';
$headerCard .= '</div>';

// ── Timeline ──
$timelineHtml = '<div class="panel-card" style="padding:24px;margin-bottom:16px">';
$timelineHtml .= '<div style="display:flex;justify-content:space-between;padding:0 10px">';
foreach ($customerTimeline as $i => $stage):
  $completed = $i < $currentCustomerIdx;
  $active = $i === $currentCustomerIdx;
  $cls = $completed ? 'completed' : ($active ? 'active' : '');
  $timelineHtml .= '<div class="tl-step ' . $cls . '"><div class="tl-dot">';
  if ($completed):
    $timelineHtml .= '<i class="fas fa-check"></i>';
  else:
    $timelineHtml .= '<i class="fas fa-circle"></i>';
  endif;
  $timelineHtml .= '</div><div class="tl-label">' . htmlspecialchars($stage) . '</div></div>';
endforeach;
$timelineHtml .= '</div></div>';

// ── Main content: Production updates + items + files ──
$mainInner = '';

// Production notes
$prodHtml = renderPageSection('Production Updates', '');
if (empty($prodNotes)):
  $prodHtml = renderPageSection('Production Updates', '<p style="font-size:0.82rem;color:var(--text-tertiary);text-align:center;padding:12px 0;margin:0">No updates yet. We\'ll post progress here as your order moves through production.</p>');
else:
  $feed = [];
  foreach ($prodNotes as $n):
    $icon = $n['note_type'] === 'handoff' ? 'fas fa-check-double' : 'fas fa-comment';
    $feed[] = ['icon' => $icon, 'text' => htmlspecialchars($n['content']), 'time' => date('M d, g:i A', strtotime($n['created_at'])), 'accent' => 'red'];
  endforeach;
  $prodHtml = renderPageSection('Production Updates', renderActivityFeed($feed));
endif;
$mainInner .= $prodHtml;

// Order items
$itemsHtml = '<div style="display:flex;flex-wrap:wrap;gap:8px">';
foreach ($orderItems as $item):
  $itemsHtml .= '<div class="item-tile"><div class="qty">' . (int)$item['quantity'] . '</div><div class="size">Size ' . htmlspecialchars($item['size']) . '</div></div>';
endforeach;
$itemsHtml .= '</div>';
$mainInner .= renderPageSection('Order Items', $itemsHtml);

// Design files
if (!empty($designFiles)):
  $filesHtml = '<div style="display:flex;flex-wrap:wrap;gap:8px">';
  foreach ($designFiles as $f):
    $path = '/public/uploads/designs/' . $f['file_path'];
    $ext = strtolower(pathinfo($f['file_path'], PATHINFO_EXTENSION));
    $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']);
    $filesHtml .= '<a href="' . $path . '" target="_blank" class="design-thumb">';
    if ($isImg):
      $filesHtml .= '<img src="' . $path . '" alt="Design">';
    else:
      $filesHtml .= '<div class="placeholder"><i class="fas fa-file-alt"></i></div>';
    endif;
    $filesHtml .= '</a>';
  endforeach;
  $filesHtml .= '</div>';
  $mainInner .= renderPageSection('Design Files', $filesHtml);
endif;

// ── Sidebar: order info + payment ──
$sidebarHtml = '<div class="panel-card" style="padding:20px;margin-bottom:12px">';
$sidebarHtml .= '<h5 style="margin:0 0 12px;font-size:0.9rem;font-weight:700;color:var(--text-primary)">Order Details</h5>';
$sidebarHtml .= '<div style="font-size:0.82rem;line-height:1.8">';
$sidebarHtml .= '<p style="margin:0 0 6px"><strong style="color:var(--text-secondary)">Status</strong><br>' . $stageBadge . '</p>';
$sidebarHtml .= '<p style="margin:0 0 6px"><strong style="color:var(--text-secondary)">Total</strong><br>₱' . number_format($order['total_price'], 2) . '</p>';
if ($order['employee_name']):
  $sidebarHtml .= '<p style="margin:0 0 6px"><strong style="color:var(--text-secondary)">Assigned Staff</strong><br>' . htmlspecialchars($order['employee_name']) . '</p>';
endif;
if ($order['expected_completion']):
  $sidebarHtml .= '<p style="margin:0 0 6px"><strong style="color:var(--text-secondary)">Expected Completion</strong><br>' . date('F d, Y', strtotime($order['expected_completion'])) . '</p>';
endif;
$sidebarHtml .= '</div></div>';

if ($payment):
  $pVariant = $payment['status'] === 'Paid' ? 'success' : 'warning';
  $sidebarHtml .= '<div class="panel-card" style="padding:20px;margin-bottom:12px">';
  $sidebarHtml .= '<h5 style="margin:0 0 12px;font-size:0.9rem;font-weight:700;color:var(--text-primary)">Payment</h5>';
  $sidebarHtml .= '<div style="font-size:0.82rem;line-height:1.8">';
  $sidebarHtml .= '<p style="margin:0 0 6px"><strong style="color:var(--text-secondary)">Amount</strong><br>₱' . number_format($payment['amount'], 2) . '</p>';
  $sidebarHtml .= '<p style="margin:0 0 6px"><strong style="color:var(--text-secondary)">Status</strong><br>' . renderStatusBadge(htmlspecialchars($payment['status']), $pVariant, 'sm') . '</p>';
  if ($payment['reference_number']):
    $sidebarHtml .= '<p style="margin:0"><strong style="color:var(--text-secondary)">Reference</strong><br>' . htmlspecialchars($payment['reference_number']) . '</p>';
  endif;
  $sidebarHtml .= '</div></div>';
endif;

// ── Combine into two-column layout ──
$workspace = $breadcrumb . $headerCard . $timelineHtml . renderTwoColumn($mainInner, $sidebarHtml);

echo renderDashboardShell(
  '',  // no separate header, breadcrumb is in workspace
  '',
  $workspace
);
?>
    </div>
  </div>
</div>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>
