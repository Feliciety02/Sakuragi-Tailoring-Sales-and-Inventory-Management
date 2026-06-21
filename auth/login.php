<?php
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';
require_once '../config/constants.php';
require_once '../app/Support/helpers.php';

redirect_if_logged_in();

$demoAccounts = [
    ['Admin', 'admin@sakuragi.ph', 'admin123', '09171234567', 'admin', null],
    ['Juan Dela Cruz', 'customer@demo.ph', 'demo123', '09159876543', 'customer', null],
    ['Maria Santos', 'employee@demo.ph', 'demo123', '09159876544', 'employee', 1],
    ['Pedro Reyes', 'tailor@demo.ph', 'demo123', '09151234561', 'employee', 1],
    ['Elena Gomez', 'senior@demo.ph', 'demo123', '09151234562', 'employee', 2],
    ['Rosa Villanueva', 'alteration@demo.ph', 'demo123', '09151234563', 'employee', 3],
    ['Mario Cruz', 'pattern@demo.ph', 'demo123', '09151234564', 'employee', 4],
    ['Josefa Torres', 'sublimation@demo.ph', 'demo123', '09151234565', 'employee', 5],
    ['Ramon Santos', 'screenprint@demo.ph', 'demo123', '09151234566', 'employee', 6],
    ['Luzviminda Co', 'embroidery@demo.ph', 'demo123', '09151234567', 'employee', 8],
    ['Antonio Garcia', 'qc@demo.ph', 'demo123', '09151234568', 'employee', 10],
    ['Teresa Lim', 'packing@demo.ph', 'demo123', '09151234569', 'employee', 11],
    ['Ricardo Reyes', 'production@demo.ph', 'demo123', '09151234570', 'employee', 12],
    ['Sofia Martinez', 'shop@demo.ph', 'demo123', '09151234571', 'employee', 14],
    ['Carlos Mercado', 'inventory@demo.ph', 'demo123', '09151234572', 'employee', 15],
];

foreach ($demoAccounts as $demo) {
    $chk = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $chk->execute([$demo[1]]);
    $existingId = $chk->fetchColumn();

    if (!$existingId) {
        $ins = $pdo->prepare("INSERT INTO users (full_name, email, password, phone_number, role, branch_id, status) VALUES (?, ?, ?, ?, ?, NULL, 'Active')");
        $ins->execute([$demo[0], $demo[1], password_hash($demo[2], PASSWORD_DEFAULT), $demo[3], $demo[4]]);
        $newUserId = $pdo->lastInsertId();
        if ($demo[5] !== null) {
            $stmt = $pdo->prepare("INSERT INTO employees (user_id, branch_id, hire_date, salary, position_id, status_id) VALUES (?, 2, CURDATE(), 0, ?, 1)");
            $stmt->execute([$newUserId, $demo[5]]);
        }
    } elseif ($demo[5] !== null) {
        $empChk = $pdo->prepare("SELECT employee_id FROM employees WHERE user_id = ?");
        $empChk->execute([$existingId]);
        if (!$empChk->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO employees (user_id, branch_id, hire_date, salary, position_id, status_id) VALUES (?, 2, CURDATE(), 0, ?, 1)");
            $stmt->execute([$existingId, $demo[5]]);
        }
    }
}

