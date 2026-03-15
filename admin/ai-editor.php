<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

/* ===============================
   MODE DETECTION
================================ */
$id     = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = false;
$success = isset($_GET['success']);

/* ===============================
   DEFAULT DATA
================================ */
$data = [
    'id'            => '',
    'provider_slug' => '',
    'provider_name' => '',
    'model'         => '',
    'api_token'     => '',
    'status'        => 'active',
];

/* ===============================
   LOAD EDIT DATA
================================ */
if ($id) {
    $row = DB::fetch(
        "SELECT * FROM ai_configs WHERE id = ? LIMIT 1",
        [$id]
    );

    if ($row) {
        $data   = $row;
        $isEdit = true;
    }
}

/* ===============================
   PROVIDER LIST
================================ */
$providers = DB::fetchAll("
    SELECT id, provider_slug, provider_name
    FROM ai_configs
    WHERE post_type = 'ai_provider'
    ORDER BY provider_name ASC
");

include "header.php";
include "sidebar.php";
?>

<main class="user-content">
<div class="settings-container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
        <h2 style="margin: 0;">🤖 <?= $isEdit ? 'Edit AI Provider' : 'Add AI Provider' ?></h2>
        <a href="dashboard.php" style="text-decoration: none; color: #4f46e5; font-weight: 600; font-size: 0.9em;">← Kembali ke Dashboard</a>
    </div>

    <?php if ($success): ?>
        <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #bbf7d0;">
            ✅ Data AI Provider berhasil disimpan ke sistem.
        </div>
    <?php endif; ?>

    <form method="post" action="ai-save.php">
        <input type="hidden" name="id" value="<?= htmlspecialchars($data['id']) ?>">

        <div class="settings-grid">
            
            <section class="card">
                <h3>🏢 Identity & Model</h3>
                
                <div class="form-group">
                    <label>Pilih Provider Template</label>
                    <select id="providerSelect">
                        <option value="">+ Tambah Provider Baru (Manual)</option>
                        <?php foreach ($providers as $p): ?>
                            <option
                                value="<?= $p['id'] ?>"
                                data-slug="<?= htmlspecialchars($p['provider_slug']) ?>"
                                data-name="<?= htmlspecialchars($p['provider_name']) ?>"
                                <?= $p['provider_slug'] === $data['provider_slug'] ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($p['provider_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Pilih dari daftar untuk pengisian otomatis, atau isi manual di bawah.</small>
                </div>

                <hr>

                <div class="form-group">
                    <label>Provider Slug</label>
                    <input type="text" id="providerSlug" name="provider_slug" 
                           value="<?= htmlspecialchars($data['provider_slug']) ?>" 
                           placeholder="misal: openai / gemini / groq" readonly>
                </div>

                <div class="form-group">
                    <label>Provider Name Display</label>
                    <input type="text" id="providerName" name="provider_name" 
                           value="<?= htmlspecialchars($data['provider_name']) ?>" 
                           placeholder="Nama tampilan di sistem" readonly>
                </div>
            </section>

            <section class="card">
                <h3>⚙️ API Configuration</h3>

                <div class="form-group">
                    <label>Model Engine</label>
                    <input type="text" name="model" 
                           value="<?= htmlspecialchars($data['model']) ?>" 
                           placeholder="Contoh: gpt-4-turbo, gemini-pro, dll">
                    <small>Pastikan nama model sesuai dengan dokumentasi provider.</small>
                </div>

                <div class="form-group">
                    <label>Status Operasional</label>
                    <select name="status">
                        <option value="active" <?= $data['status'] === 'active' ? 'selected' : '' ?>>Active (Siap Digunakan)</option>
                        <option value="inactive" <?= $data['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (Maintenance)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>API Key / Token</label>
                    <textarea name="api_token" rows="4" style="font-family: monospace;" 
                              placeholder="Masukkan API Key rahasia di sini..."><?= htmlspecialchars($data['api_token']) ?></textarea>
                </div>
            </section>

        </div>

        <div style="margin-top: 20px;">
            <button type="submit" class="btn-save">
                <?= $isEdit ? '💾 Update AI Provider' : '🚀 Save AI Provider' ?>
            </button>
        </div>
    </form>
</div>
</main>

<style>
    /* 1. VARIABLES & RESET */
    :root {
        --primary-color: #4f46e5;
        --primary-hover: #4338ca;
        --border-color: #e2e8f0;
        --bg-card: #ffffff;
        --bg-light: #f8fafc;
        --bg-disabled: #f1f5f9;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --danger: #ef4444;
        --success: #15803d;
        --warning: #92400e;
    }

    /* 2. LAYOUT & CONTAINERS */
    .settings-container {
        max-width: 1100px;
        margin: 0 auto;
        padding: 20px;
        font-family: 'Inter', system-ui, sans-serif;
    }

    .page-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 25px;
    }

    .page-header h2 { margin: 0; font-size: 1.5rem; color: var(--text-main); }

    /* 3. GRID SYSTEM */
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .grid-inner { display: grid; gap: 15px; }
    .grid-2 { grid-template-columns: repeat(2, 1fr); }
    .grid-3 { grid-template-columns: repeat(3, 1fr); }
    .grid-4 { grid-template-columns: repeat(4, 1fr); }

    .card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .card-full { grid-column: 1 / -1; }

    /* 4. FORM ELEMENTS */
    h3 {
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 1rem;
        color: var(--primary-color);
        display: flex;
        align-items: center;
        gap: 8px;
        border-bottom: 1px solid var(--bg-disabled);
        padding-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group { margin-bottom: 15px; }

    label {
        display: block;
        font-weight: 600;
        font-size: 0.85em;
        margin-bottom: 6px;
        color: var(--text-main);
    }

    input, select, textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.9em;
        box-sizing: border-box;
        transition: all 0.2s ease;
    }

    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    input:disabled, .input-disabled {
        background: var(--bg-disabled);
        color: var(--text-muted);
        cursor: not-allowed;
        font-weight: 600;
    }

    .stat-box {
        padding: 10px;
        background: var(--bg-disabled);
        border-radius: 8px;
        text-align: center;
        font-weight: bold;
        border: 1px solid var(--border-color);
    }

    /* 5. BUTTONS */
    .btn-create, .btn-save {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: var(--primary-color);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-save { width: 100%; font-size: 1em; padding: 14px; }
    .btn-create:hover, .btn-save:hover { background: var(--primary-hover); transform: translateY(-1px); }
    
    .action-bar { margin-top: 20px; display: flex; gap: 10px; }
    .btn-reset { 
        background: #94a3b8; color: white; border: none; padding: 12px; 
        border-radius: 8px; cursor: pointer; flex: 1; font-weight: 600;
    }

    .btn-sm { 
        padding: 5px 10px; background: var(--bg-disabled); 
        border: 1px solid var(--border-color); border-radius: 6px; 
        cursor: pointer; font-size: 0.8em; 
    }

    /* 6. COMPONENTS (Badges, Avatars, etc) */
    .status-badge {
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.75em;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-active { background: #dcfce7; color: var(--success); }
    .status-inactive, .status-suspended { background: #fee2e2; color: #b91c1c; }
    .status-pending { background: #fef3c7; color: var(--warning); }

    .bot-avatar { 
        width: 40px; height: 40px; border-radius: 10px; 
        object-fit: cover; background: var(--bg-disabled); border: 1px solid var(--border-color);
    }

    .table-responsive { width: 100%; overflow-x: auto; }

    /* 7. RESPONSIVE BREAKPOINTS */
    @media (max-width: 900px) {
        .grid-3, .grid-4 { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 600px) {
        .settings-grid, .grid-inner, .grid-2, .grid-3, .grid-4 { 
            grid-template-columns: 1fr; 
        }
        .action-bar { flex-direction: column; }
        .card-full { grid-column: auto; }
    }
</style>

<script>
/**
 * Logic untuk auto-fill field Slug dan Name saat memilih template
 */
document.getElementById('providerSelect').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    const slugInput = document.getElementById('providerSlug');
    const nameInput = document.getElementById('providerName');

    if (this.value === "") {
        // Jika pilih "+ Add New", buka proteksi readonly agar bisa isi manual
        slugInput.readOnly = false;
        nameInput.readOnly = false;
        slugInput.value = "";
        nameInput.value = "";
        slugInput.focus();
    } else {
        // Jika pilih template, isi otomatis dan kunci kembali
        slugInput.readOnly = true;
        nameInput.readOnly = true;
        slugInput.value = selectedOption.getAttribute('data-slug');
        nameInput.value = selectedOption.getAttribute('data-name');
    }
});
</script>

<?php include "footer.php"; ?>