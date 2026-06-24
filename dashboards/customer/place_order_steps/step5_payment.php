<?php
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/session_handler.php';
?>

<div style="text-align:center;margin-bottom:24px">
  <h5 style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin-bottom:6px">Step 5: Payment</h5>
  <p style="font-size:0.85rem;color:var(--text-tertiary);margin:0">Complete your payment and upload the proof of payment to proceed.</p>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:800px;margin:0 auto">
  <div class="panel-card" style="padding:24px">
    <div style="text-align:center">
      <div style="margin-bottom:16px">
        <img src="/public/assets/images/gcash-qr.png" alt="GCash QR Code" style="max-width:180px;border-radius:var(--radius-sm);border:1px solid var(--border);padding:8px;background:var(--surface)">
      </div>
      <div style="background:var(--surface-secondary);border-radius:var(--radius-sm);padding:16px;text-align:left;font-size:0.85rem;color:var(--text-secondary);line-height:1.8">
        <p><strong>Account Name:</strong> Sakuragi Tailoring</p>
        <p><strong>GCash Number:</strong> 09912391238</p>
      </div>
    </div>
  </div>

  <div class="panel-card" style="padding:24px">
    <h6 style="font-size:0.9rem;font-weight:700;color:var(--text-primary);margin-bottom:14px">Upload Payment Proof</h6>
    <div style="background:var(--role-accent-soft);border-radius:var(--radius-sm);padding:12px 16px;font-size:0.8rem;color:var(--text-secondary);margin-bottom:16px">
      <p style="font-weight:600;margin-bottom:6px">Instructions:</p>
      <ol style="padding-left:16px;margin:0">
        <li>Complete your payment using the details provided</li>
        <li>Take a screenshot of your payment confirmation</li>
        <li>Upload the screenshot below</li>
      </ol>
    </div>

    <div id="uploadPlaceholder" style="border:2px dashed var(--border);border-radius:var(--radius-sm);padding:28px 16px;text-align:center;cursor:pointer;transition:all 0.3s ease;background:var(--surface-secondary)" onclick="document.getElementById('paymentProof').click()">
      <input type="file" id="paymentProof" accept="image/*" style="display:none" onchange="handlePaymentImageUpload(this)">
      <div style="font-size:2.2rem;color:var(--role-accent);margin-bottom:8px"><i class="fa-solid fa-camera"></i></div>
      <p style="font-size:0.85rem;color:var(--text-primary);margin-bottom:4px">Click to upload payment proof</p>
      <p style="font-size:0.78rem;color:var(--text-tertiary);margin:0">Supported: JPG, PNG (Max: 500MB)</p>
    </div>

    <div id="paymentImagePreview" style="display:none;text-align:center;margin-top:16px">
      <img src="" alt="Payment proof preview" style="max-width:100%;max-height:200px;object-fit:contain;border-radius:var(--radius-sm);border:1px solid var(--border);margin-bottom:12px">
      <br>
      <button class="dash-btn dash-btn-danger dash-btn-sm" onclick="removePaymentImage()">Remove Image</button>
    </div>

    <div style="margin-top:16px">
      <label for="referenceNumber" style="display:block;font-size:0.8rem;font-weight:600;color:var(--text-secondary);margin-bottom:4px">Reference Number</label>
      <input type="text" id="referenceNumber" placeholder="Enter payment reference number" oninput="setupStep5()" style="width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:var(--radius-sm);font-size:0.85rem;font-family:inherit;background:var(--surface);color:var(--text-primary);outline:none;transition:border-color 0.2s;margin-bottom:6px">
      <p style="font-size:0.75rem;color:var(--text-tertiary);margin:0">Enter the reference number from your GCash transaction</p>
    </div>

    <div id="orderSubmissionStatus" style="display:none;margin-top:12px;padding:10px 14px;border-radius:var(--radius-sm);font-size:0.85rem"></div>
  </div>
</div>

<style id="cust-placeorder-step5">
#uploadPlaceholder:hover { border-color:var(--role-accent); background:var(--role-accent-soft) }
#referenceNumber:focus { border-color:var(--role-accent); box-shadow:0 0 0 3px var(--role-accent-soft) }
</style>

<script>
let paymentImageData = null;

function handlePaymentImageUpload(input) {
    const file = input.files[0];
    if (!file) return;
    if (!file.type.startsWith('image/')) {
        alert('Please upload a valid image file.');
        input.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        paymentImageData = e.target.result;
        document.getElementById('paymentImagePreview').querySelector('img').src = paymentImageData;
        document.getElementById('paymentImagePreview').style.display = 'block';
        document.getElementById('uploadPlaceholder').style.display = 'none';
    };
    reader.readAsDataURL(file);
    setupStep5();
}

function removePaymentImage() {
    document.getElementById('paymentProof').value = '';
    paymentImageData = null;
    document.getElementById('paymentImagePreview').style.display = 'none';
    document.getElementById('uploadPlaceholder').style.display = 'block';
    setupStep5();
}
</script>
