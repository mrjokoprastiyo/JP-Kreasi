<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

$providers = DB::fetchAll("
    SELECT * FROM message_providers
    ORDER BY channel ASC, provider_slug ASC
");

include "header.php";
include "sidebar.php";
?>

<main class="user-content">

<div class="settings-container">
    <?php if (isset($_GET['success'])): ?>
        <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #bbf7d0;">
            ✅ Data berhasil disimpan secara permanen.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
        <div style="background:#fee2e2; color:#991b1b; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #fecaca;">
            ⚠️ <?= htmlspecialchars($_GET['error']) ?>
        </div>
    <?php endif; ?>

    <form method="post" action="message-provider-save.php" id="providerForm">
        <h2 style="margin-bottom: 25px;">📲 Message Provider Configuration</h2>

        <div class="settings-grid">
            
            <section class="card">
                <h3>🛠️ Basic Configuration</h3>
                <div class="form-group">
                    <label>Channel *</label>
                    <select name="channel" id="channelSelect" required onchange="updateChannelFields()">
                        <option value="">-- Pilih Channel --</option>
                        <option value="messenger">Messenger</option>
                        <option value="whatsapp">WhatsApp</option>
                        <option value="telegram">Telegram</option>
                        <option value="email">Email</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Existing Provider (Optional)</label>
                    <select name="provider_id" id="providerSelect">
                        <option value="">+ Add New Provider</option>
                        <?php foreach ($providers as $p): ?>
                        <option value="<?= $p['id'] ?>" data-channel="<?= $p['channel'] ?>">
                            <?= strtoupper($p['channel']) ?> — <?= htmlspecialchars($p['provider_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr>

                <div class="form-group">
                    <label>Provider Slug *</label>
                    <input type="text" name="provider_slug" required placeholder="misal: meta-wa-official">
                </div>

                <div class="form-group">
                    <label>Provider Name *</label>
                    <input type="text" name="provider_name" required placeholder="misal: Meta Cloud API">
                </div>
            </section>

            <section class="card">
                <h3>🔗 Endpoint & Status</h3>
                <div class="form-group">
                    <label>Webhook URL</label>
                    <input type="text" name="webhook_url" placeholder="https://domain.com/webhook/callback">
                </div>

                <div class="form-group">
                    <label>Webhook Secret</label>
                    <input type="text" name="webhook_secret" placeholder="Secret Key">
                </div>

                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </section>

            <section class="card card-full" id="credentialFields">
                <h3>🔑 Credentials Details</h3>
                <p id="emptyPrompt" style="color: #64748b; font-size: 0.9em;">Silakan pilih channel terlebih dahulu untuk mengisi detail akun.</p>

                <div class="cred cred-messenger" hidden>
                    <div class="settings-grid" style="margin-bottom:0;">
                        <div class="form-group">
                            <label>Page ID *</label>
                            <input type="text" name="cred[page_id]">
                        </div>
                        <div class="form-group">
                            <label>App ID *</label>
                            <input type="text" name="cred[app_id]">
                        </div>
                        <div class="form-group">
                            <label>App Secret *</label>
                            <input type="text" name="cred[app_secret]">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Page Access Token *</label>
                        <textarea name="cred[access_token]" rows="3"></textarea>
                    </div>
                    <div class="settings-grid" style="margin-bottom:0;">
                        <div class="form-group">
                            <label>Verify Token *</label>
                            <input type="text" name="cred[verify_token]">
                        </div>
                        <div class="form-group">
                            <label>App Mode</label>
                            <select name="cred[app_mode]">
                                <option value="dev">Development</option>
                                <option value="live">Live</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="cred cred-telegram" hidden>
                    <div class="form-group">
                        <label>Bot Username *</label>
                        <input type="text" name="cred[bot_username]" placeholder="@MyCoolBot">
                    </div>
                    <div class="form-group">
                        <label>Bot Token *</label>
                        <textarea name="cred[bot_token]" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Parse Mode</label>
                        <select name="cred[parse_mode]">
                            <option value="HTML">HTML</option>
                            <option value="Markdown">Markdown</option>
                        </select>
                    </div>
                </div>

                <div class="cred cred-whatsapp" hidden>
                    <div class="form-group">
                        <label>WhatsApp Provider Type *</label>
                        <select name="cred[provider_type]" id="waType" onchange="updateWAType()">
                            <option value="">-- Pilih Tipe --</option>
                            <option value="meta">Meta Cloud API (Official)</option>
                            <option value="fonnte">Fonnte (Third-party)</option>
                            <option value="wablas">Wablas (Third-party)</option>
                        </select>
                    </div>

                    <div class="wa wa-meta" hidden>
                        <div class="settings-grid" style="margin-bottom:0;">
                            <div class="form-group">
                                <label>Phone Number ID *</label>
                                <input type="text" name="cred[meta][phone_number_id]">
                            </div>
                            <div class="form-group">
                                <label>Business Account ID *</label>
                                <input type="text" name="cred[meta][business_account_id]">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Access Token *</label>
                            <textarea name="cred[meta][access_token]" rows="3"></textarea>
                        </div>
                        <div class="settings-grid" style="margin-bottom:0;">
                            <div class="form-group">
                                <label>Verify Token *</label>
                                <input type="text" name="cred[meta][verify_token]">
                            </div>
                            <div class="form-group">
                                <label>API Version</label>
                                <input type="text" name="cred[meta][api_version]" value="v18.0">
                            </div>
                        </div>
                    </div>

                    <div class="wa wa-gateway" hidden>
                        <div class="form-group">
                            <label>API Key / Token *</label>
                            <textarea name="cred[gateway][api_key]" rows="2"></textarea>
                        </div>
                        <div class="settings-grid" style="margin-bottom:0;">
                            <div class="form-group">
                                <label>Sender ID / Phone Number *</label>
                                <input type="text" name="cred[gateway][sender_id]" placeholder="628123xxx">
                            </div>
                            <div class="form-group">
                                <label>Base API URL *</label>
                                <input type="text" name="cred[gateway][base_url]" placeholder="https://api.fonnte.com/send">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="cred cred-email" hidden>
                    <div class="settings-grid" style="margin-bottom:0;">
                        <div class="form-group">
                            <label>SMTP Host *</label>
                            <input type="text" name="cred[smtp_host]">
                        </div>
                        <div class="form-group">
                            <label>SMTP Port *</label>
                            <input type="number" name="cred[smtp_port]" value="587">
                        </div>
                        <div class="form-group">
                            <label>Encryption</label>
                            <select name="cred[encryption]">
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>
                    </div>
                    <div class="settings-grid" style="margin-bottom:0;">
                        <div class="form-group">
                            <label>SMTP User *</label>
                            <input type="text" name="cred[smtp_user]">
                        </div>
                        <div class="form-group">
                            <label>SMTP Password *</label>
                            <input type="password" name="cred[smtp_pass]">
                        </div>
                    </div>
                    <div class="settings-grid" style="margin-bottom:0;">
                        <div class="form-group">
                            <label>From Name *</label>
                            <input type="text" name="cred[from_name]">
                        </div>
                        <div class="form-group">
                            <label>From Email *</label>
                            <input type="email" name="cred[from_email]">
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <button type="submit" class="btn-save">💾 Save Provider Configuration</button>
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
function updateChannelFields() {
    const channel = document.getElementById('channelSelect').value;
    const creds = document.querySelectorAll('.cred');
    const prompt = document.getElementById('emptyPrompt');
    
    creds.forEach(c => c.hidden = true);
    prompt.style.display = 'block';

    if (channel) {
        const target = document.querySelector('.cred-' + channel);
        if (target) {
            target.hidden = false;
            prompt.style.display = 'none';
        }
    }
}

function updateWAType() {
    const waType = document.getElementById('waType').value;
    const waFields = document.querySelectorAll('.wa');
    
    waFields.forEach(f => f.hidden = true);

    if (waType === 'meta') {
        document.querySelector('.wa-meta').hidden = false;
    } else if (waType === 'fonnte' || waType === 'wablas') {
        document.querySelector('.wa-gateway').hidden = false;
        // Opsional: isi otomatis base URL jika Wablas/Fonnte
        const urlInput = document.querySelector('input[name="cred[gateway][base_url]"]');
        if (waType === 'fonnte') urlInput.value = "https://api.fonnte.com/send";
        if (waType === 'wablas') urlInput.value = "https://jakarta.wablas.com/api/send-message";
    }
}

// Inisialisasi awal
document.addEventListener('DOMContentLoaded', () => {
    updateChannelFields();
    updateWAType();
});
</script>

<?php include "footer.php"; ?>