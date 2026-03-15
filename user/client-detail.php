<?php
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/settings-loader.php';
require_once '../core/client-status.php';
require_once '../core/helpers/asset.php';
require_once '../core/helpers/product-flow.php';

Auth::check();
$user_id = $_SESSION['user']['id'] ?? null;

/* ===============================
   VALIDASI PARAMETER
================================ */
$client_id = $_GET['id'] ?? null;
if (!$client_id) {
    die('Client tidak ditemukan');
}

/* ===============================
   AMBIL CLIENT
================================ */
$client = DB::fetch(
    "SELECT * FROM clients WHERE id = ? AND user_id = ?",
    [$client_id, $user_id]
);

if (!$client) {
    die('Client tidak valid');
}

$status = resolveClientServiceStatus($client);

// ===============================
// STATUS UI HELPER
// ===============================
$isActive = $status['service_active'];

$statusColor = $isActive ? '#16a34a' : '#dc2626'; // green / red
$statusText  = $isActive ? 'AKTIF' : 'NON-AKTIF';

$trialText = null;
if (!empty($status['trial_ended_at'])) {
    $trialDate = date('d M Y', strtotime($status['trial_ended_at']));
    $trialText = $status['trial_active']
        ? "Masa trial aktif (hingga {$trialDate})"
        : "Masa trial berakhir pada {$trialDate}";
}

/* ===============================
   AMBIL PRODUK
================================ */
$product = DB::fetch(
    "SELECT * FROM products WHERE id = ?",
    [$client['product_id']]
);

if (!$product) {
    die('Produk tidak valid');
}

/* ===============================
   RESOLVE FLOW
================================ */

$flow = resolveFlow($product);

/* ===============================
   HITUNG MASA AKTIF
================================ */

$daysLeft  = null;
$timerText = '-';

// tentukan tanggal expired efektif
$expiredAt = null;

if (!empty($client['expired_at'])) {
    $expiredAt = $client['expired_at'];
} elseif (!empty($meta['trial']['ended_at'])) {
    $expiredAt = $meta['trial']['ended_at'];
}

if ($expiredAt) {
    $now     = new DateTime();
    $expired = new DateTime($expiredAt);

    $interval = $now->diff($expired);
    $daysLeft = (int)$interval->format('%r%a');

    if ($daysLeft >= 0) {
        $timerText = $daysLeft . ' hari tersisa';
    } else {
        $timerText = 'Kadaluarsa';
    }
}

/* ===============================
   AUTO SET EXPIRED
================================ */
if (
    $client['status'] === 'active'
    && $expiredAt
    && $daysLeft !== null
    && $daysLeft < 0
) {
    DB::execute(
        "UPDATE clients SET status = 'expired' WHERE id = ?",
        [$client['id']]
    );
    $client['status'] = 'expired';
}

/* ===============================
   META DATA
================================ */
$meta = [];
if (!empty($client['meta'])) {
    $meta = json_decode($client['meta'], true);
}

$credentials = [];
if (!empty($client['credentials'])) {
    $credentials = json_decode($client['credentials'], true);
}

  include 'header.php';
  include 'sidebar.php';
?>

<main class="user-content">

<?php if (Auth::isAdmin() && $flow === 'DESIGN_ORDER'): ?>
<section class="card">
    <h3>Upload Hasil Desain</h3>

    <form method="POST"
          action="upload-design.php"
          enctype="multipart/form-data">

        <input type="hidden" name="client_id" value="<?= $client['id'] ?>">

        <label>Upload File Desain (PNG/JPG/ZIP/PSD)</label>
        <input type="file" name="design_files[]" multiple required>

        <button class="btn primary">⬆️ Upload Hasil</button>
    </form>
</section>
<?php endif; ?>

<h1>Detail Client: <?= htmlspecialchars($client['name']) ?></h1>

<section class="card">
    <h3>Status Layanan</h3>

    <p>
        <strong>Status:</strong>
        <span style="color: <?= $statusColor ?>; font-weight:600;">
            <?= strtoupper($client['status']) ?>
        </span>
    </p>

    <?php if ($client['status'] === 'active'): ?>
        <p><strong>Berlaku hingga:</strong> <?= htmlspecialchars($client['expired_at'] ?? '-') ?></p>
        <p><strong>Sisa waktu:</strong> <?= $timerText ?></p>
    <?php endif; ?>

<?php if ($trialText): ?>
    <p style="margin:6px 0;">
        <strong><?= $trialText ?></strong>
    </p>
<?php endif; ?>

