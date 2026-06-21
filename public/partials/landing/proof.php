<?php
$reviews = [
    [
        'file' => 'review-volleyball-group.jpg',
        'quote' => 'The volleyball jerseys looked exactly like the approved layout, and the whole team was ready on schedule.',
        'name' => 'QEF Volley Mates',
        'role' => 'Volleyball team order',
    ],
    [
        'file' => 'review-bowling-group.jpg',
        'quote' => 'Our bowling shirts came out clean and consistent, even for a larger group with different sizes.',
        'name' => 'Tournament Bowlers',
        'role' => 'Event team shirts',
    ],
    [
        'file' => 'review-red-team-group.jpg',
        'quote' => 'The red basketball uniforms looked sharp on court and the print quality held up during actual league games.',
        'name' => 'Gush & Go Team',
        'role' => 'Basketball uniform batch',
    ],
    [
        'file' => 'review-maroon-team-group.jpg',
        'quote' => 'From design approval to release, the process stayed organized and the final set looked professional.',
        'name' => 'AKL Car Flex',
        'role' => 'Custom league uniforms',
    ],
    [
        'file' => 'review-basketball-night-group.jpg',
        'quote' => 'Our team uniforms were delivered clean and game-ready, and the final look matched the design well.',
        'name' => 'Panacan Selection',
        'role' => 'Basketball team set',
    ],
    [
        'file' => 'review-juniors-blue-group.jpg',
        'quote' => 'The juniors uniforms came out bright, complete, and easy to recognize during the event.',
        'name' => 'Jokers Juniors',
        'role' => 'Youth team uniforms',
    ],
    [
        'file' => 'review-granville-red-group.jpg',
        'quote' => 'The red set looked sharp on court and the whole batch felt consistent from player to player.',
        'name' => 'Granville Team',
        'role' => 'League basketball uniforms',
    ],
];

$proofs = [
    [
        'file' => 'work-volleyball-blue-mockup.jpg',
        'title' => 'Volleyball jersey mockup',
        'caption' => 'Front-and-back sublimation concept prepared for production.',
    ],
    [
        'file' => 'work-basketball-white-green.jpg',
        'title' => 'White and green basketball set',
        'caption' => 'Complete front, back, and shorts layout for a custom team order.',
    ],
    [
        'file' => 'work-basketball-black-red.jpg',
        'title' => 'Black and red team uniform',
        'caption' => 'High-contrast custom jersey concept with matching shorts.',
    ],
    [
        'file' => 'work-basketball-blue-set.jpg',
        'title' => 'Dark blue basketball set',
        'caption' => 'Completed jersey and shorts set with numbering and name layout.',
    ],
    [
        'file' => 'work-basketball-gold-blue.jpg',
        'title' => 'Gold and blue concept set',
        'caption' => 'Full basketball uniform layout with custom pattern, numbering, and shorts.',
    ],
    [
        'file' => 'work-basketball-jokers-blue.jpg',
        'title' => 'Blue team uniform mockup',
        'caption' => 'Clean front, back, and shorts presentation for a custom team order.',
    ],
    [
        'file' => 'work-basketball-granville-red.jpg',
        'title' => 'Red basketball set',
        'caption' => 'Minimal red-and-navy jersey concept prepared for production approval.',
    ],
    [
        'file' => 'work-bowling-black-red.jpg',
        'title' => 'Bowling shirt concept',
        'caption' => 'Custom black-and-red performance shirt prepared as a production sample.',
    ],
];

$proofBaseDir = __DIR__ . '/../../assets/images/proofs/';
$proofBaseUrl = '/public/assets/images/proofs/';
?>

<section class="section social-proof-section" id="proofs">
  <div class="container social-proof-layout">
    <div class="section-header align-left social-proof-header">
      <span class="section-kicker">Client reviews and proof of work</span>
      <h2>Real mockups, real team releases, and finished uniforms worn in actual events.</h2>
      <p>Sakuragi handles custom uniforms, team shirts, and production-ready layouts for customers who want dependable work and cleaner coordination.</p>
    </div>

    <div class="review-carousel fade-up" data-review-carousel>
      <div class="review-carousel-head">
        <div>
          <span class="section-kicker">Client reviews</span>
          <p>Real feedback from customers who trusted us with their orders.</p>
        </div>
        <div class="proof-carousel-actions">
          <button type="button" class="proof-nav" data-review-prev aria-label="Previous review">
            <i class="fas fa-arrow-left"></i>
          </button>
          <button type="button" class="proof-nav" data-review-next aria-label="Next review">
            <i class="fas fa-arrow-right"></i>
          </button>
        </div>
      </div>

      <div class="review-viewport">
        <div class="review-gallery">
          <?php foreach ($reviews as $review): ?>
            <?php $reviewPath = $proofBaseDir . $review['file']; ?>
            <article class="review-card">
              <div class="review-photo">
                <?php if (file_exists($reviewPath)): ?>
                  <img src="<?= htmlspecialchars($proofBaseUrl . $review['file']) ?>" alt="<?= htmlspecialchars($review['name']) ?>">
                <?php else: ?>
                  <div class="proof-placeholder">
                    <i class="fas fa-users"></i>
                    <span><?= htmlspecialchars($review['name']) ?></span>
                  </div>
                <?php endif; ?>
              </div>
              <div class="review-content">
                <div class="review-stars" aria-hidden="true">
                  <?php for ($i = 0; $i < 5; $i++): ?>
                    <i class="fas fa-star"></i>
                  <?php endfor; ?>
                </div>
                <p>"<?= htmlspecialchars($review['quote']) ?>"</p>
                <div class="review-meta">
                  <strong><?= htmlspecialchars($review['name']) ?></strong>
                  <span><?= htmlspecialchars($review['role']) ?></span>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="proof-carousel fade-up" data-proof-carousel>
      <div class="proof-carousel-head">
        <div>
          <span class="section-kicker">Design previews</span>
          <p>Custom shirt and uniform concepts prepared for approval before production.</p>
        </div>
        <div class="proof-carousel-actions">
          <button type="button" class="proof-nav" data-proof-prev aria-label="Previous work sample">
            <i class="fas fa-arrow-left"></i>
          </button>
          <button type="button" class="proof-nav" data-proof-next aria-label="Next work sample">
            <i class="fas fa-arrow-right"></i>
          </button>
        </div>
      </div>

      <div class="proof-viewport">
        <div class="proof-gallery">
          <?php foreach ($proofs as $proof): ?>
            <?php $proofPath = $proofBaseDir . $proof['file']; ?>
            <article class="proof-card">
              <div class="proof-media">
                <?php if (file_exists($proofPath)): ?>
                  <img src="<?= htmlspecialchars($proofBaseUrl . $proof['file']) ?>" alt="<?= htmlspecialchars($proof['title']) ?>">
                <?php else: ?>
                  <div class="proof-placeholder">
                    <i class="fas fa-image"></i>
                    <span><?= htmlspecialchars($proof['title']) ?></span>
                  </div>
                <?php endif; ?>
              </div>
              <div class="proof-copy">
                <strong><?= htmlspecialchars($proof['title']) ?></strong>
                <p><?= htmlspecialchars($proof['caption']) ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>
