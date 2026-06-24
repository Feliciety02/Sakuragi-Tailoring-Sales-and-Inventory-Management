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
];

$proofs = [
    [
        'file' => 'work-basketball-white-green.jpg',
        'title' => 'White & green set',
    ],
    [
        'file' => 'work-basketball-black-red.jpg',
        'title' => 'Black & red uniform',
    ],
    [
        'file' => 'work-volleyball-blue-mockup.jpg',
        'title' => 'Volleyball jersey',
    ],
    [
        'file' => 'work-basketball-blue-set.jpg',
        'title' => 'Dark blue set',
    ],
    [
        'file' => 'work-basketball-gold-blue.jpg',
        'title' => 'Gold & blue concept',
    ],
    [
        'file' => 'work-bowling-black-red.jpg',
        'title' => 'Bowling shirt',
    ],
];

$proofBaseDir = __DIR__ . '/../../assets/images/proofs/';
$proofBaseUrl = '/public/assets/images/proofs/';
?>

<section class="section social-proof-section" id="proofs">
  <div class="container">
    <div class="section-header align-left" style="max-width:680px">
      <span class="section-kicker">Client reviews and proof of work</span>
      <h2>Real mockups, real team releases, and finished uniforms worn in actual events.</h2>
      <p>Sakuragi handles custom uniforms, team shirts, and production-ready layouts for customers who want dependable work and cleaner coordination.</p>
    </div>

    <div class="bento-grid">
      <!-- Featured Review (2×2) -->
      <?php $r0 = $reviews[0]; $r0path = $proofBaseDir . $r0['file']; ?>
      <article class="bento-card bento-featured">
        <div class="bento-media">
          <?php if (file_exists($r0path)): ?>
            <img src="<?= htmlspecialchars($proofBaseUrl . $r0['file']) ?>" alt="<?= htmlspecialchars($r0['name']) ?>">
          <?php else: ?>
            <div class="bento-placeholder"><i class="fas fa-users"></i></div>
          <?php endif; ?>
          <div class="bento-overlay">
            <div class="bento-stars" aria-hidden="true"><?php for ($i=0;$i<5;$i++): ?><i class="fas fa-star"></i><?php endfor; ?></div>
            <p class="bento-quote">"<?= htmlspecialchars($r0['quote']) ?>"</p>
            <div class="bento-author">
              <strong><?= htmlspecialchars($r0['name']) ?></strong>
              <span><?= htmlspecialchars($r0['role']) ?></span>
            </div>
          </div>
        </div>
      </article>

      <!-- Review Card 2 -->
      <?php $r1 = $reviews[1]; $r1path = $proofBaseDir . $r1['file']; ?>
      <article class="bento-card">
        <div class="bento-media">
          <?php if (file_exists($r1path)): ?>
            <img src="<?= htmlspecialchars($proofBaseUrl . $r1['file']) ?>" alt="<?= htmlspecialchars($r1['name']) ?>">
          <?php else: ?>
            <div class="bento-placeholder"><i class="fas fa-users"></i></div>
          <?php endif; ?>
          <div class="bento-overlay">
            <p class="bento-quote bento-quote-sm">"<?= htmlspecialchars($r1['quote']) ?>"</p>
            <div class="bento-author">
              <strong><?= htmlspecialchars($r1['name']) ?></strong>
            </div>
          </div>
        </div>
      </article>

      <!-- Review Card 3 -->
      <?php $r2 = $reviews[2]; $r2path = $proofBaseDir . $r2['file']; ?>
      <article class="bento-card">
        <div class="bento-media">
          <?php if (file_exists($r2path)): ?>
            <img src="<?= htmlspecialchars($proofBaseUrl . $r2['file']) ?>" alt="<?= htmlspecialchars($r2['name']) ?>">
          <?php else: ?>
            <div class="bento-placeholder"><i class="fas fa-users"></i></div>
          <?php endif; ?>
          <div class="bento-overlay">
            <p class="bento-quote bento-quote-sm">"<?= htmlspecialchars($r2['quote']) ?>"</p>
            <div class="bento-author">
              <strong><?= htmlspecialchars($r2['name']) ?></strong>
            </div>
          </div>
        </div>
      </article>

      <!-- Mockup 1 -->
      <?php $p0 = $proofs[0]; $p0path = $proofBaseDir . $p0['file']; ?>
      <article class="bento-card">
        <div class="bento-media">
          <?php if (file_exists($p0path)): ?>
            <img src="<?= htmlspecialchars($proofBaseUrl . $p0['file']) ?>" alt="<?= htmlspecialchars($p0['title']) ?>">
          <?php else: ?>
            <div class="bento-placeholder"><i class="fas fa-image"></i></div>
          <?php endif; ?>
          <div class="bento-overlay bento-overlay-light">
            <span class="bento-label"><?= htmlspecialchars($p0['title']) ?></span>
          </div>
        </div>
      </article>

      <!-- Mockup 2 -->
      <?php $p1 = $proofs[1]; $p1path = $proofBaseDir . $p1['file']; ?>
      <article class="bento-card">
        <div class="bento-media">
          <?php if (file_exists($p1path)): ?>
            <img src="<?= htmlspecialchars($proofBaseUrl . $p1['file']) ?>" alt="<?= htmlspecialchars($p1['title']) ?>">
          <?php else: ?>
            <div class="bento-placeholder"><i class="fas fa-image"></i></div>
          <?php endif; ?>
          <div class="bento-overlay bento-overlay-light">
            <span class="bento-label"><?= htmlspecialchars($p1['title']) ?></span>
          </div>
        </div>
      </article>

      <!-- Mockup 3 -->
      <?php $p2 = $proofs[2]; $p2path = $proofBaseDir . $p2['file']; ?>
      <article class="bento-card">
        <div class="bento-media">
          <?php if (file_exists($p2path)): ?>
            <img src="<?= htmlspecialchars($proofBaseUrl . $p2['file']) ?>" alt="<?= htmlspecialchars($p2['title']) ?>">
          <?php else: ?>
            <div class="bento-placeholder"><i class="fas fa-image"></i></div>
          <?php endif; ?>
          <div class="bento-overlay bento-overlay-light">
            <span class="bento-label"><?= htmlspecialchars($p2['title']) ?></span>
          </div>
        </div>
      </article>

      <!-- Mockup 4 -->
      <?php $p3 = $proofs[3]; $p3path = $proofBaseDir . $p3['file']; ?>
      <article class="bento-card">
        <div class="bento-media">
          <?php if (file_exists($p3path)): ?>
            <img src="<?= htmlspecialchars($proofBaseUrl . $p3['file']) ?>" alt="<?= htmlspecialchars($p3['title']) ?>">
          <?php else: ?>
            <div class="bento-placeholder"><i class="fas fa-image"></i></div>
          <?php endif; ?>
          <div class="bento-overlay bento-overlay-light">
            <span class="bento-label"><?= htmlspecialchars($p3['title']) ?></span>
          </div>
        </div>
      </article>

      <!-- Mockup 5 -->
      <?php $p4 = $proofs[4]; $p4path = $proofBaseDir . $p4['file']; ?>
      <article class="bento-card">
        <div class="bento-media">
          <?php if (file_exists($p4path)): ?>
            <img src="<?= htmlspecialchars($proofBaseUrl . $p4['file']) ?>" alt="<?= htmlspecialchars($p4['title']) ?>">
          <?php else: ?>
            <div class="bento-placeholder"><i class="fas fa-image"></i></div>
          <?php endif; ?>
          <div class="bento-overlay bento-overlay-light">
            <span class="bento-label"><?= htmlspecialchars($p4['title']) ?></span>
          </div>
        </div>
      </article>

      <!-- Mockup 6 -->
      <?php $p5 = $proofs[5]; $p5path = $proofBaseDir . $p5['file']; ?>
      <article class="bento-card">
        <div class="bento-media">
          <?php if (file_exists($p5path)): ?>
            <img src="<?= htmlspecialchars($proofBaseUrl . $p5['file']) ?>" alt="<?= htmlspecialchars($p5['title']) ?>">
          <?php else: ?>
            <div class="bento-placeholder"><i class="fas fa-image"></i></div>
          <?php endif; ?>
          <div class="bento-overlay bento-overlay-light">
            <span class="bento-label"><?= htmlspecialchars($p5['title']) ?></span>
          </div>
        </div>
      </article>
    </div>
  </div>
</section>
