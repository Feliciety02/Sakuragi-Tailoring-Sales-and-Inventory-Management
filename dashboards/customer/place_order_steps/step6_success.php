<?php
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/session_handler.php';
?>
<div class="panel-card" style="max-width:520px;margin:24px auto;padding:40px;text-align:center">
  <div style="font-size:3.6rem;margin-bottom:16px;animation:bounceEmoji 1s ease infinite">🎉</div>
  <h5 style="font-size:1.15rem;font-weight:700;color:var(--color-success);margin-bottom:8px">Order Complete!</h5>
  <p style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:16px">Thank you for placing your order!</p>
  <p style="font-size:1.1rem;font-weight:700;color:var(--role-accent);margin-bottom:16px">Order #<span id="completedOrderId">---</span></p>
  <p style="font-size:0.82rem;color:var(--text-tertiary);margin-bottom:24px">You can track the progress from your dashboard.</p>
  <a href="/dashboards/customer/dashboard.php" class="dash-btn dash-btn-primary" style="padding:12px 24px;font-size:0.9rem"><i class="fa-solid fa-gauge-high"></i> Back to Dashboard</a>
</div>

<style id="cust-placeorder-step6">
@keyframes bounceEmoji { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
</style>
