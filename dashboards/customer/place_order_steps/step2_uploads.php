<?php
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/session_handler.php';
?>

<h5 class="mb-3 fw-bold text-center">Step 2: Upload Your Design File</h5>
<p class="text-muted text-center">
    Upload your final design layout. <strong>Only PSD and ZIP files</strong> are accepted for accurate processing.
</p>
<p class="text-info text-center small">
    <i class="fas fa-info-circle"></i> You'll see a progress bar while your file uploads. Once completed, file details will be displayed below.
</p>

<div class="card">
  <div class="card-body">
    <div class="upload-drop-area" id="uploadDropArea">
      <input type="file" class="d-none" id="image" accept=".psd,.zip" onchange="handleFileUpload()">
      <label for="image" class="text-center w-100 mb-0" style="cursor: pointer;">
        <div class="upload-icon mb-3">📁</div>
        <h6 class="upload-text mb-2">Drop your file here or click to browse</h6>
        <p class="text-muted small mb-0">Maximum file size: 500MB<br>Accepted formats: .PSD, .ZIP</p>
      </label>
    </div>

    <div id="uploadProgressContainer" class="mt-3 d-none">
      <p class="mb-1 small">Uploading file... <span id="uploadPercentage">0%</span></p>
      <div class="progress">
        <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
      </div>
    </div>

    <div id="fileInfoContainer" class="d-none mt-4"></div>

    <div id="imagePreviewContainer" class="text-center mt-4 d-none">
      <div class="preview-wrapper">
        <img id="imagePreview" class="img-fluid rounded" alt="Design preview" />
      </div>
      <div id="fileDetails" class="mt-3">
        <p id="fileName" class="mb-1"></p>
        <p id="fileSize" class="mb-1"></p>
        <p id="fileType" class="mb-0"></p>
      </div>
      <button type="button" class="btn btn-outline-danger btn-sm mt-3" onclick="removeUploadedFile()">Remove File</button>
    </div>
  </div>
</div>

<script>
function handleFileUpload() {
  const input = document.getElementById('image');
  const file = input.files[0];
  const ext = file?.name.split('.').pop().toLowerCase();
  const isValid = file && ['psd', 'zip'].includes(ext) && file.size <= 500 * 1024 * 1024;

  disableNextButton();
  sessionStorage.removeItem('uploadedDesign');
  updateOrderData({ design: null });

  if (!isValid) {
    input.value = '';
    return;
  }

  const designData = {
    fileName: file.name,
    fileSize: file.size,
    fileType: ext.toUpperCase(),
    uploadDate: new Date().toISOString()
  };

  updateOrderData({ design: designData });

  document.getElementById('fileInfoContainer').innerHTML = `
    <div class="alert alert-info">
      <p><strong>File:</strong> ${file.name}</p>
      <p><strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
    </div>`;
  document.getElementById('fileInfoContainer').classList.remove('d-none');

  simulateUploadProgress();
}

function simulateUploadProgress() {
  const container = document.getElementById('uploadProgressContainer');
  const bar = document.getElementById('uploadProgressBar');
  const text = document.getElementById('uploadPercentage');

  container.classList.remove('d-none');
  bar.style.width = '0%';
  text.textContent = '0%';

  let progress = 0;
  const interval = setInterval(() => {
    progress += Math.floor(Math.random() * 10) + 5;
    if (progress >= 100) {
      clearInterval(interval);
      progress = 100;
      setTimeout(() => {
        container.classList.add('d-none');
        displayUploadedFile();
        sessionStorage.setItem('uploadedDesign', 'true');
        enableNextButton();
      }, 400);
    }
    bar.style.width = `${progress}%`;
    text.textContent = `${progress}%`;
  }, 150);

}

function displayUploadedFile() {
  const file = document.getElementById('image').files[0];
  const ext = file.name.split('.').pop().toLowerCase();
  const preview = document.getElementById('imagePreview');

  preview.src = ext === 'psd'
    ? 'data:image/svg+xml;base64,...'  // your psd icon
    : 'data:image/svg+xml;base64,...'; // your zip icon

  document.getElementById('fileName').textContent = `Name: ${file.name}`;
  document.getElementById('fileSize').textContent = `Size: ${(file.size / 1024 / 1024).toFixed(2)} MB`;
  document.getElementById('fileType').textContent = `Type: ${ext.toUpperCase()}`;

  document.getElementById('imagePreviewContainer').classList.remove('d-none');
  document.getElementById('fileInfoContainer').classList.add('d-none');
}

function removeUploadedFile() {
  document.getElementById('image').value = '';
  sessionStorage.removeItem('uploadedDesign');
  updateOrderData({ design: null });

  document.getElementById('uploadProgressContainer').classList.add('d-none');
  document.getElementById('fileInfoContainer').classList.add('d-none');
  document.getElementById('imagePreviewContainer').classList.add('d-none');

  disableNextButton();
}

function enableNextButton() {
  const btn = document.getElementById('nextBtn');
  if (btn) {
    btn.disabled = false;
    btn.classList.remove('btn-secondary', 'disabled');
    btn.classList.add('btn-primary');
  }
}

function disableNextButton() {
  const btn = document.getElementById('nextBtn');
  if (btn) {
    btn.disabled = true;
    btn.classList.remove('btn-primary');
    btn.classList.add('btn-secondary', 'disabled');
  }
}

function updateOrderData(data) {
  let current = {};
  try {
    current = JSON.parse(sessionStorage.getItem('orderSummaryData')) || {};
  } catch {}
  sessionStorage.setItem('orderSummaryData', JSON.stringify({ ...current, ...data }));
}

document.addEventListener('DOMContentLoaded', () => {
  sessionStorage.removeItem('uploadedDesign');
  disableNextButton();
});
</script>

<style>
.upload-drop-area {
  border: 3px dashed #ccc;
  border-radius: 16px;
  padding: 50px 20px;
  background-color: #f9f9f9;
  transition: all 0.3s ease;
}
.upload-drop-area:hover {
  border-color: #0B5CF9;
  background-color: #f1f1ff;
}
.upload-icon {
  font-size: 48px;
  color: #0B5CF9;
  transition: transform 0.3s ease;
}
.upload-drop-area:hover .upload-icon {
  transform: scale(1.1);
}
.preview-wrapper {
  max-width: 300px;
  margin: auto;
}
#imagePreview {
  max-height: 200px;
  object-fit: contain;
  border: 2px solid #dee2e6;
  border-radius: 8px;
  padding: 8px;
}
.progress {
  height: 10px;
  border-radius: 5px;
}
.progress-bar {
  background-color: #0B5CF9;
}
#fileDetails {
  background-color: #f8f9fa;
  border-radius: 8px;
  padding: 15px;
  max-width: 300px;
  margin: auto;
  text-align: left;
}
.alert {
  border-radius: 8px;
}
</style>
