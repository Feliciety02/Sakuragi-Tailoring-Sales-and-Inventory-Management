let currentStep = 1;
const totalSteps = 6;

function updateStepper() {
    const steps = document.querySelectorAll('.wizard-step');
    const fillBar = document.getElementById('progressBarFill');
    const stepBoxes = document.querySelectorAll('.step-box');

    steps.forEach((step, index) => {
        const circle = step.querySelector('.wizard-step-circle');
        if (index + 1 < currentStep) {
            step.classList.add('completed');
            step.classList.remove('active');
        } else if (index + 1 === currentStep) {
            step.classList.add('active');
            step.classList.remove('completed');
        } else {
            step.classList.remove('completed', 'active');
        }
    });

    let percentage = ((currentStep - 1) / (totalSteps - 1)) * 100;
    if (currentStep === totalSteps) {
        percentage = ((totalSteps - 2) / (totalSteps - 1)) * 100;
    }

    if (fillBar) {
        if (window.innerWidth <= 576) {
            fillBar.style.height = percentage + '%';
            fillBar.style.width = '4px';
        } else {
            fillBar.style.width = percentage + '%';
            fillBar.style.height = '6px';
        }
    }

    stepBoxes.forEach((box, index) => {
        box.classList.toggle('active', index + 1 === currentStep);
    });

    setupStep(currentStep);

    const footer = document.querySelector('.step-footer');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');

    if (currentStep === totalSteps) {
        if (footer) footer.style.display = 'none';
    } else {
        if (footer) footer.style.display = '';
        setNextButtonState(false);
        if (prevBtn) prevBtn.disabled = currentStep === 1;
        if (nextBtn) nextBtn.innerText = 'Next';
    }
}

function nextStep() {
    if (!validateStep(currentStep)) return;

    if (currentStep === 5) {
        submitOrder();
        return;
    }

    if (currentStep < totalSteps) {
        currentStep++;
        updateStepper();
    }
}

function showStatus(msg, type) {
    const el = document.getElementById('orderSubmissionStatus');
    if (!el) return;
    el.style.display = 'block';
    el.style.background = type === 'error' ? 'var(--color-danger)' : type === 'success' ? 'var(--color-success)' : 'var(--color-info)';
    el.style.color = '#fff';
    el.style.padding = '10px 14px';
    el.style.borderRadius = 'var(--radius-sm)';
    el.style.fontSize = '0.85rem';
    el.style.marginTop = '12px';
    el.textContent = msg;
}

function submitOrder() {
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) { nextBtn.disabled = true; nextBtn.textContent = 'Submitting...'; }
    showStatus('Submitting your order...', 'info');

    let serviceData, orderData;
    try {
        serviceData = JSON.parse(sessionStorage.getItem('selectedService'));
        orderData = JSON.parse(sessionStorage.getItem('orderSummaryData'));
    } catch (e) {
        showStatus('Session data missing. Please start over.', 'error');
        if (nextBtn) { nextBtn.disabled = false; nextBtn.textContent = 'Finish'; }
        return;
    }

    const refNum = document.getElementById('referenceNumber')?.value?.trim() || '';

    const formData = new FormData();
    formData.append('service_id', serviceData?.id || 0);
    formData.append('items', JSON.stringify(orderData?.items || []));
    formData.append('reference_number', refNum);

    const designInput = document.getElementById('image');
    if (designInput && designInput.files && designInput.files.length > 0) {
        formData.append('design_file', designInput.files[0]);
    }

    const excelInput = document.getElementById('excelFile');
    if (excelInput && excelInput.files && excelInput.files.length > 0) {
        formData.append('excel_file', excelInput.files[0]);
    }

    const proofInput = document.getElementById('paymentProof');
    if (proofInput && proofInput.files && proofInput.files.length > 0) {
        formData.append('payment_proof', proofInput.files[0]);
    }

    fetch('/app/Controllers/submit_order.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        console.log('submit response:', data);
        if (data.success) {
            sessionStorage.setItem('submittedOrderId', data.order_id);
            currentStep = 6;
            updateStepper();
        } else {
            showStatus(data.error || 'Order submission failed. Please try again.', 'error');
            if (nextBtn) { nextBtn.disabled = false; nextBtn.textContent = 'Finish'; }
        }
    })
    .catch(err => {
        console.error('Submit error:', err);
        showStatus('Network error. Check console for details.', 'error');
        if (nextBtn) { nextBtn.disabled = false; nextBtn.textContent = 'Finish'; }
    });
}

