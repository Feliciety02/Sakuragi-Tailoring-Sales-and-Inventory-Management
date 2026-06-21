<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../app/Middleware/role_admin_only.php';


// Handle save
$saveMessage = '';
$saveError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $upsert = $pdo->prepare("
            INSERT INTO shop_settings (setting_key, setting_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
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

// Load settings
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shop Settings — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/admin.php'; ?>
  <div class="dash-main">
    <?php require_once '../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-12">
                <h1 class="fw-bold">Shop Settings</h1>
                <p class="text-muted">Manage general shop configuration</p>
            </div>
        </div>
    </div>

    <?php if ($saveMessage): ?>
    <div class="container-fluid mb-4">
        <div class="alert alert-success"><?= htmlspecialchars($saveMessage) ?></div>
    </div>
    <?php endif; ?>
    <?php if ($saveError): ?>
    <div class="container-fluid mb-4">
        <div class="alert alert-danger"><?= htmlspecialchars($saveError) ?></div>
    </div>
    <?php endif; ?>

    <div class="container-fluid">
        <form method="post" class="shop-settings-form">
            <div class="form-group">
                <label for="shop_name">Shop Name</label>
                <input type="text" id="shop_name" name="shop_name" value="<?= htmlspecialchars($settings['shop_name'] ?? 'Sakuragi Tailoring Shop') ?>" required>
            </div>

            <div class="form-group">
                <label for="shop_email">Contact Email</label>
                <input type="email" id="shop_email" name="shop_email" value="<?= htmlspecialchars($settings['shop_email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="contact_number">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" value="<?= htmlspecialchars($settings['contact_number'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="address">Main Branch Address</label>
                <textarea id="address" name="address" rows="3"><?= htmlspecialchars($settings['address'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="business_hours">Business Hours</label>
                <div style="display: flex; gap: 10px;">
                    <input type="time" name="open_time" value="<?= htmlspecialchars($settings['open_time'] ?? '09:00') ?>" required>
                    <span>to</span>
                    <input type="time" name="close_time" value="<?= htmlspecialchars($settings['close_time'] ?? '18:00') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" id="notifications" name="notifications" <?= ($settings['notifications_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                    Enable Email Notifications
                </label>
            </div>

            <div class="form-group">
                <label for="facebook_link">Facebook Page</label>
                <input type="url" id="facebook_link" name="facebook_link" value="<?= htmlspecialchars($settings['facebook_link'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="instagram_link">Instagram Handle</label>
                <input type="url" id="instagram_link" name="instagram_link" value="<?= htmlspecialchars($settings['instagram_link'] ?? '') ?>">
            </div>

            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
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
