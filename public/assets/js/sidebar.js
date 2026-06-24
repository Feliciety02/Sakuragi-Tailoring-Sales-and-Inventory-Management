document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const toggleBtn = document.getElementById('menuToggle');
  const overlay = document.getElementById('overlay');
  const isMobile = () => window.innerWidth <= 768;

  if (!sidebar) return;

  // ── Load saved state (desktop only) ──
  const saved = localStorage.getItem('sidebar-collapsed');
  if (saved === 'true' && !isMobile()) {
    sidebar.classList.add('collapsed');
  }

  // ── Toggle sidebar ──
  if (toggleBtn) {
    toggleBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      toggleSidebar();
    });
  }

  // ── Toggle on brand click when collapsed ──
  const brand = sidebar.querySelector('.sidebar-brand');
  if (brand) {
    brand.addEventListener('click', (e) => {
      if (sidebar.classList.contains('collapsed') && !e.target.closest('.sidebar-menu-toggle')) {
        toggleSidebar();
      }
    });
  }

  function toggleSidebar() {
    if (isMobile()) {
      sidebar.classList.toggle('open');
      overlay?.classList.toggle('active', sidebar.classList.contains('open'));
    } else {
      sidebar.classList.toggle('collapsed');
      localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
    }
  }

  // ── Mobile overlay ──
  overlay?.addEventListener('click', () => {
    if (isMobile()) {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
    }
  });

  // ── Handle resize between mobile/desktop ──
  let prevMobile = isMobile();
  window.addEventListener('resize', () => {
    const nowMobile = isMobile();
    if (nowMobile !== prevMobile) {
      prevMobile = nowMobile;
      if (nowMobile) {
        sidebar.classList.remove('collapsed');
        sidebar.classList.remove('open');
        overlay?.classList.remove('active');
      } else {
        sidebar.classList.remove('open');
        overlay?.classList.remove('active');
        const saved = localStorage.getItem('sidebar-collapsed');
        if (saved === 'true') sidebar.classList.add('collapsed');
      }
    }
  });

  // ── Collapsible nav groups with auto-collapse ──
  window.toggleNavGroup = (btn) => {
    const group = btn.closest('.nav-group');
    if (!group) return;
    group.classList.toggle('expanded');
    const groupId = btn.dataset.groupId;
    if (groupId) {
      if (group.classList.contains('expanded')) {
        localStorage.setItem('nav-group-' + groupId, 'true');
      } else {
        localStorage.removeItem('nav-group-' + groupId);
      }
    }
  };

  // ── Restore all expanded groups ──
  Object.keys(localStorage).filter(k => k.startsWith('nav-group-')).forEach(key => {
    const groupId = key.replace('nav-group-', '');
    const btn = document.querySelector(`.nav-group-btn[data-group-id="${groupId}"]`);
    if (btn) {
      const group = btn.closest('.nav-group');
      if (group) group.classList.add('expanded');
    }
  });

  // ── Active page highlight ──
  const current = window.location.pathname.split('/').pop();
  // Highlight sidebar-item (sub-items)
  document.querySelectorAll('.sidebar-item').forEach((item) => {
    const href = item.getAttribute('href');
    if (href && href.split('/').pop() === current) {
      item.classList.add('active');
      const group = item.closest('.nav-group');
      if (group) {
        group.classList.add('expanded');
        const groupId = group.querySelector('.nav-group-btn')?.dataset.groupId;
        if (groupId) localStorage.setItem('nav-group-' + groupId, 'true');
      }
    }
  });
  // Highlight sidebar-top-item (top-level links like Dashboard)
  document.querySelectorAll('.sidebar-top-item').forEach((item) => {
    const href = item.getAttribute('href');
    if (href && href.split('/').pop() === current) {
      item.classList.add('active');
    }
  });

  // ── Notification badge animation ──
  const badge = document.getElementById('notifBadge');
  if (badge) {
    const observer = new MutationObserver(() => {
      badge.style.transform = 'scale(1.3)';
      requestAnimationFrame(() => {
        badge.style.transition = 'transform 0.2s cubic-bezier(0.34,1.56,0.64,1)';
        badge.style.transform = 'scale(1)';
      });
    });
    observer.observe(badge, { childList: true, characterData: true, subtree: true });
    badge.addEventListener('transitionend', () => { badge.style.transition = ''; });
  }

  // ── Notifications toggle ──
  window.toggleNotifications = () => {
    const notifBtn = document.getElementById('notifToggle');
    const notifDropdown = document.getElementById('notifDropdown');
    if (notifDropdown) {
      const open = notifDropdown.style.display === 'block';
      notifDropdown.style.display = open ? 'none' : 'block';
      if (!open) loadNotifications?.();
    } else if (notifBtn) {
      window.location.href = '/dashboards/admin/notifications.php';
    }
  };

  // ── User dropdown ──
  window.toggleUserMenu = () => {
    const dd = document.getElementById('userDropdown');
    if (dd) dd.classList.toggle('open');
  };

  document.addEventListener('click', (e) => {
    const dd = document.getElementById('userDropdown');
    if (dd && !dd.contains(e.target)) {
      dd.classList.remove('open');
    }
  });

  // ── Skeleton loading ──
  const nav = sidebar.querySelector('.sidebar-nav');
  if (nav && nav.querySelector('.sidebar-skeleton')) {
    setTimeout(() => {
      nav.querySelectorAll('.sidebar-skeleton').forEach((sk) => {
        sk.style.transition = 'opacity 0.3s ease';
        sk.style.opacity = '0';
        setTimeout(() => sk.remove(), 300);
      });
    }, 400);
  }
});
