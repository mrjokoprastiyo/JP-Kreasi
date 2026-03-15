<?php
/**
 * FORM DESIGN ORDER (MANUAL via WhatsApp)
 */
$productName = $product['name'] ?? 'Layanan Desain';
$userName  = $_SESSION['user']['name'] ?? '';
$userEmail = $_SESSION['user']['email'] ?? '';

function wa($text) {
    return rawurlencode($text);
}
?>

<main class="user-content">
    <header class="page-header">
        <h1>Order Desain Visual</h1>
    </header>

    <form method="POST" class="form-grid" enctype="multipart/form-data">

        <!-- ===============================
             INFORMASI PEMESAN
        ================================ -->
        <section class="card">
            <h3>Informasi Pemesan</h3>

            <label>Nama</label>
            <input type="text" name="customer_name"
                   value="<?= e($userName) ?>" required>

            <label>Email</label>
            <input type="email" name="customer_email"
                   value="<?= e($userEmail) ?>" required>

            <label>WhatsApp</label>
            <input type="text" name="customer_wa"
                   placeholder="628xxxx" required>
        </section>

        <!-- ===============================
             DETAIL DESAIN
        ================================ -->
        <section class="card">
            <h3>Detail Pesanan</h3>

            <label>Jenis Desain</label>
            <select name="design_type" required>
                <option value="">-- Pilih Jenis --</option>
                <option>Logo</option>
                <option>Banner / Poster</option>
                <option>Feed Instagram</option>
                <option>UI / Web Design</option>
                <option>Landing Page</option>
                <option>Branding Kit</option>
                <option>Lainnya</option>
            </select>

            <label>Ukuran / Format</label>
            <input type="text" name="size"
                   placeholder="Contoh: 1080x1080 / A4 / Responsive Web">

            <label>Referensi Desain (URL)</label>
            <input type="url" name="reference"
                   placeholder="https://...">

            <label>Deskripsi Kebutuhan</label>
            <textarea name="description" rows="4" required
                      placeholder="Jelaskan konsep, warna, target market, dll"></textarea>
        </section>

        <!-- ===============================
             DEADLINE & CATATAN
        ================================ -->
        <section class="card">
            <h3>Deadline & Catatan</h3>

            <label>Deadline</label>
            <input type="date" name="deadline">

            <label>Catatan Tambahan</label>
            <textarea name="note" rows="3"
                      placeholder="Opsional"></textarea>
        </section>

<!-- REFERENSI FILE -->
        <section class="card">
            <label>Upload Referensi (opsional)</label>
            <input type="file"
                   name="reference_file"
                   accept=".jpg,.jpeg,.png,.pdf,.zip,.rar">
            <small class="hint">
                JPG, PNG, PDF, ZIP / RAR (max 5MB)
            </small>
        </section>

        <!-- ===============================
             SUBMIT
        ================================ -->
        <section class="card highlight">
            <h3>Kirim Order</h3>

            <p>
                Setelah dikirim, detail pesanan akan langsung
                diteruskan ke WhatsApp admin untuk proses lanjutan.
            </p>

            <button type="submit" class="btn primary">
                💬 Kirim via WhatsApp
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
select {
  width: 100%;
  padding: 9px 11px;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  font-size: 14px;
}

textarea {
    width: 100%;
    padding: 11px 12px;
    margin-top: 6px;
    border-radius: 8px;
    border: 1px solid #d1d5db;
    font-size: 14px;
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
