<?php
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/session_handler.php';
?>
<div class="order-complete-card text-center">
    <div class="emoji-wrapper">🎉</div>
    <h5 class="text-success fw-bold mb-3">Order Complete!</h5>
    <p class="text-muted mb-4">Thank you for placing your order!</p>
    <p class="fw-bold" style="font-size: 1.2rem; color: #0B5CF9;">Order #<span id="completedOrderId">---</span></p>
    <p class="text-muted mb-4">You can track the progress from your dashboard.</p>
    <a href="/dashboards/customer/dashboard.php" class="btn btn-success btn-lg">Back to Dashboard</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var orderId = sessionStorage.getItem('submittedOrderId');
    if (orderId) {
        document.getElementById('completedOrderId').textContent = 'ORD-' + orderId;
    }
    sessionStorage.removeItem('selectedService');
    sessionStorage.removeItem('uploadedDesign');
    sessionStorage.removeItem('orderSummaryData');
    sessionStorage.removeItem('uploadedDesignName');
    sessionStorage.removeItem('uploadedDesignList');
    sessionStorage.removeItem('standardDesignExcel');
    sessionStorage.removeItem('submittedOrderId');
});
</script>


<style>
    .order-complete-card {
    background: #ffffff;
    border-radius: 16px;
    padding: 40px;
    max-width: 600px;
    margin: 40px auto;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
    animation: popIn 0.5s ease;
}

.emoji-wrapper {
    font-size: 4rem;
    margin-bottom: 20px;
    animation: bounceEmoji 1s ease infinite;
}

/* Optional: subtle bounce animation for the emoji */
@keyframes bounceEmoji {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-6px);
    }
}

</style>
