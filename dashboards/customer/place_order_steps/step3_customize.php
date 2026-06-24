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

<div style="text-align:center;margin-bottom:24px">
  <h5 style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin-bottom:6px">Step 3: Design Type</h5>
  <p style="font-size:0.85rem;color:var(--text-tertiary);margin:0">Choose whether your order requires unique names and sizes (Customizable) or standard sizing across all items (Standard).</p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px;max-width:640px;margin:0 auto">
  <div class="panel-card design-type-card" onclick="selectDesignType('customizable', this)" style="text-align:center;padding:32px 20px;cursor:pointer;border:2px solid transparent;transition:all 0.25s ease;user-select:none">
    <input type="radio" name="design_type" id="customizable" value="customizable" style="display:none">
    <label for="customizable" style="display:flex;flex-direction:column;align-items:center;cursor:pointer;margin:0">
      <div style="font-size:2.8rem;color:var(--role-accent);margin-bottom:10px"><i class="fa-solid fa-shirt"></i></div>
      <h6 style="font-size:0.95rem;font-weight:700;color:var(--text-primary);margin-bottom:6px">Customizable</h6>
      <p style="font-size:0.8rem;color:var(--text-tertiary);margin:0">Upload an Excel list for personalized uniforms with different names, numbers, or roles.</p>
    </label>
  </div>
  <div class="panel-card design-type-card" onclick="selectDesignType('standard', this)" style="text-align:center;padding:32px 20px;cursor:pointer;border:2px solid transparent;transition:all 0.25s ease;user-select:none">
    <input type="radio" name="design_type" id="standard" value="standard" style="display:none">
    <label for="standard" style="display:flex;flex-direction:column;align-items:center;cursor:pointer;margin:0">
      <div style="font-size:2.8rem;color:var(--role-accent);margin-bottom:10px"><i class="fa-solid fa-ruler-combined"></i></div>
      <h6 style="font-size:0.95rem;font-weight:700;color:var(--text-primary);margin-bottom:6px">Standard</h6>
      <p style="font-size:0.8rem;color:var(--text-tertiary);margin:0">Same design and sizes for all items. Manually add sizes and quantities.</p>
    </label>
  </div>
</div>

<div id="customizableSection" style="display:none;margin-top:28px">
  <h6 style="font-size:0.9rem;font-weight:700;color:var(--role-accent);margin-bottom:12px"><i class="fa-solid fa-file-excel"></i> Upload Excel File (.xlsx)</h6>

  <div id="excelUploadInput" style="margin-bottom:12px">
    <input type="file" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.85rem;font-family:inherit;background:var(--surface);color:var(--text-primary);outline:none;transition:border-color 0.2s" id="excelFile" name="excel_file" accept=".xlsx" onchange="handleExcelUpload()">
  </div>

  <div id="excelActions" style="display:none;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
    <button class="dash-btn dash-btn-danger dash-btn-sm" onclick="removeExcelFile()"><i class="fa-solid fa-trash"></i> Remove File</button>
  </div>

  <div id="excelPreview" style="margin-top:12px"></div>
  <input type="hidden" name="customizable_table_data" id="customizableTableData">
</div>

<div id="nonCustomizableSection" style="display:none;margin-top:28px">
  <h6 style="font-size:0.9rem;font-weight:700;color:var(--role-accent);margin-bottom:12px"><i class="fa-solid fa-table"></i> Manual Entry for Sizes and Quantities</h6>
  <div class="data-table-wrapper" style="overflow-x:auto">
    <table class="data-table" style="min-width:400px">
      <thead>
        <tr>
          <th class="data-table-th">Size</th>
          <th class="data-table-th">Quantity</th>
          <th class="data-table-th">Cost</th>
          <th class="data-table-th">Action</th>
        </tr>
      </thead>
      <tbody id="manualTableBody">
        <tr class="data-table-row">
          <td class="data-table-cell">
            <select style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.82rem;font-family:inherit;background:var(--surface);color:var(--text-primary);outline:none" name="size[]" onchange="updateCost(this)">
              <option value="Small">Small</option>
              <option value="Medium">Medium</option>
              <option value="Large">Large</option>
              <option value="XL">XL</option>
            </select>
          </td>
          <td class="data-table-cell">
            <input type="number" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.82rem;font-family:inherit;background:var(--surface);color:var(--text-primary);outline:none" name="quantity[]" min="1" placeholder="e.g., 12" oninput="updateCost(this)">
          </td>
          <td class="data-table-cell cost-cell" style="font-weight:600">₱0.00</td>
          <td class="data-table-cell">
            <button class="dash-btn dash-btn-danger dash-btn-sm" onclick="removeManualRow(this)">Remove</button>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
  <button class="dash-btn dash-btn-outline dash-btn-sm" style="margin-top:12px" onclick="addManualRow()"><i class="fa-solid fa-plus"></i> Add Row</button>
