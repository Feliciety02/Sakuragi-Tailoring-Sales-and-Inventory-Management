<?php
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

redirect_if_logged_in();

// Auto-seed demo users with positions (bulk / uniform production focus)
$demoAccounts = [
    // [full_name, email, password, phone, role, position_id]
    ['Admin',              'admin@sakuragi.ph',        'admin123', '09171234567', 'admin',    null],
    ['Juan Dela Cruz',      'customer@demo.ph',         'demo123',  '09159876543', 'customer', null],
    ['Maria Santos',        'employee@demo.ph',         'demo123',  '09159876544', 'employee', 1],     // Cutter (position 1)
    ['Pedro Reyes',         'tailor@demo.ph',           'demo123',  '09151234561', 'employee', 1],     // Cutter
    ['Elena Gomez',         'senior@demo.ph',           'demo123',  '09151234562', 'employee', 2],     // Assembly Lead
    ['Rosa Villanueva',     'alteration@demo.ph',       'demo123',  '09151234563', 'employee', 3],     // Alteration Specialist
    ['Mario Cruz',          'pattern@demo.ph',          'demo123',  '09151234564', 'employee', 4],     // Pattern Maker
    ['Josefa Torres',       'sublimation@demo.ph',      'demo123',  '09151234565', 'employee', 5],     // Sublimation Tech
    ['Ramon Santos',        'screenprint@demo.ph',      'demo123',  '09151234566', 'employee', 6],     // Screen Print Operator
    ['Luzviminda Co',       'embroidery@demo.ph',       'demo123',  '09151234567', 'employee', 8],     // Embroidery Operator
    ['Antonio Garcia',      'qc@demo.ph',               'demo123',  '09151234568', 'employee', 10],    // QC Inspector (AQL sampling)
    ['Teresa Lim',          'packing@demo.ph',          'demo123',  '09151234569', 'employee', 11],    // Packing Lead
    ['Ricardo Reyes',       'production@demo.ph',       'demo123',  '09151234570', 'employee', 12],    // Production Coordinator
    ['Sofia Martinez',      'shop@demo.ph',             'demo123',  '09151234571', 'employee', 14],    // Sales Coordinator
    ['Carlos Mercado',      'inventory@demo.ph',        'demo123',  '09151234572', 'employee', 15],    // Material Handler
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
    ['admin',        'admin@sakuragi.ph',        'admin123', 'Production Manager', '#050505'],
    ['manager',      'admin@sakuragi.ph',        'admin123', 'Operations Head',     '#2b2b2b'],
    ['tailor',       'tailor@demo.ph',           'demo123',  'Cutting Team',        '#f60000'],
    ['senior',       'senior@demo.ph',           'demo123',  'Assembly Lead',       '#b30000'],
    ['alteration',   'alteration@demo.ph',       'demo123',  'Alterations',         '#7a0a0a'],
    ['pattern',      'pattern@demo.ph',          'demo123',  'Pattern / Grading',   '#4a0a0a'],
    ['sublimation',  'sublimation@demo.ph',      'demo123',  'Sublimation',         '#f04444'],
    ['screenprint',  'screenprint@demo.ph',      'demo123',  'Screen Printing',     '#8a1111'],
    ['embroidery',   'embroidery@demo.ph',       'demo123',  'Embroidery',          '#3a3a3a'],
    ['qc',           'qc@demo.ph',               'demo123',  'QC (AQL Sampling)',   '#1a1a1a'],
    ['packing',      'packing@demo.ph',          'demo123',  'Packing / Labeling',  '#5b5b5b'],
    ['production',   'production@demo.ph',       'demo123',  'Prod. Coordinator',   '#cf1010'],
    ['shop',         'shop@demo.ph',             'demo123',  'Sales / Order Intake','#ff4d4d'],
    ['inventory',    'inventory@demo.ph',        'demo123',  'Material Handler',    '#6b0000'],
    ['customer',     'customer@demo.ph',         'demo123',  'Customer',            '#8f8f8f'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    if (empty($email) || empty($password)) {
        set_flash('error', 'Email and password are required.');
        header('Location: login.php');
        exit();
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = ?");
    $stmt->execute([$email, STATUS_ACTIVE]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        switch ($user['role']) {
            case ROLE_ADMIN: header('Location: /dashboards/admin/dashboard.php'); break;
            case ROLE_MANAGER:
            case ROLE_EMPLOYEE: header('Location: /dashboards/employee/dashboard.php'); break;
            default: header('Location: /dashboards/customer/dashboard.php'); break;
        }
        exit();
    } else {
        set_flash('error', 'Invalid email or password.');
        header('Location: login.php');
        exit();
    }
}

$error = get_flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png">
  <title>Sign In — Sakuragi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #f5f5f5; color: #111111;
      min-height: 100vh; display: flex;
      -webkit-font-smoothing: antialiased;
    }
    .split { display: flex; width: 100%; min-height: 100vh; }
    .brand-side {
      flex: 1; background: linear-gradient(145deg, #050505, #181818 58%, #2a0000 100%);
      display: flex; flex-direction: column; justify-content: center;
      padding: 80px; position: relative; overflow: hidden;
    }
    .brand-side::before {
      content: ''; position: absolute; inset: 0;
      background:
        radial-gradient(circle at 18% 22%, rgba(246,0,0,.32) 0%, transparent 28%),
        radial-gradient(circle at 82% 78%, rgba(246,0,0,.18) 0%, transparent 24%);
    }
    .brand-side .content { position: relative; z-index: 1; max-width: 440px; }
    .brand-side .logo {
      display: flex; align-items: center; gap: 14px; margin-bottom: 40px;
    }
    .brand-side .logo img {
      width: 64px; height: 64px; object-fit: contain;
      filter: drop-shadow(0 8px 20px rgba(0,0,0,.25));
    }
    .brand-side .logo span { font-size: 1.2rem; font-weight: 700; color: #fff; }
    .brand-side h1 { font-size: 2.5rem; font-weight: 800; color: #fff; line-height: 1.2; letter-spacing: -.03em; margin-bottom: 16px; }
    .brand-side p { font-size: 1rem; color: rgba(255,255,255,.65); line-height: 1.7; margin-bottom: 48px; }
    .brand-side .feature-list { display: flex; flex-direction: column; gap: 16px; }
    .brand-side .feature-item {
      display: flex; align-items: center; gap: 12px;
      color: rgba(255,255,255,.8); font-size: .9rem; font-weight: 500;
    }
    .brand-side .feature-item .icon {
      width: 28px; height: 28px; border-radius: 8px;
      background: rgba(255,255,255,.1); display: flex;
      align-items: center; justify-content: center; font-size: .75rem;
      flex-shrink: 0;
    }
    .form-side {
      flex: 1; display: flex; align-items: center; justify-content: center;
      padding: 40px;
    }
    .form-container {
      width: 100%; max-width: 440px; max-height: 100vh;
      overflow-y: auto; padding: 8px 0;
    }
    .form-container::-webkit-scrollbar { width: 4px; }
    .form-container::-webkit-scrollbar-thumb { background: #d4d4d4; border-radius: 2px; }
    .form-container .back-link {
      display: inline-flex; align-items: center; gap: 6px;
      color: #737373; font-size: .85rem; font-weight: 500;
      text-decoration: none; margin-bottom: 24px; transition: .2s;
    }
    .form-container .back-link:hover { color: #111111; }
    .form-container h2 { font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; margin-bottom: 4px; }
    .form-container .subtitle { font-size: .9rem; color: #525252; margin-bottom: 28px; }
    .error-banner {
      padding: 12px 16px; background: #fef2f2; border: 1px solid #fecaca;
      border-radius: 10px; font-size: .85rem; color: #991b1b; margin-bottom: 20px;
      display: flex; align-items: center; gap: 8px;
    }
    .form-group { margin-bottom: 18px; }
    .form-group label {
      display: block; font-size: .8rem; font-weight: 600;
      color: #262626; margin-bottom: 6px;
    }
    .form-group .input-wrap { position: relative; }
    .form-group .input-wrap i {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      color: #737373; font-size: .9rem;
    }
    .form-group input {
      width: 100%; padding: 12px 14px 12px 42px;
      border: 1.5px solid #d4d4d4; border-radius: 10px;
      font-size: .9rem; font-family: inherit;
      outline: none; transition: .2s; background: #fff;
    }
    .form-group input:focus {
      border-color: #f60000; box-shadow: 0 0 0 3px rgba(246,0,0,.14);
    }
    .form-group input::placeholder { color: #a3a3a3; }
    .btn-submit {
      width: 100%; padding: 14px; border: none; border-radius: 10px;
      background: linear-gradient(135deg, #050505, #f60000); color: #fff; font-size: .95rem; font-weight: 600;
      font-family: inherit; cursor: pointer; transition: .2s;
    }
    .btn-submit:hover { transform: translateY(-1px); box-shadow: 0 8px 18px rgba(246,0,0,.28); }
    .demo-section {
      margin: 20px 0 0; padding: 16px;
      background: #fafafa; border: 1px solid #e5e5e5;
      border-radius: 12px;
    }
    .demo-section .demo-title {
      font-size: .7rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .05em; color: #737373; margin-bottom: 10px;
      text-align: center;
    }
    .demo-grid { display: flex; flex-wrap: wrap; gap: 6px; }
    .demo-grid form { flex: 1 0 calc(33.333% - 4px); min-width: 0; }
    .demo-btn {
      width: 100%; padding: 10px 4px; border: none;
      border-radius: 6px; cursor: pointer;
      font-size: .7rem; font-weight: 700; font-family: inherit;
      transition: .15s; display: flex; align-items: center;
      justify-content: center; gap: 4px; color: #fff;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .demo-btn:hover { transform: translateY(-1px); filter: brightness(1.15); box-shadow: 0 3px 8px rgba(0,0,0,.15); }
    .demo-btn i { font-size: .65rem; flex-shrink: 0; }
    .signup-link {
      text-align: center; margin-top: 20px;
      font-size: .85rem; color: #525252;
    }
    .signup-link a { color: #d10000; font-weight: 600; text-decoration: none; }
    .signup-link a:hover { text-decoration: underline; }
    @media (max-width: 1024px) {
      .brand-side { display: none; }
      .form-side { padding: 24px; }
      .form-container { max-height: none; overflow: visible; }
      .demo-grid form { flex: 1 0 calc(50% - 3px); }
    }
    @media (max-width: 480px) {
      .demo-grid form { flex: 1 0 100%; }
    }
  </style>
</head>
<body>
  <div class="split">

    <!-- Brand Side -->
    <div class="brand-side">
      <div class="content">
        <div class="logo">
          <img src="/public/assets/images/sakuragi-logo.png" alt="Sakuragi logo">
          <span>Sakuragi</span>
        </div>
        <h1>Bulk uniform production from order to delivery</h1>
        <p>Manage large-scale garment orders across cutting, printing, assembly, QC, and packing — all in one platform.</p>
        <div class="feature-list">
          <div class="feature-item"><span class="icon" style="color:#f87171"><i class="fas fa-check"></i></span> Batch tracking with quantity-based progress</div>
          <div class="feature-item"><span class="icon" style="color:#f87171"><i class="fas fa-check"></i></span> AQL sampling & lot-level QC pass/fail</div>
          <div class="feature-item"><span class="icon" style="color:#f87171"><i class="fas fa-check"></i></span> Role-based workspaces per production team</div>
        </div>
      </div>
    </div>

    <!-- Form Side -->
    <div class="form-side">
      <div class="form-container">
        <a href="/public/landing_page.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to home</a>
        <h2>Welcome back</h2>
        <p class="subtitle">Sign in to your Sakuragi account</p>

        <?php if ($error): ?>
        <div class="error-banner"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post">
          <?= csrf_field() ?>
          <div class="form-group">
            <label for="email">Email</label>
            <div class="input-wrap">
              <i class="fas fa-envelope"></i>
              <input type="email" name="email" id="email" placeholder="you@company.com" required>
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

        <div class="demo-section">
          <div class="demo-title"><i class="fas fa-bolt" style="margin-right:4px"></i> One-click demo — all roles</div>
          <div class="demo-grid">
            <?php $demoColors = [
              'admin'       => '#050505',
              'manager'     => '#2b2b2b',
              'tailor'      => '#f60000',
              'senior'      => '#b30000',
              'alteration'  => '#7a0a0a',
              'pattern'     => '#4a0a0a',
              'sublimation' => '#f04444',
              'screenprint' => '#8a1111',
              'embroidery'  => '#3a3a3a',
              'qc'          => '#1a1a1a',
              'packing'     => '#5b5b5b',
              'production'  => '#cf1010',
              'shop'        => '#ff4d4d',
              'inventory'   => '#6b0000',
              'customer'    => '#8f8f8f',
            ];
            $demoIcons = [
              'admin'       => 'fa-industry',
              'manager'     => 'fa-chart-line',
              'tailor'      => 'fa-cut',
              'senior'      => 'fa-tshirt',
              'alteration'  => 'fa-pencil-ruler',
              'pattern'     => 'fa-drafting-compass',
              'sublimation' => 'fa-print',
              'screenprint' => 'fa-palette',
              'embroidery'  => 'fa-thread',
              'qc'          => 'fa-clipboard-check',
              'packing'     => 'fa-box',
              'production'  => 'fa-clipboard-list',
              'shop'        => 'fa-store',
              'inventory'   => 'fa-roll',
              'customer'    => 'fa-user',
            ];
            foreach ($demoPositions as $dp):
              $key = $dp[0]; $label = $dp[3]; $color = $demoColors[$key] ?? '#6b7280';
            ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="email" value="<?= $dp[1] ?>">
              <input type="hidden" name="password" value="<?= $dp[2] ?>">
              <button type="submit" class="demo-btn" style="background:<?= $color ?>"><i class="fas <?= $demoIcons[$key] ?? 'fa-user' ?>"></i> <?= $label ?></button>
            </form>
            <?php endforeach; ?>
          </div>
        </div>

        <p class="signup-link">Don't have an account? <a href="register.php">Sign up</a></p>
      </div>
    </div>

  </div>
</body>
</html>
