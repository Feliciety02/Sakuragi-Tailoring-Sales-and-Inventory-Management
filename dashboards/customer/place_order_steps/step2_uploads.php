<?php
require_once __DIR__ . '/../../../config/db_connect.php';
require_once __DIR__ . '/../../../config/session_handler.php';
?>

<div style="text-align:center;margin-bottom:24px">
  <h5 style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin-bottom:6px">Step 2: Upload Your Design File</h5>
  <p style="font-size:0.85rem;color:var(--text-tertiary);margin:0">Upload your final design layout. <strong>Only PSD and ZIP files</strong> are accepted.</p>
</div>

<div class="panel-card" style="max-width:640px;margin:0 auto;padding:32px">
  <div class="upload-drop-area" id="uploadDropArea">
    <input type="file" style="display:none" id="image" accept=".psd,.zip" onchange="handleFileUpload()">
    <label for="image" style="display:block;text-align:center;cursor:pointer;margin:0">
      <div style="font-size:2.8rem;color:var(--role-accent);margin-bottom:12px;transition:transform 0.3s ease">
        <i class="fa-solid fa-cloud-arrow-up"></i>
      </div>
      <h6 style="font-size:0.9rem;font-weight:600;color:var(--text-primary);margin-bottom:6px">Drop your file here or click to browse</h6>
      <p style="font-size:0.78rem;color:var(--text-tertiary);margin:0">Maximum file size: 500MB &bull; Accepted: .PSD, .ZIP</p>
    </label>
  </div>

  <div id="uploadProgressContainer" style="display:none;margin-top:16px">
    <p style="font-size:0.82rem;color:var(--text-secondary);margin-bottom:6px">Uploading file... <span id="uploadPercentage">0%</span></p>
    <div class="progress-bar-track"><div id="uploadProgressBar" class="progress-bar-fill" style="width:0%"></div></div>
  </div>

  <div id="fileInfoContainer" style="display:none;margin-top:20px"></div>

  <div id="imagePreviewContainer" style="display:none;text-align:center;margin-top:20px">
    <div style="max-width:280px;margin:0 auto">
      <img id="imagePreview" style="max-height:180px;object-fit:contain;border:2px solid var(--border);border-radius:var(--radius-sm);padding:8px;background:var(--surface)" alt="Design preview" />
    </div>
    <div style="margin-top:12px;background:var(--surface-secondary);border-radius:var(--radius-sm);padding:12px 16px;max-width:300px;margin:12px auto 0;text-align:left" id="fileDetails">
      <p id="fileName" style="font-size:0.82rem;color:var(--text-primary);margin-bottom:4px"></p>
      <p id="fileSize" style="font-size:0.78rem;color:var(--text-secondary);margin-bottom:4px"></p>
      <p id="fileType" style="font-size:0.78rem;color:var(--text-secondary);margin:0"></p>
    </div>
    <button type="button" class="dash-btn dash-btn-danger dash-btn-sm" style="margin-top:12px" onclick="removeUploadedFile()">Remove File</button>
  </div>
</div>

<style id="cust-placeorder-step2">
.upload-drop-area { border:2px dashed var(--border); border-radius:var(--radius-lg); padding:40px 20px; background:var(--surface-secondary); transition:all 0.3s ease }
.upload-drop-area:hover { border-color:var(--role-accent); background:var(--role-accent-soft) }
.upload-drop-area:hover i { transform:scale(1.15) }
</style>

<script>
function handleFileUpload() {
  const input = document.getElementById('image');
  const file = input.files[0];
  const ext = file?.name.split('.').pop().toLowerCase();
  const isValid = file && ['psd', 'zip'].includes(ext) && file.size <= 500 * 1024 * 1024;

  setNextButtonState(false);
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
    <div style="background:var(--role-accent-soft);color:var(--role-accent);padding:12px 16px;border-radius:var(--radius-sm);font-size:0.85rem">
      <p style="margin-bottom:4px"><strong>File:</strong> ${file.name}</p>
      <p style="margin:0"><strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB</p>
    </div>`;
  document.getElementById('fileInfoContainer').style.display = 'block';

  simulateUploadProgress();
}

function simulateUploadProgress() {
  const container = document.getElementById('uploadProgressContainer');
  const bar = document.getElementById('uploadProgressBar');
  const text = document.getElementById('uploadPercentage');

  container.style.display = 'block';
  bar.style.width = '0%';
  text.textContent = '0%';

  let progress = 0;
  const interval = setInterval(() => {
    progress += Math.floor(Math.random() * 10) + 5;
    if (progress >= 100) {
      clearInterval(interval);
      progress = 100;
      setTimeout(() => {
        container.style.display = 'none';
        displayUploadedFile();
        sessionStorage.setItem('uploadedDesign', 'true');
        setNextButtonState(true);
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
    ? 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MCIgaGVpZ2h0PSI4MCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNENjI4MjgiIHN0cm9rZS13aWR0aD0iMS41IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxwYXRoIGQ9Ik0xNCAySDYuYTIgMiAwIDAgMC0yIDJ2MTZhMiAyIDAgMCAwIDIgMmgxMmEyIDIgMCAwIDAgMi0yVjh6Ii8+PHBvbHlsaW5lIHBvaW50cz0iMTQgMiAxNCA4IDIwIDgiLz48bGluZSB4MT0iMTYiIHkxPSIxMyIgeDI9IjgiIHkyPSIxMyIvPjxsaW5lIHgxPSIxNiIgeTE9IjE3IiB4Mj0iOCIgeTI9IjE3Ii8+PHBvbHlsaW5lIHBvaW50cz0iMTAgOSA5IDkgOCA5Ii8+PC9zdmc+'
    : 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI4MCIgaGVpZ2h0PSI4MCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiNENjI4MjgiIHN0cm9rZS13aWR0aD0iMS41IiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiPjxwYXRoIGQ9Ik0xNCAySDYuYTIgMiAwIDAgMC0yIDJ2MTZhMiAyIDAgMCAwIDIgMmgxMmEyIDIgMCAwIDAgMi0yVjh6Ii8+PHBvbHlsaW5lIHBvaW50cz0iMTQgMiAxNCA4IDIwIDgiLz48cGF0aCBkPSJNMTIgMTV2LTUiLz48cGF0aCBkPSJNMTUgMTJsLTMgMy0zLTMiLz48L3N2Zz4=';

  document.getElementById('fileName').textContent = `Name: ${file.name}`;
  document.getElementById('fileSize').textContent = `Size: ${(file.size / 1024 / 1024).toFixed(2)} MB`;
  document.getElementById('fileType').textContent = `Type: ${ext.toUpperCase()}`;

  document.getElementById('imagePreviewContainer').style.display = 'block';
  document.getElementById('fileInfoContainer').style.display = 'none';
}

function removeUploadedFile() {
  document.getElementById('image').value = '';
  sessionStorage.removeItem('uploadedDesign');
  updateOrderData({ design: null });

  document.getElementById('uploadProgressContainer').style.display = 'none';
  document.getElementById('fileInfoContainer').style.display = 'none';
  document.getElementById('imagePreviewContainer').style.display = 'none';

  setNextButtonState(false);
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
  setNextButtonState(false);
});
</script>