$demoPositions = [
    ['admin', 'admin@sakuragi.ph', 'admin123', 'Production Manager', '#151515', 'fa-industry'],
    ['manager', 'admin@sakuragi.ph', 'admin123', 'Operations Head', '#2b2b2b', 'fa-chart-line'],
    ['tailor', 'tailor@demo.ph', 'demo123', 'Cutting Team', '#9c1111', 'fa-cut'],
    ['senior', 'senior@demo.ph', 'demo123', 'Assembly Lead', '#7c1010', 'fa-shirt'],
    ['alteration', 'alteration@demo.ph', 'demo123', 'Alterations', '#5e1b1b', 'fa-pencil-ruler'],
    ['pattern', 'pattern@demo.ph', 'demo123', 'Pattern / Grading', '#4a0a0a', 'fa-drafting-compass'],
    ['sublimation', 'sublimation@demo.ph', 'demo123', 'Sublimation', '#b32020', 'fa-print'],
    ['screenprint', 'screenprint@demo.ph', 'demo123', 'Screen Printing', '#862121', 'fa-palette'],
    ['embroidery', 'embroidery@demo.ph', 'demo123', 'Embroidery', '#3a3a3a', 'fa-scissors'],
    ['qc', 'qc@demo.ph', 'demo123', 'QC (AQL Sampling)', '#1a1a1a', 'fa-clipboard-check'],
    ['packing', 'packing@demo.ph', 'demo123', 'Packing / Labeling', '#5b5b5b', 'fa-box'],
    ['production', 'production@demo.ph', 'demo123', 'Prod. Coordinator', '#a51414', 'fa-clipboard-list'],
    ['shop', 'shop@demo.ph', 'demo123', 'Sales / Order Intake', '#cf2f2f', 'fa-store'],
    ['inventory', 'inventory@demo.ph', 'demo123', 'Material Handler', '#6b0000', 'fa-roll'],
    ['customer', 'customer@demo.ph', 'demo123', 'Customer', '#7b7b7b', 'fa-user'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        set_flash('error', 'Email and password are required.');
        header('Location: login.php');
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = ?");
    $stmt->execute([$email, STATUS_ACTIVE]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $positionStmt = $pdo->prepare("
            SELECT p.position_name
            FROM employees e
            LEFT JOIN positions p ON e.position_id = p.position_id
            WHERE e.user_id = ?
            LIMIT 1
        ");
        $positionStmt->execute([(int) $user['user_id']]);
        $positionName = (string) ($positionStmt->fetchColumn() ?: '');
        $normalizedRole = normalize_user_role((string) $user['role'], $positionName);

        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['base_role'] = $user['role'];
        $_SESSION['role'] = $normalizedRole;
        $_SESSION['role_context'] = $normalizedRole;
        header('Location: ' . get_role_dashboard_home($pdo, $normalizedRole, (int) $user['user_id']));
        exit();
    }

    set_flash('error', 'Invalid email or password.');
    header('Location: login.php');
    exit();
}

$error = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png">
  <title>Sign In - Sakuragi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/public/css/login.css">
</head>
<body>
  <div class="auth-shell">
    <a href="/" class="back-link"><i class="fas fa-arrow-left"></i> Back to home</a>

    <div class="auth-grid">
      <section class="brand-side">
        <div class="content">
          <div class="logo">
            <img src="/public/assets/images/sakuragi-logo.png" alt="Sakuragi logo">
            <div class="logo-copy">
              <span>Sakuragi</span>
              <small>Tailoring Main Branch</small>
            </div>
          </div>

          <span class="eyebrow">Customer and staff access</span>
          <h1>Sign in to manage orders, updates, and production work.</h1>
          <p>Use one account system for customer tracking, shop coordination, and team workflows without switching between separate tools.</p>

          <div class="feature-list">
            <div class="feature-item"><span class="icon"><i class="fas fa-ruler-combined"></i></span> Measurements, notes, and requests stay attached to each order.</div>
            <div class="feature-item"><span class="icon"><i class="fas fa-list-check"></i></span> Follow progress from intake to release with clearer status updates.</div>
            <div class="feature-item"><span class="icon"><i class="fas fa-people-group"></i></span> Separate access for admin, staff, and customers.</div>
          </div>
        </div>
      </section>

      <section class="form-side">
        <div class="form-container">
          <span class="eyebrow dark">Account access</span>
          <h2>Welcome back</h2>
          <p class="subtitle">Sign in to your Sakuragi account.</p>

          <?php if ($error): ?>
            <div class="error-banner"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="post">
            <?= csrf_field() ?>
            <div class="form-group">
              <label for="email">Email</label>
              <div class="input-wrap">
                <i class="fas fa-envelope"></i>
                <input type="email" name="email" id="email" placeholder="you@example.com" required>
              </div>
            </div>

            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-wrap">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" placeholder="Enter your password" required>
              </div>
            </div>

            <button type="submit" class="btn-submit">Sign In</button>
          </form>

          <div class="form-actions-row">
            <button type="button" class="demo-trigger" data-modal-open="demo-access-modal">
              <i class="fas fa-bolt"></i>
              Demo access
            </button>
          </div>

          <p class="signup-link">Don't have an account? <a href="register.php">Sign up</a></p>
        </div>
      </section>
    </div>

    <div class="modal-backdrop" data-modal="demo-access-modal" hidden>
      <div class="demo-modal" role="dialog" aria-modal="true" aria-labelledby="demo-access-title">
        <div class="demo-modal-head">
          <div>
            <p class="modal-kicker">Sample accounts</p>
            <h3 id="demo-access-title">Demo access</h3>
          </div>
          <button type="button" class="modal-close" aria-label="Close demo access" data-modal-close>
            <i class="fas fa-xmark"></i>
          </button>
        </div>
        <p class="demo-copy">Quick sign-in for available roles in the sample system.</p>
        <div class="demo-grid">
          <?php foreach ($demoPositions as $demo): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="email" value="<?= htmlspecialchars($demo[1]) ?>">
              <input type="hidden" name="password" value="<?= htmlspecialchars($demo[2]) ?>">
              <button type="submit" class="demo-btn" style="--demo-accent: <?= htmlspecialchars($demo[4]) ?>">
                <i class="fas <?= htmlspecialchars($demo[5]) ?>"></i>
                <span><?= htmlspecialchars($demo[3]) ?></span>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <script>
    const modalTriggers = document.querySelectorAll('[data-modal-open]');
    const modalBackdrops = document.querySelectorAll('[data-modal]');

    function closeModal(modal) {
      modal.hidden = true;
      document.body.classList.remove('modal-open');
    }

    modalTriggers.forEach(trigger => {
      trigger.addEventListener('click', () => {
        const modal = document.querySelector(`[data-modal="${trigger.dataset.modalOpen}"]`);
        if (!modal) return;
        modal.hidden = false;
        document.body.classList.add('modal-open');
      });
    });

    modalBackdrops.forEach(modal => {
      modal.addEventListener('click', event => {
        if (event.target === modal || event.target.closest('[data-modal-close]')) {
          closeModal(modal);
        }
      });
    });

    document.addEventListener('keydown', event => {
      if (event.key !== 'Escape') return;
      modalBackdrops.forEach(modal => {
        if (!modal.hidden) closeModal(modal);
      });
    });
  </script>
</body>
</html>
