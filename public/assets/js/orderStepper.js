let currentStep = 1;
const totalSteps = 6;

function updateStepper() {
    const steps = document.querySelectorAll('.progress-step');
    const fillBar = document.querySelector('.progress-bar-fill');
    const stepBoxes = document.querySelectorAll('.step-box');

    steps.forEach((step, index) => {
        const circle = step.querySelector('.step-circle');
        if (index + 1 < currentStep) {
            circle.classList.add('completed');
            circle.classList.remove('active');
            step.classList.add('completed');
            step.classList.remove('active');
        } else if (index + 1 === currentStep) {
            circle.classList.add('active');
            circle.classList.remove('completed');
            step.classList.add('active');
            step.classList.remove('completed');
        } else {
            circle.classList.remove('completed', 'active');
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
            fillBar.style.height = '4px';
        }
    }

    stepBoxes.forEach((box, index) => {
        box.classList.toggle('active', index + 1 === currentStep);
    });

    // Reset button state on step change
    setNextButtonState(false);
    setupStep(currentStep);

    document.getElementById('prevBtn').disabled = currentStep === 1;
    document.getElementById('nextBtn').innerText = currentStep === totalSteps ? 'Finish' : 'Next';
}

function nextStep() {
    if (!validateStep(currentStep)) return;

    if (currentStep < totalSteps) {
        currentStep++;
        updateStepper();
    }
}

function prevStep() {
    if (currentStep > 1) {
        currentStep--;
        updateStepper();
    }
}

// Reusable button toggle
function setNextButtonState(enabled) {
    const nextBtn = document.getElementById('nextBtn');
    if (nextBtn) {
        nextBtn.disabled = !enabled;
        nextBtn.classList.toggle('btn-primary', enabled);
        nextBtn.classList.toggle('btn-secondary', !enabled);
        nextBtn.classList.toggle('disabled', !enabled);
    }
}

// Per-step initialization
function setupStep(step) {
    switch (step) {
        case 1: setupStep1(); break;
        case 2: setupStep2(); break;
        case 3: setupStep3(); break;
        case 4: setupStep4(); break;
        case 5: setupStep5(); break;
        case 6: break;
    }
}

// Validation for each step before proceeding
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

// -------- STEP 1: SERVICE --------
function setupStep1() {
    const selected = sessionStorage.getItem('selectedService');
    setNextButtonState(!!selected);
}
function validateStep1() {
    return !!sessionStorage.getItem('selectedService');
}

// -------- STEP 2: UPLOAD --------
function setupStep2() {
  const uploaded = sessionStorage.getItem('uploadedDesign');
  setNextButtonState(!!uploaded); // this must be called after DOM updates
}

function validateStep2() {
  return !!sessionStorage.getItem('uploadedDesign');
}


// -------- STEP 3: CUSTOMIZE --------

// Setup logic when Step 3 is activated
function setupStep3() {
    const isCustom = document.getElementById('customizable')?.checked;
    const isStandard = document.getElementById('standard')?.checked;

    if (isCustom) {
        const hasCustomData = document.getElementById('customizableTableData')?.value.length > 0;
        setNextButtonState(hasCustomData);
    } else if (isStandard) {
        updateStandardValidation(); // will internally call setNextButtonState
    } else {
        setNextButtonState(false);
    }
}

// Validation logic before moving to Step 4
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


// -------- STEP 4: SUMMARY --------
function setupStep4() {
    setNextButtonState(true);
}
function validateStep4() {
    return true;
}

// -------- STEP 5: PAYMENT --------
function setupStep5() {
    const paymentFile = document.getElementById('paymentProof')?.files[0];
    setNextButtonState(!!paymentFile);
}
function validateStep5() {
    const paymentFile = document.getElementById('paymentProof')?.files[0];
    return !!paymentFile;
}

document.addEventListener('DOMContentLoaded', updateStepper);