<p style="margin-top:6px;">
    <strong>Status API:</strong>
    <span style="color: <?= $statusColor ?>; font-weight:600;">
        <?= $statusText ?>
    </span>
</p>

    <!-- ===============================
         TOMBOL PEMBAYARAN
    ================================ -->
    <?php if ($client['status'] === 'pending'): ?>
        <form method="POST" action="renew.php">
            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
            <button class="btn primary">Bayar & Aktifkan Layanan</button>
        </form>

    <?php elseif (in_array($client['status'], ['active', 'expired'])): ?>
        <form method="POST" action="renew.php">
            <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
            <button class="btn primary">Perpanjang Layanan</button>
        </form>
    <?php endif; ?>
</section>

<!-- ===============================
     INTEGRATION / EMBED
================================ -->
<?php if ($flow === 'DESIGN_ORDER'): ?>

<section class="card">
    <h3>Detail Pesanan Desain</h3>

    <table style="width:100%;border-collapse:collapse;font-size:0.92rem;">
        <tr>
            <td><strong>Nama Pemesan</strong></td>
            <td><?= htmlspecialchars($credentials['customer_name'] ?? '-') ?></td>
        </tr>
        <tr>
            <td><strong>Email</strong></td>
            <td><?= htmlspecialchars($credentials['customer_email'] ?? '-') ?></td>
        </tr>
        <tr>
            <td><strong>WhatsApp</strong></td>
            <td><?= htmlspecialchars($credentials['customer_wa'] ?? '-') ?></td>
        </tr>
        <tr>
            <td><strong>Jenis Desain</strong></td>
            <td><?= htmlspecialchars($credentials['design_type'] ?? '-') ?></td>
        </tr>
        <tr>
            <td><strong>Ukuran / Format</strong></td>
            <td><?= htmlspecialchars($credentials['size'] ?? '-') ?></td>
        </tr>
        <tr>
            <td><strong>Deadline</strong></td>
            <td><?= htmlspecialchars($credentials['deadline'] ?? '-') ?></td>
        </tr>
    </table>
</section>

<section class="card">
    <h3>Deskripsi Kebutuhan</h3>

    <p style="white-space:pre-line;">
        <?= htmlspecialchars($credentials['description'] ?? '-') ?>
    </p>
</section>

<?php if (!empty($credentials['note'])): ?>
<section class="card">
    <h3>Catatan Tambahan</h3>
    <p><?= htmlspecialchars($credentials['note']) ?></p>
</section>
<?php endif; ?>

<?php if (!empty($credentials['reference'])): ?>
<section class="card">
    <h3>Referensi URL</h3>
    <a href="<?= htmlspecialchars($credentials['reference']) ?>"
       target="_blank"
       class="btn">
        🔗 Buka Referensi
    </a>
</section>
<?php endif; ?>

<?php if (!empty($credentials['reference_file'])): ?>
<section class="card">
    <h3>File Referensi</h3>

    <a href="<?= asset_url($credentials['reference_file']) ?>"
       target="_blank"
       class="btn">
        📎 Download File
    </a>
</section>
<?php endif; ?>

<?php
$designResult = [];
if (!empty($client['design_result'])) {
    $designResult = json_decode($client['design_result'], true);
}
?>

<?php if (!empty($designResult['preview'])): ?>
<section class="card">
    <h3>Preview Hasil Desain</h3>
    <div class="preview-grid">
        <?php foreach ($designResult['preview'] as $img): ?>
            <div class="preview-box">
                <img src="<?= uploads_url($img) ?>"
                     draggable="false"
                     oncontextmenu="return false">
            </div>
        <?php endforeach; ?>
    </div>

    <small class="muted">
        * Preview kualitas rendah. File final diberikan setelah pembayaran/approval.
    </small>
</section>
<?php endif; ?>

<?php elseif ($flow === 'CHATBOT_WEB'): ?>
<section class="card">
    <h3>Widget Chatbot Website</h3>
    <p>Salin kode berikut dan pasang di website Anda:</p>

<div class="code-container">
    <div class="code-header">
        <span>Script Widget</span>
        <button class="copy-btn" onclick="copyToClipboard()">
            <span id="copy-text">Copy</span>
        </button>
    </div>
    <textarea id="scriptArea" rows="4" readonly style="width:100%; font-family:monospace;">
<script
  src="<?= setting('integration-chatbot-widget_script_url') ?>"
  data-api-key="<?= htmlspecialchars($client['api_key']) ?>">
</script>
    </textarea>
</div>
</section>

