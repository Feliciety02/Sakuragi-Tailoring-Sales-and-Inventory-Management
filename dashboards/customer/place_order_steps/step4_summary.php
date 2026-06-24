<?php
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/session_handler.php';

$orderData = $_SESSION['order_data'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $sizes = $_POST['size'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    if (empty($sizes) || empty($quantities)) {
        echo json_encode(['error' => 'No sizes or quantities provided']);
        exit();
    }
    $response = ['totalShirts' => 0, 'shirtTotalPrice' => 0, 'breakdown' => []];
    for ($i = 0; $i < count($sizes); $i++) {
        $size = $sizes[$i];
        $quantity = (int) $quantities[$i];
        $pricePerUnit = 200;
        $subtotal = $pricePerUnit * $quantity;
        $response['totalShirts'] += $quantity;
        $response['shirtTotalPrice'] += $subtotal;
        $response['breakdown'][] = ['size' => $size, 'quantity' => $quantity, 'pricePerUnit' => $pricePerUnit, 'subtotal' => $subtotal];
    }
    $response['servicePrice'] = 500;
    $response['grandTotal'] = $response['shirtTotalPrice'] + $response['servicePrice'];
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO orders (branch_id, user_id, service_id, total_price, status, payment_status, design_file_id) VALUES (?, ?, ?, ?, 'Pending', 'Pending', ?)");
        $stmt->execute([1, $_SESSION['user_id'], $_POST['service_id'], $_POST['total_price'], $_POST['design_file_id']]);
        $order_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO order_details (order_id, service_id, quantity, unit_price, size) VALUES (?, ?, ?, ?, ?)");
        foreach ($_POST['items'] as $item) {
            $stmt->execute([$order_id, $_POST['service_id'], $item['quantity'], $item['price_per_unit'], $item['size']]);
        }
        $stmt = $pdo->prepare("INSERT INTO order_workflow (order_id, stage) VALUES (?, 'Designing')");
        $stmt->execute([$order_id]);
        $pdo->commit();
        echo json_encode(['success' => true, 'order_id' => $order_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log($e->getMessage());
        echo json_encode(['error' => 'Failed to create order']);
    }
}
?>

<div style="text-align:center;margin-bottom:24px">
  <h5 style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin-bottom:6px">Step 4: Order Summary</h5>
  <p style="font-size:0.85rem;color:var(--text-tertiary);margin:0">Please confirm all details below before proceeding.</p>
</div>

<div class="panel-card" style="max-width:800px;margin:0 auto;padding:28px">
  <div class="panel-card-body">
    <div style="margin-bottom:20px">
      <h6 style="font-size:0.9rem;font-weight:700;color:var(--role-accent);margin-bottom:10px"><i class="fa-solid fa-concierge-bell"></i> Selected Service</h6>
      <div id="serviceSummary" style="background:var(--surface-secondary);padding:14px 16px;border-radius:var(--radius-sm);font-size:0.85rem;color:var(--text-secondary);line-height:1.7"></div>
    </div>

    <div style="margin-bottom:20px">
      <h6 style="font-size:0.9rem;font-weight:700;color:var(--role-accent);margin-bottom:10px"><i class="fa-solid fa-image"></i> Uploaded Design</h6>
      <div id="designSummary" style="background:var(--surface-secondary);padding:14px 16px;border-radius:var(--radius-sm);font-size:0.85rem;color:var(--text-secondary)"></div>
    </div>

    <div>
      <h6 style="font-size:0.9rem;font-weight:700;color:var(--role-accent);margin-bottom:10px"><i class="fa-solid fa-table-list"></i> Size & Quantity Details</h6>
      <div class="data-table-wrapper" style="overflow-x:auto">
        <table class="data-table" style="min-width:400px">
          <thead>
            <tr>
              <th class="data-table-th">Size</th>
              <th class="data-table-th">Quantity</th>
              <th class="data-table-th">Price per Unit</th>
              <th class="data-table-th">Subtotal</th>
            </tr>
          </thead>
          <tbody id="summaryTableBody"></tbody>
        </table>
      </div>
      <div style="margin-top:12px;background:var(--surface-secondary);border-radius:var(--radius-sm);padding:12px 16px">
        <div style="display:flex;justify-content:space-between;font-size:0.85rem;padding:4px 0;color:var(--text-secondary)">
          <span>Total Items:</span>
          <strong style="color:var(--text-primary)" id="totalItems">0</strong>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:0.85rem;padding:4px 0;color:var(--text-secondary)">
          <span>Shirt Total:</span>
          <strong style="color:var(--text-primary)" id="shirtTotal">₱0.00</strong>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:0.85rem;padding:4px 0;color:var(--text-secondary)">
          <span>Service Price:</span>
          <strong style="color:var(--text-primary)" id="servicePrice">₱0.00</strong>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:0.95rem;padding:8px 0 0;margin-top:4px;border-top:1px solid var(--border);color:var(--text-primary)">
          <span style="font-weight:700">Grand Total:</span>
          <strong style="color:var(--role-accent)" id="grandTotal">₱0.00</strong>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function displayOrderSummary() {
    const orderData = JSON.parse(sessionStorage.getItem('orderSummaryData'));
    const serviceData = JSON.parse(sessionStorage.getItem('selectedService'));
    const designFile = sessionStorage.getItem('uploadedDesign');

    if (!orderData || !serviceData) return;

    document.getElementById('serviceSummary').innerHTML = `
        <p><strong>Service:</strong> ${serviceData.name}</p>
        <p><strong>Category:</strong> ${serviceData.category}</p>
        <p><strong>Service Price:</strong> ₱${serviceData.price.toFixed(2)}</p>
        <p><strong>Description:</strong> ${serviceData.description}</p>
    `;

    const designName = orderData.design?.fileName || designFile || '';
    document.getElementById('designSummary').innerHTML = designName ?
        `<p><strong>File:</strong> ${designName}</p>` :
        '<p style="color:var(--text-tertiary)">No design file uploaded</p>';

    let totalItems = 0;
    let shirtTotal = 0;

    const tableBody = document.getElementById('summaryTableBody');
    tableBody.innerHTML = orderData.items.map(item => {
        const quantity = parseInt(item.quantity);
        const cost = typeof item.cost === 'string' ? parseFloat(item.cost.replace('₱', '')) : parseFloat(item.cost);
        totalItems += quantity;
        shirtTotal += cost;
        return `
            <tr class="data-table-row">
                <td class="data-table-cell">${item.size}</td>
                <td class="data-table-cell">${quantity}</td>
                <td class="data-table-cell">₱${(cost/quantity).toFixed(2)}</td>
                <td class="data-table-cell" style="font-weight:600">₱${cost.toFixed(2)}</td>
            </tr>
        `;
    }).join('');

    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('shirtTotal').textContent = `₱${shirtTotal.toFixed(2)}`;
    document.getElementById('servicePrice').textContent = `₱${serviceData.price.toFixed(2)}`;
    document.getElementById('grandTotal').textContent = `₱${(shirtTotal + parseFloat(serviceData.price)).toFixed(2)}`;
}

document.addEventListener('DOMContentLoaded', () => {
    if (document.querySelector('#step4.active')) {
        displayOrderSummary();
    }
});
</script>
