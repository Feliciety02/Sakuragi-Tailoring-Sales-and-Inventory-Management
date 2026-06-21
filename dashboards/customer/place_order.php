<?php
require_once __DIR__ . '/../../config/session_handler.php';
require_once __DIR__ . '/../../config/constants.php';
require_once '../../app/Middleware/auth_required.php';


if (get_user_role() !== ROLE_CUSTOMER) {
    header('Location: /dashboards/employee/dashboard.php');
    exit();
}
<?php
$pageTitle = 'Place Order';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Place Order — Sakuragi</title>
  <link rel="icon" type="image/png" href="/public/assets/images/sakuragi-logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" />
  <link rel="stylesheet" href="/public/assets/css/dashboard-modern.css" />
</head>
<body>
<div class="dash-layout">
  <?php require_once '../../app/Views/Shared/Sidebars/customer.php'; ?>
  <div class="dash-main">
    <?php require_once '../../app/Views/Shared/topnav.php'; ?>
    <div class="dash-content">
    <!-- Landing Section -->
    <section id="orderLanding" class="order-landing">
        <h2>Welcome to Sakuragi Custom Orders</h2>
        <p>Ready to bring your designs to life? Whether it's embroidery, sublimation, or screen printing — we've got you covered.</p>

        <button class="start-order-btn" onclick="startOrder()">
            <i class="fas fa-rocket"></i>
            <span>Start Your Order</span>
        </button>
    </section>

    <!-- Order Wizard Section -->
    <section id="orderFormWizard" class="d-none">
        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-bar-track"></div>
            <div class="progress-bar-fill" id="progressBar"></div>

            <div class="progress-step" data-step="1">
                <div class="step-circle">1</div>
                <div class="step-label">Service</div>
            </div>
            <div class="progress-step" data-step="2">
                <div class="step-circle">2</div>
                <div class="step-label">Design</div>
            </div>
            <div class="progress-step" data-step="3">
                <div class="step-circle">3</div>
                <div class="step-label">Customization</div>
            </div>
            <div class="progress-step" data-step="4">
                <div class="step-circle">4</div>
                <div class="step-label">Review</div>
            </div>
            <div class="progress-step" data-step="5">
                <div class="step-circle">5</div>
                <div class="step-label">Payment</div>
            </div>
            <div class="progress-step" data-step="6">
                <div class="step-circle">6</div>
                <div class="step-label">Complete</div>
            </div>
        </div>

        <!-- Step Content -->
        <div class="step-box active" id="step1"><?php include 'place_order_steps/step1_services.php'; ?></div>
        <div class="step-box" id="step2"><?php include 'place_order_steps/step2_uploads.php'; ?></div>
        <div class="step-box" id="step3"><?php include 'place_order_steps/step3_customize.php'; ?></div>
        <div class="step-box" id="step4"><?php include 'place_order_steps/step4_summary.php'; ?></div>
        <div class="step-box" id="step5"><?php include 'place_order_steps/step5_payment.php'; ?></div>
        <div class="step-box" id="step6"><?php include 'place_order_steps/step6_success.php'; ?></div>

        <!-- Navigation -->
        <div class="step-navigation">
            <button id="prevBtn" onclick="prevStep()">Back</button>
              <button id="nextBtn" class="btn btn-secondary" onclick="nextStep()" disabled>Next</button>

        </div>
    </section>
</div>
  </div>
</div>

<script>
    function startOrder() {
        document.getElementById('orderLanding').classList.add('d-none');
        document.getElementById('orderFormWizard').classList.remove('d-none');
    }
</script>

<script>
document.getElementById('menuToggle')?.addEventListener('click', function() {
  document.getElementById('sidebar')?.classList.toggle('collapsed');
});
</script>

<!-- Scripts -->
<script src="/public/assets/js/orderStepper.js"></script>
<script src="/public/assets/js/excelPreview.js"></script>


<style>
    /* ========== ORDER LANDING SECTION ========== */
.order-landing {
    padding: 60px 40px;
    text-align: center;
    border-radius: 16px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
    max-width: 850px;
    margin: 0 auto;
    transition: all 0.4s ease;
}

.order-landing h2 {
    font-size: 2rem;
    color: #0B5CF9;
    margin-bottom: 15px;
    font-weight: 700;
}

.order-landing p {
    font-size: 1rem;
    color: #555;
    margin-bottom: 30px;
}

