<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../app/Middleware/auth_required.php';
require_once '../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once __DIR__ . '/../../app/Support/helpers.php';
$pageTitle = 'My Profile';

if (get_user_role() === ROLE_CUSTOMER) {
    header('Location: /dashboards/customer/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
try {
    $positionSql = "
        SELECT p.position_name 
        FROM employees e
        JOIN positions p ON e.position_id = p.position_id
        WHERE e.user_id = ?
    ";
    $positionStmt = $pdo->prepare($positionSql);
    $positionStmt->execute([$user_id]);
    $positionData = $positionStmt->fetch();
    $positionName = $positionData ? $positionData['position_name'] : '';
} catch (PDOException $e) {
}

$user_id = $_SESSION['user_id'];

try {
    $userSql = "
        SELECT u.*, e.position_id, e.hire_date, e.branch_id
        FROM users u
        LEFT JOIN employees e ON u.user_id = e.user_id
        WHERE u.user_id = ?
    ";
    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute([$user_id]);
    $user = $userStmt->fetch();

    $positionSql = 'SELECT position_name FROM positions WHERE position_id = ?';
    $positionStmt = $pdo->prepare($positionSql);
    $positionStmt->execute([$user['position_id'] ?? 0]);
    $position = $positionStmt->fetch();
    $positionName = $position ? $position['position_name'] : 'Tailor';

    $branchSql = 'SELECT branch_name FROM branches WHERE branch_id = ?';
    $branchStmt = $pdo->prepare($branchSql);
    $branchStmt->execute([$user['branch_id'] ?? 0]);
    $branch = $branchStmt->fetch();
    $branchName = $branch ? $branch['branch_name'] : 'Main Branch';

    $startOfWeek = date('Y-m-d', strtotime('monday this week'));
    $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
    $startOfMonth = date('Y-m-01');
    $endOfMonth = date('Y-m-t');

    $weekStatsSql = "
        SELECT 
            COUNT(CASE WHEN ow.assigned_employee = ? THEN 1 END) AS assigned_count,
            COUNT(CASE WHEN ow.assigned_employee = ? AND o.status = 'Completed' THEN 1 END) AS completed_count
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        WHERE o.order_date BETWEEN ? AND ?
    ";
    $weekStatsStmt = $pdo->prepare($weekStatsSql);
    $weekStatsStmt->execute([$user_id, $user_id, $startOfWeek, $endOfWeek]);
    $weekStats = $weekStatsStmt->fetch();

    $monthStatsSql = "
        SELECT 
            COUNT(CASE WHEN ow.assigned_employee = ? THEN 1 END) AS assigned_count,
            COUNT(CASE WHEN ow.assigned_employee = ? AND o.status = 'Completed' THEN 1 END) AS completed_count
        FROM order_workflow ow
        JOIN orders o ON ow.order_id = o.order_id
        WHERE o.order_date BETWEEN ? AND ?
    ";
    $monthStatsStmt = $pdo->prepare($monthStatsSql);
    $monthStatsStmt->execute([$user_id, $user_id, $startOfMonth, $endOfMonth]);
    $monthStats = $monthStatsStmt->fetch();

    $totalSubmissions = 0;
    $passRate = 95;
    try {
        $qualitySql = "
            SELECT 
                COUNT(ws.submission_id) AS total_submissions,
                COUNT(CASE WHEN ws.status = 'Passed' THEN 1 END) AS passed_count
            FROM work_submissions ws
            WHERE ws.employee_id = ?
            AND ws.submission_date >= ?
        ";
        $qualityStmt = $pdo->prepare($qualitySql);
        $qualityStmt->execute([$user_id, $startOfMonth]);
        $qualityStats = $qualityStmt->fetch();
        $totalSubmissions = $qualityStats['total_submissions'] ?? 0;
        $passRate = $totalSubmissions > 0 ? round(($qualityStats['passed_count'] / $totalSubmissions) * 100) : 95;
    } catch (PDOException $e) {
        error_log('Quality stats unavailable: ' . $e->getMessage());
    }

} catch (PDOException $e) {
    error_log('Profile error: ' . $e->getMessage());
    $user = [];
    $positionName = 'Tailor';
    $branchName = 'Main Branch';
    $weekStats = ['assigned_count' => 0, 'completed_count' => 0];
    $monthStats = ['assigned_count' => 0, 'completed_count' => 0];
    $passRate = 0;
    $totalSubmissions = 0;
}

$passwordMessage = '';
$passwordError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $passwordError = 'All fields are required';
    } elseif ($newPassword !== $confirmPassword) {
        $passwordError = 'New passwords do not match';
    } elseif (strlen($newPassword) < 8) {
        $passwordError = 'Password must be at least 8 characters long';
    } else {
        try {
            $checkPasswordSql = 'SELECT password FROM users WHERE user_id = ?';
            $checkPasswordStmt = $pdo->prepare($checkPasswordSql);
            $checkPasswordStmt->execute([$user_id]);
            $storedPassword = $checkPasswordStmt->fetchColumn();

            if (!password_verify($currentPassword, $storedPassword)) {
                $passwordError = 'Current password is incorrect';
            } else {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updatePasswordSql = 'UPDATE users SET password = ? WHERE user_id = ?';
                $updatePasswordStmt = $pdo->prepare($updatePasswordSql);
                $updatePasswordStmt->execute([$hashedPassword, $user_id]);
                $passwordMessage = 'Password changed successfully';
            }
        } catch (PDOException $e) {
            error_log('Password change error: ' . $e->getMessage());
            $passwordError = 'An error occurred. Please try again.';
        }
    }
}