<?php elseif ($flow === 'AUTOMATION_NOTIFICATION'): ?>
<?php
$config = json_decode($client['credentials'], true) ?? [];

$source = $config['source'] ?? [];
$file   = $config['file'] ?? [];
$target = $config['target'] ?? [];

$isSystem = ($client['provider'] === 'system');
$webhookUrl = setting('integration-automation-webhook_url');
?>

<section class="card">
  <h3>Automation Notification</h3>

  <p>
    <strong>Mode Produk:</strong>
    <?= $isSystem ? 'SYSTEM MANAGED' : 'CLIENT MANAGED' ?>
  </p>

  <p>
    <strong>Channel:</strong>
    <?= strtoupper($target['channel'] ?? '-') ?>
  </p>
</section>

<section class="card">
  <h3>🚀 Integrasi Google Sheets Automation</h3>

  <!-- STEP FLOW -->
  <div style="display:flex;gap:10px;margin-bottom:25px;font-size:0.85em;flex-wrap:wrap;">
    <div style="flex:1;min-width:200px;background:#f8fafc;padding:15px;border-radius:10px;border:1px solid #e2e8f0;text-align:center;">
      <strong style="color:#4f46e5;">1. Ambil File</strong><br>
      Klik tombol <strong>"Buka Master Sheet"</strong> lalu pilih <strong>"Buat Salinan"</strong>.
    </div>
    <div style="flex:1;min-width:200px;background:#f8fafc;padding:15px;border-radius:10px;border:1px solid #e2e8f0;text-align:center;">
      <strong style="color:#4f46e5;">2. Aktivasi</strong><br>
      Di Sheet baru, klik menu <strong>🚀 SYSTEM → 🔑 Aktivasi</strong>.
    </div>
    <div style="flex:1;min-width:200px;background:#f8fafc;padding:15px;border-radius:10px;border:1px solid #e2e8f0;text-align:center;">
      <strong style="color:#4f46e5;">3. Hubungkan</strong><br>
      Tempelkan <strong>API Key</strong> dari dashboard ini, lalu klik OK.
    </div>
  </div>

  <!-- API KEY BOX -->
  <div style="background:#f1f5f9;padding:20px;border-radius:12px;margin-bottom:25px;border:2px dashed #cbd5e1;">
    <label style="display:block;margin-bottom:10px;font-weight:bold;color:#1e293b;">
      🔑 API Key Anda (salin sebelum membuka Sheet):
    </label>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <input
        type="text"
        id="apiKeyInput"
        value="<?= htmlspecialchars($client['api_key']) ?>"
        readonly
        style="flex:1;min-width:240px;padding:12px;background:#fff;border:1px solid #94a3b8;border-radius:8px;font-family:monospace;font-size:1.05em;color:#1e293b;"
      >
      <button
        onclick="copyKey()"
        id="btnCopy"
        style="padding:0 25px;background:#1e293b;color:#fff;border:none;border-radius:8px;cursor:pointer;font-weight:bold;transition:0.3s;"
      >
        COPY
      </button>
    </div>
  </div>

  <!-- MASTER SHEET BUTTON -->
  <div style="text-align:center;margin-bottom:30px;">
    <?php
      $masterSheetId = setting('integration-automation-master_sheet_id');
    ?>

      <a href="https://docs.google.com/spreadsheets/d/<?= htmlspecialchars($masterSheetId) ?>/copy"
      target="_blank"
      style="display:inline-block;padding:16px 40px;background:#4f46e5;color:#fff;text-decoration:none;border-radius:12px;font-weight:bold;font-size:1.05em;box-shadow:0 4px 15px rgba(79,70,229,0.3);"
      >
         📂 BUAT SALINAN MASTER SHEET
    </a>
  </div>

  <!-- HOW TO -->
  <div style="background:#fff;border:1px solid #e2e8f0;padding:20px;border-radius:12px;">
    <h4 style="margin-top:0;color:#1e293b;border-bottom:1px solid #f1f5f9;padding-bottom:10px;">
      📖 Cara Penggunaan & Otorisasi
    </h4>
    <p style="font-size:0.95em;color:#475569;line-height:1.7;">
      1. Klik <strong>"Buat Salinan" (Make a copy)</strong> saat halaman Google Sheets terbuka.<br>
      2. Setelah Sheet terbuka, tunggu ±5 detik. Jika menu <strong>🚀 SYSTEM</strong> belum muncul, silakan <strong>Refresh (F5)</strong>.<br>
      3. Pilih menu <strong>🚀 SYSTEM → 🔑 Aktivasi</strong>.<br>
      4. Saat muncul permintaan izin, klik <strong>Continue</strong> dan pilih akun Google Anda.<br>
      5. Jika muncul peringatan <em>"Google hasn’t verified this app"</em>, klik:<br>
      &nbsp;&nbsp;&nbsp;<strong>Advanced → Go to Automation Engine (unsafe) → Allow</strong>.<br>
      6. Tempelkan API Key yang sudah Anda salin. Automation siap digunakan.
    </p>
    <p style="margin-top:10px;font-size:0.85em;color:#64748b;">
      💡 Anda dapat membuat <strong>banyak Sheet automation</strong> selama masih berada dalam file hasil salinan ini.
    </p>
  </div>

  <!-- SECURITY NOTE -->
  <div style="margin-top:20px;background:#f0f9ff;padding:15px;border-radius:8px;border:1px solid #bae6fd;">
    <h4 style="margin-top:0;color:#0369a1;font-size:1em;">🛡️ Mengapa Aman?</h4>
    <p style="font-size:0.85em;color:#075985;margin-bottom:0;line-height:1.6;">
      Sistem hanya meminta <strong>izin sementara (OAuth)</strong> untuk mengekspor file Anda sesuai perintah automation.
      Kami <strong>tidak menyimpan</strong> akses permanen ke Google Drive atau akun Google Anda.
    </p>
  </div>
