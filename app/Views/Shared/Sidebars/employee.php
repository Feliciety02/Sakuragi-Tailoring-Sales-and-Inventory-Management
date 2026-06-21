<?php
require_once __DIR__ . '/../../../Support/helpers.php';

$resolvedSidebar = get_role_sidebar_view($pdo, $_SESSION['role'] ?? ROLE_EMPLOYEE, $_SESSION['user_id'] ?? 0);
$employeeSidebarPath = __FILE__;

if (realpath($resolvedSidebar) && realpath($resolvedSidebar) !== realpath($employeeSidebarPath)) {
    require $resolvedSidebar;
    return;
}
?>
<aside class="sidebar-modern" id="sidebar">
  <div class="sidebar-brand">
    <svg viewBox="0 0 28 28" fill="none" style="width:24px;height:24px">
      <rect width="28" height="28" rx="6" fill="#1e3a5f"/>
      <path d="M7 10h14l-3 8H10L7 10z" fill="#fff" opacity=".9"/>
    </svg>
    <span>Sakuragi</span>
  </div>
  <nav class="sidebar-nav">
    <div class="section-label">Workspace</div>
    <a href="/dashboards/employee/dashboard.php" class="sidebar-item"><i class="fas fa-th-large"></i> Dashboard</a>
    <a href="/dashboards/employee/my_tasks.php" class="sidebar-item"><i class="fas fa-tasks"></i> My Tasks</a>
    <a href="/dashboards/employee/kanban.php" class="sidebar-item"><i class="fas fa-columns"></i> Kanban</a>
    <a href="/dashboards/employee/completed_tasks.php" class="sidebar-item"><i class="fas fa-check-circle"></i> Completed</a>
    <a href="/dashboards/employee/assigned_orders.php" class="sidebar-item"><i class="fas fa-clipboard-list"></i> Assigned Orders</a>
    <div class="section-label">Resources</div>
    <a href="/dashboards/employee/inventory.php" class="sidebar-item"><i class="fas fa-box"></i> Inventory</a>
    <a href="/dashboards/employee/garment_tracking.php" class="sidebar-item"><i class="fas fa-shirt"></i> Garment Tracking</a>
    <a href="/dashboards/employee/profile.php" class="sidebar-item"><i class="fas fa-user"></i> Profile</a>
    <div class="sidebar-footer">
      <a href="/auth/logout.php" class="sidebar-item" style="color:var(--accent-red)"><i class="fas fa-sign-out-alt"></i> Sign Out</a>
    </div>
  </nav>
</aside>
<div class="overlay" id="overlay"></div>
