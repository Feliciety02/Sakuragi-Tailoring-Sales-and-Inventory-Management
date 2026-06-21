<?php
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/session_handler.php';

$orderData = $_SESSION['order_data'] ?? [];

// Check if the request is an AJAX POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    $sizes = $_POST['size'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if (empty($sizes) || empty($quantities)) {
        echo json_encode(['error' => 'No sizes or quantities provided']);
        exit();
    }

    $response = [
        'totalShirts' => 0,
        'shirtTotalPrice' => 0,
        'breakdown' => [],
    ];

    // Loop through the sizes and quantities
    for ($i = 0; $i < count($sizes); $i++) {
        $size = $sizes[$i];
        $quantity = (int) $quantities[$i];
        $pricePerUnit = 200; // Fixed price for each shirt

        // Calculate the subtotal for the current size
        $subtotal = $pricePerUnit * $quantity;

        // Add to the total shirts and total price
        $response['totalShirts'] += $quantity;
        $response['shirtTotalPrice'] += $subtotal;

        // Add to the breakdown
        $response['breakdown'][] = [
            'size' => $size,
            'quantity' => $quantity,
            'pricePerUnit' => $pricePerUnit,
            'subtotal' => $subtotal,
        ];
    }

    // Fetch the service price (e.g., Embroidery)
    $servicePrice = 500;
    $response['servicePrice'] = $servicePrice;

    // Calculate the grand total
    $response['grandTotal'] = $response['shirtTotalPrice'] + $servicePrice;

    // Return the response as JSON
    echo json_encode($response);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order'])) {
    try {
        $pdo->beginTransaction();

        // Insert into orders table
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                branch_id, 
                user_id, 
                service_id, 
                total_price, 
                status, 
                payment_status,
                design_file_id
            ) VALUES (?, ?, ?, ?, 'Pending', 'Pending', ?)
        ");

        $stmt->execute([
            1, // Default branch_id
            $_SESSION['user_id'],
            $_POST['service_id'],
            $_POST['total_price'],
            $_POST['design_file_id'],
        ]);

        $order_id = $pdo->lastInsertId();

        // Insert order details
        $stmt = $pdo->prepare("
            INSERT INTO order_details (
                order_id, 
                service_id, 
                quantity, 
                unit_price, 
                size
            ) VALUES (?, ?, ?, ?, ?)
        ");

        foreach ($_POST['items'] as $item) {
            $stmt->execute([
                $order_id,
                $_POST['service_id'],
                $item['quantity'],
                $item['price_per_unit'],
                $item['size'],
            ]);
        }

        // Create workflow entry
        $stmt = $pdo->prepare("
            INSERT INTO order_workflow (
                order_id, 
                stage
            ) VALUES (?, 'Designing')
        ");
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
<h5 class="mb-3 fw-bold text-center">Step 4: Order Summary</h5>
<p class="text-muted text-center mb-4">
    Here's a breakdown of your order. Please confirm that all details below are correct before proceeding.
</p>

<div class="order-summary-card">
    <div class="summary-header text-center">
        <h4>Order Summary</h4>
        <p class="summary-subtext">Review your complete order details below</p>
    </div>

    <!-- Service Details -->
    <div class="service-details mb-4">
        <h5 class="text-primary">Selected Service</h5>
        <div class="service-info" id="serviceSummary"></div>
    </div>

    <!-- Design Upload -->
    <div class="design-details mb-4">
        <h5 class="text-primary">Uploaded Design</h5>
        <div class="design-info" id="designSummary"></div>
    </div>

    <!-- Size and Quantity Breakdown -->
    <div class="size-details">
        <h5 class="text-primary">Size & Quantity Details</h5>
        <div class="summary-table-wrapper">
            <table class="summary-table">
                <thead>
                    <tr>
                        <th>Size</th>
                        <th>Quantity</th>
                        <th>Price per Unit</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody id="summaryTableBody"></tbody>
                <tfoot>
                    <tr>
                        <td colspan="2" class="text-end fw-bold">Total Items:</td>
                        <td colspan="2" class="fw-bold" id="totalItems">0</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="text-end fw-bold">Shirt Total:</td>
                        <td colspan="2" class="fw-bold" id="shirtTotal">₱0.00</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="text-end fw-bold">Service Price:</td>
                        <td colspan="2" class="fw-bold" id="servicePrice">₱0.00</td>
                    </tr>
                    <tr class="table-primary">
                        <td colspan="2" class="text-end fw-bold">Grand Total:</td>
                        <td colspan="2" class="fw-bold" id="grandTotal">₱0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div>

<style>
.order-summary-card {
    background: #fff;
    border-radius: 16px;
    padding: 32px;
    box-shadow: 0 6px 24px rgba(0,0,0,0.06);
    max-width: 800px;
    margin: 0 auto;
    animation: fadeInStep 0.4s ease;
}

.summary-header h4 {
    color: #0B5CF9;
    font-size: 1.8rem;
    margin-bottom: 8px;
    font-weight: 700;
}

.summary-subtext {
    font-size: 1.1rem;
    color: #555;
    margin-bottom: 28px;
}

.summary-details {
    margin-bottom: 24px;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    font-size: 1.1rem;
    border-bottom: 1px dashed #d4dce5;
}

.summary-row span {
    color: #333;
    font-weight: 500;
}

.summary-row strong {
    color: #0B5CF9;
    font-weight: 700;
}

.summary-table-wrapper {
    margin-top: 28px;
}

.summary-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.summary-table th,
.summary-table td {
    padding: 12px;
    border: 1px solid #e0e6ed;
}

.summary-table th {
    background: #0B5CF9;
    color: white;
    font-weight: 600;
}

.summary-table tfoot tr {
    background-color: #f8f9fa;
}

.summary-table tfoot tr.table-primary {
    background-color: #e7f1ff;
}

.text-end {
    text-align: right;
}

.fw-bold {
    font-weight: 600;
}

.service-details,
.design-details,
.size-details {
    margin-bottom: 24px;
}

.text-primary {
    color: #0B5CF9;
    font-weight: 600;
    margin-bottom: 12px;
}

.service-info,
.design-info {
    background: #f9f9f9;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 16px;
}

.table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 16px;
}

.table th,
.table td {
    padding: 12px;
    border: 1px solid #e0e6ed;
    text-align: left;
}

.table th {
    background: #0B5CF9;
    color: white;
    font-weight: 600;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 1.1rem;
}

.summary-row span {
    color: #333;
    font-weight: 500;
}

.summary-row strong {
    color: #0B5CF9;
    font-weight: 700;
}
</style>
<script>
function displayOrderSummary() {
    const orderData = JSON.parse(sessionStorage.getItem('orderSummaryData'));
    const serviceData = JSON.parse(sessionStorage.getItem('selectedService'));
    const designFile = sessionStorage.getItem('uploadedDesign');
    
    if (!orderData || !serviceData) {
        console.error('Missing order data');
        return;
    }

    // Update service details with actual selected service data
    document.getElementById('serviceSummary').innerHTML = `
        <p><strong>Service:</strong> ${serviceData.name}</p>
        <p><strong>Category:</strong> ${serviceData.category}</p>
        <p><strong>Service Price:</strong> ₱${serviceData.price.toFixed(2)}</p>
        <p><strong>Description:</strong> ${serviceData.description}</p>
    `;

    // Show only file name for uploaded design
    document.getElementById('designSummary').innerHTML = designFile ? 
        `<p><strong>File:</strong> ${designFile}</p>` : 
        '<p class="text-muted">No design file uploaded</p>';

    let totalItems = 0;
    let shirtTotal = 0;

    // Update table body
    const tableBody = document.getElementById('summaryTableBody');
    tableBody.innerHTML = orderData.items.map(item => {
        const quantity = parseInt(item.quantity);
        const cost = parseFloat(item.cost.replace('₱', ''));
        totalItems += quantity;
        shirtTotal += cost;

        return `
            <tr>
                <td>${item.size}</td>
                <td>${quantity}</td>
                <td>₱${(cost/quantity).toFixed(2)}</td>
                <td>₱${cost.toFixed(2)}</td>
            </tr>
        `;
    }).join('');

    // Update summary totals
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
