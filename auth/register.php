<?php
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';

redirect_if_logged_in();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize_input($_POST['full_name']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $phone_number = sanitize_input($_POST['phone_number']);
    $role = ROLE_CUSTOMER;

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        set_flash('error', 'All fields are required.');
        header('Location: register.php');
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        set_flash('error', 'Invalid email format.');
        header('Location: register.php');
        exit();
    }

    if ($password !== $confirm_password) {
        set_flash('error', 'Passwords do not match.');
        header('Location: register.php');
        exit();
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        set_flash('error', 'Email is already registered.');
        header('Location: register.php');
        exit();
    }

    $hashed_password = hash_password($password);
    $insert_stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, phone_number, role, status) VALUES (?, ?, ?, ?, ?, ?)");
    $insert_stmt->execute([$full_name, $email, $hashed_password, $phone_number, $role, STATUS_ACTIVE]);

    set_flash('success', 'Registration successful! Please log in.');
    header('Location: login.php');
    exit();
}

$error = get_flash('error');
$success = get_flash('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign Up — Sakuragi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #f8fafc; color: #0f172a;
      min-height: 100vh; display: flex;
      -webkit-font-smoothing: antialiased;
    }
    .split { display: flex; width: 100%; min-height: 100vh; }
    .brand-side {
      flex: 1; background: #1e3a5f;
      display: flex; flex-direction: column; justify-content: center;
      padding: 80px; position: relative; overflow: hidden;
    }
    .brand-side::before {
      content: ''; position: absolute; inset: 0;
      background: radial-gradient(ellipse at 30% 50%, rgba(37,99,235,.15) 0%, transparent 60%);
    }
    .brand-side .content { position: relative; z-index: 1; max-width: 440px; }
    .brand-side .logo {
      display: flex; align-items: center; gap: 10px; margin-bottom: 40px;
    }
    .brand-side .logo svg { width: 32px; height: 32px; }
    .brand-side .logo span { font-size: 1.2rem; font-weight: 700; color: #fff; }
    .brand-side h1 { font-size: 2.5rem; font-weight: 800; color: #fff; line-height: 1.2; letter-spacing: -.03em; margin-bottom: 16px; }
    .brand-side p { font-size: 1rem; color: rgba(255,255,255,.65); line-height: 1.7; margin-bottom: 48px; }
    .brand-side .testimonial {
      background: rgba(255,255,255,.08); border-radius: 12px;
      padding: 24px; border: 1px solid rgba(255,255,255,.06);
    }
    .brand-side .testimonial .quote {
      font-size: .95rem; color: rgba(255,255,255,.8); line-height: 1.6;
      font-style: italic; margin-bottom: 12px;
    }
    .brand-side .testimonial .author {
      display: flex; align-items: center; gap: 10px;
    }
    .brand-side .testimonial .author .avatar {
      width: 36px; height: 36px; border-radius: 50%;
      background: #2563eb; display: flex; align-items: center;
      justify-content: center; color: #fff; font-size: .8rem; font-weight: 700;
    }
    .brand-side .testimonial .author .info { font-size: .85rem; }
    .brand-side .testimonial .author .name { font-weight: 600; color: #fff; }
    .brand-side .testimonial .author .role { color: rgba(255,255,255,.5); font-size: .8rem; }
    .form-side {
      flex: 1; display: flex; align-items: center; justify-content: center;
      padding: 40px;
    }
    .form-container {
      width: 100%; max-width: 420px; max-height: 100vh;
      overflow-y: auto; padding: 8px 0;
    }
    .form-container::-webkit-scrollbar { width: 4px; }
    .form-container::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 2px; }
    .form-container .back-link {
      display: inline-flex; align-items: center; gap: 6px;
      color: #94a3b8; font-size: .85rem; font-weight: 500;
      text-decoration: none; margin-bottom: 24px; transition: .2s;
    }
    .form-container .back-link:hover { color: #475569; }
    .form-container h2 { font-size: 1.5rem; font-weight: 800; letter-spacing: -.02em; margin-bottom: 4px; }
    .form-container .subtitle { font-size: .9rem; color: #64748b; margin-bottom: 28px; }
    .error-banner {
      padding: 12px 16px; background: #fef2f2; border: 1px solid #fecaca;
      border-radius: 10px; font-size: .85rem; color: #991b1b; margin-bottom: 20px;
      display: flex; align-items: center; gap: 8px;
    }
    .success-banner {
      padding: 12px 16px; background: #d1fae5; border: 1px solid #a7f3d0;
      border-radius: 10px; font-size: .85rem; color: #065f46; margin-bottom: 20px;
      display: flex; align-items: center; gap: 8px;
    }
    .form-group { margin-bottom: 18px; }
    .form-group label {
      display: block; font-size: .8rem; font-weight: 600;
      color: #475569; margin-bottom: 6px;
    }
    .form-group .input-wrap {
      position: relative;
    }
    .form-group .input-wrap i {
      position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
      color: #94a3b8; font-size: .9rem;
    }
    .form-group input {
      width: 100%; padding: 12px 14px 12px 42px;
      border: 1.5px solid #e2e8f0; border-radius: 10px;
      font-size: .9rem; font-family: inherit;
      outline: none; transition: .2s; background: #fff;
    }
    .form-group input:focus {
      border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.1);
    }
    .form-group input::placeholder { color: #94a3b8; }
    .btn-submit {
      width: 100%; padding: 14px; border: none; border-radius: 10px;
      background: #1e3a5f; color: #fff; font-size: .95rem; font-weight: 600;
      font-family: inherit; cursor: pointer; transition: .2s; margin-top: 4px;
    }
    .btn-submit:hover { background: #2563eb; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,.25); }
    .demo-section {
      margin: 20px 0 0; padding: 16px;
      background: #f8fafc; border: 1px solid #e2e8f0;
      border-radius: 12px;
    }
    .demo-section .demo-title {
      font-size: .7rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: .05em; color: #94a3b8; margin-bottom: 10px;
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
    .signin-link {
      text-align: center; margin-top: 24px;
      font-size: .85rem; color: #64748b;
    }
    .signin-link a { color: #2563eb; font-weight: 600; text-decoration: none; }
    .signin-link a:hover { text-decoration: underline; }
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
          <svg viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="8" fill="#2563eb"/>
            <path d="M8 12h16l-3.5 9h-9L8 12z" fill="#fff" opacity=".9"/>
            <path d="M16 8v16M11 11l5 5M21 11l-5 5" stroke="#fff" stroke-width="1.5" stroke-linecap="round" opacity=".5"/>
          </svg>
          <span>Sakuragi</span>
        </div>
        <h1>Start managing your production</h1>
        <p>Join hundreds of garment businesses using Sakuragi to streamline orders, production, and quality control.</p>
        <div class="testimonial">
          <div class="quote">"Sakuragi transformed how we manage our tailoring shop. From order intake to QC, everything is in one place."</div>
          <div class="author">
            <div class="avatar">MC</div>
            <div class="info"><div class="name">Maria Cruz</div><div class="role">Owner, Cruz Tailoring</div></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Form Side -->
    <div class="form-side">
      <div class="form-container">
        <a href="/public/landing_page.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to home</a>
        <h2>Create your account</h2>
        <p class="subtitle">Start your free trial — no credit card needed</p>

        <?php if ($error): ?>
        <div class="error-banner"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="success-banner"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="post">
          <div class="form-group">
            <label for="full_name">Full Name</label>
            <div class="input-wrap">
              <i class="fas fa-user"></i>
              <input type="text" name="full_name" id="full_name" placeholder="Juan Dela Cruz" required>
            </div>
          </div>
          <div class="form-group">
            <label for="email">Email</label>
            <div class="input-wrap">
              <i class="fas fa-envelope"></i>
              <input type="email" name="email" id="email" placeholder="you@company.com" required>
            </div>
          </div>
          <div class="form-group">
            <label for="phone_number">Phone Number</label>
            <div class="input-wrap">
              <i class="fas fa-phone"></i>
              <input type="text" name="phone_number" id="phone_number" placeholder="+63 912 345 6789" required>
            </div>
          </div>
          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
              <i class="fas fa-lock"></i>
              <input type="password" name="password" id="password" placeholder="Create a strong password" required>
            </div>
          </div>
          <div class="form-group">
            <label for="confirm_password">Confirm Password</label>
            <div class="input-wrap">
              <i class="fas fa-lock"></i>
              <input type="password" name="confirm_password" id="confirm_password" placeholder="Repeat your password" required>
            </div>
          </div>
          <button type="submit" class="btn-submit">Create Account</button>
        </form>

        <div class="demo-section">
          <div class="demo-title"><i class="fas fa-bolt" style="margin-right:4px"></i> Or try a demo account</div>
          <div class="demo-grid">
            <?php $demoColors = [
              'admin'       => '#1e3a5f',
              'manager'     => '#7c3aed',
              'tailor'      => '#2563eb',
              'senior'      => '#0891b2',
              'alteration'  => '#0d9488',
              'pattern'     => '#4f46e5',
              'sublimation' => '#d97706',
              'screenprint' => '#ea580c',
              'embroidery'  => '#db2777',
              'qc'          => '#059669',
              'packing'     => '#6366f1',
              'production'  => '#f97316',
              'shop'        => '#ec4899',
              'inventory'   => '#14b8a6',
              'customer'    => '#65a30d',
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
            $demoPositions = [
              ['admin',        'admin@sakuragi.ph',        'admin123', 'Production Manager'],
              ['manager',      'admin@sakuragi.ph',        'admin123', 'Operations Head'],
              ['tailor',       'tailor@demo.ph',           'demo123',  'Cutting Team'],
              ['senior',       'senior@demo.ph',           'demo123',  'Assembly Lead'],
              ['alteration',   'alteration@demo.ph',       'demo123',  'Alterations'],
              ['pattern',      'pattern@demo.ph',          'demo123',  'Pattern / Grading'],
              ['sublimation',  'sublimation@demo.ph',      'demo123',  'Sublimation'],
              ['screenprint',  'screenprint@demo.ph',      'demo123',  'Screen Printing'],
              ['embroidery',   'embroidery@demo.ph',       'demo123',  'Embroidery'],
              ['qc',           'qc@demo.ph',               'demo123',  'QC (AQL Sampling)'],
              ['packing',      'packing@demo.ph',          'demo123',  'Packing / Labeling'],
              ['production',   'production@demo.ph',       'demo123',  'Prod. Coordinator'],
              ['shop',         'shop@demo.ph',             'demo123',  'Sales / Order Intake'],
              ['inventory',    'inventory@demo.ph',        'demo123',  'Material Handler'],
              ['customer',     'customer@demo.ph',         'demo123',  'Customer'],
            ];
            foreach ($demoPositions as $dp):
              $key = $dp[0]; $label = $dp[3]; $color = $demoColors[$key] ?? '#6b7280';
            ?>
            <form method="post" action="login.php">
              <input type="hidden" name="email" value="<?= $dp[1] ?>">
              <input type="hidden" name="password" value="<?= $dp[2] ?>">
              <button type="submit" class="demo-btn" style="background:<?= $color ?>"><i class="fas <?= $demoIcons[$key] ?? 'fa-user' ?>"></i> <?= $label ?></button>
            </form>
            <?php endforeach; ?>
          </div>
        </div>

        <p class="signin-link">Already have an account? <a href="login.php">Sign in</a></p>
      </div>
    </div>

  </div>
</body>
</html>
