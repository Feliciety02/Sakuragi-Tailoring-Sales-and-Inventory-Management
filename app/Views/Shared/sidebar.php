<aside class="sidebar-modern" id="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-brand-left">
      <img src="/public/assets/images/sakuragi-logo.svg" alt="Sakuragi" class="sidebar-logo">
      <div class="sidebar-brand-inner">
        <span class="sidebar-brand-text">Sakuragi</span>
        <span class="sidebar-brand-label">Main Branch</span>
      </div>
    </div>
    <button class="sidebar-menu-toggle" id="menuToggle" title="Toggle sidebar">
      <i class="fas fa-bars"></i>
    </button>
  </div>

  <nav class="sidebar-nav">
    <?php foreach ($navItems as $item):
      if ($item['type'] === 'link'): ?>
    <a href="<?= htmlspecialchars($item['href']) ?>" class="sidebar-top-item" data-tooltip="<?= htmlspecialchars($item['tooltip'] ?? $item['label']) ?>">
      <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
      <span class="nav-text"><?= htmlspecialchars($item['label']) ?></span>
    </a>
    <?php elseif ($item['type'] === 'group'): ?>
    <div class="nav-group">
      <button class="nav-group-btn" data-group-id="<?= htmlspecialchars($item['id']) ?>" onclick="toggleNavGroup(this)">
        <i class="nav-chevron fas fa-chevron-right"></i>
        <i class="nav-icon <?= htmlspecialchars($item['icon']) ?>"></i>
        <span class="nav-label"><?= htmlspecialchars($item['label']) ?></span>
      </button>
      <div class="nav-group-items">
        <?php foreach ($item['children'] as $child): ?>
        <a href="<?= htmlspecialchars($child['href']) ?>" class="sidebar-item" data-tooltip="<?= htmlspecialchars($child['tooltip'] ?? $child['label']) ?>">
          <i class="<?= htmlspecialchars($child['icon']) ?>"></i>
          <span class="nav-text"><?= htmlspecialchars($child['label']) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; endforeach; ?>
  </nav>

  <div class="sidebar-profile" data-collapsed-hide>
    <div class="profile-avatar"><?= htmlspecialchars($initials) ?></div>
    <div class="profile-body">
      <div class="profile-name"><?= htmlspecialchars($userName) ?></div>
      <div class="profile-meta"><?= htmlspecialchars($roleLabel) ?><?= $branch ? ' &middot; ' . htmlspecialchars($branch) : '' ?></div>
    </div>
  </div>

  <div class="sidebar-footer">
    <a href="/auth/logout.php" class="sidebar-top-item" data-tooltip="Sign Out">
      <i class="fas fa-sign-out-alt"></i>
      <span class="nav-text">Sign Out</span>
    </a>
  </div>
</aside>
<div class="overlay" id="overlay"></div>
<script src="/public/assets/js/sidebar.js" defer></script>
<script src="/public/assets/js/ajax-nav.js" defer></script>