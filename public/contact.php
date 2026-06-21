<?php
require_once __DIR__ . '/../config/session_handler.php';
require_once __DIR__ . '/../config/db_connect.php';

$settings = [
    'shop_name' => 'Sakuragi Tailoring Shop',
    'shop_email' => 'contact@sakuragi.ph',
    'contact_number' => '0917 123 4567',
    'address' => '123 JP Laurel Ave, Davao City',
    'open_time' => '09:00',
    'close_time' => '18:00',
    'facebook_link' => 'https://facebook.com/sakuragi.shop',
    'instagram_link' => 'https://instagram.com/sakuragi.shop',
];

try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM shop_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    error_log('Contact page settings load error: ' . $e->getMessage());
}

$openTime = !empty($settings['open_time']) ? date('g:i A', strtotime($settings['open_time'])) : null;
$closeTime = !empty($settings['close_time']) ? date('g:i A', strtotime($settings['close_time'])) : null;
$hoursLabel = ($openTime && $closeTime) ? $openTime . ' to ' . $closeTime : 'Please contact the shop for business hours';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Sakuragi Tailoring</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/public/assets/css/landing-modern.css">
</head>
<body class="contact-page">
  <?php require __DIR__ . '/partials/landing/nav.php'; ?>

  <main>
    <section class="contact-hero">
      <div class="container contact-shell">
        <div class="contact-intro fade-up">
          <span class="section-kicker">Contact Sakuragi</span>
          <h1>Reach the shop directly for orders, follow-ups, and pickup questions.</h1>
          <p>Use the details below to contact Sakuragi Tailoring about custom uniforms, tailoring work, alterations, and order status concerns.</p>
        </div>

        <div class="contact-grid">
          <article class="contact-card fade-up">
            <span class="contact-icon"><i class="fas fa-phone"></i></span>
            <span class="contact-label">Phone</span>
            <strong><?= htmlspecialchars($settings['contact_number']) ?></strong>
            <p>Best for quick order follow-ups and pickup coordination.</p>
            <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $settings['contact_number'])) ?>">Call now</a>
          </article>

          <article class="contact-card fade-up" style="animation-delay: .08s">
            <span class="contact-icon"><i class="fas fa-envelope"></i></span>
            <span class="contact-label">Email</span>
            <strong><?= htmlspecialchars($settings['shop_email']) ?></strong>
            <p>Send detailed inquiries for uniforms, tailoring, and special requests.</p>
            <a href="mailto:<?= htmlspecialchars($settings['shop_email']) ?>">Send email</a>
          </article>

          <article class="contact-card fade-up" style="animation-delay: .16s">
            <span class="contact-icon"><i class="fas fa-location-dot"></i></span>
            <span class="contact-label">Address</span>
            <strong><?= htmlspecialchars($settings['shop_name']) ?></strong>
            <p><?= nl2br(htmlspecialchars($settings['address'])) ?></p>
          </article>

          <article class="contact-card fade-up" style="animation-delay: .24s">
            <span class="contact-icon"><i class="fas fa-clock"></i></span>
            <span class="contact-label">Business Hours</span>
            <strong><?= htmlspecialchars($hoursLabel) ?></strong>
            <p>For faster service, contact the shop during open hours before visiting.</p>
          </article>
        </div>

        <div class="contact-panel fade-up">
          <div class="contact-panel-copy">
            <span class="section-kicker">Online access</span>
            <h2>Need to place an order or check progress online?</h2>
            <p>Create an account for a new request, or sign in to review updates on an existing order.</p>
          </div>
          <div class="contact-panel-actions">
            <?php if (!empty($settings['facebook_link'])): ?>
              <a href="<?= htmlspecialchars($settings['facebook_link']) ?>" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-facebook-f"></i>
                Facebook Page
              </a>
            <?php endif; ?>
            <?php if (!empty($settings['instagram_link'])): ?>
              <a href="<?= htmlspecialchars($settings['instagram_link']) ?>" class="btn btn-secondary" target="_blank" rel="noopener noreferrer">
                <i class="fab fa-instagram"></i>
                Instagram
              </a>
            <?php endif; ?>
            <a href="/auth/register.php" class="btn btn-primary">
              <i class="fas fa-user-plus"></i>
              Create Account
            </a>
            <a href="/auth/login.php" class="btn btn-secondary">
              <i class="fas fa-right-to-bracket"></i>
              Sign In
            </a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <?php require __DIR__ . '/partials/landing/footer.php'; ?>
</body>
</html>