</section>

<script>
function copyKey() {
  const input = document.getElementById("apiKeyInput");
  input.select();
  input.setSelectionRange(0, 99999);
  document.execCommand("copy");

  const btn = document.getElementById("btnCopy");
  btn.innerText = "COPIED!";
  btn.style.background = "#22c55e";

  setTimeout(() => {
    btn.innerText = "COPY";
    btn.style.background = "#1e293b";
  }, 2000);
}
</script>

<!-- ================= SOURCE ================= -->
<section class="card">
  <h3>Sumber Data</h3>

  <ul>
    <li><strong>Jenis:</strong> <?= strtoupper($source['type'] ?? '-') ?></li>
    <li><strong>Spreadsheet ID:</strong> <?= htmlspecialchars($source['spreadsheet_id'] ?? '-') ?></li>
    <li><strong>GID:</strong> <?= htmlspecialchars($source['gid'] ?? '-') ?></li>
  </ul>
</section>

<!-- ================= FILE ================= -->
<section class="card">
  <h3>Output File</h3>

  <p>
    <strong>Format:</strong>
    <?= strtoupper($file['type'] ?? '-') ?>
  </p>
</section>

<!-- ================= TARGET ================= -->
<section class="card">
  <h3>Target Pengiriman</h3>

  <ul>
    <li>
      <strong>Channel:</strong>
      <?= strtoupper($target['channel'] ?? '-') ?>
    </li>

    <?php if (!empty($target['destination'])): ?>
      <?php foreach ($target['destination'] as $k => $v): ?>
        <li>
          <strong><?= ucfirst(str_replace('_',' ',$k)) ?>:</strong>
          <?= htmlspecialchars($v) ?>
        </li>
      <?php endforeach; ?>
    <?php endif; ?>
  </ul>
</section>

<!-- ================= MODE INFO ================= -->
<section class="card">
  <h3>Mode Eksekusi</h3>

  <?php if ($isSystem): ?>
    <p>
      Automation ini dijalankan oleh sistem.
      Token, sender, dan provider sepenuhnya dikelola oleh platform.
    </p>
  <?php else: ?>
    <p>
      Automation ini menggunakan token milik Anda sendiri.
      Pastikan kredensial selalu aktif dan valid.
    </p>
  <?php endif; ?>
</section>


<?php 
// ... (kode awal sama hingga bagian resolve flow) ...

elseif ($flow === 'CHATBOT_CHANNEL'): ?>
<section class="card">
    <h3>Detail Konfigurasi Channel: <?= strtoupper($client['service']) ?></h3>
    <table class="detail-table">
        <tr>
            <td><strong>Provider</strong></td>
            <td><span class="badge"><?= strtoupper($client['provider']) ?></span></td>
        </tr>
        <?php foreach ($credentials as $key => $val): ?>
        <tr>
            <td><strong><?= ucwords(str_replace('_', ' ', $key)) ?></strong></td>
            <td style="word-break: break-all; font-family: monospace; color: #4338ca;">
                <?= !empty($val) ? htmlspecialchars($val) : '-' ?>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
</section>