function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        updateStepper();
    }
}

function setNextButtonState(enabled) {
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        nextBtn.disabled = !enabled;
        if (!enabled) {
            nextBtn.style.opacity = '0.5';
            nextBtn.style.cursor = 'not-allowed';
        } else {
            nextBtn.style.opacity = '1';
            nextBtn.style.cursor = 'pointer';
        }
    }
}

function setupStep(step) {
    switch (step) {
        case 1: setupStep1(); break;
        case 2: setupStep2(); break;
        case 3: setupStep3(); break;
        case 4: setupStep4(); break;
        case 5: setupStep5(); break;
        case 6: setupStep6(); break;
    }
}

function validateStep(step) {
    switch (step) {
        case 1: return validateStep1();
        case 2: return validateStep2();
        case 3: return validateStep3();
        case 4: return validateStep4();
        case 5: return validateStep5();
        default: return true;
    }
}

function setupStep1() {
    const selected = sessionStorage.getItem('selectedService');
    setNextButtonState(!!selected);
}
function validateStep1() {
    return !!sessionStorage.getItem('selectedService');
}

function setupStep2() {
  const uploaded = sessionStorage.getItem('uploadedDesign');
  setNextButtonState(!!uploaded);
}

function validateStep2() {
  return !!sessionStorage.getItem('uploadedDesign');
}

function setupStep3() {
    const isCustom = document.getElementById('customizable')?.checked;
    const isStandard = document.getElementById('standard')?.checked;

    if (isCustom) {
        const hasCustomData = document.getElementById('customizableTableData')?.value.length > 0;
        setNextButtonState(hasCustomData);
    } else if (isStandard) {
        updateStandardValidation();
    } else {
        setNextButtonState(false);
    }
}

function validateStep3() {
    const isCustom = document.getElementById('customizable')?.checked;
    const isStandard = document.getElementById('standard')?.checked;

    if (!isCustom && !isStandard) return false;

    if (isCustom) {
        const customData = document.getElementById('customizableTableData')?.value;
        return customData && customData.length > 0;
    }

    if (isStandard) {
        const rows = document.querySelectorAll('#manualTableBody tr');
        let valid = false;
        rows.forEach(row => {
            const qty = parseInt(row.querySelector('input[name="quantity[]"]').value) || 0;
            if (qty > 0) valid = true;
        });
        return valid;
    }

    return false;
}

function setupStep4() {
    setNextButtonState(true);
    if (typeof displayOrderSummary === 'function') {
        setTimeout(displayOrderSummary, 0);
    }
}
function validateStep4() {
    return true;
}

function setupStep5() {
    const paymentInput = document.getElementById('paymentProof');
    const hasFile = paymentInput && paymentInput.files && paymentInput.files.length > 0;
    const hasReference = document.getElementById('referenceNumber') && document.getElementById('referenceNumber').value.trim().length > 0;
    setNextButtonState(!!(hasFile && hasReference));
}
function validateStep5() {
    const paymentInput = document.getElementById('paymentProof');
    const hasFile = paymentInput && paymentInput.files && paymentInput.files.length > 0;
    const refNum = document.getElementById('referenceNumber');
    const hasReference = refNum && refNum.value.trim().length > 0;
    if (!hasFile) {
        alert('Please upload a payment proof screenshot.');
        return false;
    }
    if (!hasReference) {
        alert('Please enter the payment reference number.');
        return false;
    }
    return true;
}

function setupStep6() {
    const orderId = sessionStorage.getItem('submittedOrderId');
    const el = document.getElementById('completedOrderId');
    if (orderId && el) {
        el.textContent = 'ORD-' + orderId;
    }
    sessionStorage.removeItem('selectedService');
    sessionStorage.removeItem('uploadedDesign');
    sessionStorage.removeItem('orderSummaryData');
    sessionStorage.removeItem('uploadedDesignName');
    sessionStorage.removeItem('uploadedDesignList');
    sessionStorage.removeItem('standardDesignExcel');
    sessionStorage.removeItem('submittedOrderId');
}

document.addEventListener('DOMContentLoaded', updateStepper);
