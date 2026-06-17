<?php
require_once __DIR__ . '../../../../config/db_connect.php';
require_once __DIR__ . '../../../../config/session_handler.php';
?>

<h5 class="mb-3 fw-bold text-center">Step 5: Payment</h5>
<p class="text-muted text-center mb-4">Please complete your payment and upload the proof of payment to proceed.</p>

<div class="row g-4">
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <div id="paymentDetails" class="mt-4">
                    <div class="payment-info">
                        <div class="qr-code text-center mb-3">
                            <img src="../../../public/assets/images/gcash-qr.png" 
                                 alt="GCash QR Code" class="img-fluid gcash-qr-sm">
                        </div>
                        <div class="account-details">
                            <p class="mb-2"><strong>Account Name:</strong> Sakuragi Tailoring</p>
                            <p class="mb-2"><strong>GCash Number:</strong> 09912391238</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="card-title mb-4">Upload Payment Proof</h6>
                <div class="upload-instructions alert alert-info mb-4">
                    <p class="mb-2"><strong>Instructions:</strong></p>
                    <ol class="mb-0">
                        <li>Complete your payment using the details provided</li>
                        <li>Take a screenshot of your payment confirmation</li>
                        <li>Upload the screenshot below</li>
                        <li>Click "Complete Order" to submit your order</li>
                    </ol>
                </div>
                <div class="payment-upload-area p-4 text-center" id="uploadPlaceholder">
                    <input type="file" id="paymentProof" class="d-none" accept="image/*" onchange="handlePaymentImageUpload(this)">
                    <label for="paymentProof" class="mb-0" style="cursor: pointer;">
                        <div class="mb-3">📸</div>
                        <p class="mb-0">Click to upload payment proof</p>
                        <small class="text-muted d-block">Supported formats: JPG, PNG (Max: 500MB)</small>
                    </label>
                </div>
                <div id="paymentImagePreview" class="text-center mt-3 d-none">
                    <img src="" alt="Payment proof preview" class="img-fluid mb-2">
                    <button class="btn btn-danger btn-sm mt-2" onclick="removePaymentImage()">Remove Image</button>
                </div>
                <div class="mt-4">
                    <div class="form-group mb-3">
                        <label for="referenceNumber" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="referenceNumber" placeholder="Enter payment reference number">
                        <small class="text-muted">Enter the reference number from your GCash transaction</small>
                    </div>
                    <div id="orderSubmissionStatus" class="alert d-none mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.payment-container {
    max-width: 850px;
    margin: 0 auto;
}
.payment-upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}
.payment-upload-area:hover {
    border-color: #0B5CF9;
    background-color: #f8f9fa;
}
.gcash-qr-sm {
    max-width: 200px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    padding: 8px;
    background: white;
}
.payment-info {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 1.5rem;
}
.account-details p {
    font-size: 0.95rem;
}
#paymentImagePreview img {
    max-width: 100%;
    max-height: 300px;
    object-fit: contain;
}
.upload-instructions ol {
    padding-left: 1rem;
}
.upload-instructions li {
    margin-bottom: 0.5rem;
}
.upload-instructions li:last-child {
    margin-bottom: 0;
}</style>