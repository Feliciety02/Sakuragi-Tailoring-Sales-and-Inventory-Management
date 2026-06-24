<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sakuragi Tailoring | Custom Tailoring and Alterations</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="/public/assets/css/landing-modern.css">
</head>
<body>
  <?php require __DIR__ . '/partials/landing/nav.php'; ?>

  <main>
    <?php require __DIR__ . '/partials/landing/hero.php'; ?>
    <?php require __DIR__ . '/partials/landing/services.php'; ?>
    <?php require __DIR__ . '/partials/landing/workflow.php'; ?>
    <?php require __DIR__ . '/partials/landing/platform.php'; ?>
    <?php require __DIR__ . '/partials/landing/proof.php'; ?>
    <?php require __DIR__ . '/partials/landing/cta.php'; ?>
  </main>

  <?php require __DIR__ . '/partials/landing/footer.php'; ?>

  <script>
    const sections = document.querySelectorAll('section[id], .hero');
    const navLinks = document.querySelectorAll('.nav-links a:not(.btn-nav):not(.btn-primary-nav)');

    function setActiveLink() {
      let current = '';
      sections.forEach(s => {
        const top = s.getBoundingClientRect().top;
        if (top <= 200) current = s.id || 'hero';
      });
      navLinks.forEach(a => {
        a.classList.toggle('active', a.getAttribute('href') === '#' + current);
      });
    }

    window.addEventListener('scroll', setActiveLink, { passive: true });
    window.addEventListener('load', setActiveLink);
  </script>
</body>
</html>
