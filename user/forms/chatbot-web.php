<?php
// forms/chatbot-web.php
?>

<main class="user-content">
  <header class="page-header">
    <h1><?= isset($client) ? 'Edit Chatbot Website' : 'Setup Chatbot Website' ?></h1>
    <p class="subtitle">
      Atur identitas, AI engine, dan tampilan chatbot website Anda
    </p>
  </header>

  <form method="POST" class="form-grid" enctype="multipart/form-data">

    <!-- LEFT COLUMN -->
    <div class="form-col">

      <section class="card">
        <h3>Informasi Website</h3>

        <div class="field">
          <label>Nama Website</label>
          <input type="text" name="name" required
            placeholder="Contoh: Toko Online Saya">
        </div>

        <div class="field">
          <label>Domain</label>
          <input type="text" name="domain" required
            placeholder="contoh.com">
        </div>
      </section>

      <section class="card">
        <h3>Identitas Bot</h3>

        <div class="field">
          <label>Nama Bot</label>
          <input type="text" name="bot_name" required
            value="<?= s('chatbot-web-bot_name') ?>">
        </div>

        <div class="field">
          <label>Deskripsi</label>
          <input type="text" name="bot_desc"
            value="<?= s('chatbot-web-bot_desc') ?>">
        </div>

        <div class="field">
          <label>Pesan Sambutan</label>
          <textarea name="bot_greeting" rows="3"><?= s('chatbot-web-bot_greeting') ?></textarea>
        </div>
      </section>

      <section class="card">
        <h3>Prompt AI</h3>

        <textarea name="prompt" rows="6" required><?= s('chatbot-web-prompt') ?></textarea>

        <small class="hint">
          Mengatur perilaku dan gaya respon chatbot
        </small>
      </section>

    </div>

    <!-- RIGHT COLUMN -->
    <div class="form-col">

      <section class="card">
        <h3>AI Engine</h3>

        <select name="ai_config_id" required>
          <option value="">-- Pilih AI Engine --</option>
          <?php foreach ($aiConfigs as $ai): ?>
            <option value="<?= $ai['id'] ?>">
              <?= e($ai['provider_name']) ?> — <?= e($ai['model']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <small class="hint">
          Engine AI ditentukan oleh admin sistem
        </small>
      </section>

      <section class="card">
        <h3>Tampilan Widget</h3>

        <div class="inline">
          <div class="field">
            <label>Warna Background</label>
            <input type="color" name="widget_background"
              value="<?= s('chatbot-web-widget_background') ?>">
          </div>
        </div>

        <div class="field">
          <label>Avatar Bot</label>
          <input type="file" name="bot_avatar">
          <?php if (!empty($previewBotAvatar)): ?>
            <img class="preview" src="<?= e($previewBotAvatar) ?>">
          <?php endif; ?>
        </div>

        <div class="field">
          <label>Icon Widget</label>
          <input type="file" name="widget_icon">
          <?php if (!empty($previewWidgetIcon)): ?>
            <img class="preview icon" src="<?= e($previewWidgetIcon) ?>">
          <?php endif; ?>
        </div>
      </section>

      <section class="card">
        <h3>Notifikasi</h3>

        <div class="checkbox-panel">
          <label class="checkbox-item">
            <input type="checkbox" name="notif_badge"
              <?= setting('chatbot-web-notif_badge') ? 'checked' : '' ?>>
            <span>Badge notifikasi</span>
          </label>

          <label class="checkbox-item">
            <input type="checkbox" name="notif_popup"
              <?= setting('chatbot-web-notif_popup') ? 'checked' : '' ?>>
            <span>Popup pesan otomatis</span>
          </label>

          <label class="checkbox-item">
            <input type="checkbox" id="notifSoundToggle" name="notif_sound_enabled"
              <?= setting('chatbot-web-notif_sound_enabled') ? 'checked' : '' ?>>
            <span>Suara notifikasi</span>
          </label>
        </div>

        <div class="field" id="notifSoundField">
          <label>Sound Notifikasi</label>
          <input type="file" name="notif_sound">
          <?php if (!empty($previewNotifSound)): ?>
            <audio controls src="<?= e($previewNotifSound) ?>"></audio>
          <?php endif; ?>
        </div>

        <small class="hint warning">
          ⚠️ Beberapa browser dan perangkat <b>tidak mendukung autoplay suara</b>.
          Suara notifikasi hanya akan berbunyi setelah ada interaksi pengguna
          dan bisa berbeda tergantung browser, device, dan pengaturan sistem.
        </small>
      </section>

    </div>

    <!-- SUBMIT -->
    <section class="card highlight full">
      <button class="btn primary">
        <?= isset($client) ? '💾 Simpan Perubahan' : '🚀 Buat Chatbot Website' ?>
      </button>
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
(function () {
  const toggle = document.getElementById('notifSoundToggle');
  const field  = document.getElementById('notifSoundField');

  if (!toggle || !field) return;

  function update() {
    field.style.display = toggle.checked ? 'block' : 'none';
  }

  toggle.addEventListener('change', update);
  update(); // initial state
})();
</script>
<script>
(function () {
  const detect = document.querySelector('.hint.detect');

  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) {
      detect.innerHTML =
        '⚠️ Browser ini tidak mendukung API audio modern. ' +
        'Suara notifikasi kemungkinan tidak berfungsi.';
    }
  } catch (e) {
    // silently fail
  }
})();
</script>