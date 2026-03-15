<?php    
// form/chatbot-channel.php

require_once __DIR__ . '/../../core/settings-loader.php';
require_once __DIR__ . '/../../core/base.php';

        $app_id = setting('meta-app-id');
        $app_version    = setting('meta-app-version');

$service = $product['service'];     
$aiRows = DB::fetchAll("SELECT provider_slug, provider_name, model FROM ai_configs WHERE status = 'active' ORDER BY provider_name ASC");    
$aiProviders = [];    
foreach ($aiRows as $row) {    
    $aiProviders[$row['provider_slug']]['name'] = $row['provider_name'];    
    $aiProviders[$row['provider_slug']]['models'][] = $row['model'];    
}       
?>    
    
<main class="user-content">    
    <header class="page-header">    
        <h1>Setup Chatbot Channel</h1>    
    </header>    
    
    <form method="POST" class="form-grid">    
    
        <section class="card">    
            <h3>Informasi Bot</h3>    
            <label>Nama Bot / Project</label>    
            <input type="text" name="name" required placeholder="Contoh: CS Otomatis">    
                
            <label>Channel</label>    
            <input type="text" value="<?= strtoupper($service) ?>" disabled style="background: #f4f4f4;">    
                
            <label>Provider</label>    
            <select name="provider" id="providerSelect" required>    
<?php if ($service === 'messenger'): ?>
    <option value="meta">Meta (Facebook Messenger)</option>

<?php elseif ($service === 'comment'): ?>
    <option value="meta">Meta (Facebook Comment Automation)</option>

<?php elseif ($service === 'telegram'): ?> 
                    <option value="telegram">Telegram Bot API</option>    
                <?php elseif ($service === 'whatsapp'): ?>    
                    <option value="meta">WhatsApp Cloud API (Official)</option>    
                    <option value="wablas">Wablas</option>    
                    <option value="fonnte">Fonnte</option>    
                <?php endif; ?>    
            </select>    
        </section>    
    
        <section class="card">    
            <h3>Konfigurasi Koneksi</h3>    
                
            <?php if ($service === 'messenger' || $service === 'comment'): ?>
<div id="messenger-meta-fields">
<?php if ($service === 'messenger') { ?>
<a href="<?= BASE_URL ?>/user/meta-start.php?channel=messenger&product_id=<?= $product['id'] ?>"
   class="btn secondary">
   🔗 Connect Facebook Page
</a> <?php } else { ?>
<a href="<?= BASE_URL ?>/user/meta-start.php?channel=comment&product_id=<?= $product['id'] ?>"
   class="btn secondary">
   🔗 Connect Facebook Page
</a> <?php } ?>
    <div id="fb-page-list" style="margin-top:15px;">

<?php if (!empty($metaPages)): ?>

<label>Pilih Facebook Page</label>

<select name="page_id" required>
<option value="">-- Pilih Page --</option>
<?php foreach ($metaPages as $page): ?>
<option value="<?= htmlspecialchars($page['id']) ?>"
        data-token="<?= htmlspecialchars($page['access_token']) ?>"
        data-name="<?= htmlspecialchars($page['name']) ?>">
    <?= htmlspecialchars($page['name']) ?>
</option>
<?php endforeach; ?>
</select>

<input type="hidden" name="page_access_token" id="page_token">
<input type="hidden" name="page_name" id="page_name">

<?php endif; ?>
                
                <p class="helper-text">Halaman ini akan otomatis diintegrasikan dengan AI Engine pilihan Anda.</p>

    </div>

</div>
    
            <?php elseif ($service === 'telegram'): ?>    
                <label>Bot Token</label>    
                <input type="text" name="bot_token" placeholder="123456:ABC-DEF...">    
    
            <?php elseif ($service === 'whatsapp'): ?>    
                <div id="wa-meta-connect" style="display:none; margin-bottom:15px;">     
<a href="<?= BASE_URL ?>/user/meta-start.php?channel=whatsapp&product_id=<?= $product['id'] ?>"
   class="btn secondary">
   🔗 Connect WhatsApp Business
</a>   
                </div>    
    
                <div id="wa-manual-fields">    
                    <label>Phone Number ID</label>    
                    <input type="text" name="phone_number_id" id="wa_phone_number_id">    
                        
                    <label>WhatsApp Business Account ID</label>    
                    <input type="text" name="waba_id" id="wa_waba_id">    
                        
                    <label>Access Token / API Key</label>    
                    <textarea name="access_token" id="wa_access_token" rows="3" placeholder="EAAB..."></textarea>    
                </div>    
            <?php endif; ?>    
        </section>    
    
