<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../config/component_helpers.php';
require_once __DIR__ . '/../../app/Support/helpers.php';
require_once __DIR__ . '/../../app/Controllers/InventoryController.php';

$user_id = $_SESSION['user_id'];
$pageTitle = 'Inventory';
$role = get_user_role();
$isAdmin = in_array($role, [ROLE_ADMIN, ROLE_INVENTORY_MANAGER]);
$roleAttr = in_array($role, ['admin','customer','production_staff','quality_control_inspector','inventory_manager','operations_manager','senior_tailor']) ? $role : 'production_staff';

$inventoryItems = getInventory($pdo);
$suppliers = getSuppliers($pdo);
$types = getSupplyTypes($pdo);

// Priority sort: Out of Stock first, then Critical Reorder, then Low Stock, then Normal
usort($inventoryItems, function($a, $b) {
    $qa = (int)($a['quantity'] ?? 0);
    $ra = (int)($a['reorder_level'] ?? 0);
    $qb = (int)($b['quantity'] ?? 0);
    $rb = (int)($b['reorder_level'] ?? 0);

    $pa = $qa === 0 ? 0 : ($qa < $ra ? 1 : 2);
    $pb = $qb === 0 ? 0 : ($qb < $rb ? 1 : 2);
    if ($pa !== $pb) return $pa - $pb;

    // Within same priority, sort by qty ascending (most critical first)
    if ($qa !== $qb) return $qa - $qb;
    return strcmp($a['item_name'] ?? '', $b['item_name'] ?? '');
});

$lowStockItems = array_filter($inventoryItems, function($i) {
    $qty = (int)($i['quantity'] ?? 0);
    $reorder = (int)($i['reorder_level'] ?? 0);
    return $qty > 0 && $qty < $reorder;
});
$criticalReorderItems = array_filter($inventoryItems, function($i) {
    $qty = (int)($i['quantity'] ?? 0);
    $reorder = (int)($i['reorder_level'] ?? 0);
    return $qty > 0 && $qty < $reorder && $qty <= $reorder * 0.25;
});
$outOfStockItems = array_filter($inventoryItems, function($i) {
    return (int)($i['quantity'] ?? 0) === 0;
});
$totalItems = count($inventoryItems);
$totalValue = array_sum(array_map(function($i) {
    return (float)($i['quantity'] ?? 0) * (float)($i['unit_price'] ?? 0);
}, $inventoryItems));


