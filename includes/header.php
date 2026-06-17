<?php
// No session_start() here — it's already handled in session_handler.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Sakuragi Tailoring Shop</title>
  <link rel="stylesheet" href="/public/assets/css/includes.css" />
  <link rel="stylesheet" href="/public/assets/css/tables.css" />
  
  <script src="/public/assets/js/sidebar.js" defer></script>
  <script src="/public/assets/js/tables.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">


  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
 
</head>
<body>

<header class="main-header">
    <!-- Particles animation -->
    <div class="particles-header">
  <?php for ($i = 1; $i <= 20; $i++): ?>
    <span></span>
  <?php endfor; ?>
</div>


    <!-- Content -->
    <div class="header-container">
        <div class="logo-area">
            <i class="fas fa-scissors logo-icon"></i>
            <span class="header-title">Sakuragi Tailoring Shop</span>
        </div>

        <div class="user-area">
        <?php if (is_logged_in()): ?>
    <div class="user-profile">
        <div class="position-relative me-3 notification-bell" style="cursor:pointer;">
            <i class="fas fa-bell fs-5 text-white" id="notifBell" onclick="toggleNotifications()"></i>
            <span id="notifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;display:none;">0</span>
            <div id="notifDropdown" class="dropdown-menu dropdown-menu-end p-0 shadow" style="display:none;width:320px;max-height:400px;overflow-y:auto;right:0;left:auto;">
                <div class="dropdown-header bg-primary text-white fw-bold py-2 d-flex justify-content-between align-items-center">
                    <span>Notifications</span>
                    <button class="btn btn-sm text-white p-0" onclick="markAllRead()" title="Mark all as read"><i class="fas fa-check-double"></i></button>
                </div>
                <div id="notifList" class="py-2">
                    <div class="text-center text-muted py-3 small">Loading...</div>
                </div>
            </div>
        </div>
        <div class="avatar">
        <a href="/dashboards/customer/account.php" class="avatar-btn" title="My Account">
            <i class="fas fa-user-circle"></i>
            <span class="user-name"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></span>
        </a>
        </div>
       
        <a href="/auth/logout.php" class="logout-btn" onclick="return confirm('Logout?')">Logout</a>
    </div>
      <?php else: ?>
          <a href="/auth/login.php" class="header-btn">Login</a>
      <?php endif; ?>

        </div>
        
        <script>
        let notifOpen = false;
        function toggleNotifications() {
            notifOpen = !notifOpen;
            document.getElementById('notifDropdown').style.display = notifOpen ? 'block' : 'none';
            if (notifOpen) loadNotifications();
        }
        function loadNotifications() {
            fetch('/controller/notifications_api.php?action=list')
                .then(r => r.json())
                .then(data => {
                    const list = document.getElementById('notifList');
                    if (!data.notifications || data.notifications.length === 0) {
                        list.innerHTML = '<div class="text-center text-muted py-3 small">No notifications</div>';
                        return;
                    }
                    list.innerHTML = data.notifications.map(n =>
                        '<div class="dropdown-item small border-bottom px-3 py-2" onclick="markRead(' + n.notification_id + ')">' +
                        '<div class="d-flex justify-content-between"><span>' + escapeHtml(n.message) + '</span>' +
                        '<small class="text-muted ms-2" style="white-space:nowrap">' + timeAgo(n.created_at) + '</small></div></div>'
                    ).join('');
                });
        }
        function loadNotifCount() {
            fetch('/controller/notifications_api.php?action=count')
                .then(r => r.json())
                .then(data => {
                    const badge = document.getElementById('notifBadge');
                    if (data.count > 0) {
                        badge.textContent = data.count;
                        badge.style.display = 'inline';
                    } else {
                        badge.style.display = 'none';
                    }
                });
        }
        function markRead(id) {
            fetch('/controller/notifications_api.php?action=read&id=' + id).then(() => loadNotifCount());
        }
        function markAllRead() {
            fetch('/controller/notifications_api.php?action=read_all').then(() => { loadNotifCount(); loadNotifications(); });
        }
        function escapeHtml(t) { return document.createElement('div').appendChild(document.createTextNode(t)).parentNode.innerHTML; }
        function timeAgo(dateStr) {
            const d = new Date(dateStr.replace(' ', 'T') + 'Z');
            const s = Math.floor((new Date() - d) / 1000);
            if (s < 60) return 'now';
            const m = Math.floor(s / 60); if (m < 60) return m + 'm';
            const h = Math.floor(m / 60); if (h < 24) return h + 'h';
            return Math.floor(h / 24) + 'd';
        }
        document.addEventListener('DOMContentLoaded', loadNotifCount);
        document.addEventListener('click', function(e) {
            if (!document.querySelector('.notification-bell')?.contains(e.target)) {
                document.getElementById('notifDropdown').style.display = 'none';
                notifOpen = false;
            }
        });
        setInterval(loadNotifCount, 30000);
        </script>
    </div>
</header>