<section class="card">    
    <h3>AI Engine</h3>    
    <label>AI Provider</label>    
    <select name="ai_provider" id="aiProvider" required onchange="updateModels()">    
        <option value="">-- Pilih Provider --</option>    
        <?php foreach ($aiProviders as $slug => $p): ?>    
            <option value="<?= htmlspecialchars($slug) ?>"><?= htmlspecialchars($p['name']) ?></option>    
        <?php endforeach; ?>    
    </select>    
    
    <label>AI Model</label>    
    <select name="ai_model" id="aiModel" required>    
        <option value="">-- Pilih Model --</option>    
    </select>    
</section>
    
<?php if ($service === 'messenger'): ?>
<section class="card">
    <h3>Pengaturan Tampilan Bot (UI Messenger)</h3>

    <div class="field">
        <label>Teks Saat Limit</label>
        <textarea name="ui_limit_text" rows="2">{name}, akses ke fitur ini sedang dibatasi.</textarea>
    </div>

    <div class="field">
        <label>Teks Tombol Limit</label>
        <input type="text" name="ui_limit_button_text" value="Dukung kami untuk melanjutkan percakapan:">
    </div>

    <div class="field">
        <label>Teks Setelah AI Menjawab</label>
        <input type="text" name="ui_ai_after_text" value="Butuh bantuan lainnya?">
    </div>

    <div class="field">
        <label>Teks Forward ke Admin</label>
        <textarea name="ui_admin_forward_text" rows="2">{name}, permintaan kamu sedang diteruskan ke tim kami.</textarea>
    </div>

    <div class="field">
        <label>Teks Prompt Admin</label>
        <textarea name="ui_admin_prompt_text" rows="2">{name}, silakan tulis kebutuhan kamu.</textarea>
    </div>

    <div class="field">
        <label>Teks Prompt Assistant</label>
        <textarea name="ui_assistant_prompt_text" rows="2">{name}, silakan tanya apa saja.</textarea>
    </div>

    <div class="inline">
        <div class="field">
            <label>Judul Tombol Chat Admin</label>
            <input type="text" name="ui_chat_admin_title" value="CHAT DENGAN TIM">
        </div>

        <div class="field">
            <label>Judul Tombol Chat Assistant</label>
            <input type="text" name="ui_chat_ai_title" value="CHAT DENGAN ASSISTANT">
        </div>
    </div>

    <div class="inline">
        <div class="field">
            <label>Judul Tombol Menu Utama</label>
            <input type="text" name="ui_main_menu_title" value="MENU UTAMA">
        </div>

        <div class="field">
            <label>Judul Tombol Support</label>
            <input type="text" name="ui_support_button_title" value="DUKUNG">
        </div>
    </div>

    <p class="hint">
        Gunakan {name} untuk menampilkan nama user secara otomatis.
    </p>
</section>
<?php endif; ?>

        <section class="card highlight">    
            <button type="submit" class="btn primary" id="btnSubmit">💾 Simpan & Aktifkan</button>    
        </section>    
    </form>    
</main>    
    

<style>
.page-header {
  margin-bottom: 28px;
}

.page-header h1 {
  font-size: 22px;
  margin-bottom: 4px;
}

.page-header .subtitle {
  margin: 0;
  color: #6b7280;
  font-size: 14px;
  max-width: 520px;
}


.form-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

.form-col {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

@media (min-width: 1024px) {
  .form-grid {
    grid-template-columns: 1fr 1fr;
    align-items: start;
  }
  .full {
    grid-column: 1 / -1;
  }
}

.card {
  background: #fff;
  padding: 16px 18px;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0,0,0,.04);
}

.card h3 {
  font-size: 16px;
  margin-bottom: 12px;
}

.card + .card {
  margin-top: 2px;
}

.field {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin-bottom: 14px;
}

label {
  font-weight: 600;
  font-size: 13px;
  color: #374151;
}

.inline {
  display: grid;
  grid-template-columns: 1fr;
  gap: 14px;
}

@media (min-width: 640px) {
  .inline {
    grid-template-columns: 1fr 1fr;
  }
}

