<?php
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/session_handler.php';

try {
    $query = 'SELECT service_id, service_name, service_description, service_price, service_category FROM services';
    $stmt = $pdo->query($query);
    $dbServices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('Error fetching services: ' . $e->getMessage());
    $dbServices = [];
}

$icons = [
    'Embroidery' => 'fa-solid fa-feather-pointed',
    'Sublimation' => 'fa-solid fa-palette',
    'Screen Printing' => 'fa-solid fa-print',
    'Alterations' => 'fa-solid fa-scissors',
    'Patches' => 'fa-solid fa-circle-patch',
];
?>

<div style="text-align:center;margin-bottom:24px">
  <h5 style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin-bottom:6px">Step 1: Select a Service</h5>
  <p style="font-size:0.85rem;color:var(--text-tertiary);margin:0">Choose the type of service for your custom order.</p>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;max-width:900px;margin:0 auto">
    <?php foreach ($dbServices as $service): ?>
        <div class="panel-card service-card"
            onclick="selectService('<?= htmlspecialchars($service['service_name']) ?>', this)"
            data-service-id="<?= $service['service_id'] ?>"
            data-price="<?= $service['service_price'] ?>"
            data-category="<?= htmlspecialchars($service['service_category']) ?>"
            style="text-align:center;padding:28px 20px;cursor:pointer;border:2px solid transparent;transition:all 0.25s ease;user-select:none">
            <div style="font-size:2.2rem;color:var(--role-accent);margin-bottom:12px;transition:transform 0.25s ease">
                <i class="<?= $icons[$service['service_category']] ?? 'fa-solid fa-tag' ?>"></i>
            </div>
            <h6 style="font-size:0.95rem;font-weight:700;color:var(--text-primary);margin-bottom:8px"><?= htmlspecialchars($service['service_name']) ?></h6>
            <div style="font-size:1.05rem;font-weight:700;color:var(--role-accent);margin-bottom:8px">₱<?= number_format($service['service_price'], 2) ?></div>
            <p style="font-size:0.78rem;color:var(--text-tertiary);margin:0;line-height:1.5"><?= htmlspecialchars($service['service_description']) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<style id="cust-placeorder-step1">
.service-card:hover { border-color:var(--role-accent-soft); transform:translateY(-2px); box-shadow:0 6px 20px var(--role-accent-glow) }
.service-card:hover i { transform:scale(1.15) }
.service-card.selected { border-color:var(--role-accent); background:var(--role-accent-soft); box-shadow:0 0 0 3px var(--role-accent-soft), 0 4px 16px var(--role-accent-glow); transform:scale(1.02) }
.service-card.selected i { color:var(--role-accent); transform:scale(1.1) }
</style>

<script>
function selectService(serviceName, element) {
    const alreadySelected = element.classList.contains('selected');
    document.querySelectorAll('.service-card').forEach(card => card.classList.remove('selected'));
    const nextBtn = document.getElementById('nextBtn');

    if (alreadySelected) {
        sessionStorage.removeItem('selectedService');
        setNextButtonState(false);
    } else {
        element.classList.add('selected');
        const serviceData = {
            id: parseInt(element.dataset.serviceId),
            name: serviceName,
            price: parseFloat(element.dataset.price),
            category: element.dataset.category,
            description: element.querySelector('p').textContent
        };
        sessionStorage.setItem('selectedService', JSON.stringify(serviceData));
        setNextButtonState(true);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    sessionStorage.removeItem('selectedService');
    setNextButtonState(false);
});
</script>
