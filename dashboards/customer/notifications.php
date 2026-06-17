<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/auth_required.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar_customer.php';
require_once __DIR__ . '/../../controller/NotificationController.php';

if (get_user_role() !== ROLE_CUSTOMER) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}

$notif = new NotificationController($pdo);
$user_id = $_SESSION['user_id'];
$notifications = $notif->getUnread($user_id, 50);

// Mark as read
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
?>
<main class="main-content">
    <div class="container-fluid mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="fw-bold">Notifications</h1>
                <p class="text-muted">Stay updated on your orders</p>
            </div>
            <div class="col-auto">
                <form method="post" class="d-inline">
                    <button type="submit" name="mark_all_read" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-check-double me-1"></i>Mark All Read
                    </button>
                </form>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bell-slash fs-1 text-muted mb-3"></i>
                    <p class="text-muted mb-0">No notifications yet</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $n): ?>
                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-3">
                        <div class="me-3">
                            <p class="mb-1"><?= htmlspecialchars($n['message']) ?></p>
                            <small class="text-muted"><?= date('M d, Y g:i A', strtotime($n['created_at'])) ?></small>
                        </div>
                        <form method="post" class="m-0">
                            <input type="hidden" name="notification_id" value="<?= $n['notification_id'] ?>">
                            <button type="submit" name="mark_read" class="btn btn-sm btn-outline-secondary" title="Mark as read">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?php require_once '../../includes/footer.php'; ?>
