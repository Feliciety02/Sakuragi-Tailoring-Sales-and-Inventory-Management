<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once '../../middleware/auth_required.php';
require_once '../../includes/header.php';
require_once '../../includes/sidebar_customer.php';

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
            SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled
        FROM orders WHERE user_id = ?
    ");
    $statsStmt->execute([$user_id]);
    $stats = $statsStmt->fetch();
} catch (PDOException $e) {
    $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 'cancelled' => 0];
}

// Fetch loyalty info
try {
    $loyaltyStmt = $pdo->prepare("SELECT * FROM loyalty WHERE user_id = ?");
    $loyaltyStmt->execute([$user_id]);
    $loyalty = $loyaltyStmt->fetch();
} catch (PDOException $e) {
    $loyalty = null;
}
$free_earned = $loyalty['free_shirts_earned'] ?? 0;
$free_claimed = $loyalty['free_shirts_claimed'] ?? 0;
$free_available = $free_earned - $free_claimed;

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

// Full name
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
?>
<main class="main-content">
    <div class="container-fluid mb-4">
        <div class="row">
            <div class="col-12">
                <h1 class="fw-bold">My Account</h1>
                <p class="text-muted">Manage your profile, view your order history and loyalty rewards</p>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-body text-center py-4">
                        <div class="mb-3">
                            <div class="avatar-placeholder mx-auto mb-3">
                                <?php
                                $initials = '';
                                $parts = explode(' ', $fullName);
                                if (count($parts) >= 2) {
                                    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts)-1], 0, 1));
                                } else {
                                    $initials = strtoupper(substr($fullName, 0, 2));
                                }
                                ?>
                                <span class="initials"><?= htmlspecialchars($initials) ?></span>
                            </div>
                            <h4 class="fw-bold mb-1"><?= htmlspecialchars($fullName) ?></h4>
                            <p class="text-muted mb-0">Customer</p>
                            <p class="text-muted small"><?= htmlspecialchars($user['email'] ?? '') ?></p>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mt-4">
                    <div class="card-body">
                        <h5 class="card-title mb-3">Loyalty Rewards</h5>
                        <div class="d-flex justify-content-around text-center">
                            <div>
                                <h3 class="fw-bold text-primary mb-0"><?= $free_earned ?></h3>
                                <small class="text-muted">Earned</small>
                            </div>
                            <div>
                                <h3 class="fw-bold text-success mb-0"><?= $free_claimed ?></h3>
                                <small class="text-muted">Claimed</small>
                            </div>
                            <div>
                                <h3 class="fw-bold text-warning mb-0"><?= max(0, $free_available) ?></h3>
                                <small class="text-muted">Available</small>
                            </div>
                        </div>
                        <p class="text-muted small text-center mt-3 mb-0">Earn 1 free shirt for every 12 items ordered!</p>
                    </div>
                </div>
            </div>

            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <h5 class="card-title mb-4">Order Overview</h5>
                        <div class="row g-3">
                            <div class="col-6 col-md">
                                <div class="card border-0 bg-light text-center p-3">
                                    <h3 class="fw-bold text-primary mb-0"><?= $stats['total'] ?></h3>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                            <div class="col-6 col-md">
                                <div class="card border-0 bg-light text-center p-3">
                                    <h3 class="fw-bold text-warning mb-0"><?= $stats['pending'] ?></h3>
                                    <small class="text-muted">Pending</small>
                                </div>
                            </div>
                            <div class="col-6 col-md">
                                <div class="card border-0 bg-light text-center p-3">
                                    <h3 class="fw-bold text-info mb-0"><?= $stats['in_progress'] ?></h3>
                                    <small class="text-muted">In Progress</small>
                                </div>
                            </div>
                            <div class="col-6 col-md">
                                <div class="card border-0 bg-light text-center p-3">
                                    <h3 class="fw-bold text-success mb-0"><?= $stats['completed'] ?></h3>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>
                            <div class="col-6 col-md">
                                <div class="card border-0 bg-light text-center p-3">
                                    <h3 class="fw-bold text-danger mb-0"><?= $stats['cancelled'] ?></h3>
                                    <small class="text-muted">Cancelled</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Recent Orders</h5>
                            <a href="my_orders.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <?php if (!empty($recentOrders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Service</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $ord): ?>
                                    <tr>
                                        <td>#ORD-<?= $ord['order_id'] ?></td>
                                        <td><?= date('M d, Y', strtotime($ord['order_date'])) ?></td>
                                        <td><?= htmlspecialchars($ord['service_name']) ?></td>
                                        <td><span class="badge bg-<?= $ord['status'] === 'Completed' ? 'success' : ($ord['status'] === 'Cancelled' ? 'danger' : ($ord['status'] === 'In Progress' ? 'info' : 'warning')) ?>"><?= htmlspecialchars($ord['status']) ?></span></td>
                                        <td>₱<?= number_format($ord['total_price'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <p class="text-muted text-center py-3 mb-0">No orders yet. <a href="place_order.php">Place your first order!</a></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-body">
                        <ul class="nav nav-tabs" id="accountTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">Personal Details</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">Change Password</button>
                            </li>
                        </ul>
                        <div class="tab-content p-4" id="accountTabContent">
                            <div class="tab-pane fade show active" id="personal" role="tabpanel">
                                <?php if ($profileMessage): ?>
                                <div class="alert alert-success"><?= htmlspecialchars($profileMessage) ?></div>
                                <?php endif; ?>
                                <?php if ($profileError): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($profileError) ?></div>
                                <?php endif; ?>
                                <form method="post">
                                    <div class="mb-3">
                                        <label class="form-label">Full Name</label>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($fullName) ?>" disabled>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">Save Changes</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="password" role="tabpanel">
                                <?php if ($passwordMessage): ?>
                                <div class="alert alert-success"><?= htmlspecialchars($passwordMessage) ?></div>
                                <?php endif; ?>
                                <?php if ($passwordError): ?>
                                <div class="alert alert-danger"><?= htmlspecialchars($passwordError) ?></div>
                                <?php endif; ?>
                                <form method="post">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<style>
.avatar-placeholder {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background-color: #e9ecef;
    display: flex;
    align-items: center;
    justify-content: center;
}
.avatar-placeholder .initials {
    font-size: 40px;
    font-weight: bold;
    color: #6c757d;
}
</style>

<?php require_once '../../includes/footer.php'; ?>
