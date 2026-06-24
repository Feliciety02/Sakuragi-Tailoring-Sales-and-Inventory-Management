<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/auth_required.php';
require_once __DIR__ . '/../../app/Controllers/NotificationController.php';

if (get_user_role() !== ROLE_CUSTOMER) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$notif = new NotificationController($pdo);
$user_id = $_SESSION['user_id'];
$notifications = $notif->getUnread($user_id, 50);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $id = (int)($_POST['notification_id'] ?? 0);
    if ($id) $notif->markAsRead($id, $user_id);
    header('Location: notifications.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $notif->markAllAsRead($user_id);
    header('Location: notifications.php');
    exit();
}
$pageTitle = 'Notifications';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
</head>
<body data-role="customer">
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/customer.php'; ?>
  <div class="dash-main">
<?php
$notifHtml = '';

if (empty($notifications)):
  $notifHtml = renderEmptyState('fas fa-bell-slash', 'No notifications', 'You\'re all caught up! Notifications about your orders will appear here.');
else:
  ob_start();
  foreach ($notifications as $n):
    $time = date('M d, Y g:i A', strtotime($n['created_at']));
?>
<div class="panel-card" style="display:flex;align-items:center;gap:14px;padding:14px 18px;margin-bottom:8px">
  <div style="width:36px;height:36px;border-radius:50%;background:var(--role-accent-light,rgba(214,40,40,0.1));display:flex;align-items:center;justify-content:center;color:var(--role-accent);flex-shrink:0">
    <i class="fas fa-bell" style="font-size:0.85rem"></i>
  </div>
  <div style="flex:1;min-width:0">
    <p style="margin:0 0 2px;font-size:0.88rem;color:var(--text-primary);line-height:1.4"><?= htmlspecialchars($n['message']) ?></p>
    <span style="font-size:0.75rem;color:var(--text-tertiary)"><?= $time ?></span>
  </div>
  <form method="post" style="margin:0;flex-shrink:0">
    <input type="hidden" name="notification_id" value="<?= $n['notification_id'] ?>">
    <button type="submit" name="mark_read" class="dash-btn dash-btn-outline dash-btn-sm" title="Mark as read" style="border-radius:50%;width:32px;height:32px;padding:0">
      <i class="fas fa-check"></i>
    </button>
  </form>
</div>
<?php
  endforeach;
  $notifHtml = ob_get_clean();
endif;

echo renderDashboardShell(
  renderPageHeader(
    'Notifications',
    'Stay updated on your orders',
    '',
    [['label' => 'Mark All Read', 'icon' => 'fas fa-check-double', 'href' => '#', 'variant' => 'outline', 'size' => 'sm', 'onclick' => "document.getElementById('markAllForm').submit();return false"]]
  ),
  '',
  $notifHtml
);
?>
<form method="post" id="markAllForm" style="display:none"><button type="submit" name="mark_all_read"></button></form>

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
