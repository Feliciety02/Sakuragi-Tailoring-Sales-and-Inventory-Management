<?php
require_once __DIR__ . '/../../app/bootstrap.php';
require_once APP_ROOT . '/app/Middleware/auth_required.php';

if (get_user_role() !== ROLE_CUSTOMER) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}
$pageTitle = 'Place Order';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Place Order — Sakuragi</title>
  <link rel="icon" type="image/svg+xml" href="/public/assets/images/sakuragi-logo.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="apple-touch-icon" href="/public/assets/images/sakuragi-logo.png" />
  <link rel="manifest" href="/public/manifest.json" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
  <link rel="stylesheet" href="/public/assets/css/components.css" />
  <style id="cust-placeorder-styles">
    .wizard-stepper { display:flex; justify-content:space-between; align-items:center; position:relative; padding:20px 10px; margin:0 auto; max-width:800px }
    .wizard-stepper-track { position:absolute; top:35%; left:10%; right:10%; height:6px; background:var(--surface-secondary); z-index:0; border-radius:3px; overflow:hidden }
    .wizard-stepper-fill { height:100%; border-radius:3px; background:var(--role-accent); transition:width 0.4s ease; width:0% }
    .wizard-step { position:relative; z-index:2; display:flex; flex-direction:column; align-items:center; width:100%; text-align:center }
    .wizard-step-circle { width:44px; height:44px; font-size:0.95rem; border-radius:50%; border:3px solid var(--border); background:var(--surface); color:var(--text-tertiary); display:flex; align-items:center; justify-content:center; font-weight:700; transition:all 0.3s; flex-shrink:0 }
    .wizard-step.active .wizard-step-circle { background:var(--surface); color:var(--role-accent); border-color:var(--role-accent); transform:scale(1.1); box-shadow:0 0 0 4px var(--role-accent-soft) }
    .wizard-step.completed .wizard-step-circle { background:var(--role-accent); color:#fff; border-color:var(--role-accent) }
    .wizard-step-label { margin-top:8px; font-size:0.82rem; color:var(--text-secondary); font-weight:500; transition:color 0.3s }
    .wizard-step.active .wizard-step-label { color:var(--role-accent); font-weight:600 }
    .wizard-step.completed .wizard-step-label { color:var(--text-primary) }
    .step-box { display:none; animation:fadeInStep 0.4s ease-in-out }
    .step-box.active { display:block }
    .step-footer { display:flex; justify-content:space-between; margin-top:30px; max-width:800px; margin-left:auto; margin-right:auto }
    @keyframes fadeInStep { 0%{opacity:0;transform:translateY(20px)} 100%{opacity:1;transform:translateY(0)} }
    @media (max-width:600px) {
      .wizard-stepper { padding:16px 4px }
      .wizard-step-circle { width:36px; height:36px; font-size:0.8rem }
      .wizard-step-label { font-size:0.7rem; margin-top:4px }
    }
  </style>
</head>
<body data-role="customer">
<div class="dash-layout">
  <?php render_role_sidebar($pdo); ?>
  <div class="dash-main">
<?php
ob_start(); ?>
<section id="orderLanding" class="panel-card" style="padding:60px 40px;text-align:center;max-width:850px;margin:0 auto">
  <div style="font-size:3rem;color:var(--role-accent);margin-bottom:16px"><i class="fas fa-palette"></i></div>
  <h2 style="font-size:1.6rem;color:var(--text-primary);margin-bottom:12px;font-weight:700">Welcome to Sakuragi Custom Orders</h2>
  <p style="font-size:0.95rem;color:var(--text-secondary);margin-bottom:28px;max-width:520px;margin-left:auto;margin-right:auto">Ready to bring your designs to life? Whether it's embroidery, sublimation, or screen printing — we've got you covered.</p>
  <button onclick="startOrder()" class="dash-btn dash-btn-primary" style="padding:14px 28px;font-size:1rem;box-shadow:0 4px 14px var(--role-accent-glow)"><i class="fas fa-rocket"></i> Start Your Order</button>
</section>

<section id="orderFormWizard" style="display:none">
  <div class="wizard-stepper">
    <div class="wizard-stepper-track"><div id="progressBarFill" class="wizard-stepper-fill"></div></div>
    <?php foreach (['Service','Design','Customization','Review','Payment','Complete'] as $i => $label): $n = $i + 1; ?>
    <div class="wizard-step" data-step="<?= $n ?>">
      <div class="wizard-step-circle"><?= $n ?></div>
      <div class="wizard-step-label"><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="step-box active" id="step1"><?php include 'place_order_steps/step1_services.php'; ?></div>
  <div class="step-box" id="step2"><?php include 'place_order_steps/step2_uploads.php'; ?></div>
  <div class="step-box" id="step3"><?php include 'place_order_steps/step3_customize.php'; ?></div>
  <div class="step-box" id="step4"><?php include 'place_order_steps/step4_summary.php'; ?></div>
  <div class="step-box" id="step5"><?php include 'place_order_steps/step5_payment.php'; ?></div>
  <div class="step-box" id="step6"><?php include 'place_order_steps/step6_success.php'; ?></div>

  <div class="step-footer">
    <button id="prevBtn" onclick="prevStep()" class="dash-btn dash-btn-outline">Back</button>
    <button id="nextBtn" onclick="nextStep()" disabled class="dash-btn dash-btn-primary">Next</button>
  </div>
</section>
<?php
$workspace = ob_get_clean();
echo renderDashboardShell(renderPageHeader($pageTitle, 'Start a new custom order.'), '', $workspace);
?>
<script>
function startOrder() { document.getElementById('orderLanding').style.display = 'none'; document.getElementById('orderFormWizard').style.display = 'block'; }
document.getElementById('menuToggle')?.addEventListener('click', function() { document.getElementById('sidebar')?.classList.toggle('collapsed'); });
</script>
<script src="/public/assets/js/orderStepper.js"></script>
    </div>
  </div>
</div>
</body>
</html>