$profileMessage = '';
$profileError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';

    if (empty($email)) {
        $profileError = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $profileError = 'Please enter a valid email address';
    } else {
        try {
            $updateProfileSql = 'UPDATE users SET email = ?, phone_number = ? WHERE user_id = ?';
            $updateProfileStmt = $pdo->prepare($updateProfileSql);
            $updateProfileStmt->execute([$email, $phone, $user_id]);
            $user['email'] = $email;
            $user['phone_number'] = $phone;
            $profileMessage = 'Profile updated successfully';
        } catch (PDOException $e) {
            error_log('Profile update error: ' . $e->getMessage());
            $profileError = 'An error occurred. Please try again.';
        }
    }
}

$fullName = '';
if (isset($user['first_name']) && isset($user['last_name'])) {
    $fullName = $user['first_name'] . ' ' . $user['last_name'];
} elseif (isset($user['full_name'])) {
    $fullName = $user['full_name'];
} elseif (isset($user['name'])) {
    $fullName = $user['name'];
} else {
    $fullName = 'Employee User';
}

$hireDate = isset($user['hire_date']) ? date('m/d/Y', strtotime($user['hire_date'])) : '';
$employeeId = 'EMP-' . str_pad($user_id, 4, '0', STR_PAD_LEFT);

// Get initials
$nameParts = explode(' ', $fullName);
$initials = '';
if (count($nameParts) >= 2) {
    $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1));
} else {
    $initials = strtoupper(substr($fullName, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Profile — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="emp-profile-styles">
    /* ── Profile Page Styles ── */
    .profile-layout {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 24px;
      align-items: start;
    }
    .profile-card {
      background: var(--surface);
      border-radius: var(--radius-lg);
      border: 1px solid var(--border);
      padding: 28px;
      box-shadow: var(--shadow-xs);
    }
    .profile-avatar {
      width: 96px;
      height: 96px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--accent), #15294a);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.2rem;
      font-weight: 700;
      margin: 0 auto 16px;
      border: 3px solid rgba(255,255,255,0.8);
      box-shadow: 0 4px 20px rgba(0,0,0,0.1);
    }
    .profile-name {
      font-size: 1.15rem;
      font-weight: 700;
      color: var(--text-primary);
      text-align: center;
      margin-bottom: 4px;
    }
    .profile-role {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--accent-blue);
      background: rgba(37,99,235,0.08);
      padding: 4px 12px;
      border-radius: 100px;
      margin: 0 auto 20px;
      width: fit-content;
    }
    .profile-role i {
      font-size: 0.65rem;
    }
    .profile-meta {
      border-top: 1px solid var(--border-light);
      padding-top: 20px;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .profile-meta-item {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .profile-meta-item i {
      width: 16px;
      color: var(--text-tertiary);
      font-size: 0.9rem;
      text-align: center;
      flex-shrink: 0;
    }
    .profile-meta-item .label {
      font-size: 0.72rem;
      color: var(--text-tertiary);
      font-weight: 500;
    }
    .profile-meta-item .value {
      font-size: 0.85rem;
      color: var(--text-primary);
      font-weight: 500;
    }
    .profile-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-top: 20px;
      border-top: 1px solid var(--border-light);
      padding-top: 20px;
    }
    .profile-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 10px 16px;
      border-radius: var(--radius-sm);
      font-size: 0.82rem;
      font-weight: 600;
      font-family: inherit;
      border: none;
      cursor: pointer;
      transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
      text-decoration: none;
    }
    .profile-btn-outline {
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text-secondary);
    }
    .profile-btn-outline:hover {
      background: rgba(0,0,0,0.03);
      border-color: #d1d5db;
      color: var(--text-primary);
    }

    /* ── Stats Grid ── */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin-bottom: 24px;
    }
    .stat-card {
      background: var(--surface);
      border-radius: var(--radius-md);
      border: 1px solid var(--border);
      padding: 18px 16px;
      text-align: center;
      box-shadow: var(--shadow-xs);
      transition: box-shadow 0.2s ease, transform 0.2s ease;
    }
    .stat-card:hover {
      box-shadow: var(--shadow-sm);
      transform: translateY(-1px);
    }
    .stat-card .stat-value {
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--text-primary);
      letter-spacing: -0.02em;
      line-height: 1.2;
    }
    .stat-card .stat-label {
      font-size: 0.72rem;
      color: var(--text-tertiary);
      font-weight: 500;
      margin-top: 4px;
    }

    /* ── Profile Tabs ── */
    .profile-tabs {
      display: flex;
      gap: 0;
      border-bottom: 1px solid var(--border);
      margin-bottom: 24px;
    }
    .profile-tab {
      padding: 10px 20px;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-tertiary);
      background: none;
      border: none;
      border-bottom: 2px solid transparent;
      cursor: pointer;
      transition: color 0.15s ease, border-color 0.15s ease;
      font-family: inherit;
      margin-bottom: -1px;
    }
    .profile-tab:hover {
      color: var(--text-secondary);
    }
    .profile-tab.active {
      color: var(--accent-blue);
      border-bottom-color: var(--accent-blue);
    }
    .profile-tab-content { display: none; }
    .profile-tab-content.active { display: block; }

    /* ── Form Styles ── */
    .form-group {
      margin-bottom: 18px;
    }
    .form-group label {
      display: block;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--text-secondary);
      margin-bottom: 6px;
    }
    .form-group .form-input {
      width: 100%;
      padding: 10px 14px;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      font-size: 0.85rem;
      font-family: inherit;
      outline: none;
      transition: border-color 0.15s ease, box-shadow 0.15s ease;
      color: var(--text-primary);
      background: #fff;
    }
    .form-group .form-input:focus {
      border-color: var(--accent-blue);
      box-shadow: 0 0 0 3px rgba(37,99,235,0.08);
    }
    .form-group .form-input:disabled {
      background: #f8fafc;
      color: var(--text-tertiary);
      cursor: not-allowed;
    }
    .form-group .form-hint {
      font-size: 0.72rem;
      color: var(--text-tertiary);
      margin-top: 4px;
    }
    .form-group .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px;
    }
    .form-submit {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 24px;
      border-radius: var(--radius-sm);
      font-size: 0.85rem;
      font-weight: 600;
      font-family: inherit;
      border: none;
      cursor: pointer;
      background: var(--accent);
      color: #fff;
      transition: background 0.15s ease, box-shadow 0.15s ease;
    }
    .form-submit:hover {
      background: #162d4a;
      box-shadow: 0 4px 12px rgba(30,58,95,0.2);
    }
    .form-alert {
      padding: 10px 14px;
      border-radius: var(--radius-sm);
      font-size: 0.82rem;
      font-weight: 500;
      margin-bottom: 18px;
    }
    .form-alert.success {
      background: rgba(5,150,105,0.08);
      color: var(--accent-emerald);
      border: 1px solid rgba(5,150,105,0.12);
    }
    .form-alert.error {
      background: rgba(220,38,38,0.08);
      color: var(--accent-red);
      border: 1px solid rgba(220,38,38,0.12);
    }

    @media (max-width: 860px) {
      .profile-layout { grid-template-columns: 1fr; }
      .stats-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 480px) {
      .stats-grid { grid-template-columns: 1fr; }
      .form-group .form-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body data-role="<?= htmlspecialchars(get_user_role()) ?>">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<div class="dash-content">
      <?= renderPageHeader('Profile', 'Manage your account and view your performance') ?>

      <div class="profile-layout">
        <!-- Left Column -->
        <div class="profile-card">
          <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
          <div class="profile-name"><?= htmlspecialchars($fullName) ?></div>
          <div class="profile-role"><i class="fas fa-circle" style="font-size:0.5rem"></i> <?= htmlspecialchars($positionName) ?></div>

          <div class="profile-meta">
            <div class="profile-meta-item">
              <i class="fas fa-envelope"></i>
              <div>
                <div class="label">Email</div>
                <div class="value"><?= htmlspecialchars($user['email'] ?? '—') ?></div>
              </div>
            </div>
            <div class="profile-meta-item">
              <i class="fas fa-id-card"></i>
              <div>
                <div class="label">Employee ID</div>
                <div class="value"><?= htmlspecialchars($employeeId) ?></div>
              </div>
            </div>
            <div class="profile-meta-item">
              <i class="fas fa-building"></i>
              <div>
                <div class="label">Branch</div>
                <div class="value"><?= htmlspecialchars($branchName) ?></div>
              </div>
            </div>
            <div class="profile-meta-item">
              <i class="fas fa-calendar-alt"></i>
              <div>
                <div class="label">Joined</div>
                <div class="value"><?= !empty($hireDate) ? htmlspecialchars($hireDate) : '—' ?></div>
              </div>
            </div>
          </div>

          <div class="profile-actions">
            <button class="profile-btn profile-btn-outline" onclick="document.getElementById('photoUpload').click()">
              <i class="fas fa-camera"></i> Update Photo
            </button>
            <input type="file" id="photoUpload" accept="image/*" style="display:none">
          </div>
        </div>

        <!-- Right Column -->
        <div>
          <!-- Stats -->
          <div class="stats-grid">
            <div class="stat-card">
              <div class="stat-value"><?= $weekStats['assigned_count'] ?? 0 ?></div>
              <div class="stat-label">Assigned This Week</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= $weekStats['completed_count'] ?? 0 ?></div>
              <div class="stat-label">Completed This Week</div>
            </div>
            <div class="stat-card">
              <div class="stat-value"><?= $passRate ?>%</div>
              <div class="stat-label">Quality Rate</div>
            </div>
          </div>

          <!-- Tabs -->
          <div class="profile-card">
            <div class="profile-tabs">
              <button class="profile-tab active" data-tab="personal">Personal Details</button>
              <button class="profile-tab" data-tab="password">Password</button>
            </div>

            <!-- Personal Details Tab -->
            <div class="profile-tab-content active" id="tab-personal">
              <?php if ($profileMessage): ?>
                <div class="form-alert success"><?= htmlspecialchars($profileMessage) ?></div>
              <?php endif; ?>
              <?php if ($profileError): ?>
                <div class="form-alert error"><?= htmlspecialchars($profileError) ?></div>
              <?php endif; ?>
              <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <div class="form-group">
                  <label>Full Name</label>
                  <input type="text" class="form-input" value="<?= htmlspecialchars($fullName) ?>" disabled>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" class="form-input" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                  </div>
                  <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" class="form-input" name="phone" value="<?= htmlspecialchars($user['phone_number'] ?? '') ?>">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Branch</label>
                    <input type="text" class="form-input" value="<?= htmlspecialchars($branchName) ?>" disabled>
                  </div>
                  <div class="form-group">
                    <label>Position</label>
                    <input type="text" class="form-input" value="<?= htmlspecialchars($positionName) ?>" disabled>
                  </div>
                </div>
                <button type="submit" name="update_profile" class="form-submit">
                  <i class="fas fa-check"></i> Save Changes
                </button>
              </form>
            </div>

            <!-- Password Tab -->
            <div class="profile-tab-content" id="tab-password">
              <?php if ($passwordMessage): ?>
                <div class="form-alert success"><?= htmlspecialchars($passwordMessage) ?></div>
              <?php endif; ?>
              <?php if ($passwordError): ?>
                <div class="form-alert error"><?= htmlspecialchars($passwordError) ?></div>
              <?php endif; ?>
              <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>">
                <div class="form-group">
                  <label>Current Password</label>
                  <input type="password" class="form-input" name="current_password" required>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>New Password</label>
                    <input type="password" class="form-input" id="new_password" name="new_password" required minlength="8">
                    <div class="form-hint">At least 8 characters</div>
                  </div>
                  <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" class="form-input" id="confirm_password" name="confirm_password" required minlength="8">
                  </div>
                </div>
                <button type="submit" name="change_password" class="form-submit">
                  <i class="fas fa-lock"></i> Change Password
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // Tab switching
  document.querySelectorAll('.profile-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.profile-tab-content').forEach(c => c.classList.remove('active'));
      tab.classList.add('active');
      const target = document.getElementById('tab-' + tab.dataset.tab);
      if (target) target.classList.add('active');
    });
  });

  // Password match validation
  const newPwd = document.getElementById('new_password');
  const confirmPwd = document.getElementById('confirm_password');
  if (confirmPwd) {
    confirmPwd.addEventListener('input', () => {
      if (confirmPwd.value && confirmPwd.value !== newPwd.value) {
        confirmPwd.setCustomValidity('Passwords do not match');
      } else {
        confirmPwd.setCustomValidity('');
      }
    });
  }

  // Photo upload
  document.getElementById('photoUpload')?.addEventListener('change', function() {
    if (this.files && this.files[0]) {
      alert('Photo upload is not yet implemented on the server side.');
    }
  });
});
</script>
</body>
</html>
