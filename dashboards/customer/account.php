<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/auth_required.php';

if (get_user_role() !== ROLE_CUSTOMER) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch real user data
try {
    $userStmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();
} catch (PDOException $e) {
    $user = [];
    error_log('Account fetch error: ' . $e->getMessage());
}

// Fetch order stats
try {
    $statsStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
            COALESCE(SUM(total_quantity), 0) AS total_items
        FROM orders WHERE user_id = ?
    ");
    $statsStmt->execute([$user_id]);
    $stats = $statsStmt->fetch();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0, 'total_items' => 0];
}

// Fetch loyalty info
try {
    $loyaltyStmt = $pdo->prepare("SELECT * FROM loyalty WHERE user_id = ?");
    $loyaltyStmt->execute([$user_id]);
    $loyalty = $loyaltyStmt->fetch();
} catch (PDOException $e) {
    $loyalty = null;
}
$freeEarned = $loyalty['free_shirts_earned'] ?? 0;
$freeClaimed = $loyalty['free_shirts_claimed'] ?? 0;
$freeAvailable = max(0, $freeEarned - $freeClaimed);

// Calculate progress toward next free shirt
$itemsOrdered = (int)($stats['total_items'] ?? 0);
$progressToNext = $itemsOrdered % 12;
$nextAt = 12 - $progressToNext;

// Fetch recent 5 orders
try {
    $recentStmt = $pdo->prepare("
        SELECT o.order_id, o.order_date, o.status, o.total_price, s.service_name
        FROM orders o
        JOIN services s ON o.service_id = s.service_id
        WHERE o.user_id = ?
        ORDER BY o.order_date DESC LIMIT 5
    ");
    $recentStmt->execute([$user_id]);
    $recentOrders = $recentStmt->fetchAll();
} catch (PDOException $e) {
    $recentOrders = [];
}

$fullName = $user['full_name'] ?? $_SESSION['full_name'] ?? 'Customer';

// Handle profile update
$profileMessage = '';
$profileError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if (empty($email)) {
        $profileError = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileError = 'Please enter a valid email address';
    } else {
        try {
            $updateStmt = $pdo->prepare("UPDATE users SET email = ?, phone_number = ? WHERE user_id = ?");
            $updateStmt->execute([$email, $phone, $user_id]);
            $user['email'] = $email;
            $user['phone_number'] = $phone;
            $profileMessage = 'Profile updated successfully';
        } catch (PDOException $e) {
            $profileError = 'Failed to update profile';
            error_log('Profile update error: ' . $e->getMessage());
        }
    }
}

// Handle password change
$passwordMessage = '';
$passwordError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    if (empty($current) || empty($new) || empty($confirm)) {
        $passwordError = 'All fields are required';
    } elseif ($new !== $confirm) {
        $passwordError = 'New passwords do not match';
    } elseif (strlen($new) < 8) {
        $passwordError = 'Password must be at least 8 characters';
    } else {
        try {
            $pwStmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
            $pwStmt->execute([$user_id]);
            $stored = $pwStmt->fetchColumn();
            if (!password_verify($current, $stored)) {
                $passwordError = 'Current password is incorrect';
            } else {
                $hashed = password_hash($new, PASSWORD_DEFAULT);
                $updatePw = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $updatePw->execute([$hashed, $user_id]);
                $passwordMessage = 'Password changed successfully';
            }
        } catch (PDOException $e) {
            $passwordError = 'An error occurred';
            error_log('Password change error: ' . $e->getMessage());
        }
    }
}

$pageTitle = 'My Account';