</div>

<style id="cust-placeorder-step3">
.design-type-card:hover { border-color:var(--role-accent-soft); transform:translateY(-2px); box-shadow:0 6px 20px var(--role-accent-glow) }
.design-type-card.selected { border-color:var(--role-accent); background:var(--role-accent-soft); box-shadow:0 0 0 3px var(--role-accent-soft), 0 4px 16px var(--role-accent-glow); transform:scale(1.02) }
#excelFile:focus { border-color:var(--role-accent); box-shadow:0 0 0 3px var(--role-accent-soft) }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
function setNextButtonState(enabled) {
  const btn = document.getElementById('nextBtn');
  if (!btn) return;
  btn.disabled = !enabled;
  btn.style.opacity = enabled ? '1' : '0.5';
  btn.style.cursor = enabled ? 'pointer' : 'not-allowed';
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

function showEl(id, show) { document.getElementById(id).style.display = show ? '' : 'none'; }

function selectDesignType(type, el) {
  showEl('customizableSection', type === 'customizable');
  showEl('nonCustomizableSection', type === 'standard');
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
  const sizeIndex = headers.findIndex(h => String(h).toLowerCase().includes('size'));
  const qtyIndex = headers.findIndex(h => /qty|quantity|number/i.test(String(h)));

  if (sizeIndex === -1 || qtyIndex === -1) {
    const available = headers.map(h => `"${h}"`).join(', ');
    document.getElementById('excelPreview').innerHTML = `<div style="background:var(--color-warning);color:#fff;padding:12px 16px;border-radius:var(--radius-sm);font-size:0.85rem">Could not find required columns. Need a "Size" column and a "Quantity"/"Number" column.<br><span style="font-size:0.78rem;opacity:0.85">Found: ${available}</span></div>`;
    setNextButtonState(false);
    return;
  }
  showEl('excelUploadInput', false);
  showEl('excelActions', true);

  let html = `<div class="data-table-wrapper"><table class="data-table"><thead><tr>`;
  headers.forEach(h => html += `<th class="data-table-th">${h}</th>`);
  html += `<th class="data-table-th">Cost</th><th class="data-table-th">Action</th></tr></thead><tbody>`;

  const dataRows = [];
  for (let i = 1; i < json.length; i++) {
    const row = json[i];
    if (!row || !row[sizeIndex] || !row[qtyIndex]) continue;
    const size = row[sizeIndex]?.trim();
    const quantity = parseInt(row[qtyIndex]) || 0;
    if (!size || quantity <= 0) continue;
    const cost = quantity * unitPrice;
    html += `<tr class="data-table-row">`;
    row.forEach((cell, colIndex) => {
      const val = cell ?? '';
      if (colIndex === sizeIndex) {
        html += `<td class="data-table-cell"><select style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:0.8rem;font-family:inherit;background:var(--surface);color:var(--text-primary);outline:none" onchange="updateCustomRow(this)" data-row="${i}" data-col="${colIndex}">
          <option value="Small" ${val === 'Small' ? 'selected' : ''}>Small</option>
          <option value="Medium" ${val === 'Medium' ? 'selected' : ''}>Medium</option>
          <option value="Large" ${val === 'Large' ? 'selected' : ''}>Large</option>
          <option value="XL" ${val === 'XL' ? 'selected' : ''}>XL</option>
        </select></td>`;
      } else if (colIndex === qtyIndex) {
        html += `<td class="data-table-cell"><input type="number" style="width:100%;padding:6px 8px;border:1px solid var(--border);border-radius:6px;font-size:0.8rem;font-family:inherit;background:var(--surface);color:var(--text-primary);outline:none" min="1" value="${val}" oninput="updateCustomRow(this)" data-row="${i}" data-col="${colIndex}"></td>`;
      } else {
        html += `<td class="data-table-cell">${val}</td>`;
      }
    });
    html += `<td class="data-table-cell" style="font-weight:600">₱${cost.toFixed(2)}</td>`;
    html += `<td class="data-table-cell"><button class="dash-btn dash-btn-danger dash-btn-sm" onclick="removeExcelRow(this, ${i})">Remove</button></td></tr>`;
    dataRows.push({ size, quantity, cost, meta: row });
  }
  html += `</tbody></table></div>`;
  const totalQty = dataRows.reduce((sum, r) => sum + r.quantity, 0);
  const freeShirts = Math.floor(totalQty / 12);
  if (freeShirts > 0) {
    html += `<p style="margin-top:12px;font-size:0.85rem;color:var(--color-success)"><i class="fa-solid fa-gift"></i> Eligible for <strong>${freeShirts}</strong> free shirt(s).</p>`;
  }
  sessionStorage.setItem('uploadedDesignList', JSON.stringify(json));
  document.getElementById('customizableTableData').value = JSON.stringify(dataRows);
  document.getElementById('excelPreview').innerHTML = html;
  computeOrderSummary();
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

function removeExcelFile() {
  document.getElementById('excelFile').value = '';
  document.getElementById('excelPreview').innerHTML = '';
  document.getElementById('customizableTableData').value = '';
  sessionStorage.removeItem('uploadedDesignList');
  showEl('excelUploadInput', true);
  showEl('excelActions', false);
  setNextButtonState(false);
}

function addManualRow() {
  const tbody = document.getElementById('manualTableBody');
  const newRow = document.createElement('tr');
  newRow.className = 'data-table-row';
  newRow.innerHTML = `
    <td class="data-table-cell">
      <select style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.82rem;font-family:inherit;background:var(--surface);color:var(--text-primary);outline:none" name="size[]" onchange="updateCost(this)">
        <option value="Small">Small</option>
        <option value="Medium">Medium</option>
        <option value="Large">Large</option>
        <option value="XL">XL</option>
      </select>
    </td>
    <td class="data-table-cell">
      <input type="number" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.82rem;font-family:inherit;background:var(--surface);color:var(--text-primary);outline:none" name="quantity[]" min="1" oninput="updateCost(this)">
    </td>
    <td class="data-table-cell cost-cell" style="font-weight:600">₱0.00</td>
    <td class="data-table-cell">
      <button class="dash-btn dash-btn-danger dash-btn-sm" onclick="removeManualRow(this)">Remove</button>
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

function computeOrderSummary() {
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
      return { size: row.size, quantity, price_per_unit: cost / quantity, cost };
    });
  } else if (isStandard) {
    const data = JSON.parse(document.getElementById('standardTableData')?.value || '[]');
    items = data.map(row => {
      const quantity = parseInt(row.quantity);
      const unitPrice = selectedService ? selectedService.price : 0;
      const cost = quantity * unitPrice;
      totalItems += quantity;
      shirtTotal += cost;
      return { size: row.size, quantity, price_per_unit: unitPrice, cost };
    });
  }

  const grandTotal = shirtTotal + servicePrice;
  const existing = JSON.parse(sessionStorage.getItem('orderSummaryData') || '{}');
  sessionStorage.setItem('orderSummaryData', JSON.stringify({
    items, totalItems, shirtTotal, servicePrice, grandTotal,
    design: existing.design || null
  }));
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
  computeOrderSummary();
  checkNextButtonCondition();
}

document.addEventListener('DOMContentLoaded', () => {
  setNextButtonState(false);
  updateStandardValidation();
});
</script>