/* ========== START ORDER BUTTON ========== */
.start-order-btn {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, #0B5CF9, #4D8CFF);
    color: white;
    padding: 14px 28px;
    font-size: 1rem;
    border: none;
    border-radius: 50px;
    font-weight: 600;
    box-shadow: 0 4px 14px rgba(11, 92, 249, 0.25);
    cursor: pointer;
    transition: background 0.3s ease, transform 0.2s ease;
}

.start-order-btn:hover {
    transform: translateY(-2px);
    background: linear-gradient(135deg, #246BFD, #0B5CF9);
}

.start-order-btn i {
    font-size: 18px;
}

/* ========== HIDE/SHOW LOGIC ========== */
.d-none {
    display: none !important;
}

/* ========== HIDE CLASS ========== */
.d-none {
    display: none !important;
}

/* ========== ANIMATIONS ========== */
@keyframes fadeIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes popIn {
    0% { transform: scale(0.8); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}

@keyframes slideDown {
    0% { transform: translateY(-20px); opacity: 0; }
    100% { transform: translateY(0); opacity: 1; }
}

/* ========== STEP PROGRESS CONTAINER ========== */
.progress-container {
    margin: 40px auto;
    max-width: 800px;
    position: relative;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 10px;
}

/* ========== LINE TRACK ========== */
.progress-bar-track {   
    position: absolute;
    top: 35%;
    left: 10%;
    right: 10%;
    height: 6px;
    background-color: #dfe6ed;
    z-index: 0;
    border-radius: 2px;
}

.progress-bar-fill {
    position: absolute;
    top: 35%;
    left: 10%;
    right: 10%;
    height: 6px;
    background: linear-gradient(90deg, #0B5CF9, #4D8CFF);
    z-index: 1;
    border-radius: 2px;
    transition: width 0.4s ease;
}

/* ========== INDIVIDUAL STEPS ========== */
.progress-step {
    position: relative;
    z-index: 2;
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100%;
    text-align: center;
}

.step-circle {
    width: 48px;         /* Increased from 32px */
    height: 48px;        /* Increased from 32px */
    font-size: 1.1rem;   /* Larger number */
    border-radius: 50%;
    border: 3px solid #ccc;
    background-color: white;
    color: #999;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    transition: background-color 0.3s ease, transform 0.3s ease;
}

.progress-step.active .step-circle {
    background-color: white;
    color: #0B5CF9;
    border: 2px solid #0B5CF9;


    transform: scale(1.1);
}

.progress-step.completed .step-circle {

    background-color:#0B5CF9;
    color: white;
    border: 2px solid #0B5CF9;
}


.step-label {
    margin-top: 8px;
    font-size: 0.95rem; /* Slightly larger label */
    color: #34495e;
    font-weight: 500;
    transition: all 0.3s ease;
}

/* ========== STEP BOXES (CONTENT) ========== */
.step-box {
    display: none;
    animation: fadeInStep 0.4s ease-in-out;
    margin-top: 30px;
}

.step-box.active {
    display: block;
}

/* ========== BUTTON CONTROLS ========== */
.step-navigation {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    max-width: 800px;
    margin-left: auto;
    margin-right: auto;
}

.step-navigation button {
    background-color: #0B5CF9;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 1rem;
    cursor: pointer;
    font-weight: 500;
    transition: background 0.3s ease;
}

.step-navigation button:disabled {
    background-color: #ccc;
    cursor: not-allowed;
}

.step-navigation button:hover:not(:disabled) {
    background-color: #246BFD;
}

/* ========== ANIMATION ========== */
@keyframes fadeInStep {
    0% { opacity: 0; transform: translateY(20px); }
    100% { opacity: 1; transform: translateY(0); }
}

/* ========== MOBILE ========== */
@media (max-width: 576px) {
    .progress-container {
        flex-direction: column;
        gap: 20px;
    }

    .progress-bar-track, .progress-bar-fill {
        top: auto;
        left: 50%;
        transform: translateX(-50%);
        width: 4px;
        height: 100%;
    }

    .progress-bar-fill {
        transition: height 0.4s ease;
    }

    .progress-step {
        flex-direction: row;
        justify-content: flex-start;
        align-items: center;
        gap: 10px;
    }

    .step-label {
        font-size: 0.85rem;
    }
}


</style>
</body>
</html>
