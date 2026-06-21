// Sidebar toggle for sidebar-modern
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
  const menuToggle = document.getElementById('menuToggle');
  const overlay = document.getElementById('overlay');

  if (menuToggle) {
    menuToggle.addEventListener('click', function() {
      sidebar?.classList.toggle('collapsed');
      if (window.innerWidth < 768) {
        sidebar?.classList.toggle('open');
        overlay?.classList.toggle('show');
      }
    });
  }

  if (overlay) {
    overlay.addEventListener('click', function() {
      sidebar?.classList.remove('open');
      overlay.classList.remove('show');
    });
  }
});
