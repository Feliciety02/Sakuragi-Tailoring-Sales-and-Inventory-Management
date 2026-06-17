<?php
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/session_handler.php';

$serviceId = $_SESSION['selected_service_id'] ?? $_POST['service_id'] ?? null;
$unitPrice = 0;

if ($serviceId) {
    $stmt = $pdo->prepare('SELECT service_price FROM services WHERE service_id = ?');
    $stmt->execute([$serviceId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $unitPrice = $row['service_price'] ?? 0;
}
?>

<input type="hidden" id="unitPrice" value="<?= $unitPrice ?>">
<input type="hidden" name="standard_table_data" id="standardTableData">

<h5 class="mb-3 fw-bold text-center">Step 3: Design Type</h5>
<p class="text-muted text-center mb-4">Choose whether your order requires unique names and sizes (Customizable) or standard sizing across all items (Standard).</p>

<!-- Design Type Selection -->
<div class="row g-4 justify-content-center">
    <div class="col-md-5 col-sm-6">
        <div class="design-type-card text-center p-4 shadow-sm rounded-4 h-100" onclick="selectDesignType('customizable', this)">
            <input type="radio" name="design_type" id="customizable" value="customizable" class="d-none">
            <label for="customizable" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                <div class="option-icon mb-2">👕</div>
                <h6 class="fw-bold mb-2">Customizable</h6>
                <small>Upload an Excel list for personalized uniforms with different names, numbers, or roles.</small>
            </label>
        </div>
    </div>
    <div class="col-md-5 col-sm-6">
        <div class="design-type-card text-center p-4 shadow-sm rounded-4 h-100" onclick="selectDesignType('standard', this)">
            <input type="radio" name="design_type" id="standard" value="standard" class="d-none">
            <label for="standard" class="w-100 h-100 d-flex flex-column align-items-center justify-content-center">
                <div class="option-icon mb-2">🧵</div>
                <h6 class="fw-bold mb-2">Standard</h6>
                <small>Same design and sizes for all items. Manually add sizes and quantities.</small>
            </label>
        </div>
    </div>
</div>

<!-- Customizable Section -->
<div id="customizableSection" class="d-none mt-5">
    <h6 class="fw-bold mb-3 text-primary">📂 Upload Excel File (.xlsx)</h6>
    <input type="file" class="form-control mb-3" id="excelFile" name="excel_file" accept=".xlsx" onchange="handleExcelUpload()">

    <div id="excelActions" class="d-none mb-3">
        <button class="btn btn-outline-danger btn-sm rounded-pill" onclick="removeExcelFile()">❌ Remove Uploaded File</button>
        <input type="text" class="form-control mt-3" id="excelSearch" placeholder="🔍 Search rows...">
    </div>

    <div id="excelPreview" class="mt-3"></div>

    <!-- 🔽 Hidden input to store parsed data for submission -->
    <input type="hidden" name="customizable_table_data" id="customizableTableData">
</div>


<!-- Standard Section -->
<div id="nonCustomizableSection" class="d-none mt-5">
    <h6 class="fw-bold mb-3 text-primary">📋 Manual Entry for Sizes and Quantities</h6>
    <table class="table table-bordered align-middle">
        <thead class="table-light">
            <tr>
                <th>Size</th>
                <th>Quantity</th>
                <th>Cost</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="manualTableBody">
            <tr>
                <td>
                    <select class="form-control" name="size[]" onchange="updateCost(this)">
                        <option value="Small">Small</option>
                        <option value="Medium">Medium</option>
                        <option value="Large">Large</option>
                    </select>
                </td>
                <td>
                    <input type="number" class="form-control" name="quantity[]" min="1" placeholder="e.g., 12" oninput="updateCost(this)">
                </td>
                <td class="cost-cell">₱0.00</td>
                <td>
                    <button class="btn btn-outline-danger btn-sm" onclick="removeManualRow(this)">Remove</button>
                </td>
            </tr>
        </tbody>
    </table>
    <button class="btn btn-outline-primary btn-sm mt-2 rounded-pill" onclick="addManualRow()">➕ Add Row</button>
</div>

<!-- CSS Styling (same as before) -->
<style>
.design-type-card {
    border: 2px solid transparent;
    background-color: #fff;
    transition: all 0.3s ease;
    cursor: pointer;
    border-radius: 16px;
    padding: 30px 20px;
    box-shadow: 0 0 0 rgba(0,0,0,0);
    user-select: none;
}
.design-type-card:hover {
    border-color: #0b5cf9;
    box-shadow: 0 8px 20px rgba(11, 92, 249, 0.15);
    transform: translateY(-2px);
}
.design-type-card.selected {
    border-color: #0b5cf9;
    background: #eef4ff;
    box-shadow: 0 10px 25px rgba(11, 92, 249, 0.15);
}
.design-type-card.selected h6,
.design-type-card.selected small {
    color: #0b5cf9;
}
.option-icon {
    font-size: 2.8rem;
    line-height: 1;
}
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
function setNextButtonState(enabled) {
  const btn = document.getElementById('nextBtn');
  if (!btn) return;
  btn.disabled = !enabled;
  btn.classList.toggle('btn-primary', enabled);
  btn.classList.toggle('btn-secondary', !enabled);
}

function checkNextButtonCondition() {
  const isCustom = document.getElementById('customizable')?.checked;
  const isStandard = document.getElementById('standard')?.checked;

  if (isCustom) {
    const customData = document.getElementById('customizableTableData')?.value;
    setNextButtonState(customData && customData.length > 0);
  } else if (isStandard) {
    const standardData = document.getElementById('standardTableData')?.value;
    setNextButtonState(standardData && JSON.parse(standardData).length > 0);
  } else {
    setNextButtonState(false);
  }
}

function selectDesignType(type, el) {
  document.getElementById('customizableSection').classList.toggle('d-none', type !== 'customizable');
  document.getElementById('nonCustomizableSection').classList.toggle('d-none', type !== 'standard');
  document.querySelectorAll('.design-type-card').forEach(card => card.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById(type).checked = true;

  if (type === 'customizable') {
    sessionStorage.removeItem('standardDesignExcel');
    document.getElementById('standardTableData').value = '';
  } else {
    sessionStorage.removeItem('uploadedDesignList');
    document.getElementById('customizableTableData').value = '';
  }

  checkNextButtonCondition();
}

function handleExcelUpload() {
  const file = document.getElementById('excelFile').files[0];
  if (!file) return;

  const reader = new FileReader();
  reader.onload = function(e) {
    const data = new Uint8Array(e.target.result);
    const workbook = XLSX.read(data, { type: 'array' });
    const sheet = workbook.Sheets[workbook.SheetNames[0]];
    const json = XLSX.utils.sheet_to_json(sheet, { header: 1 });
    renderExcelTable(json);
  };
  reader.readAsArrayBuffer(file);
}

function renderExcelTable(json) {
  const selectedService = JSON.parse(sessionStorage.getItem('selectedService'));
  const unitPrice = selectedService?.price || 0;
  const headers = json[0];

  const sizeIndex = headers.findIndex(h => h.toLowerCase().includes('size'));
  const qtyIndex = headers.findIndex(h => h.toLowerCase().includes('quantity') || h.toLowerCase().includes('number'));

  if (sizeIndex === -1 || qtyIndex === -1) {
    document.getElementById('excelPreview').innerHTML = `<div class='alert alert-warning'>Missing "Size" or "Quantity/Number" column.</div>`;
    setNextButtonState(false);
    return;
  }

  let html = `<table class='table table-bordered align-middle'><thead><tr>`;
  headers.forEach(h => html += `<th>${h}</th>`);
  html += `<th>Cost</th><th>Action</th></tr></thead><tbody>`;

  const dataRows = [];

  for (let i = 1; i < json.length; i++) {
    const row = json[i];
    if (!row || !row[sizeIndex] || !row[qtyIndex]) continue;

    const size = row[sizeIndex]?.trim();
    const quantity = parseInt(row[qtyIndex]) || 0;
    if (!size || quantity <= 0) continue;

    const cost = quantity * unitPrice;

    html += `<tr>`;
    row.forEach((cell, colIndex) => {
      const val = cell ?? '';
      if (colIndex === sizeIndex) {
        html += `<td><select class='form-control' onchange='updateCustomRow(this)' data-row='${i}' data-col='${colIndex}'>
          <option value='Small' ${val === 'Small' ? 'selected' : ''}>Small</option>
          <option value='Medium' ${val === 'Medium' ? 'selected' : ''}>Medium</option>
          <option value='Large' ${val === 'Large' ? 'selected' : ''}>Large</option>
          <option value='XL' ${val === 'XL' ? 'selected' : ''}>XL</option>
        </select></td>`;
      } else if (colIndex === qtyIndex) {
        html += `<td><input type='number' class='form-control' min='1' value='${val}' oninput='updateCustomRow(this)' data-row='${i}' data-col='${colIndex}'></td>`;
      } else {
        html += `<td>${val}</td>`;
      }
    });

    html += `<td class='cost-cell'>₱${cost.toFixed(2)}</td>`;
    html += `<td><button class='btn btn-outline-danger btn-sm' onclick='removeExcelRow(this, ${i})'>Remove</button></td></tr>`;
    dataRows.push({ size, quantity, cost, meta: row });
  }

  html += `</tbody></table>`;
  const totalQty = dataRows.reduce((sum, r) => sum + r.quantity, 0);
  const freeShirts = Math.floor(totalQty / 12);
  html += `<p class='mt-3 text-success'>🎁 Eligible for <strong>${freeShirts}</strong> free shirt(s).</p>`;

  sessionStorage.setItem('uploadedDesignList', JSON.stringify(json));
  document.getElementById('customizableTableData').value = JSON.stringify(dataRows);
  document.getElementById('excelPreview').innerHTML = html;

  checkNextButtonCondition();
}

function updateCustomRow(el) {
  const rowData = JSON.parse(sessionStorage.getItem('uploadedDesignList'));
  const rowIndex = parseInt(el.dataset.row);
  const colIndex = parseInt(el.dataset.col);
  const value = el.value;

  if (rowData[rowIndex]) {
    rowData[rowIndex][colIndex] = value;
    sessionStorage.setItem('uploadedDesignList', JSON.stringify(rowData));
    renderExcelTable(rowData);
  }
}

function removeExcelRow(btn, rowIndex) {
  const rowData = JSON.parse(sessionStorage.getItem('uploadedDesignList'));
  rowData.splice(rowIndex, 1);
  sessionStorage.setItem('uploadedDesignList', JSON.stringify(rowData));
  renderExcelTable(rowData);
}

function addManualRow() {
  const tbody = document.getElementById('manualTableBody');
  const newRow = document.createElement('tr');
  newRow.innerHTML = `
    <td>
      <select class="form-control" name="size[]" onchange="updateCost(this)">
        <option value="Small">Small</option>
        <option value="Medium">Medium</option>
        <option value="Large">Large</option>
        <option value="XL">XL</option>
      </select>
    </td>
    <td>
      <input type="number" class="form-control" name="quantity[]" min="1" oninput="updateCost(this)">
    </td>
    <td class="cost-cell">₱0.00</td>
    <td>
      <button class="btn btn-outline-danger btn-sm" onclick="removeManualRow(this)">Remove</button>
    </td>
  `;
  tbody.appendChild(newRow);
}

function removeManualRow(btn) {
  btn.closest('tr').remove();
  updateStandardValidation();
}

function updateCost(el) {
  const row = el.closest('tr');
  const quantity = parseInt(row.querySelector('input[name="quantity[]"]').value) || 0;
  const selectedService = JSON.parse(sessionStorage.getItem('selectedService'));
  const unitPrice = selectedService?.price || 0;
  const total = quantity * unitPrice;
  row.querySelector('.cost-cell').textContent = `₱${total.toFixed(2)}`;
  updateStandardValidation();
}

function updateStandardValidation() {
  const rows = document.querySelectorAll('#manualTableBody tr');
  const data = [];
  let valid = false;

  rows.forEach(row => {
    const size = row.querySelector('select[name="size[]"]').value;
    const quantity = parseInt(row.querySelector('input[name="quantity[]"]').value) || 0;
    if (quantity > 0) {
      data.push([size, quantity]);
      valid = true;
    }
  });

  if (valid) {
    const worksheet = XLSX.utils.aoa_to_sheet([['Size', 'Quantity'], ...data]);
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, worksheet, 'StandardOrder');
    const excelData = XLSX.write(workbook, { type: 'base64', bookType: 'xlsx' });
    sessionStorage.setItem('standardDesignExcel', excelData);
    document.getElementById('standardTableData').value = JSON.stringify(data.map(([size, qty]) => ({ size, quantity: qty })));
  } else {
    sessionStorage.removeItem('standardDesignExcel');
    document.getElementById('standardTableData').value = '';
  }

  checkNextButtonCondition();
}

document.addEventListener('DOMContentLoaded', () => {
  setNextButtonState(false);
  updateStandardValidation();
});

document.getElementById('nextBtn')?.addEventListener('click', function () {
  const isCustom = document.getElementById('customizable')?.checked;
  const isStandard = document.getElementById('standard')?.checked;
  const selectedService = JSON.parse(sessionStorage.getItem('selectedService'));
  const servicePrice = parseFloat(selectedService?.price) || 0;

  let items = [], totalItems = 0, shirtTotal = 0;

  if (isCustom) {
    const data = JSON.parse(document.getElementById('customizableTableData')?.value || '[]');
    items = data.map(row => {
      const quantity = parseInt(row.quantity);
      const cost = row.cost;
      totalItems += quantity;
      shirtTotal += cost;
      return {
        size: row.size,
        quantity,
        price_per_unit: cost / quantity,
        cost
      };
    });
  } else if (isStandard) {
    const data = JSON.parse(document.getElementById('standardTableData')?.value || '[]');
    items = data.map(row => {
      const quantity = parseInt(row.quantity);
      const unitPrice = selectedService.price;
      const cost = quantity * unitPrice;
      totalItems += quantity;
      shirtTotal += cost;
      return {
        size: row.size,
        quantity,
        price_per_unit: unitPrice,
        cost
      };
    });
  }

  const grandTotal = shirtTotal + servicePrice;

  sessionStorage.setItem('orderSummaryData', JSON.stringify({
    items,
    totalItems,
    shirtTotal,
    servicePrice,
    grandTotal
  }));

  const designInput = document.getElementById('excelFile');
  if (designInput && designInput.files.length > 0) {
    const file = designInput.files[0];
    sessionStorage.setItem('uploadedDesign', URL.createObjectURL(file));
    sessionStorage.setItem('uploadedDesignName', file.name);
  }

  currentStep = 4;
  updateStepper();
  if (typeof displayOrderSummary === 'function') displayOrderSummary();
});
</script>