?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="inv-styles">
    .inv-table-card { background:var(--surface);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;box-shadow:var(--shadow-sm) }

    /* ── Alert Banner ── */
    .inv-alert { display:flex;align-items:center;gap:12px;padding:14px 20px;border-bottom:1px solid var(--border);font-size:0.82rem; }
    .inv-alert.inv-alert-danger { background:#fef2f2; }
    .inv-alert.inv-alert-warning { background:#fffbeb; }
    .inv-alert-icon { width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.9rem;flex-shrink:0; }
    .inv-alert-danger .inv-alert-icon { background:#fecaca;color:#dc2626; }
    .inv-alert-warning .inv-alert-icon { background:#fde68a;color:#d97706; }
    .inv-alert-content { flex:1; }
    .inv-alert-title { font-weight:700;font-size:0.82rem; }
    .inv-alert-danger .inv-alert-title { color:#991b1b; }
    .inv-alert-warning .inv-alert-title { color:#92400e; }
    .inv-alert-desc { font-size:0.75rem;margin-top:1px; }
    .inv-alert-danger .inv-alert-desc { color:#b91c1c; }
    .inv-alert-warning .inv-alert-desc { color:#a16207; }

    /* ── Toolbar ── */
    .inv-toolbar { display:flex;flex-wrap:wrap;gap:10px;padding:14px 20px;border-bottom:1px solid var(--border);align-items:center; }
    .inv-search-wrap { position:relative;flex:1;min-width:180px;max-width:320px; }
    .inv-search-wrap .fa-search { position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-tertiary);font-size:0.78rem;pointer-events:none; }
    .inv-search-wrap input { width:100%;padding:8px 10px 8px 30px;border:1px solid var(--border);border-radius:8px;font-size:0.82rem;background:var(--surface);color:var(--text-primary);outline:none;transition:border-color .15s,box-shadow .15s; }
    .inv-search-wrap input:focus { border-color:var(--role-accent);box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
    .inv-search-wrap input::placeholder { color:var(--text-tertiary); }
    .inv-filter-group { display:flex;gap:6px;flex-wrap:wrap;align-items:center; }
    .inv-filter-group select { padding:8px 28px 8px 10px;font-size:0.78rem;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text-secondary);cursor:pointer;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;outline:none;transition:border-color .15s; }
    .inv-filter-group select:focus { border-color:var(--role-accent); }
    .inv-filter-group .reset-btn { width:34px;height:34px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text-tertiary);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .12s;font-size:0.75rem; }
    .inv-filter-group .reset-btn:hover { border-color:var(--text-tertiary);color:var(--text-primary); }
    .inv-toolbar-actions { display:flex;gap:6px;margin-left:auto; }

    /* ── Table ── */
    .inv-table-wrap { overflow-x:auto; }
    .inv-table { width:100%;border-collapse:separate;border-spacing:0;font-size:0.82rem; }
    .inv-table thead { position:sticky;top:0;z-index:2; }
    .inv-table th { padding:12px 16px;text-align:left;font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-tertiary);background:var(--surface-secondary);border-bottom:1px solid var(--border);white-space:nowrap;cursor:pointer;user-select:none; }
    .inv-table th:hover { color:var(--text-primary); }
    .inv-table th .sort-icon { margin-left:4px;font-size:0.55rem;opacity:0.3; }
    .inv-table th.sort-asc .sort-icon,
    .inv-table th.sort-desc .sort-icon { opacity:1;color:var(--role-accent); }
    .inv-table td { padding:12px 16px;border-bottom:1px solid var(--border);color:var(--text-primary);vertical-align:middle; }
    .inv-table tbody tr:last-child td { border-bottom:none; }
    .inv-row { transition:background .12s; }
    .inv-row:hover { background:var(--surface-secondary) !important; }
    .inv-row.row-out { background:rgba(239,68,68,0.03); }
    .inv-row.row-critical { background:rgba(239,68,68,0.03); }
    .inv-row.row-low { background:rgba(245,158,11,0.03); }
    .inv-row.row-out:hover { background:rgba(239,68,68,0.06) !important; }
    .inv-row.row-critical:hover { background:rgba(239,68,68,0.06) !important; }
    .inv-row.row-low:hover { background:rgba(245,158,11,0.06) !important; }

    /* ── Material Cell ── */
    .inv-mat-cell { display:flex;align-items:center;gap:10px; }
    .inv-mat-icon { width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:0.85rem;flex-shrink:0; }
    .inv-mat-info { display:flex;flex-direction:column;gap:1px; }
    .inv-mat-name { font-weight:600;font-size:0.85rem;color:var(--text-primary);line-height:1.3; }
    .inv-mat-sku { font-size:0.7rem;color:var(--text-tertiary); }

    /* ── Category Chip ── */
    .inv-cat-chip { display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:0.72rem;font-weight:600; }
    .inv-cat-chip::before { content:'';width:6px;height:6px;border-radius:50%;background:currentColor;opacity:0.6; }

    /* ── Stock Cell ── */
    .inv-stock-cell { display:flex;align-items:center;gap:10px; }
    .inv-stock-num { font-weight:700;font-size:0.85rem;min-width:32px;font-variant-numeric:tabular-nums; }
    .inv-prog-track { flex:1;max-width:80px;height:5px;background:var(--surface-secondary);border-radius:3px;overflow:hidden; }
    .inv-prog-fill { height:100%;border-radius:3px;transition:width .3s; }
    .inv-prog-fill.safe { background:var(--color-success); }
    .inv-prog-fill.warn { background:var(--color-warning); }
    .inv-prog-fill.danger { background:var(--color-danger); }

    /* ── Reorder Cell ── */
    .inv-reorder-cell { display:flex;align-items:baseline;gap:4px; }
    .inv-reorder-num { font-weight:600;font-size:0.85rem;font-variant-numeric:tabular-nums; }
    .inv-reorder-label { font-size:0.65rem;color:var(--text-tertiary);text-transform:uppercase;letter-spacing:0.03em; }

    /* ── Action Buttons ── */
    .inv-action-group { display:flex;gap:2px;flex-wrap:nowrap; }
    .inv-action-btn { width:32px;height:32px;border:none;border-radius:50%;background:transparent;color:var(--text-tertiary);cursor:pointer;font-size:0.75rem;display:inline-flex;align-items:center;justify-content:center;transition:all .12s; }
    .inv-action-btn:hover { background:var(--surface-secondary);color:var(--text-primary); }
    .inv-action-btn.btn-view:hover { background:rgba(59,130,246,0.12);color:#3b82f6; }
    .inv-action-btn.btn-edit:hover { background:rgba(245,158,11,0.12);color:#d97706; }
    .inv-action-btn.btn-in:hover { background:rgba(34,197,94,0.12);color:#16a34a; }
    .inv-action-btn.btn-out:hover { background:rgba(139,92,246,0.12);color:#7c3aed; }
    .inv-action-btn.btn-del:hover { background:rgba(239,68,68,0.12);color:#dc2626; }

    /* ── Footer / Pagination ── */
    .inv-footer { display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid var(--border);font-size:0.78rem;color:var(--text-tertiary);flex-wrap:wrap;gap:10px; }
    .inv-footer .pagination { display:flex;gap:3px;align-items:center; }
    .inv-footer .page-btn { min-width:32px;height:32px;padding:0 8px;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text-secondary);font-size:0.75rem;cursor:pointer;transition:all .12s;display:inline-flex;align-items:center;justify-content:center; }
    .inv-footer .page-btn:hover { border-color:var(--role-accent);color:var(--role-accent);background:rgba(59,130,246,0.05); }
    .inv-footer .page-btn.active { background:var(--role-accent);color:#fff;border-color:var(--role-accent); }
    .inv-footer .page-btn:disabled { opacity:0.35;cursor:default;pointer-events:none; }
    .inv-footer .rows-select { padding:6px 28px 6px 10px;font-size:0.72rem;border:1px solid var(--border);border-radius:6px;background:var(--surface);color:var(--text-secondary);-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236b7280'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center;cursor:pointer;outline:none; }
    .inv-footer .rows-select:focus { border-color:var(--role-accent); }

    .inv-empty { text-align:center;padding:48px 16px;color:var(--text-tertiary); }
    .inv-loading { text-align:center;padding:48px 16px;color:var(--text-tertiary); }
    .inv-loading i { font-size:1.5rem;margin-bottom:8px; }

    /* ── Shared detail / modal styles ── */
    .detail-grid { display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:0.82rem; }
    .detail-grid .field { display:flex;flex-direction:column;gap:2px; }
    .detail-grid .field-label { font-size:0.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--text-tertiary); }
    .detail-grid .field-value { color:var(--text-primary);font-weight:500; }
    .detail-grid .field-value.full { grid-column:1 / -1; }
    .stock-log-list { margin-top:16px;border-top:1px solid var(--border);padding-top:12px; }
    .stock-log-item { display:flex;justify-content:space-between;padding:6px 0;font-size:0.78rem;border-bottom:1px solid var(--border-light); }
    .stock-log-item:last-child { border-bottom:none; }
    .stock-log-item .log-type { font-weight:600; }
    .log-in { color:#16a34a; }
    .log-out { color:#dc2626; }
    .stock-form { display:flex;flex-direction:column;gap:14px; }
    .stock-form .form-row { display:flex;gap:10px;align-items:flex-end; }
    .stock-form .form-row label { flex:1; }
    .stock-form label { font-size:0.78rem;font-weight:600;color:var(--text-secondary);display:flex;flex-direction:column;gap:4px; }
    .stock-form input, .stock-form select { padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:0.82rem;background:var(--surface);color:var(--text-primary); }
    .stock-form input:focus, .stock-form select:focus { outline:none;border-color:var(--role-accent); }

    @media (max-width:768px) {
      .inv-toolbar { flex-direction:column;align-items:stretch; }
      .inv-search-wrap { max-width:none; }
      .inv-toolbar-actions { margin-left:0; }
      .inv-action-group { gap:0; }
      .inv-footer { flex-direction:column;align-items:flex-start; }
    }

    /* ── Modal Overlay ── */
    .modal {
      display: none;
      position: fixed;
      z-index: 1050;
      left: 0; top: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.45);
      justify-content: center;
      align-items: center;
      padding: 20px;
      backdrop-filter: blur(2px);
    }
    .modal-content {
      background: var(--surface);
      padding: 24px;
      width: 100%;
      max-width: 500px;
      position: relative;
      max-height: 90vh;
      overflow-y: auto;
    }
    .close-btn {
      position: absolute;
      top: 14px;
      right: 14px;
      width: 28px;
      height: 28px;
      border-radius: 6px;
      background: transparent;
      color: var(--text-tertiary);
      font-size: 20px;
      line-height: 28px;
      text-align: center;
      cursor: pointer;
      border: none;
      transition: all .12s;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .close-btn:hover { background:var(--surface-secondary);color:var(--text-primary); }
    .modal-title { font-size:1.05rem;font-weight:700;color:var(--text-primary);margin-bottom:20px;display:flex;align-items:center;gap:8px; }
    .modal-content form label { display:flex;flex-direction:column;gap:4px;font-size:0.78rem;font-weight:600;color:var(--text-secondary);margin-bottom:12px; }
    .modal-content form input,
    .modal-content form select { padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:0.82rem;background:var(--surface);color:var(--text-primary);outline:none;transition:border-color .15s; }
    .modal-content form input:focus,
    .modal-content form select:focus { border-color:var(--role-accent);box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
    .modal-content form hr { border:none;border-top:1px solid var(--border);margin:16px 0; }
    .modal-button-group { display:flex;justify-content:flex-end;gap:8px;margin-top:20px; }
    .modal-button-group button { padding:8px 18px;border-radius:8px;font-weight:600;font-size:0.82rem;border:none;cursor:pointer;transition:all .12s; }
    .modal-button-group .btn-primary { background:var(--role-accent);color:#fff; }
    .modal-button-group .btn-primary:hover { opacity:0.9; }
    .modal-button-group .btn-cancel,
    .modal-button-group button[type="button"] { background:var(--surface-secondary);color:var(--text-secondary); }
    .modal-button-group .btn-cancel:hover,
    .modal-button-group button[type="button"]:hover { background:var(--border);color:var(--text-primary); }
    .stock-row { display:flex;gap:8px;align-items:center;margin-top:4px; }
    .stock-row input { flex:1; }
    .stock-row button { padding:8px 14px;border:none;border-radius:8px;font-weight:600;font-size:0.78rem;cursor:pointer;transition:all .12s;color:#fff; }
    .stock-row .stock-in { background:#16a34a; }
    .stock-row .stock-in:hover { background:#15803d; }
    .stock-row .stock-out { background:#7c3aed; }
    .stock-row .stock-out:hover { background:#6d28d9; }
    .modal.add .modal-content { border-left:4px solid var(--color-success); }
    .modal.edit .modal-content { border-left:4px solid var(--color-info); }
    .modal.delete .modal-content { border-left:4px solid var(--color-danger); }
    .modal.add .btn-primary { background:var(--color-success); }
    .modal.edit .btn-primary { background:var(--color-info); }
    .modal.delete .btn-primary { background:var(--color-danger); }

    @media (max-width:768px) {
      .modal { padding:10px;align-items:flex-start;padding-top:40px; }
      .modal-content { padding:18px; }
      .modal-button-group { flex-direction:column; }
      .modal-button-group button { width:100%; }
    }
  </style>
</head>
<body data-role="<?= $roleAttr ?>">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
// ── Columns ──
$columns = [
  ['field' => 'item_name', 'label' => 'Material', 'sortable' => true],
  ['field' => 'category',  'label' => 'Category', 'sortable' => true, 'safeHtml' => true],
  ['field' => 'supplier',  'label' => 'Supplier', 'sortable' => true],
  ['field' => 'qty',       'label' => 'Stock',    'sortable' => true],
  ['field' => 'reorder',   'label' => 'Reorder',  'sortable' => true],
  ['field' => 'status',    'label' => 'Status',   'type' => 'badge'],
  ['field' => 'updated',   'label' => 'Updated',  'sortable' => true],
];
if ($isAdmin) {
  $columns[] = ['field' => 'actions', 'label' => 'Actions', 'type' => 'actions'];
}

// ── Rows ──
$rows = [];
foreach ($inventoryItems as $item):
  $qty = (int)($item['quantity'] ?? 0);
  $reorder = (int)($item['reorder_level'] ?? 0);
  if ($qty === 0) {
    $status = ['text' => 'Out of Stock', 'variant' => 'danger'];
  } elseif ($qty < $reorder && $qty <= $reorder * 0.25) {
    $status = ['text' => 'Critical', 'variant' => 'danger'];
  } elseif ($qty < $reorder) {
    $status = ['text' => 'Low Stock', 'variant' => 'warning'];
  } else {
    $status = ['text' => 'In Stock', 'variant' => 'success'];
  }
  $row = [
    'item_name' => htmlspecialchars($item['item_name'] ?? ''),
    'category' => '<span class="category-badge" style="background:' . stringToColorJS($item['supply_type'] ?? 'Unknown') . '">' . htmlspecialchars($item['supply_type'] ?? 'Unknown') . '</span>',
    'supplier' => htmlspecialchars($item['supplier_name'] ?? ''),
    'qty' => $qty,
    'reorder' => $reorder,
    'status' => $status,
    'updated' => htmlspecialchars($item['last_updated'] ?? '-'),
  ];
  if ($isAdmin) {
    $row['actions'] = [
      ['label' => 'View', 'onclick' => "showDetail({$item['inventory_id']})", 'icon' => 'fas fa-eye', 'variant' => 'outline', 'tag' => 'button'],
      ['label' => 'Edit', 'onclick' => "showEdit({$item['inventory_id']})", 'icon' => 'fas fa-pen', 'variant' => 'outline', 'tag' => 'button'],
      ['label' => 'Stock In', 'onclick' => "showStockIn({$item['inventory_id']})", 'icon' => 'fas fa-arrow-down', 'variant' => 'outline', 'tag' => 'button'],
      ['label' => 'Stock Out', 'onclick' => "showStockOut({$item['inventory_id']})", 'icon' => 'fas fa-arrow-up', 'variant' => 'outline', 'tag' => 'button'],
      ['label' => 'Delete', 'onclick' => "showDelete({$item['inventory_id']})", 'icon' => 'fas fa-trash', 'variant' => 'danger', 'tag' => 'button'],
    ];
  }
  $rows[] = $row;
endforeach;

// ── Row CSS classes ──
$rowClasses = [];
foreach ($inventoryItems as $item) {
  $qty = (int)($item['quantity'] ?? 0);
  $reorder = (int)($item['reorder_level'] ?? 0);
  if ($qty === 0) $rowClasses[] = 'row-out';
  elseif ($qty < $reorder && $qty <= $reorder * 0.25) $rowClasses[] = 'row-critical';
  elseif ($qty < $reorder) $rowClasses[] = 'row-low';
  else $rowClasses[] = '';
}

$uniqueCategories = json_encode(array_unique(array_map(fn($i) => $i['supply_type'] ?? 'Unknown', $inventoryItems)));
$uniqueSuppliers = json_encode(array_unique(array_map(fn($i) => $i['supplier_name'] ?? 'Unknown', $inventoryItems)));
$inventoryJson = json_encode($inventoryItems);
?>

<?php
// ── Alert Banner ──
$urgentCount = count($outOfStockItems) + count($criticalReorderItems);
$lowCount = count($lowStockItems);
$alertBanner = '';
if ($urgentCount > 0 || $lowCount > 0) {
  $level = $urgentCount > 0 ? 'danger' : 'warning';
  $icon = $urgentCount > 0 ? 'fa-exclamation-circle' : 'fa-exclamation-triangle';
  $parts = [];
  if ($urgentCount > 0) $parts[] = $urgentCount . ' material' . ($urgentCount > 1 ? 's' : '') . ' ' . ($urgentCount > 1 ? 'need' : 'needs') . ' immediate attention';
  if ($lowCount > 0) $parts[] = $lowCount . ' material' . ($lowCount > 1 ? 's' : '') . ' below reorder point';
  $alertBanner = '<div class="inv-alert inv-alert-' . $level . '">'
    . '<div class="inv-alert-icon"><i class="fas ' . $icon . '"></i></div>'
    . '<div class="inv-alert-content">'
    . '<div class="inv-alert-title">Stock Alert</div>'
    . '<div class="inv-alert-desc">' . implode(' and ', $parts) . '. Review and restock soon.</div>'
    . '</div>'
    . '<button class="dash-btn dash-btn-sm" style="flex-shrink:0;background:' . ($urgentCount > 0 ? '#dc2626' : '#d97706') . ';color:#fff;border:none;border-radius:6px;padding:6px 14px;cursor:pointer;font-size:0.75rem;font-weight:600" onclick="document.getElementById(\'filterStatus\').value=\'danger\';document.getElementById(\'filterStatus\').dispatchEvent(new Event(\'change\'))">View Critical</button>'
    . '</div>';
}

$workspace = '<div class="inv-table-card">'
  // ── Toolbar ──
  . '<div class="inv-toolbar">'
  . '<div class="inv-search-wrap"><i class="fas fa-search"></i><input type="text" id="invSearch" placeholder="Search materials..."></div>'
  . '<div class="inv-filter-group">'
  . '<select id="filterCategory"><option value="">Category</option></select>'
  . '<select id="filterStatus"><option value="">Status</option><option value="success">In Stock</option><option value="warning">Low Stock</option><option value="danger">Out of Stock</option></select>'
  . '<select id="filterSupplier"><option value="">Supplier</option></select>'
  . '<button id="resetFilters" class="reset-btn" title="Reset filters"><i class="fas fa-undo"></i></button>'
  . '</div>'
  . '<div class="inv-toolbar-actions">'
  . '<button onclick="exportCSV()" class="dash-btn dash-btn-outline dash-btn-sm"><i class="fas fa-download"></i> Export</button>'
  . ($isAdmin ? '<button onclick="showAddInventoryModal()" class="dash-btn dash-btn-primary dash-btn-sm"><i class="fas fa-plus"></i> Add Material</button>' : '')
  . '</div>'
  . '</div>'
  // ── Alert Banner ──
  . $alertBanner
  // ── Table ──
  . '<div class="inv-table-wrap">'
  . '<table class="inv-table" id="invTable">'
  . '<thead><tr>'
  . implode('', array_map(fn($c) => '<th data-field="' . $c['field'] . '"' . (!empty($c['sortable']) ? ' class="sortable"' : '') . '>' . htmlspecialchars($c['label']) . (!empty($c['sortable']) ? ' <i class="fas fa-sort sort-icon"></i>' : '') . '</th>', $columns))
  . '</tr></thead>'
  . '<tbody id="invBody">'
  . implode('', array_map(function($r, $i) use ($columns, $inventoryItems, $isAdmin, $rowClasses) {
    $item = $inventoryItems[$i] ?? [];
    $qty = (int)($item['quantity'] ?? 0);
    $reorder = (int)($item['reorder_level'] ?? 0);
    $isOut = $qty === 0;
    $isCritical = $qty > 0 && $qty < $reorder && $qty <= $reorder * 0.25;
    $isLow = $qty > 0 && $qty < $reorder && !$isCritical;
    $statusText = $isOut ? 'danger' : ($isCritical ? 'danger' : ($isLow ? 'warning' : 'success'));
    $statusLabel = $isOut ? 'Out of Stock' : ($isCritical ? 'Critical' : ($isLow ? 'Low Stock' : 'In Stock'));
    $pct = $reorder > 0 ? min(100, round(($qty / $reorder) * 100)) : ($qty > 0 ? 100 : 0);
    $barClass = $isOut || $isCritical ? 'danger' : ($isLow ? 'warn' : 'safe');
    $category = htmlspecialchars($item['supply_type'] ?? 'Unknown');
    $supplier = htmlspecialchars($item['supplier_name'] ?? '');
    $catColor = stringToColorJS($item['supply_type'] ?? 'Unknown');
    $catBg = str_replace(['hsl(', ')'], ['hsla(', ', 0.15)'], $catColor);
    $rowClass = $rowClasses[$i] ?? '';
    $sku = 'SKU-' . str_pad((string)($item['inventory_id'] ?? 0), 4, '0', STR_PAD_LEFT);
    $itemName = htmlspecialchars($item['item_name'] ?? '');
    $itemNameAttr = htmlspecialchars($item['item_name'] ?? '', ENT_QUOTES);
    $id = $item['inventory_id'] ?? 0;

    $cells = '';
    foreach ($columns as $ci) {
      $f = $ci['field'];
      if ($f === 'item_name') {
        $cells .= '<td><div class="inv-mat-cell"><div class="inv-mat-icon" style="background:' . $catColor . '"><i class="fas fa-box"></i></div><div class="inv-mat-info"><span class="inv-mat-name">' . $itemName . '</span><span class="inv-mat-sku">' . $sku . '</span></div></div></td>';
      } elseif ($f === 'category') {
        $cells .= '<td><span class="inv-cat-chip" style="background:' . $catBg . ';color:' . $catColor . '">' . $category . '</span></td>';
      } elseif ($f === 'supplier') {
        $cells .= '<td>' . $supplier . '</td>';
      } elseif ($f === 'qty') {
        $cells .= '<td><div class="inv-stock-cell"><span class="inv-stock-num">' . $qty . '</span><div class="inv-prog-track"><div class="inv-prog-fill ' . $barClass . '" style="width:' . $pct . '%"></div></div></div></td>';
      } elseif ($f === 'reorder') {
        $cells .= '<td><div class="inv-reorder-cell"><span class="inv-reorder-num">' . $reorder . '</span><span class="inv-reorder-label">min</span></div></td>';
      } elseif ($f === 'status') {
        $cells .= '<td>' . renderStatusBadge($statusLabel, $statusText, 'sm') . '</td>';
      } elseif ($f === 'updated') {
        $cells .= '<td style="font-size:0.75rem;color:var(--text-tertiary);white-space:nowrap">' . htmlspecialchars($item['last_updated'] ?? '-') . '</td>';
      } elseif ($f === 'actions' && $isAdmin) {
        $cells .= '<td><div class="inv-action-group">'
          . '<button onclick="showDetail(' . $id . ')" class="inv-action-btn btn-view" title="View details"><i class="fas fa-eye"></i></button>'
          . '<button onclick="showEdit(' . $id . ')" class="inv-action-btn btn-edit" title="Edit"><i class="fas fa-pen"></i></button>'
          . '<button onclick="showStockIn(' . $id . ')" class="inv-action-btn btn-in" title="Stock In"><i class="fas fa-arrow-down"></i></button>'
          . '<button onclick="showStockOut(' . $id . ')" class="inv-action-btn btn-out" title="Stock Out"><i class="fas fa-arrow-up"></i></button>'
          . '<button onclick="showDelete(' . $id . ')" class="inv-action-btn btn-del" title="Delete"><i class="fas fa-trash"></i></button>'
          . '</div></td>';
      }
    }
    return '<tr class="inv-row ' . $rowClass . '" data-id="' . $id . '" data-itemname="' . $itemNameAttr . '" data-status="' . $statusText . '" data-category="' . $category . '" data-supplier="' . $supplier . '" data-qty="' . $qty . '" data-reorder="' . $reorder . '">' . $cells . '</tr>';
  }, $rows, array_keys($rows)))
  . '</tbody>'
  . '</table>'
  . '<div class="inv-empty" id="invEmpty" style="display:none"><span class="empty-state-icon"><i class="fas fa-search"></i></span><strong class="empty-state-title">No materials match your filters</strong><p style="color:var(--text-tertiary);font-size:0.82rem;margin:4px 0 0">Try adjusting your search or filter criteria</p></div>'
  . '<div class="inv-loading" id="invLoading" style="display:none"><i class="fas fa-spinner fa-spin"></i><p>Loading inventory...</p></div>'
  . '</div>'
  // ── Footer ──
  . '<div class="inv-footer">'
  . '<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap"><span id="invInfo">' . count($rows) . ' materials</span><select class="rows-select" id="rowsPerPage"><option value="10">10</option><option value="25" selected>25</option><option value="50">50</option><option value="100">100</option></select><span style="font-size:0.72rem">per page</span></div>'
  . '<div class="pagination" id="invPagination"></div>'
  . '</div>'
  . '</div>';

$scriptsHtml = '';
ob_start(); ?>

<!-- ════════════ MODALS ════════════ -->

<!-- Add Modal -->
<?php if ($isAdmin): ?>
<div id="addInventoryModal" class="modal add" style="display:none">
  <div class="modal-content">
    <span class="close-btn" onclick="closeAddInventoryModal()">×</span>
    <h2 class="modal-title">Add Inventory Item</h2>
    <form method="POST" action="../../app/Controllers/InventoryController.php">
      <input type="hidden" name="action" value="add">
      <label>Item Name <input type="text" name="item_name" required></label>
      <label>Category
        <select name="supply_type_id" required>
          <?php foreach ($types as $type): ?>
            <option value="<?= $type['supply_type_id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Supplier
        <select name="supplier_id" required>
          <?php foreach ($suppliers as $supplier): ?>
            <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Quantity <input type="number" name="quantity" required></label>
      <label>Reorder Level <input type="number" name="reorder_level" value="10" required></label>
      <div class="modal-button-group">
        <button type="submit" class="btn-primary">Save</button>
        <button type="button" onclick="closeAddInventoryModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="editInventoryModal" class="modal edit" style="display:none">
  <div class="modal-content">
    <span class="close-btn" onclick="closeEditInventoryModal()">×</span>
    <h2 class="modal-title">Edit Inventory Item</h2>
    <form method="POST" action="../../app/Controllers/InventoryController.php" class="edit-form">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" id="editInventoryId" name="inventory_id">
      <label>Item Name <input type="text" id="editItemName" name="item_name" required></label>
      <label>Category
        <select id="editType" name="supply_type_id" required>
          <?php foreach ($types as $type): ?>
            <option value="<?= $type['supply_type_id'] ?>"><?= htmlspecialchars($type['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Supplier
        <select id="editSupplier" name="supplier_id" required>
          <?php foreach ($suppliers as $supplier): ?>
            <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Reorder Level <input type="number" id="editReorder" name="reorder_level" required></label>
      <hr>
      <label>Stock Adjustment</label>
      <input type="hidden" name="stock_inventory_id" id="stockInOutInventoryId">
      <input type="hidden" name="stock_supplier_id" id="stockInOutSupplierId">
      <div class="stock-row">
        <input type="number" name="quantity" min="1" placeholder="Qty" style="flex:1" required>
        <button type="submit" name="action" value="stock_in" class="stock-in"><i class="fas fa-arrow-down"></i> In</button>
        <button type="submit" name="action" value="stock_out" class="stock-out"><i class="fas fa-arrow-up"></i> Out</button>
      </div>
      <div class="modal-button-group" style="margin-top:20px">
        <button type="submit" name="action" value="edit" class="btn-primary">Update</button>
        <button type="button" onclick="closeEditInventoryModal()" class="btn-cancel">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Modal -->
<div id="deleteInventoryModal" class="modal delete" style="display:none">
  <div class="modal-content">
    <span class="close-btn" onclick="closeDeleteModal()">×</span>
    <h2 class="modal-title">Delete Inventory Item</h2>
    <form method="POST" action="../../app/Controllers/InventoryController.php">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" id="deleteInventoryId" name="inventory_id">
      <p>Are you sure you want to delete this item?</p>
      <div class="modal-button-group">
        <button type="submit" class="btn-primary">Yes, Delete</button>
        <button type="button" onclick="closeDeleteModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Stock In Modal -->
<div id="stockInModal" class="modal" style="display:none">
  <div class="modal-content" style="max-width:400px">
    <span class="close-btn" onclick="closeStockIn()">×</span>
    <h2 class="modal-title"><i class="fas fa-arrow-down" style="color:#16a34a"></i> Stock In</h2>
    <form method="POST" action="../../app/Controllers/InventoryController.php" class="stock-form">
      <input type="hidden" name="action" value="stock_in">
      <input type="hidden" id="stockInId" name="inventory_id">
      <input type="hidden" id="stockInSupplierId" name="stock_supplier_id">
      <div>
        <label>Item <span id="stockInName" style="font-weight:400;color:var(--text-primary)"></span></label>
      </div>
      <label>Quantity to add <input type="number" name="quantity" min="1" required></label>
      <label>Supplier
        <select name="supplier_id" id="stockInSupplier" required>
          <?php foreach ($suppliers as $supplier): ?>
            <option value="<?= $supplier['supplier_id'] ?>"><?= htmlspecialchars($supplier['supplier_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Note <input type="text" name="note" placeholder="Optional note"></label>
      <div class="modal-button-group">
        <button type="submit" class="btn-primary" style="background:#16a34a;border-color:#16a34a"><i class="fas fa-arrow-down"></i> Stock In</button>
        <button type="button" onclick="closeStockIn()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Stock Out Modal -->
<div id="stockOutModal" class="modal" style="display:none">
  <div class="modal-content" style="max-width:400px">
    <span class="close-btn" onclick="closeStockOut()">×</span>
    <h2 class="modal-title"><i class="fas fa-arrow-up" style="color:#7c3aed"></i> Stock Out</h2>
    <form method="POST" action="../../app/Controllers/InventoryController.php" class="stock-form">
      <input type="hidden" name="action" value="stock_out">
      <input type="hidden" id="stockOutId" name="inventory_id">
      <div>
        <label>Item <span id="stockOutName" style="font-weight:400;color:var(--text-primary)"></span></label>
        <label style="font-size:0.75rem;color:var(--text-tertiary)">Current stock: <span id="stockOutCurrent"></span></label>
      </div>
      <label>Quantity to remove <input type="number" name="quantity" min="1" required></label>
      <label>Note <input type="text" name="note" placeholder="Reason / destination"></label>
      <div class="modal-button-group">
        <button type="submit" class="btn-primary" style="background:#7c3aed;border-color:#7c3aed"><i class="fas fa-arrow-up"></i> Stock Out</button>
        <button type="button" onclick="closeStockOut()">Cancel</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Detail Modal -->
<div id="detailModal" class="modal" style="display:none">
  <div class="modal-content" style="max-width:520px">
    <span class="close-btn" onclick="closeDetail()">×</span>
    <h2 class="modal-title" id="detailTitle">Item Details</h2>
    <div id="detailBody">
      <div class="inv-loading"><i class="fas fa-spinner fa-spin"></i><p>Loading...</p></div>
    </div>
    <div class="modal-button-group" style="margin-top:16px">
      <button onclick="closeDetail()" class="btn-cancel">Close</button>
    </div>
  </div>
</div>

<script>
const typeIdMap = <?= json_encode(array_column($types, 'supply_type_id', 'name')) ?>;
const supplierIdMap = <?= json_encode(array_column($suppliers, 'supplier_id', 'supplier_name')) ?>;
const inventoryData = <?= $inventoryJson ?>;

function getUnique(arr, key) { return [...new Set(arr.map(i => i[key] ?? 'Unknown').filter(Boolean))].sort(); }

(function() {
  var categories = getUnique(inventoryData, 'supply_type');
  var suppliers = getUnique(inventoryData, 'supplier_name');
  var catSel = document.getElementById('filterCategory');
  var supSel = document.getElementById('filterSupplier');
  categories.forEach(function(c) { var o = document.createElement('option'); o.value = c; o.textContent = c; catSel.appendChild(o); });
  suppliers.forEach(function(s) { var o = document.createElement('option'); o.value = s; o.textContent = s; supSel.appendChild(o); });
})();

var state = { page: 1, perPage: 25, sortField: '', sortDir: '', search: '', catFilter: '', statFilter: '', supFilter: '' };

function getFilteredRows() {
  var rows = Array.from(document.querySelectorAll('#invBody .inv-row'));
  return rows.filter(function(r) {
    var text = r.textContent.toLowerCase();
    if (state.search && text.indexOf(state.search.toLowerCase()) === -1) return false;
    if (state.catFilter && r.getAttribute('data-category') !== state.catFilter) return false;
    if (state.statFilter && r.getAttribute('data-status') !== state.statFilter) return false;
    if (state.supFilter && r.getAttribute('data-supplier') !== state.supFilter) return false;
    return true;
  });
}

function sortRows(rows) {
  if (!state.sortField) return rows;
  return rows.sort(function(a, b) {
    var va = a.querySelector('td:nth-child(' + (getFieldIndex(state.sortField) + 1) + ')')?.textContent.trim() || '';
    var vb = b.querySelector('td:nth-child(' + (getFieldIndex(state.sortField) + 1) + ')')?.textContent.trim() || '';
    var na = parseFloat(va), nb = parseFloat(vb);
    if (!isNaN(na) && !isNaN(nb)) { va = na; vb = nb; }
    if (va < vb) return state.sortDir === 'asc' ? -1 : 1;
    if (va > vb) return state.sortDir === 'asc' ? 1 : -1;
    return 0;
  });
}

function getFieldIndex(field) {
  var cols = ['item_name','category','supplier','qty','reorder','status','updated','actions'];
  return cols.indexOf(field);
}

function renderTable() {
  var filtered = getFilteredRows();
  var sorted = sortRows([...filtered]);
  var total = sorted.length;
  var pages = Math.ceil(total / state.perPage) || 1;
  if (state.page > pages) state.page = pages;
  var start = (state.page - 1) * state.perPage;
  var pageRows = sorted.slice(start, start + state.perPage);

  var tbody = document.getElementById('invBody');
  tbody.innerHTML = '';
  pageRows.forEach(function(r) { tbody.appendChild(r); });

  var empty = document.getElementById('invEmpty');
  empty.style.display = total === 0 ? 'block' : 'none';

  var info = document.getElementById('invInfo');
  if (total === 0) {
    info.textContent = 'No materials found';
  } else {
    info.textContent = 'Showing ' + (start + 1) + '-' + Math.min(total, start + state.perPage) + ' of ' + total + ' materials';
  }
  renderPagination(pages, state.page);
}

function renderPagination(pages, current) {
  var el = document.getElementById('invPagination');
  var html = '<button class="page-btn" onclick="goPage(1)"' + (current <= 1 ? ' disabled' : '') + '><i class="fas fa-chevron-left"></i><i class="fas fa-chevron-left" style="margin-left:-5px"></i></button>';
  html += '<button class="page-btn" onclick="goPage(' + (current - 1) + ')"' + (current <= 1 ? ' disabled' : '') + '><i class="fas fa-chevron-left"></i></button>';
  var startP = Math.max(1, current - 2);
  var endP = Math.min(pages, current + 2);
  if (startP > 1) html += '<button class="page-btn" onclick="goPage(1)">1</button>' + (startP > 2 ? '<span style="padding:0 4px;color:var(--text-tertiary);font-size:0.7rem">...</span>' : '');
  for (var i = startP; i <= endP; i++) html += '<button class="page-btn' + (i === current ? ' active' : '') + '" onclick="goPage(' + i + ')">' + i + '</button>';
  if (endP < pages) html += (endP < pages - 1 ? '<span style="padding:0 4px;color:var(--text-tertiary);font-size:0.7rem">...</span>' : '') + '<button class="page-btn" onclick="goPage(' + pages + ')">' + pages + '</button>';
  html += '<button class="page-btn" onclick="goPage(' + (current + 1) + ')"' + (current >= pages ? ' disabled' : '') + '><i class="fas fa-chevron-right"></i></button>';
  html += '<button class="page-btn" onclick="goPage(' + pages + ')"' + (current >= pages ? ' disabled' : '') + '><i class="fas fa-chevron-right"></i><i class="fas fa-chevron-right" style="margin-left:-5px"></i></button>';
  el.innerHTML = html;
}

function goPage(p) { state.page = p; renderTable(); }

document.getElementById('invSearch').addEventListener('input', function() { state.search = this.value; state.page = 1; renderTable(); });
document.getElementById('filterCategory').addEventListener('change', function() { state.catFilter = this.value; state.page = 1; renderTable(); });
document.getElementById('filterStatus').addEventListener('change', function() { state.statFilter = this.value; state.page = 1; renderTable(); });
document.getElementById('filterSupplier').addEventListener('change', function() { state.supFilter = this.value; state.page = 1; renderTable(); });
document.getElementById('rowsPerPage').addEventListener('change', function() { state.perPage = parseInt(this.value); state.page = 1; renderTable(); });
document.getElementById('resetFilters').addEventListener('click', function() {
  state.search = ''; state.catFilter = ''; state.statFilter = ''; state.supFilter = '';
  document.getElementById('invSearch').value = '';
  document.getElementById('filterCategory').value = '';
  document.getElementById('filterStatus').value = '';
  document.getElementById('filterSupplier').value = '';
  state.page = 1; renderTable();
});

document.querySelectorAll('#invTable th.sortable').forEach(function(th) {
  th.addEventListener('click', function() {
    var field = this.getAttribute('data-field');
    if (state.sortField === field) { state.sortDir = state.sortDir === 'asc' ? 'desc' : 'asc'; }
    else { state.sortField = field; state.sortDir = 'asc'; }
    document.querySelectorAll('#invTable th').forEach(function(h) { h.classList.remove('sort-asc', 'sort-desc'); });
    this.classList.add('sort-' + state.sortDir);
    renderTable();
  });
});

function showDetail(id) {
  var item = inventoryData.find(function(i) { return parseInt(i.inventory_id) === id; });
  if (!item) return;
  document.getElementById('detailTitle').textContent = item.item_name;
  var qty = parseInt(item.quantity) || 0;
  var reorder = parseInt(item.reorder_level) || 0;
  var status = qty === 0 ? 'Out of Stock' : (qty < reorder ? 'Low Stock' : 'In Stock');
  var statusV = qty === 0 ? 'danger' : (qty < reorder ? 'warning' : 'success');
  var html = '<div class="detail-grid">'
    + '<div class="field"><span class="field-label">Material</span><span class="field-value">' + esc(item.item_name) + '</span></div>'
    + '<div class="field"><span class="field-label">Category</span><span class="field-value">' + esc(item.supply_type) + '</span></div>'
    + '<div class="field"><span class="field-label">Supplier</span><span class="field-value">' + esc(item.supplier_name) + '</span></div>'
    + '<div class="field"><span class="field-label">Status</span><span class="field-value">' + renderBadge(status, statusV) + '</span></div>'
    + '<div class="field"><span class="field-label">Current Stock</span><span class="field-value" style="font-size:1.1rem;font-weight:700">' + qty + '</span></div>'
    + '<div class="field"><span class="field-label">Reorder Level</span><span class="field-value">' + reorder + '</span></div>'
    + '<div class="field full"><span class="field-label">Last Updated</span><span class="field-value">' + esc(item.last_updated || '-') + '</span></div>'
    + '</div>';
  html += '<div class="stock-log-list"><h4 style="margin:0 0 8px;font-size:0.78rem;font-weight:700;color:var(--text-secondary)"><i class="fas fa-history"></i> Recent Movements</h4>';
  html += '<div id="stockLogs"><p style="font-size:0.75rem;color:var(--text-tertiary)"><i class="fas fa-spinner fa-spin"></i> Loading...</p></div></div>';
  document.getElementById('detailBody').innerHTML = html;
  document.getElementById('detailModal').style.display = 'flex';
  fetch('../../app/Controllers/get_stock_logs.php?id=' + id)
    .then(function(r) { return r.json(); })
    .then(function(logs) {
      var el = document.getElementById('stockLogs');
      if (!logs || logs.length === 0) { el.innerHTML = '<p style="font-size:0.75rem;color:var(--text-tertiary)">No stock movements recorded</p>'; return; }
      el.innerHTML = logs.map(function(l) {
        var typeClass = l.change_type === 'in' ? 'log-in' : 'log-out';
        return '<div class="stock-log-item"><span class="log-type ' + typeClass + '"><i class="fas fa-arrow-' + (l.change_type === 'in' ? 'down' : 'up') + '"></i> ' + l.change_type.toUpperCase() + '</span><span>' + l.quantity + ' pcs</span><span style="color:var(--text-tertiary)">' + (l.note ? esc(l.note) : '') + '</span><span style="color:var(--text-tertiary);font-size:0.7rem">' + (l.created_at ? new Date(l.created_at).toLocaleString() : '') + '</span></div>';
      }).join('');
    })
    .catch(function() { document.getElementById('stockLogs').innerHTML = '<p style="font-size:0.75rem;color:var(--text-tertiary)">Failed to load history</p>'; });
}

function closeDetail() { document.getElementById('detailModal').style.display = 'none'; }

function showEdit(id) {
  var row = document.querySelector('.inv-row[data-id="' + id + '"]');
  if (!row) return;
  document.getElementById('editInventoryId').value = id;
  document.getElementById('editItemName').value = row.getAttribute('data-itemname') || '';
  var cat = row.getAttribute('data-category') || '';
  document.getElementById('editType').value = typeIdMap[Object.keys(typeIdMap).find(function(k) { return k.toLowerCase() === cat.toLowerCase(); })] || Object.values(typeIdMap)[0];
  var sup = row.getAttribute('data-supplier') || '';
  document.getElementById('editSupplier').value = supplierIdMap[Object.keys(supplierIdMap).find(function(k) { return k.toLowerCase() === sup.toLowerCase(); })] || Object.values(supplierIdMap)[0];
  document.getElementById('editReorder').value = row.getAttribute('data-reorder') || '10';
  document.getElementById('stockInOutInventoryId').value = id;
  document.getElementById('stockInOutSupplierId').value = supplierIdMap[Object.keys(supplierIdMap).find(function(k) { return k.toLowerCase() === sup.toLowerCase(); })] || Object.values(supplierIdMap)[0];
  document.getElementById('editInventoryModal').style.display = 'flex';
}
function closeEditInventoryModal() { document.getElementById('editInventoryModal').style.display = 'none'; }

function showStockIn(id) {
  var item = inventoryData.find(function(i) { return parseInt(i.inventory_id) === id; });
  if (!item) return;
  document.getElementById('stockInId').value = id;
  document.getElementById('stockInName').textContent = item.item_name;
  document.getElementById('stockInSupplierId').value = item.supplier_id || '';
  document.getElementById('stockInSupplier').value = item.supplier_id || '';
  document.getElementById('stockInModal').style.display = 'flex';
}
function closeStockIn() { document.getElementById('stockInModal').style.display = 'none'; }

function showStockOut(id) {
  var item = inventoryData.find(function(i) { return parseInt(i.inventory_id) === id; });
  if (!item) return;
  document.getElementById('stockOutId').value = id;
  document.getElementById('stockOutName').textContent = item.item_name;
  document.getElementById('stockOutCurrent').textContent = item.quantity || 0;
  document.getElementById('stockOutModal').style.display = 'flex';
}
function closeStockOut() { document.getElementById('stockOutModal').style.display = 'none'; }

function showDelete(id) { document.getElementById('deleteInventoryId').value = id; document.getElementById('deleteInventoryModal').style.display = 'flex'; }
function closeDeleteModal() { document.getElementById('deleteInventoryModal').style.display = 'none'; }
function showAddInventoryModal() { document.getElementById('addInventoryModal').style.display = 'flex'; }
function closeAddInventoryModal() { document.getElementById('addInventoryModal').style.display = 'none'; }

function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
function renderBadge(text, variant) { return '<span class="status-badge status-badge-' + variant + ' status-badge-sm">' + esc(text) + '</span>'; }

function exportCSV() {
  var rows = getFilteredRows();
  var headers = ['Material','Category','Supplier','Quantity','Reorder Level','Status','Last Updated'];
  var csv = headers.join(',') + '\n';
  var statusMap = { 'success': 'In Stock', 'warning': 'Low Stock', 'danger': 'Out of Stock' };
  rows.forEach(function(r) {
    var itemName = r.getAttribute('data-itemname') || '';
    var category = r.getAttribute('data-category') || '';
    var supplier = r.getAttribute('data-supplier') || '';
    var qty = r.getAttribute('data-qty') || '0';
    var reorder = r.getAttribute('data-reorder') || '0';
    var status = statusMap[r.getAttribute('data-status')] || '';
    var updated = r.querySelectorAll('td')[6]?.textContent.trim() || '';
    var vals = [itemName, category, supplier, qty, reorder, status, updated].map(function(v) { return '"' + String(v).replace(/"/g, '""') + '"'; });
    csv += vals.join(',') + '\n';
  });
  var blob = new Blob([csv], { type: 'text/csv' });
  var a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'inventory_export.csv'; a.click();
  URL.revokeObjectURL(a.href);
}

document.getElementById('menuToggle')?.addEventListener('click', function() { document.getElementById('sidebar')?.classList.toggle('collapsed'); });

document.querySelectorAll('.modal').forEach(function(m) {
  m.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});

renderTable();
</script>

<?php
function stringToColorJS($str) {
  if (!$str) return '#6b7280';
  $hash = 0;
  for ($i = 0; $i < strlen($str); $i++) { $hash = ord($str[$i]) + (($hash << 5) - $hash); }
  $h = abs($hash) % 360;
  return "hsl({$h}, 55%, 55%)";
}
$scriptsHtml = ob_get_clean();

$workspace .= $scriptsHtml;

echo renderDashboardShell(
  renderPageHeader(
    'Inventory',
    'Manage materials, supplies, and stock levels',
    '',
    []
  ),
  renderKPIRow([
    ['icon' => 'fas fa-box',         'label' => 'Total Items',        'value' => $totalItems,                          'accent' => 'blue'],
    ['icon' => 'fas fa-exclamation',  'label' => 'Low Stock',         'value' => count($lowStockItems),                'accent' => 'amber'],
    ['icon' => 'fas fa-times-circle', 'label' => 'Out of Stock',      'value' => count($outOfStockItems),              'accent' => 'red'],
    ['icon' => 'fas fa-tag',          'label' => 'Est. Value',        'value' => '₱' . number_format($totalValue, 0),  'accent' => 'green'],
  ]),
  $workspace
);