input,
textarea,
select {
  width: 100%;
  padding: 9px 11px;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  font-size: 14px;
}

textarea {
  resize: vertical;
}

input:focus,
textarea:focus,
select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37,99,235,.15);
}

/* ===============================
   COLOR PICKER FIX
================================ */
input[type="color"] {
    -webkit-appearance: none;
    appearance: none;

    height: 32px;
    padding: 0;
    cursor: pointer;

    background: none;
    border-radius: 8px;
    border: 1px solid #d1d5db;
}

/* Chrome / Edge / Brave */
input[type="color"]::-webkit-color-swatch-wrapper {
    padding: 0;
    border-radius: 8px;
}

input[type="color"]::-webkit-color-swatch {
    border: none;
    border-radius: 8px;
}

/* Firefox */
input[type="color"]::-moz-color-swatch {
    border: none;
    border-radius: 8px;
}

.preview {
  margin-top: 8px;
  max-width: 96px;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  padding: 4px;
  background: #f9fafb;
}

.preview.icon {
  width: 44px;
  height: 44px;
}

.checkbox-panel {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 10px 12px;
  background: #f9fafb;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
}

.checkbox-item {
  display: flex;
  align-items: center;
  gap: 10px;
  cursor: pointer;
  font-size: 14px;
}

.checkbox-item input {
  appearance: none;
  width: 18px;
  height: 18px;
  border-radius: 4px;
  border: 2px solid #d1d5db;
  display: grid;
  place-content: center;
}

.checkbox-item input:checked {
  background: #2563eb;
  border-color: #2563eb;
}

.checkbox-item input::before {
  content: "";
  width: 9px;
  height: 9px;
  transform: scale(0);
  background: white;
  clip-path: polygon(14% 44%,0 65%,50% 100%,100% 16%,80% 0%,43% 62%);
}

.checkbox-item input:checked::before {
  transform: scale(1);
}

.hint {
  font-size: 12px;
  color: #6b7280;
  margin-top: 6px;
}

.card.highlight {
  background: linear-gradient(
    to right,
    #f8fafc,
    #f1f5f9
  );
  border: 1px dashed #c7d2fe;
text-align: center;
}

.card.highlight .btn.primary {
  min-width: 260px;
}

@media (max-width: 640px) {
  .card.highlight .btn.primary {
    width: 100%;
  }
}

.hint.warning {
  display: block;
  width: 100%;
  background: #fff7ed;
  border: 1px solid #fed7aa;
  color: #9a3412;
  padding-inline: 12px;
  padding-block: 12px;
  border-radius: 8px;
  font-size: 12px;
  line-height: 1.6;
  box-sizing: border-box;
}

/* ===============================
   AUDIO AUTOPLAY WARNING
================================ */

.hint.detect {
  margin-top: 8px;
  padding: 8px 12px;
  font-size: 13px;
  line-height: 1.4;
  color: #7a3b00;
  background: #fff4e5;
  border: 1px solid #ffd2a1;
  border-left: 4px solid #ff9800;
  border-radius: 6px;
  display: none; /* default hidden */
}

/* tampil otomatis kalau ada isi */
.hint.detect:not(:empty) {
  display: block;
}

/* icon spacing */
.hint.detect::before {
  content: "🔊";
  margin-right: 6px;
}

/* dark mode friendly (optional) */
@media (prefers-color-scheme: dark) {
  .hint.detect {
    color: #ffcc80;
    background: #2a1f12;
    border-color: #ff9800;
  }
}

.btn.primaryX {
  padding: 12px 18px;
  font-size: 15px;
  border-radius: 10px;
}
</style>

<script>
document.querySelector('select[name="page_id"]')?.addEventListener('change', function() {
    const opt = this.selectedOptions[0];
    document.getElementById('page_access_token').value = opt.dataset.token || '';
    document.getElementById('page_name').value  = opt.dataset.name || '';
});
</script>
<script>
const aiData = <?= json_encode($aiProviders) ?>;
function updateModels() {
    const provider = document.getElementById('aiProvider').value;
    const modelSelect = document.getElementById('aiModel');
    modelSelect.innerHTML = '<option value="">-- Pilih Model --</option>';
    
    if(aiData[provider]) {
        aiData[provider].models.forEach(model => {
            let opt = document.createElement('option');
            opt.value = model;
            opt.innerHTML = model;
            modelSelect.appendChild(opt);
        });
    }
}
</script>
