<?php
// admin/dashboard.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';

// FETCH DATA
$aiProviders = DB::fetchAll("SELECT id, provider_slug, provider_name, model, status, updated_at FROM ai_configs ORDER BY created_at DESC");
$msgProviders = DB::fetchAll("SELECT id, channel, provider_slug, provider_name, status FROM message_providers ORDER BY channel, created_at DESC");
$settingsCount = DB::fetchColumn("SELECT COUNT(*) FROM settings");

include __DIR__ . '/header.php';
include __DIR__ . '/sidebar.php';
?>

<main class="user-content">
  <div class="settings-container">
    
    <div class="page-header">
        <div>
            <h2>🚀 Dashboard Overview</h2>
            <p style="color:var(--text-muted); font-size:0.85em; margin:0;">Ringkasan sistem dan status provider aktif.</p>
        </div>
    </div>

    <div class="settings-grid grid-3">
        <div class="card" style="text-align:center;">
            <h3>🧠 AI Providers</h3>
            <div style="font-size: 2rem; font-weight: 800; color: var(--primary-color);"><?= count($aiProviders) ?></div>
        </div>
        <div class="card" style="text-align:center;">
            <h3>💬 Channels</h3>
            <div style="font-size: 2rem; font-weight: 800; color: var(--primary-color);"><?= count($msgProviders) ?></div>
        </div>
        <div class="card" style="text-align:center;">
            <h3>⚙️ Settings</h3>
            <div style="font-size: 2rem; font-weight: 800; color: var(--primary-color);"><?= $settingsCount ?></div>
        </div>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
        <div style="background:#dcfce7; color:var(--success); padding:15px; border-radius:12px; margin-bottom:20px; border:1px solid #bbf7d0; font-weight:600;">
            ✅ Data berhasil dihapus secara permanen.
        </div>
    <?php endif; ?>

    <?php if (($_GET['error'] ?? '') === 'active'): ?>
        <div style="background:#fee2e2; color:var(--danger); padding:15px; border-radius:12px; margin-bottom:20px; border:1px solid #fecaca; font-weight:600;">
            ⚠️ Gagal! Nonaktifkan provider terlebih dahulu sebelum menghapus.
        </div>
    <?php endif; ?>

    <section class="card" style="margin-bottom: 25px; padding: 0; overflow: hidden;">
        <div class="page-header" style="padding: 20px 20px 0; margin-bottom: 15px;">
            <h3 style="border:none; margin:0;">🤖 AI Providers</h3>
            <a href="ai-editor.php" class="btn-create">+ Add Provider</a>
        </div>
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: var(--bg-light); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 15px; text-align: left;">Provider Name</th>
                        <th style="padding: 15px; text-align: left;">Model Version</th>
                        <th style="padding: 15px; text-align: left;">Status</th>
                        <th style="padding: 15px; text-align: left;">Last Updated</th>
                        <th style="padding: 15px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($aiProviders as $ai): ?>
                    <?php if (empty($ai['provider_name'])) continue; ?>
                    <tr style="border-bottom: 1px solid var(--bg-disabled);">
                        <td style="padding: 15px;"><strong><?= htmlspecialchars($ai['provider_name']) ?></strong></td>
                        <td style="padding: 15px;"><code style="background:var(--bg-disabled); padding:3px 6px; border-radius:4px;"><?= htmlspecialchars($ai['model']) ?></code></td>
                        <td style="padding: 15px;"><span class="status-badge status-<?= strtolower($ai['status']) ?>"><?= $ai['status'] ?></span></td>
                        <td style="padding: 15px; font-size: 0.85em; color: var(--text-muted);"><?= date('d M Y, H:i', strtotime($ai['updated_at'])) ?></td>
                        <td style="padding: 15px; text-align: center;">
                            <div style="display:flex; gap:5px; justify-content:center;">
                                <a href="ai-editor.php?id=<?= $ai['id'] ?>" class="btn-sm" style="text-decoration:none; color:var(--primary-color);">Edit</a>
                                <a href="ai-delete.php?id=<?= $ai['id'] ?>" class="btn-sm" style="text-decoration:none; color:var(--danger); border-color: #fecaca;" onclick="return confirm('Hapus AI Provider ini?')">Hapus</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card" style="margin-bottom: 25px; padding: 0; overflow: hidden;">
        <div class="page-header" style="padding: 20px 20px 0; margin-bottom: 15px;">
            <h3 style="border:none; margin:0;">📲 Message Channels</h3>
            <a href="message-providers.php" class="btn-create">+ Add Channel</a>
        </div>
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse;">
                <thead style="background: var(--bg-light); border-bottom: 1px solid var(--border-color);">
                    <tr>
                        <th style="padding: 15px; text-align: left;">Channel</th>
                        <th style="padding: 15px; text-align: left;">Active Provider</th>
                        <th style="padding: 15px; text-align: left;">Status</th>
                        <th style="padding: 15px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($msgProviders as $mp): ?>
                    <tr style="border-bottom: 1px solid var(--bg-disabled);">
                        <td style="padding: 15px;">
                            <span style="display:flex; align-items:center; gap:8px; font-weight:600;">
                                <?= ucfirst($mp['channel']) ?>
                            </span>
                        </td>
                        <td style="padding: 15px;">
                            <strong><?= htmlspecialchars($mp['provider_name']) ?></strong>
                            <br><small style="color:var(--text-muted)"><?= ($mp['provider_name'] == 'meta') ? 'Official API' : 'Third-party' ?></small>
                        </td>
                        <td style="padding: 15px;"><span class="status-badge status-<?= strtolower($mp['status']) ?>"><?= $mp['status'] ?></span></td>
                        <td style="padding: 15px; text-align: center;">
                            <a href="message-providers.php?id=<?= $mp['id'] ?>" class="btn-sm" style="text-decoration:none; color:var(--primary-color);">Configure API</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card card-full" style="background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);">
        <div class="page-header" style="margin-bottom:15px;">
            <div>
                <h3 style="border:none; margin:0;">⚙️ Global System Settings</h3>
                <p style="margin:5px 0 0; font-size:0.85em; color:var(--text-muted);">SMTP, Payment, dan Master Sheet ID.</p>
            </div>
            <a href="system-settings.php" class="btn-create" style="background:var(--text-main);">Open Settings</a>
        </div>
        <div style="display:flex; flex-wrap:wrap; gap:10px;">
            <div class="stat-box" style="background:white; flex:1; min-width:200px; text-align:left;">
                <small style="color:var(--text-muted);">Master Sheet:</small><br>
                <code><?= setting('master-sheet-id','Not Set') ?></code>
            </div>
            <div class="stat-box" style="background:white; flex:1; min-width:200px; text-align:left;">
                <small style="color:var(--text-muted);">WA Provider:</small><br>
                <strong><?= strtoupper(setting('whatsapp_provider','-')) ?></strong>
            </div>
        </div>
    </section>

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

<?php include "footer.php"; ?>
