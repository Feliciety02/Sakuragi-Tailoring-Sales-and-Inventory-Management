<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../app/Middleware/auth_required.php';
require_once __DIR__ . '/../../config/db_connect.php'; // This defines $pdo
require_once __DIR__ . '/../../app/Controllers/InventoryController.php';

$pageTitle = 'Inventory';

$inventoryItems = getInventory($pdo);
$suppliers = getSuppliers($pdo);
$types = getSupplyTypes($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/adminInventory.css" />
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/employee.php'; ?>
  <div class="dash-main">
    <?php require_once '../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
  <h1>Manage Inventory</h1>

  <div class="table-controls">
<div class="search-wrapper">
  <input type="text" id="inventorySearch" placeholder="Search item..." onkeyup="filterInventoryTable()" />

</div>

      <button id="downloadCSV" class="btn-export" onclick="downloadCSV()">
      <i class="fas fa-download"></i> CSV
    </button>

    <button onclick="showAddInventoryModal()" class="btn-export">
      <i class="fas fa-plus"></i> Add Item
    </button>
  </div>

  <div class="table-responsive">
    <table id="inventoryTable" data-sort-dir="asc">
      <thead>
  <tr>
    <th onclick="sortTableByColumn(0)">Item Name</th>
    <th onclick="sortTableByColumn(1)">Category</th>
    <th onclick="sortTableByColumn(2)">Supplier</th>
    <th onclick="sortTableByColumn(3)">Qty</th>
    <th onclick="sortTableByColumn(4)">Reorder Level</th>  
    <th onclick="sortTableByColumn(5)">Status</th>
    <th onclick="sortTableByColumn(6)">Last Updated</th>
    <th>Actions</th>
  </tr>
</thead>
<tbody>
  <?php foreach ($inventoryItems as $item): ?>
    <tr data-id="<?= $item['inventory_id'] ?? '' ?>">
      <td><?= htmlspecialchars($item['item_name'] ?? '') ?></td>
      <td>
        <span class="category-badge category-<?= str_replace([' ', '&'], '', $item['supply_type'] ?? 'Unknown') ?>">
          <?= htmlspecialchars($item['supply_type'] ?? 'Unknown') ?>
        </span>
      </td>
      <td><?= htmlspecialchars($item['supplier_name'] ?? '') ?></td>
      <td><?= htmlspecialchars((string) ($item['quantity'] ?? 0)) ?></td>
      <td><?= htmlspecialchars((string) ($item['reorder_level'] ?? 0)) ?></td>
      <td>
        <?php
        $qty = $item['quantity'] ?? 0;
        $reorder = $item['reorder_level'] ?? 0;
        ?>
        <?php if ($qty === 0): ?>
          <span class="status-badge out">Out of Stock</span>
        <?php elseif ($qty < $reorder): ?>
          <span class="status-badge low">Low Stock</span>
        <?php else: ?>
          <span class="status-badge ok">In Stock</span>
        <?php endif; ?>
      </td>
      <td><?= htmlspecialchars($item['last_updated'] ?? '-') ?></td>
      <td class="action-buttons">
        <button class="edit" onclick="showEditInventoryModal(<?= $item['inventory_id'] ?? 0 ?>)">
          <i class="fas fa-pen"></i>
        </button>
        <button class="delete" onclick="showDeleteInventoryModal(<?= $item['inventory_id'] ?? 0 ?>)">
          <i class="fas fa-trash"></i>
        </button>
      </td>
    </tr>
  <?php endforeach; ?>
</tbody>

    </table>
    <div id="paginationControls" class="pagination-controls"></div>
  </div>
</div>
  </div>
</div>

<!-- Add Modal -->
<div id="addInventoryModal" class="modal add">
  <div class="modal-content">
    <span class="close-btn" onclick="closeAddInventoryModal()">×</span>
    <h2 class="modal-title">Add Inventory Item</h2>
    <form method="POST" action="../../app/Controllers/InventoryController.php">
      <input type="hidden" name="action" value="add">
      <label>Item Name</label>
      <input type="text" name="item_name" required>
      <label>Category</label>
        <select name="supply_type_id" required>
          <?php foreach ($types as $type): ?>
            <option value="<?= $type['supply_type_id'] ?>"><?= $type['name'] ?></option>
          <?php endforeach; ?>
        </select>

      <label>Supplier</label>
      <select name="supplier_id" required>
        <?php foreach ($suppliers as $supplier): ?>
          <option value="<?= $supplier['supplier_id'] ?>"><?= $supplier['supplier_name'] ?></option>
        <?php endforeach; ?>
      </select>
      <label>Quantity</label>
      <input type="number" name="quantity" required>
      <label>Reorder Level</label>
      <input type="number" name="reorder_level" value="10" required>
      <div class="modal-button-group">
        <button type="submit" class="btn-primary">Save</button>
        <button type="button" onclick="closeAddInventoryModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>
<!-- Edit Modal -->
<div id="editInventoryModal" class="modal edit">
  <div class="modal-content">
    <span class="close-btn" onclick="closeEditInventoryModal()">×</span>
    <h2 class="modal-title">Edit Inventory Item</h2>

    <form method="POST" action="../../app/Controllers/InventoryController.php" class="edit-form">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" id="editInventoryId" name="inventory_id">

      <label>Item Name</label>
      <input type="text" id="editItemName" name="item_name" required>

      <label>Category</label>
      <select id="editType" name="supply_type_id" required>
        <?php foreach ($types as $type): ?>
          <option value="<?= $type['supply_type_id'] ?>"><?= $type['name'] ?></option>
        <?php endforeach; ?>
      </select>

      <label>Supplier</label>
      <select id="editSupplier" name="supplier_id" required>
        <?php foreach ($suppliers as $supplier): ?>
          <option value="<?= $supplier['supplier_id'] ?>"><?= $supplier['supplier_name'] ?></option>
        <?php endforeach; ?>
      </select>

      <label>Reorder Level</label>
      <input type="number" id="editReorder" name="reorder_level" required>

      <hr style="margin: 20px 0;">

      <label>Stock In / Out</label>
      <input type="hidden" name="stock_inventory_id" id="stockInOutInventoryId">
      <input type="hidden" name="stock_supplier_id" id="stockInOutSupplierId">

      <div class="stock-row">
        <input type="number" name="quantity" min="1" placeholder="Qty" style="flex: 1;" required>
        <button type="submit" name="action" value="stock_in" class="stock-in">
          <i class="fas fa-arrow-down"></i> In
        </button>
        <button type="submit" name="action" value="stock_out" class="stock-out">
          <i class="fas fa-arrow-up"></i> Out
        </button>
      </div>

      <div class="modal-button-group" style="margin-top: 20px;">
        <button type="submit" name="action" value="edit" class="btn-primary">Update</button>
        <button type="button" onclick="closeEditInventoryModal()" class="btn-cancel">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Modal -->
<div id="deleteInventoryModal" class="modal delete">
  <div class="modal-content">
    <span class="close-btn" onclick="closeDeleteInventoryModal()">×</span>
    <h2 class="modal-title">Delete Inventory Item</h2>
    <form method="POST" action="../../app/Controllers/InventoryController.php">
      <input type="hidden" name="action" value="delete">
      <input type="hidden" id="deleteInventoryId" name="inventory_id">
      <p>Are you sure you want to delete this item?</p>
      <div class="modal-button-group">
        <button type="submit" class="btn-primary">Yes, Delete</button>
        <button type="button" onclick="closeDeleteInventoryModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>
</body>
</html>

<script>
const typeIdMap = <?= json_encode(array_column($types, 'supply_type_id', 'name')) ?>;
const supplierIdMap = <?= json_encode(array_column($suppliers, 'supplier_id', 'supplier_name')) ?>;

let currentPage = 1;
const rowsPerPage = 10;

function showAddInventoryModal() {
  document.getElementById('addInventoryModal').style.display = 'flex';
}
function closeAddInventoryModal() {
  document.getElementById('addInventoryModal').style.display = 'none';
}
function showEditInventoryModal(id) {
  const row = document.querySelector(`tr[data-id='${id}']`);
  const cells = row.querySelectorAll('td');
  document.getElementById('editInventoryId').value = id;
  document.getElementById('editItemName').value = cells[0].textContent.trim();
  document.getElementById('editType').value = getTypeIdByName(cells[1].textContent.trim());
  document.getElementById('editSupplier').value = getSupplierIdByName(cells[2].textContent.trim());
document.getElementById('editReorder').value = cells[4].textContent.trim();

  document.getElementById('stockInOutInventoryId').value = id;
  document.getElementById('stockInOutSupplierId').value = getSupplierIdByName(cells[2].textContent.trim());
  document.getElementById('editInventoryModal').style.display = 'flex';
}
function closeEditInventoryModal() {
  document.getElementById('editInventoryModal').style.display = 'none';
}
function showDeleteInventoryModal(id) {
  document.getElementById('deleteInventoryId').value = id;
  document.getElementById('deleteInventoryModal').style.display = 'flex';
}
function closeDeleteInventoryModal() {
  document.getElementById('deleteInventoryModal').style.display = 'none';
}
function showStockInModal(id) {
  document.getElementById('stockInInventoryId').value = id;
  document.getElementById('stockInModal').style.display = 'flex';
}
function closeStockInModal() {
  document.getElementById('stockInModal').style.display = 'none';
}
function showStockOutModal(id) {
  document.getElementById('stockOutInventoryId').value = id;
  document.getElementById('stockOutModal').style.display = 'flex';
}
function closeStockOutModal() {
  document.getElementById('stockOutModal').style.display = 'none';
}
function getTypeIdByName(name) {
  return typeIdMap[name] || "";
}
function getSupplierIdByName(name) {
  return supplierIdMap[name] || "";
}

// 🔍 Live Search + Pagination Sync
function filterInventoryTable() {
  currentPage = 1;
  paginateTable(); // will update based on input value
}

// ✅ Paginate only visible rows based on search
function paginateTable() {
  const searchValue = document.getElementById("inventorySearch").value.toLowerCase();
  const allRows = document.querySelectorAll("#inventoryTable tbody tr");
  const pagination = document.getElementById("paginationControls");

  const filteredRows = Array.from(allRows).filter(row =>
    row.innerText.toLowerCase().includes(searchValue)
  );

  const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
  pagination.innerHTML = "";

  // Hide all rows initially
  allRows.forEach(row => row.style.display = "none");

  // Show only the current page's filtered rows
  const start = (currentPage - 1) * rowsPerPage;
  const end = start + rowsPerPage;
  filteredRows.forEach((row, index) => {
    if (index >= start && index < end) row.style.display = "";
  });

  // Pagination buttons
  if (filteredRows.length === 0) {
    pagination.innerHTML = "<span style='color: #777;'>No results found.</span>";
  } else {
    for (let i = 1; i <= totalPages; i++) {
      const btn = document.createElement("button");
      btn.textContent = i;
      btn.className = i === currentPage ? "active" : "";
      btn.onclick = () => {
        currentPage = i;
        paginateTable();
      };
      pagination.appendChild(btn);
    }
  }
}

function stringToColor(str) {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = str.charCodeAt(i) + ((hash << 5) - hash);
  }
  const hue = hash % 360;
  return `hsl(${hue}, 65%, 55%)`;
}

document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".category-badge").forEach(badge => {
    const category = badge.getAttribute("data-category");
    badge.style.backgroundColor = stringToColor(category);
  });
});


function downloadCSV() {
  const table = document.querySelector("#inventoryTable");
  const rows = Array.from(table.querySelectorAll("tbody tr")); // grab ALL rows

  let csvContent = "data:text/csv;charset=utf-8,";

  // Extract headers (excluding "Actions" column)
  const headers = Array.from(table.querySelectorAll("thead th"))
    .map(th => `"${th.textContent.trim()}"`).slice(0, 6); // assuming Actions is column 7
  csvContent += headers.join(",") + "\r\n";

  // Extract all data rows (first 6 columns only)
  rows.forEach(row => {
    const cols = Array.from(row.querySelectorAll("td")).slice(0, 6);
    const line = cols.map(td => `"${td.textContent.trim()}"`).join(",");
    csvContent += line + "\r\n";
  });

  // Download trigger
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "inventory_data.csv");
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Trigger search+paginate on input
document.getElementById("inventorySearch").addEventListener("input", filterInventoryTable);
document.addEventListener("DOMContentLoaded", filterInventoryTable);
</script>