// ── Build avatar initials ──
$initials = '';
$parts = explode(' ', $fullName);
if (count($parts) >= 2) {
    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
} else {
    $initials = strtoupper(substr($fullName, 0, 2));
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Account — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="cust-account-styles">
    .avatar-xl { width:90px;height:90px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:2.2rem;font-weight:700;background:var(--role-accent-light,rgba(214,40,40,0.12));color:var(--role-accent,#D62828);margin:0 auto 12px }
    .form-group { margin-bottom:16px }
    .form-group label { display:block;font-size:0.82rem;font-weight:600;color:var(--text-secondary);margin-bottom:4px }
    .form-group input { width:100%;padding:10px 12px;border:1px solid var(--border-color,rgba(0,0,0,0.06));border-radius:8px;font-size:0.9rem;background:var(--bg-secondary,#F3F0EB);color:var(--text-primary);outline:none;transition:border-color .2s }
    .form-group input:focus { border-color:var(--role-accent,#D62828) }
    .form-group input:disabled { opacity:.6;cursor:not-allowed }
    .tab-bar { display:flex;gap:0;border-bottom:1px solid var(--border-color,rgba(0,0,0,0.06));margin-bottom:20px }
    .tab-btn { padding:10px 20px;font-size:0.85rem;font-weight:600;color:var(--text-tertiary);background:none;border:none;cursor:pointer;border-bottom:2px solid transparent;transition:all .2s }
    .tab-btn:hover { color:var(--text-primary) }
    .tab-btn.active { color:var(--role-accent,#D62828);border-bottom-color:var(--role-accent,#D62828) }
    .tab-pane { display:none }
    .tab-pane.active { display:block }
    .alert { padding:10px 14px;border-radius:8px;font-size:0.85rem;margin-bottom:16px }
    .alert-success { background:rgba(34,197,94,0.1);color:#22c55e;border:1px solid rgba(34,197,94,0.2) }
    .alert-danger { background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.2) }
    .btn-primary-custom { padding:10px 20px;border:none;border-radius:8px;font-size:0.85rem;font-weight:600;cursor:pointer;background:var(--role-accent,#D62828);color:#fff;transition:opacity .2s }
    .btn-primary-custom:hover { opacity:.85 }

    .rewards-progress { margin-top:12px;text-align:center }
    .rewards-progress .progress-text { font-size:0.78rem;color:var(--text-tertiary);margin-bottom:4px }

    .loyalty-milestone { display:flex;align-items:center;gap:8px;padding:8px 0 }
    .loyalty-milestone .check { width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.65rem;flex-shrink:0 }
    .loyalty-milestone .check.earned { background:rgba(34,197,94,0.12);color:#22c55e }
    .loyalty-milestone .check.pending { background:var(--bg-secondary,#F3F0EB);color:var(--text-tertiary) }
    .loyalty-milestone .mlabel { font-size:0.85rem;color:var(--text-primary) }
    .loyalty-milestone .mdesc { font-size:0.75rem;color:var(--text-tertiary) }
  </style>
</head>
<body data-role="customer">
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/customer.php'; ?>
  <div class="dash-main">
<?php
// ── Build profile sidebar card ──
$profileSidebar = '<div class="panel-card" style="text-align:center;padding:28px 20px">';
$profileSidebar .= '<div class="avatar-xl">' . htmlspecialchars($initials) . '</div>';
$profileSidebar .= '<h4 style="margin:0 0 2px;font-size:1.1rem;font-weight:700;color:var(--text-primary)">' . htmlspecialchars($fullName) . '</h4>';
$profileSidebar .= '<p style="margin:0 0 2px;font-size:0.82rem;color:var(--text-secondary)">Customer</p>';
$profileSidebar .= '<p style="margin:0;font-size:0.78rem;color:var(--text-tertiary)">' . htmlspecialchars($user['email'] ?? '') . '</p>';
$profileSidebar .= '</div>';

// ── Loyalty Rewards card ──
$profileSidebar .= '<div class="panel-card" style="padding:20px">';
$profileSidebar .= '<h5 style="margin:0 0 16px;font-size:0.95rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-gem" style="color:var(--role-accent);margin-right:6px"></i> Loyalty Rewards</h5>';
$profileSidebar .= '<div style="display:flex;gap:8px;margin-bottom:16px">';
$profileSidebar .= '<div style="flex:1;text-align:center;padding:12px 6px;background:var(--bg-secondary);border-radius:10px"><div style="font-size:1.4rem;font-weight:700;color:var(--role-accent)">' . $freeEarned . '</div><div style="font-size:0.7rem;color:var(--text-tertiary)">Earned</div></div>';
$profileSidebar .= '<div style="flex:1;text-align:center;padding:12px 6px;background:var(--bg-secondary);border-radius:10px"><div style="font-size:1.4rem;font-weight:700;color:#22c55e">' . $freeClaimed . '</div><div style="font-size:0.7rem;color:var(--text-tertiary)">Claimed</div></div>';
$profileSidebar .= '<div style="flex:1;text-align:center;padding:12px 6px;background:var(--bg-secondary);border-radius:10px"><div style="font-size:1.4rem;font-weight:700;color:#eab308">' . $freeAvailable . '</div><div style="font-size:0.7rem;color:var(--text-tertiary)">Available</div></div>';
$profileSidebar .= '</div>';
$profileSidebar .= '<div class="rewards-progress">';
$profileSidebar .= '<div class="progress-text">' . $progressToNext . ' more item' . ($progressToNext !== 1 ? 's' : '') . ' until next free shirt</div>';
$profileSidebar .= '<div class="progress-bar" style="max-width:240px;margin:0 auto"><div class="progress-bar-track"><div class="progress-bar-fill" style="width:' . ($progressToNext > 0 ? (($itemsOrdered % 12) / 12 * 100) : 0) . '%"></div></div></div>';
$profileSidebar .= '</div>';
$profileSidebar .= '<p style="font-size:0.75rem;color:var(--text-tertiary);margin:12px 0 0;text-align:center">Earn 1 free shirt for every 12 items ordered</p>';

// Show earned milestones
for ($i = 1; $i <= max($freeEarned, 1); $i++):
  $claimed = $i <= $freeClaimed;
  $profileSidebar .= '<div class="loyalty-milestone"><div class="check ' . ($claimed ? 'earned' : 'pending') . '"><i class="fas ' . ($claimed ? 'fa-check' : 'fa-shirt') . '"></i></div><div><div class="mlabel">Free Shirt #' . $i . '</div><div class="mdesc">' . ($claimed ? 'Claimed' : 'Ready to claim') . '</div></div></div>';
endfor;
$profileSidebar .= '</div>';

// ── Order metrics row ──
$metricsRow = '';
$metricsRow .= '<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(100px,1fr));gap:10px;margin-bottom:24px">';
$kpis = [
  ['label' => 'Total Orders', 'value' => $stats['total'], 'icon' => 'fas fa-shopping-bag', 'accent' => 'red'],
  ['label' => 'Pending', 'value' => $stats['pending'], 'icon' => 'fas fa-clock', 'accent' => 'amber'],
  ['label' => 'In Progress', 'value' => $stats['in_progress'], 'icon' => 'fas fa-spinner', 'accent' => 'blue'],
  ['label' => 'Completed', 'value' => $stats['completed'], 'icon' => 'fas fa-check-circle', 'accent' => 'green'],
  ['label' => 'Cancelled', 'value' => $stats['cancelled'], 'icon' => 'fas fa-times-circle', 'accent' => 'red'],
];
foreach ($kpis as $k) {
  $metricsRow .= renderKPICard($k['icon'], $k['label'], (string)$k['value'], '', '', $k['accent']);
}
$metricsRow .= '</div>';

// ── Recent Orders table ──
$recentHtml = '';
if (!empty($recentOrders)) {
  $cols = [
    ['field' => 'order_link', 'label' => 'Order #', 'safeHtml' => true],
    ['field' => 'date', 'label' => 'Date'],
    ['field' => 'service', 'label' => 'Service'],
    ['field' => 'status', 'label' => 'Status', 'safeHtml' => true],
    ['field' => 'total', 'label' => 'Total'],
  ];
  $dataRows = [];
  foreach ($recentOrders as $ord) {
    $statusVariant = $ord['status'] === 'Completed' ? 'success' : ($ord['status'] === 'Cancelled' ? 'danger' : ($ord['status'] === 'In Progress' ? 'accent' : 'warning'));
    $dataRows[] = [
      'order_link' => '<a href="view_order.php?id=' . $ord['order_id'] . '" style="color:var(--role-accent);text-decoration:none;font-weight:600">#ORD-' . $ord['order_id'] . '</a>',
      'date' => date('M d, Y', strtotime($ord['order_date'])),
      'service' => htmlspecialchars($ord['service_name']),
      'status' => renderStatusBadge(htmlspecialchars($ord['status']), $statusVariant, 'sm'),
      'total' => '₱' . number_format($ord['total_price'], 2),
    ];
  }
  $recentHtml = renderPageSection('Recent Orders', renderDataTable('recent-orders', $cols, $dataRows));
} else {
  $recentHtml = renderPageSection('Recent Orders', renderEmptyState('fas fa-inbox', 'No orders yet', 'Place your first order to see it here.', ['label' => 'Place Order', 'href' => 'place_order.php', 'icon' => 'fas fa-plus']));
}

// ── Account Settings (Profile + Password) ──
$settingsHtml = '<div style="display:flex;flex-wrap:wrap;gap:20px">';

// Profile form
$profileForm = '<div class="panel-card" style="flex:1;min-width:280px;padding:20px">';
$profileForm .= '<h5 style="margin:0 0 16px;font-size:0.95rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-user" style="color:var(--role-accent);margin-right:6px"></i> Personal Details</h5>';
if ($profileMessage) $profileForm .= '<div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);color:var(--color-success);border-radius:var(--radius-sm);padding:10px 14px;font-size:.82rem;margin-bottom:12px">' . htmlspecialchars($profileMessage) . '</div>';
if ($profileError) $profileForm .= '<div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:var(--color-danger);border-radius:var(--radius-sm);padding:10px 14px;font-size:.82rem;margin-bottom:12px">' . htmlspecialchars($profileError) . '</div>';
$profileForm .= '<form method="post">';
$profileForm .= '<div class="form-group"><label>Full Name</label><input type="text" value="' . htmlspecialchars($fullName) . '" disabled></div>';
$profileForm .= '<div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" value="' . htmlspecialchars($user['email'] ?? '') . '" required></div>';
$profileForm .= '<div class="form-group"><label for="phone">Phone Number</label><input type="tel" id="phone" name="phone" value="' . htmlspecialchars($user['phone_number'] ?? '') . '"></div>';
$profileForm .= '<button type="submit" name="update_profile" class="btn-primary-custom">Save Changes</button>';
$profileForm .= '</form></div>';

// Password form
$pwForm = '<div class="panel-card" style="flex:1;min-width:280px;padding:20px">';
$pwForm .= '<h5 style="margin:0 0 16px;font-size:0.95rem;font-weight:700;color:var(--text-primary)"><i class="fas fa-lock" style="color:var(--role-accent);margin-right:6px"></i> Change Password</h5>';
if ($passwordMessage) $pwForm .= '<div style="background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);color:var(--color-success);border-radius:var(--radius-sm);padding:10px 14px;font-size:.82rem;margin-bottom:12px">' . htmlspecialchars($passwordMessage) . '</div>';
if ($passwordError) $pwForm .= '<div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:var(--color-danger);border-radius:var(--radius-sm);padding:10px 14px;font-size:.82rem;margin-bottom:12px">' . htmlspecialchars($passwordError) . '</div>';
$pwForm .= '<form method="post">';
$pwForm .= '<div class="form-group"><label for="current_password">Current Password</label><input type="password" id="current_password" name="current_password" required></div>';
$pwForm .= '<div class="form-group"><label for="new_password">New Password</label><input type="password" id="new_password" name="new_password" required minlength="8"></div>';
$pwForm .= '<div class="form-group"><label for="confirm_password">Confirm New Password</label><input type="password" id="confirm_password" name="confirm_password" required minlength="8"></div>';
$pwForm .= '<button type="submit" name="change_password" class="btn-primary-custom">Change Password</button>';
$pwForm .= '</form></div>';

$settingsHtml .= $profileForm . $pwForm;
$settingsHtml .= '</div>';

$mainWorkspace = $recentHtml . $settingsHtml;

echo renderDashboardShell(
  renderPageHeader('My Account', 'Manage your profile, view your order history and loyalty rewards.'),
  $metricsRow,
  $mainWorkspace
);
?>
    </div>
  </div>
</div>

<script src="/public/assets/js/order.js"></script>
</body>
</html>
