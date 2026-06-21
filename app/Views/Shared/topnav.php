<?php
$topNavInitials = strtoupper(substr($_SESSION['full_name'] ?? 'SU', 0, 2));
$topNavTitle = $pageTitle ?? 'Dashboard';
?>
<header class="top-nav">
  <div class="top-nav-left">
    <button class="menu-toggle" id="menuToggle"><i class="fas fa-bars"></i></button>
    <span class="fw-semibold"><?= htmlspecialchars($topNavTitle) ?></span>
  </div>
  <div class="top-nav-right">
    <button class="icon-btn" id="notifToggle" onclick="toggleNotifications()" title="Notifications">
      <i class="fas fa-bell"></i>
      <span class="badge" id="notifBadge" style="display:none;">0</span>
    </button>
    <div class="avatar" title="<?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?>"><?= htmlspecialchars($topNavInitials) ?></div>
    <a href="/auth/logout.php" class="icon-btn" title="Logout" onclick="return confirm('Logout?')">
      <i class="fas fa-right-from-bracket"></i>
    </a>
  </div>
</header>
