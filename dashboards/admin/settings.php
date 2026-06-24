<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once '../../app/Middleware/role_admin_only.php';

$saveMessage = '';
$saveError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $upsert = $pdo->prepare("INSERT INTO shop_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $fields = ['shop_name', 'shop_email', 'contact_number', 'address', 'open_time', 'close_time', 'facebook_link', 'instagram_link'];
        foreach ($fields as $f) {
            $val = trim($_POST[$f] ?? '');
            $upsert->execute([$f, $val]);
        }
        $notif = isset($_POST['notifications']) ? '1' : '0';
        $upsert->execute(['notifications_enabled', $notif]);
        $saveMessage = 'Settings saved successfully';
    } catch (PDOException $e) {
        $saveError = 'Failed to save settings: ' . $e->getMessage();
    }
}

$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM shop_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log('Settings load error: ' . $e->getMessage());
}
$pageTitle = 'Shop Settings';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shop Settings — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="settings-styles">
    .settings-form .s-group { margin-bottom:18px }
    .settings-form .s-group label { display:block;font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:4px }
    .settings-form .s-group input[type="text"], .settings-form .s-group input[type="email"], .settings-form .s-group input[type="url"], .settings-form .s-group input[type="time"] { width:100%;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;font-size:0.9rem;background:var(--bg-secondary);color:var(--text-primary);outline:none;transition:border-color .2s }
    .settings-form .s-group input:focus { border-color:var(--role-accent) }
    .settings-form .s-group textarea { width:100%;padding:10px 12px;border:1px solid var(--border-color);border-radius:8px;font-size:0.9rem;background:var(--bg-secondary);color:var(--text-primary);outline:none;resize:vertical;font-family:inherit }
    .settings-form .s-group textarea:focus { border-color:var(--role-accent) }
    .settings-form .s-group .hr-row { display:flex;gap:10px;align-items:center }
    .settings-form .s-group .hr-row input { flex:1 }
    .settings-form .s-group .hr-row span { color:var(--text-tertiary);font-size:0.85rem }
    .settings-form .s-group .cb-label { display:flex;align-items:center;gap:8px;font-size:0.9rem;color:var(--text-primary);cursor:pointer }
    .settings-form .s-group .cb-label input { width:16px;height:16px;accent-color:var(--role-accent) }
  </style>
</head>
<body data-role="admin">
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/admin.php'; ?>
  <div class="dash-main">
<?php
$alerts = '';
if ($saveMessage) $alerts .= '<div class="panel-card" style="padding:10px 14px;margin-bottom:12px;background:rgba(34,197,94,0.08);border:1px solid rgba(34,197,94,0.2);color:#22c55e;font-size:0.85rem"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($saveMessage) . '</div>';
if ($saveError) $alerts .= '<div class="panel-card" style="padding:10px 14px;margin-bottom:12px;background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.2);color:#ef4444;font-size:0.85rem"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($saveError) . '</div>';

ob_start();
?>
<div class="panel-card" style="padding:24px;max-width:680px">
  <form method="post" class="settings-form">
    <div class="s-group">
      <label for="shop_name">Shop Name</label>
      <input type="text" id="shop_name" name="shop_name" value="<?= htmlspecialchars($settings['shop_name'] ?? 'Sakuragi Tailoring Shop') ?>" required>
    </div>
    <div class="s-group">
      <label for="shop_email">Contact Email</label>
      <input type="email" id="shop_email" name="shop_email" value="<?= htmlspecialchars($settings['shop_email'] ?? '') ?>" required>
    </div>
    <div class="s-group">
      <label for="contact_number">Contact Number</label>
      <input type="text" id="contact_number" name="contact_number" value="<?= htmlspecialchars($settings['contact_number'] ?? '') ?>" required>
    </div>
    <div class="s-group">
      <label for="address">Main Branch Address</label>
      <textarea id="address" name="address" rows="3"><?= htmlspecialchars($settings['address'] ?? '') ?></textarea>
    </div>
    <div class="s-group">
      <label>Business Hours</label>
      <div class="hr-row">
        <input type="time" name="open_time" value="<?= htmlspecialchars($settings['open_time'] ?? '09:00') ?>" required>
        <span>to</span>
        <input type="time" name="close_time" value="<?= htmlspecialchars($settings['close_time'] ?? '18:00') ?>" required>
      </div>
    </div>
    <div class="s-group">
      <label class="cb-label">
        <input type="checkbox" id="notifications" name="notifications" <?= ($settings['notifications_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
        Enable Email Notifications
      </label>
    </div>
    <div class="s-group">
      <label for="facebook_link">Facebook Page</label>
      <input type="url" id="facebook_link" name="facebook_link" value="<?= htmlspecialchars($settings['facebook_link'] ?? '') ?>">
    </div>
    <div class="s-group">
      <label for="instagram_link">Instagram Handle</label>
      <input type="url" id="instagram_link" name="instagram_link" value="<?= htmlspecialchars($settings['instagram_link'] ?? '') ?>">
    </div>
    <button type="submit" class="dash-btn dash-btn-primary dash-btn-sm">Save Settings</button>
  </form>
</div>
<?php
$formHtml = ob_get_clean();

echo renderDashboardShell(
  renderPageHeader('Shop Settings', 'Manage general shop configuration.'),
  '',
  $alerts . $formHtml . '<script>document.getElementById(\'menuToggle\')?.addEventListener(\'click\', function() { document.getElementById(\'sidebar\')?.classList.toggle(\'collapsed\'); });</script>'
);
