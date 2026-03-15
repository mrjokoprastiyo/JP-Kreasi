<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/settings-loader.php';

Auth::check();

/* ===============================
   HANDLE SAVE + UPLOAD
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* ========= UPLOAD LOGO ========= */
    if (!empty($_FILES['site-logo']['name'])) {

        $allowed = ['image/png','image/jpeg','image/jpg','image/svg+xml'];
        $file    = $_FILES['site-logo'];

        if (in_array($file['type'], $allowed)) {

            $extX  = pathinfo($file['name'], PATHINFO_EXTENSION);
            $nameX = 'logo.' . $ext;

$ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
$name = 'logo_' . time() . '.' . $ext;

            $dir  = __DIR__ . '/../uploads/site/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $path = $dir . $name;
            move_uploaded_file($file['tmp_name'], $path);

            // simpan ke settings
            DB::query(
                "INSERT INTO settings (setting_key, setting_value)
                 VALUES ('site-logo', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                ['/uploads/site/' . $name]
            );
        }
    }

    /* ========= SAVE SETTINGS ========= */
    foreach ($_POST as $key => $value) {

        if ($key === 'site-logo') continue;

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        DB::query(
            "INSERT INTO settings (setting_key, setting_value)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$key, $value]
        );
    }

    header("Location: system-settings.php?success=1");
    exit;
}

include "header.php";
include "sidebar.php";
?>

<main class="user-content">