<section class="card">
    <h3>AI Configuration</h3>
    <p><strong>Engine:</strong> <?= $meta['ai_engine']['provider'] ?? '-' ?> (<?= $meta['ai_engine']['model'] ?? '-' ?>)</p>
</section>

<?php if($client['provider'] === 'meta' || $client['service'] === 'telegram'): ?>
<section class="card">
    <h3>Webhook Callback URL</h3>
    <p class="hint">Gunakan URL ini di dashboard developer <?= ucfirst($client['provider']) ?>:</p>
    <div class="code-box">
        <?= (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]/api/webhook/{$client['service']}.php?key={$client['api_key']}" ?>
    </div>
</section>
<?php endif; ?>

<style>
.detail-table { width: 100%; border-collapse: collapse; }
.detail-table td { padding: 12px 8px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
.badge { background: #e0e7ff; color: #4338ca; padding: 2px 8px; border-radius: 4px; font-weight: bold; }
.code-box { background: #1e293b; color: #38bdf8; padding: 12px; border-radius: 8px; font-family: monospace; font-size: 13px; margin-top: 10px; }
</style>

<?php // endif; ?>

<?php endif; ?>
</main>

<style>
/* ===============================
   CARD BASE
================================ */
.card {
  background: #ffffff;
  border-radius: 12px;
  padding: 18px 20px;
  margin-bottom: 18px;
  box-shadow: 0 4px 14px rgba(0,0,0,0.06);
}

.card h3 {
  margin-top: 0;
  margin-bottom: 10px;
  font-size: 1.05rem;
  color: #111827;
}

.card p,
.card li {
  font-size: 0.92rem;
  line-height: 1.6;
  color: #374151;
}

.card ul {
  padding-left: 18px;
}

.user-content h1 {
  font-size: 1.4rem;
  margin-bottom: 20px;
  color: #111827;
}

@media (max-width: 768px) {
  .user-content h1 {
    font-size: 1.15rem;
  }
}

.btn {
  display: inline-block;
  background: #2563eb;
  color: #fff;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 0.9rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
}

.btn.primary {
  background: #4f46e5;
}

.btn:hover {
  opacity: 0.9;
}

@media (max-width: 768px) {
  .btn {
    width: 100%;
    text-align: center;
  }
}

textarea {
  resize: vertical;
  min-height: 80px;
  font-size: 0.85rem;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  padding: 12px;
}

@media (max-width: 768px) {
  textarea {
    font-size: 0.8rem;
  }
}

.automation-steps {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 12px;
  margin-bottom: 20px;
}

.automation-step {
  background: #f8fafc;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid #e2e8f0;
  font-size: 0.85rem;
}

@media (max-width: 768px) {
  .automation-steps {
    grid-template-columns: 1fr;
  }
}

.status-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 700;
}

.status-active {
  background: #dcfce7;
  color: #166534;
}

.status-inactive {
  background: #fee2e2;
  color: #991b1b;
}

@media (max-width: 768px) {
  .card {
    padding: 15px;
  }

  .user-contentX {
    padding-left: 15px;
    padding-right: 15px;
  }
}

.preview-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
    gap:15px;
}

.preview-box{
    position:relative;
    overflow:hidden;
}

.preview-box img{
    width:100%;
    pointer-events:none;
    user-select:none;
    filter:blur(0.2px);
}
</style>

<style>
.code-container {
    position: relative;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    background: #1f2937; /* Dark theme untuk area kode */
    margin-top: 10px;
}

.code-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 15px;
    background: #374151;
    color: #d1d5db;
    font-size: 12px;
}

.copy-btn {
    background: #4f46e5;
    color: white;
    border: none;
    padding: 4px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    transition: background 0.2s;
}

.copy-btn:hover {
    background: #4338ca;
}

#scriptArea {
    width: 100%;
    background: #111827;
    color: #10b981; /* Warna hijau khas code editor */
    padding: 15px;
    border: none;
    font-family: 'Fira Code', monospace;
    font-size: 13px;
    resize: none;
    outline: none;
    display: block;
}
</style>

<script>
function copyToClipboard() {
    const textArea = document.getElementById("scriptArea");
    const btnText = document.getElementById("copy-text");

    // Pilih teks
    textArea.select();
    textArea.setSelectionRange(0, 99999); // Untuk mobile

    // Salin
    navigator.clipboard.writeText(textArea.value).then(() => {
        // Feedback visual
        btnText.innerText = "Copied!";
        setTimeout(() => {
            btnText.innerText = "Copy";
        }, 2000);
    }).catch(err => {
        console.error('Gagal menyalin: ', err);
    });
}

</script>
<?php include 'footer.php';