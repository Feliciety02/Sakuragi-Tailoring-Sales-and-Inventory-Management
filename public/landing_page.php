<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sakuragi Tailoring | Custom Tailoring and Alterations</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png">
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
    const proofCarousel = document.querySelector('[data-proof-carousel]');

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

    function initCarousel(carouselEl, viewportSel, cardSel, prevAttr, nextAttr) {
      if (!carouselEl) return;
      const viewport = carouselEl.querySelector(viewportSel);
      const prev = carouselEl.querySelector(prevAttr);
      const next = carouselEl.querySelector(nextAttr);

      const getStep = () => {
        const firstCard = viewport.querySelector(cardSel);
        if (!firstCard) return viewport.clientWidth;
        const cardWidth = firstCard.getBoundingClientRect().width;
        return cardWidth + 18;
      };

      const scrollByStep = direction => {
        viewport.scrollBy({
          left: getStep() * direction,
          behavior: 'smooth'
        });
      };

      prev?.addEventListener('click', () => scrollByStep(-1));
      next?.addEventListener('click', () => scrollByStep(1));

      let autoSlide = window.setInterval(() => {
        const maxScrollLeft = viewport.scrollWidth - viewport.clientWidth - 4;
        if (viewport.scrollLeft >= maxScrollLeft) {
          viewport.scrollTo({ left: 0, behavior: 'smooth' });
          return;
        }
        scrollByStep(1);
      }, 4000);

      const pauseAutoSlide = () => {
        if (autoSlide) {
          window.clearInterval(autoSlide);
          autoSlide = null;
        }
      };

      const resumeAutoSlide = () => {
        if (autoSlide) return;
        autoSlide = window.setInterval(() => {
          const maxScrollLeft = viewport.scrollWidth - viewport.clientWidth - 4;
          if (viewport.scrollLeft >= maxScrollLeft) {
            viewport.scrollTo({ left: 0, behavior: 'smooth' });
            return;
          }
          scrollByStep(1);
        }, 4000);
      };

      carouselEl.addEventListener('mouseenter', pauseAutoSlide);
      carouselEl.addEventListener('mouseleave', resumeAutoSlide);
      carouselEl.addEventListener('focusin', pauseAutoSlide);
      carouselEl.addEventListener('focusout', resumeAutoSlide);
    }

    initCarousel(
      document.querySelector('[data-review-carousel]'),
      '.review-viewport', '.review-card',
      '[data-review-prev]', '[data-review-next]'
    );

    initCarousel(
      proofCarousel,
      '.proof-viewport', '.proof-card',
      '[data-proof-prev]', '[data-proof-next]'
    );
  </script>
</body>
</html>