<div class="settings-container">
    <form action="" method="POST" enctype="multipart/form-data">
        <h2 style="margin-bottom: 25px;">⚙️ System Settings</h2>

        <div class="settings-grid">
            
            <section class="card">
                <h3>🖼️ Site Identity</h3>
                <div class="form-group">
                    <label>Site Name</label>
                    <input type="text" name="site-name" placeholder="Nama aplikasi" value="<?= setting('site-name','JP System') ?>">
                </div>
                <div class="form-group">
                    <label>Site Tagline</label>
                    <input type="text" name="site-tagline" placeholder="Automation Platform" value="<?= setting('site-tagline','Automation & AI Platform') ?>">
                </div>
                <div class="form-group">
                    <label>Site Description</label>
                    <input type="text" name="site-description" placeholder="Site Description...." value="<?= setting('site-description','Site Description....') ?>">
                </div>
                <div class="form-group">
                    <label>Site Logo</label>
                    <div style="margin-bottom:10px">
                        <?php if ($logo = setting('site-logo')): ?>
                            <img id="logoPreview" src="<?= e($logo) ?>" alt="Logo" style="max-height:50px; border-radius:4px;">
                        <?php else: ?>
                            <img id="logoPreview" style="display:none; max-height:50px; border-radius:4px;">
                        <?php endif; ?>
                    </div>
                    <input type="file" name="site-logo" accept="image/*" onchange="previewLogo(this)">
                    <small>PNG/JPG/SVG. Tinggi ideal ±32–60px.</small>
                </div>
            </section>

            <section class="card">
                <h3>🔐 Email Verification (OTP)</h3>
                <div class="form-group">
                    <label>Enable OTP</label>
                    <select name="email-otp-enabled">
                        <option value="1" <?= setting('email-otp-enabled')=='1'?'selected':'' ?>>Yes, Active</option>
                        <option value="0" <?= setting('email-otp-enabled')=='0'?'selected':'' ?>>No, Disable</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>OTP Expired (minutes)</label>
                    <input type="number" name="email-otp-expired_minutes" value="<?= setting('email-otp-expired_minutes','10') ?>">
                </div>
            </section>

            <section class="card card-full">
                <h3>📧 Email SMTP (Mailing System)</h3>
                <div class="settings-grid" style="margin-bottom:0; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                    <div class="form-group">
                        <label>SMTP Host</label>
                        <input type="text" name="email-smtp-host" value="<?= setting('email-smtp-host') ?>">
                    </div>
                    <div class="form-group">
                        <label>SMTP Port</label>
                        <input type="number" name="email-smtp-port" value="<?= setting('email-smtp-port','587') ?>">
                    </div>
                    <div class="form-group">
                        <label>Encryption</label>
                        <select name="email-smtp-encryption">
                            <option value="tls" <?= setting('email-smtp-encryption')=='tls'?'selected':'' ?>>TLS</option>
                            <option value="ssl" <?= setting('email-smtp-encryption')=='ssl'?'selected':'' ?>>SSL</option>
                        </select>
                    </div>
                </div>
                <div class="settings-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                    <div class="form-group">
                        <label>SMTP Username</label>
                        <input type="text" name="email-smtp-username" value="<?= setting('email-smtp-username') ?>">
                    </div>
                    <div class="form-group">
                        <label>SMTP Password</label>
                        <input type="password" name="email-smtp-password" value="<?= setting('email-smtp-password') ?>">
                    </div>
                </div>
                <div class="settings-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                    <div class="form-group">
                        <label>From Name</label>
                        <input type="text" name="email-from-name" value="<?= setting('email-from-name','JP System') ?>">
                    </div>
                    <div class="form-group">
                        <label>From Address</label>
                        <input type="email" name="email-from-address" value="<?= setting('email-from-address','noreply@domain.com') ?>">
                    </div>
                </div>
            </section>

            <section class="card">
                <h3>💳 Payment – PayPal</h3>
                <div class="form-group">
                    <label>Enable PayPal</label>
                    <select name="payment-paypal-enabled">
                        <option value="1" <?= setting('payment-paypal-enabled')=='1'?'selected':'' ?>>Active</option>
                        <option value="0" <?= setting('payment-paypal-enabled')=='0'?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Client ID</label>
                    <input type="text" name="payment-paypal-client_id" value="<?= setting('payment-paypal-client_id') ?>">
                </div>
                <div class="form-group">
                    <label>Secret</label>
                    <input type="password" name="payment-paypal-secret" value="<?= setting('payment-paypal-secret') ?>">
                </div>
                <div class="form-group">
                    <label>Mode</label>
                    <select name="payment-paypal-mode">
                        <option value="sandbox" <?= setting('payment-paypal-mode')=='sandbox'?'selected':'' ?>>Sandbox</option>
                        <option value="live" <?= setting('payment-paypal-mode')=='live'?'selected':'' ?>>Live</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Webhook URL (PayPal)</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="text" id="ppWebhookUrl" class="input-disabled" readonly 
                               value="https://<?= $_SERVER['HTTP_HOST'] ?>/api/webhook/paypal.php">
                        <button type="button" class="btn-create" onclick="copyToClipboard('ppWebhookUrl')" style="padding: 0 10px; font-size: 0.75rem; white-space: nowrap;">📋 Copy</button>
                    </div>
                    <small>Daftarkan untuk event: <b>CHECKOUT.ORDER.APPROVED</b></small>
                </div>
            </section>

            <section class="card">
                <h3>🇮🇩 Payment – Doku (Jokul)</h3>
                <div class="form-group">
                    <label>Enable Doku</label>
                    <select name="payment-doku-enabled">
                        <option value="1" <?= setting('payment-doku-enabled')=='1'?'selected':'' ?>>Active</option>
                        <option value="0" <?= setting('payment-doku-enabled')=='0'?'selected':'' ?>>Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Client ID</label>
                    <input type="text" name="payment-doku-client_id" value="<?= setting('payment-doku-client_id') ?>" placeholder="M-XXXXX">
                </div>
                <div class="form-group">
                    <label>Shared Key (Secret Key)</label>
                    <input type="password" name="payment-doku-shared_key" value="<?= setting('payment-doku-shared_key') ?>" placeholder="SK-XXXXX">
                </div>
                <div class="form-group">
                    <label>Mode</label>
                    <select name="payment-doku-mode">
                        <option value="sandbox" <?= setting('payment-doku-mode')=='sandbox'?'selected':'' ?>>Sandbox (Testing)</option>
                        <option value="live" <?= setting('payment-doku-mode')=='live'?'selected':'' ?>>Live (Production)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Notification URL (Doku Webhook)</label>
                    <div style="display: flex; gap: 5px;">
                        <input type="text" id="dokuWebhookUrl" class="input-disabled" readonly 
                               value="https://<?= $_SERVER['HTTP_HOST'] ?>/api/webhook/doku.php">
                        <button type="button" class="btn-create" onclick="copyToClipboard('dokuWebhookUrl')" style="padding: 0 10px; font-size: 0.75rem; white-space: nowrap;">📋 Copy</button>
                    </div>
                    <small>Set "URL Notification" di dashboard Jokul ke URL ini.</small>
                </div>
            </section>

            <section class="card card-full">
                <h3>💰 Payment Fees & Tax Configuration</h3>
    
                <div class="settings-grid" style="grid-template-columns: 1fr 1fr; gap: 30px;">
        
                    <div class="fee-group">
                        <h4 style="color: #e11919; margin-bottom: 15px; border-bottom: 2px solid #fecaca; padding-bottom: 5px;">🇮🇩 Doku / Local Payment</h4>
                        <div class="form-group">
                            <label>Doku Tax (PPN %)</label>
                            <input type="number" step="0.01" name="payment-doku-tax-percent" value="<?= setting('payment-doku-tax-percent', '11') ?>">
                            <small>Default PPN Indonesia (11%)</small>
                        </div>
                        <div class="form-group">
                            <label>Doku Flat Fee (IDR)</label>
                            <input type="number" name="payment-doku-fee-flat" value="<?= setting('payment-doku-fee-flat', '4500') ?>">
                            <small>Biaya admin tetap (Contoh: MDR QRIS/VA)</small>
                        </div>
                        <div class="form-group">
                            <label>Doku Variable Fee (%)</label>
                            <input type="number" step="0.01" name="payment-doku-fee-percent" value="<?= setting('payment-doku-fee-percent', '0') ?>">
                            <small>Tambahan fee persentase jika ada.</small>
                        </div>
                    </div>

                    <div class="fee-group">
                        <h4 style="color: #0070ba; margin-bottom: 15px; border-bottom: 2px solid #bfe3f5; padding-bottom: 5px;">🌎 PayPal / Global Payment</h4>
                        <div class="form-group">
                            <label>PayPal Tax (%)</label>
                            <input type="number" step="0.01" name="payment-paypal-tax-percent" value="<?= setting('payment-paypal-tax-percent', '0') ?>">
                            <small>Pajak internasional (biasanya 0 jika sudah inklusif)</small>
                        </div>
                        <div class="form-group">
                            <label>PayPal Flat Fee (USD)</label>
                            <input type="number" step="0.01" name="payment-paypal-fee-flat" value="<?= setting('payment-paypal-fee-flat', '0.30') ?>">
                            <small>Fixed fee PayPal (Contoh: $0.30)</small>
                        </div>
                        <div class="form-group">
                            <label>PayPal Variable Fee (%)</label>
                            <input type="number" step="0.01" name="payment-paypal-fee-percent" value="<?= setting('payment-paypal-fee-percent', '4.4') ?>">
                            <small>Fee PayPal Internasional (Rata-rata 4.4%)</small>
                        </div>
                    </div>

                </div>
            </section>

            <section class="card card-full">
                <h3>🔗 Integration & Automation</h3>
                <div class="form-group">
                    <label>Chatbot Widget Script URL</label>
                    <input type="text" name="integration-chatbot-widget_script_url" value="<?= setting('integration-chatbot-widget_script_url') ?>" placeholder="http://domain.com/jp-chatbot-widget.js">
                </div>
                <div class="form-group">
                    <label>Automation Webhook Endpoint</label>
                    <input type="text" name="integration-automation-webhook_url" value="<?= setting('integration-automation-webhook_url') ?>" placeholder="https://domain.com/api/automation">
                </div>
                <div class="form-group">
                    <label>Master Google Sheet ID</label>
                    <input type="text" name="integration-automation-master_sheet_id" value="<?= setting('integration-automation-master_sheet_id') ?>" placeholder="1abc123... (Hanya ID)">
                    <small>ID ini digunakan sebagai master template saat client melakukan Copy Sheet.</small>
                </div>
            </section>

            <section class="card card-full">
                <h3>💙 Meta Developer Platform (Facebook & WhatsApp)</h3>
                <div class="settings-grid" style="margin-bottom:0; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                    <div class="form-group">
                        <label>Meta App ID</label>
                        <input type="text" name="meta-app-id" value="<?= setting('meta-app-id') ?>" placeholder="Contoh: 123456789012345">
                        <small>Didapatkan dari Dashboard Meta Developer.</small>
                    </div>
                    <div class="form-group">
                        <label>Meta App Secret</label>
                        <input type="password" name="meta-app-secret" value="<?= setting('meta-app-secret') ?>" placeholder="••••••••">
                        <small>Digunakan untuk validasi keamanan X-Hub-Signature-256.</small>
                    </div>
                    <div class="form-group">
                        <label>Meta App Version</label>
                        <input type="text" name="meta-app-version" value="<?= setting('meta-app-version') ?>" placeholder="v18.0">
                        
                    </div>
                    <div class="form-group">
                        <label>Global Verify Token</label>
                        <input type="text" name="meta-verify-token" value="<?= setting('meta-verify-token', 'GLOBAL_VERIFY_TOKEN') ?>">
                        <small>Samakan token ini dengan "Verify Token" di Dashboard Meta.</small>
                    </div>
                </div>
                
                <hr>
                
                <div class="form-group">
                    <label>Your Webhook URL (Callback URL)</label>
                    <div style="display: flex; gap: 10px;">
                        <input type="text" id="webhookUrl" class="input-disabled" readonly 
                               value="https://<?= $_SERVER['HTTP_HOST'] ?>/webhook.php">
                        <button type="button" class="btn-create" onclick="copyWebhook()" style="padding: 0 15px; font-size: 0.8em;">📋 Copy</button>
                    </div>
                    <small>Salin URL ini ke bagian Webhook di Facebook Login, Messenger, dan WhatsApp settings di Meta Dashboard.</small>
                </div>

                <div class="form-group">
                    <label>Facebook Login Callback URL</label>
                    <div style="display:flex; gap:10px;">
                        <input type="text" id="metaLoginCallback" class="input-disabled" readonly 
                               value="https://<?= $_SERVER['HTTP_HOST'] ?>/user/meta-callback.php">
                        <button type="button" class="btn-create" onclick="copyToClipboard('metaLoginCallback')" style="padding:0 15px;font-size:0.8em;">📋 Copy</button>
                    </div>
                    <small>Masukkan URL ini ke <b>Facebook Login → Valid OAuth Redirect URI</b> di Meta Developer Dashboard.</small>
                </div>
            </section>

            <section class="card">
                <h3>📱 WhatsApp Provider Settings</h3>
    
                <div class="form-group">
                    <label>Active Provider</label>
                    <select name="whatsapp_provider" id="waProviderSelect" onchange="toggleWAFields()">
                        <option value="meta" <?= setting('whatsapp_provider')=='meta'?'selected':'' ?>>Meta Business Suite (Official API)</option>
                        <option value="fonnte" <?= setting('whatsapp_provider')=='fonnte'?'selected':'' ?>>Fonnte</option>
                        <option value="wablas" <?= setting('whatsapp_provider')=='wablas'?'selected':'' ?>>Wablas</option>
                        <option value="twilio" <?= setting('whatsapp_provider')=='twilio'?'selected':'' ?>>Twilio</option>
                    </select>
                </div>

                <hr>

                <div class="wa-field meta" style="display:none;">
                    <div class="form-group">
                        <label>Meta Access Token</label>
                        <textarea name="wa_meta_token" rows="2" placeholder="EAAB... (Permanent Token)"><?= setting('wa_meta_token') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Phone Number ID</label>
                        <input type="text" name="wa_meta_phone_id" value="<?= setting('wa_meta_phone_id') ?>" placeholder="Contoh: 106555xxxxx">
                    </div>
                    <div class="form-group">
                        <label>WhatsApp Business Account ID (WABA ID)</label>
                        <input type="text" name="wa_meta_waba_id" value="<?= setting('wa_meta_waba_id') ?>" placeholder="Contoh: 10444xxxxx">
                    </div>
                    <div class="form-group">
                        <label>Graph API Version</label>
                        <input type="text" name="wa_meta_version" value="<?= setting('wa_meta_version', 'v18.0') ?>">
                    </div>
                </div>

                <div class="wa-field fonnte" style="display:none;">
                    <div class="form-group">
                        <label>Fonnte API Token</label>
                        <input type="text" name="wa_fonnte_token" value="<?= setting('wa_fonnte_token') ?>" placeholder="Masukkan Token Fonnte">
                    </div>
                    <div class="form-group">
                        <label>Fonnte Endpoint URL</label>
                        <input type="text" name="wa_fonnte_url" value="<?= setting('wa_fonnte_url', 'https://api.fonnte.com/send') ?>">
                    </div>
                </div>

                <div class="wa-field wablas" style="display:none;">
                    <div class="form-group">
                        <label>Wablas API Token</label>
                        <input type="text" name="wa_wablas_token" value="<?= setting('wa_wablas_token') ?>" placeholder="Masukkan Token Wablas">
                    </div>
                    <div class="form-group">
                        <label>Wablas Server URL</label>
                        <input type="text" name="wa_wablas_url" value="<?= setting('wa_wablas_url') ?>" placeholder="Contoh: https://jakarta.wablas.com">
                        <small>Pilih server sesuai lokasi device Anda di dashboard Wablas.</small>
                    </div>
                </div>

                <div class="wa-field twilio" style="display:none;">
                    <div class="form-group">
                        <label>Twilio Account SID</label>
                        <input type="text" name="wa_twilio_sid" value="<?= setting('wa_twilio_sid') ?>" placeholder="ACxxxxxxxxxxxxxxxx">
                    </div>
                    <div class="form-group">
                        <label>Twilio Auth Token</label>
                        <input type="text" name="wa_twilio_token" value="<?= setting('wa_twilio_token') ?>" placeholder="Masukkan Auth Token">
                    </div>
                    <div class="form-group">
                        <label>Twilio "From" Number (WhatsApp)</label>
                        <input type="text" name="wa_twilio_from" value="<?= setting('wa_twilio_from') ?>" placeholder="whatsapp:+14155xxxx">
                    </div>
                </div>
            </section>

            <section class="card">
                <h3>📞 Admin Contact</h3>
                <div class="form-group">
                    <label>No WhatsApp Admin</label>
                    <input type="text" name="admin-contact-whatsapp" placeholder="628123xxx" value="<?= setting('admin-contact-whatsapp') ?>">
                    <small>Format internasional tanpa tanda +</small>
                </div>
                <div class="form-group">
                    <label>Username Messenger Admin</label>
                    <input type="text" name="admin-contact-messenger_username" placeholder="johndoe.admin" value="<?= setting('admin-contact-messenger_username') ?>">
                </div>
            </section>

        </div>

        <button type="submit" class="btn-save">💾 Save All Settings</button>
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

   small {
        display: block;
        margin-top: 5px;
        color: var(--text-muted);
        font-size: 0.75em;
    }

    hr {
        border: 0;
        border-top: 1px solid #f1f5f9;
        margin: 20px 0;
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
function previewLogo(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var preview = document.getElementById('logoPreview');
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function copyToClipboard(elementId) {
    var copyText = document.getElementById(elementId);
    copyText.select();
    copyText.setSelectionRange(0, 99999); // Untuk mobile
    navigator.clipboard.writeText(copyText.value).then(() => {
        alert("URL Berhasil disalin: " + copyText.value);
    });
}

function toggleWAFields() {
    const provider = document.getElementById('waProviderSelect').value;
    document.querySelectorAll('.wa-field').forEach(el => el.style.display = 'none');
    const activeField = document.querySelector('.wa-field.' + provider);
    if (activeField) activeField.style.display = 'block';
}

// Jalankan saat halaman load
document.addEventListener('DOMContentLoaded', toggleWAFields);
</script>

<script>
/**
 * Fungsi untuk menyembunyikan/menampilkan kolom berdasarkan provider yang dipilih
 */
function toggleWAFields() {
    const selectedProvider = document.getElementById('waProviderSelect').value;
    
    // Ambil semua elemen dengan class 'wa-field'
    const allFields = document.querySelectorAll('.wa-field');
    
    // Sembunyikan semua terlebih dahulu
    allFields.forEach(field => {
        field.style.display = 'none';
    });

    // Tampilkan field yang sesuai dengan value dropdown
    const targetFields = document.querySelectorAll('.wa-field.' + selectedProvider);
    targetFields.forEach(field => {
        field.style.display = 'block';
    });
}

// Jalankan saat pertama kali halaman dibuka (untuk load data existing)
document.addEventListener('DOMContentLoaded', toggleWAFields);
</script>

<?php include "footer.php"; ?>