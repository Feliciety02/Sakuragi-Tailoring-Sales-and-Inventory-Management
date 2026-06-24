<?php
require_once '../config/db_connect.php';
require_once '../config/session_handler.php';
require_once '../config/constants.php';
require_once '../app/Support/helpers.php';

redirect_if_logged_in();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize_input($_POST['full_name'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone_number = sanitize_input($_POST['phone_number'] ?? '');
    $role = ROLE_CUSTOMER;

    if ($full_name === '' || $email === '' || $password === '' || $confirm_password === '' || $phone_number === '') {
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
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <title>Sign Up - Sakuragi</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@500;600;700;800&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/public/css/login.css">
</head>
<body>
  <div class="auth-shell">
    <a href="/" class="back-link"><i class="fas fa-arrow-left"></i> Back to home</a>

    <div class="auth-grid auth-grid-register">
      <section class="brand-side brand-side-customer">
        <div class="content">
          <div class="logo">
            <img src="/public/assets/images/sakuragi-logo.png" alt="Sakuragi logo">
            <div class="logo-copy">
              <span>Sakuragi</span>
              <small>Tailoring Main Branch</small>
            </div>
          </div>

          <span class="eyebrow">Customer signup</span>
          <h1>Create an account to place orders and track updates more easily.</h1>
          <p>Your account helps keep measurements, order notes, and status updates in one place whenever you request custom garments, uniforms, or alterations.</p>

          <div class="feature-list">
            <div class="feature-item"><span class="icon"><i class="fas fa-shirt"></i></span> Book custom tailoring, uniforms, and alterations with clearer order details.</div>
            <div class="feature-item"><span class="icon"><i class="fas fa-ruler-combined"></i></span> Keep your measurements and notes attached to future requests.</div>
            <div class="feature-item"><span class="icon"><i class="fas fa-receipt"></i></span> Check progress and pickup readiness without asking for manual updates every time.</div>
          </div>

          <div class="auth-note">
            <strong>For customers</strong>
            <p>This signup creates a customer account for placing orders and tracking them online.</p>
          </div>
        </div>
      </section>

      <section class="form-side">
        <div class="form-container">
          <span class="eyebrow dark">New customer account</span>
          <h2>Create your account</h2>
          <p class="subtitle">Use your account to submit requests and review order progress online.</p>

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
                <input type="email" name="email" id="email" placeholder="you@example.com" required>
              </div>
            </div>

            <div class="form-group">
              <label for="phone_number">Phone Number</label>
              <div class="input-wrap">
                <i class="fas fa-phone"></i>
                <input type="text" name="phone_number" id="phone_number" placeholder="09XX XXX XXXX" required>
              </div>
            </div>

            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-wrap">
                <i class="fas fa-lock"></i>
                <input type="password" name="password" id="password" placeholder="Create a password" required>
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

          <div class="form-actions-row">
            <button type="button" class="demo-trigger" data-modal-open="customer-demo-modal">
              <i class="fas fa-user"></i>
              Customer demo
            </button>
          </div>

          <p class="signup-link">Already have an account? <a href="login.php">Sign in</a></p>
        </div>
      </section>
    </div>

    <div class="modal-backdrop" data-modal="customer-demo-modal" hidden>
      <div class="demo-modal demo-modal-compact" role="dialog" aria-modal="true" aria-labelledby="customer-demo-title">
        <div class="demo-modal-head">
          <div>
            <p class="modal-kicker">Sample access</p>
            <h3 id="customer-demo-title">Customer demo</h3>
          </div>
          <button type="button" class="modal-close" aria-label="Close customer demo" data-modal-close onclick="this.closest('[data-modal]').hidden = true; document.body.classList.remove('modal-open');">
            <i class="fas fa-xmark"></i>
          </button>
        </div>
        <p class="demo-copy">If you only want to explore the customer dashboard first, use the demo account below.</p>
        <div class="demo-grid demo-grid-single">
          <form method="post" action="login.php">
            <?= csrf_field() ?>
            <input type="hidden" name="email" value="customer@demo.ph">
            <input type="hidden" name="password" value="demo123">
            <button type="submit" class="demo-btn" style="--demo-accent: #7b7b7b">
              <i class="fas fa-user"></i>
              <span>Customer Demo</span>
            </button>
          </form>
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
