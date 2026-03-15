<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

/* ===============================
   AMBIL INPUT
================================ */
$provider_id   = $_POST['provider_id'] ?? null;
$channel       = trim($_POST['channel'] ?? '');
$slug          = trim($_POST['provider_slug'] ?? '');
$name          = trim($_POST['provider_name'] ?? '');
$status        = $_POST['status'] ?? 'inactive';
$cred          = $_POST['cred'] ?? [];
$webhook_url   = $_POST['webhook_url'] ?? null;
$webhook_sec   = $_POST['webhook_secret'] ?? null;

/* ===============================
   VALIDASI DASAR
================================ */
if (!$channel) {
    die('Channel wajib diisi');
}

if (!$provider_id && (!$slug || !$name)) {
    die('Provider slug & name wajib diisi');
}

/* ===============================
   NORMALISASI CREDENTIAL
================================ */
$credentials = [
    'channel' => $channel
];

/* ===============================
   VALIDASI PER CHANNEL
================================ */
switch ($channel) {

    /* ========= MESSENGER ========= */
    case 'messenger':
        $required = ['page_id','app_id','app_secret','access_token','verify_token'];
        foreach ($required as $f) {
            if (empty($cred[$f])) {
                die("Messenger: {$f} wajib diisi");
            }
        }

        $credentials['messenger'] = [
            'page_id'       => $cred['page_id'],
            'app_id'        => $cred['app_id'],
            'app_secret'    => $cred['app_secret'],
            'access_token'  => $cred['access_token'],
            'verify_token'  => $cred['verify_token'],
            'app_mode'      => $cred['app_mode'] ?? 'dev'
        ];
        break;

    /* ========= TELEGRAM ========= */
    case 'telegram':
        if (empty($cred['bot_token'])) {
            die('Telegram: bot_token wajib diisi');
        }

        $credentials['telegram'] = [
            'bot_username' => $cred['bot_username'] ?? null,
            'bot_token'    => $cred['bot_token'],
            'parse_mode'   => $cred['parse_mode'] ?? 'HTML'
        ];
        break;

    /* ========= WHATSAPP ========= */
    case 'whatsapp':
        $type = $cred['provider_type'] ?? null;
        if (!$type) {
            die('WhatsApp: provider_type wajib diisi');
        }

        $credentials['provider_type'] = $type;

        /* ---- META CLOUD API ---- */
        if ($type === 'meta') {
            $meta = $cred['meta'] ?? [];
            $required = ['phone_number_id','business_account_id','access_token'];

            foreach ($required as $f) {
                if (empty($meta[$f])) {
                    die("WA Meta: {$f} wajib diisi");
                }
            }

            $credentials['meta'] = [
                'phone_number_id'     => $meta['phone_number_id'],
                'business_account_id'=> $meta['business_account_id'],
                'access_token'       => $meta['access_token'],
                'verify_token'       => $meta['verify_token'] ?? null,
                'api_version'        => $meta['api_version'] ?? 'v18.0'
            ];
        }

        /* ---- GATEWAY (FONNTE / WABLAS) ---- */
        if (in_array($type, ['fonnte','wablas'])) {
            $gw = $cred['gateway'] ?? [];
            $required = ['api_key','sender_id','base_url'];

            foreach ($required as $f) {
                if (empty($gw[$f])) {
                    die("WA {$type}: {$f} wajib diisi");
                }
            }

            $credentials['gateway'] = [
                'provider'  => $type,
                'api_key'   => $gw['api_key'],
                'sender_id' => $gw['sender_id'],
                'base_url'  => rtrim($gw['base_url'], '/')
            ];
        }

        break;

    /* ========= EMAIL ========= */
    case 'email':
        $required = ['smtp_host','smtp_port','smtp_user','smtp_pass','from_email'];
        foreach ($required as $f) {
            if (empty($cred[$f])) {
                die("Email: {$f} wajib diisi");
            }
        }

        $credentials['email'] = [
            'smtp_host' => $cred['smtp_host'],
            'smtp_port' => (int)$cred['smtp_port'],
            'smtp_user' => $cred['smtp_user'],
            'smtp_pass' => $cred['smtp_pass'],
            'encryption'=> $cred['encryption'] ?? 'tls',
            'from_name' => $cred['from_name'] ?? 'System',
            'from_email'=> $cred['from_email']
        ];
        break;

    default:
        die('Channel tidak didukung');
}

/* ===============================
   SIMPAN KE DATABASE
================================ */
$credentials_json = json_encode($credentials, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

if ($provider_id) {
    DB::execute("
        UPDATE message_providers SET
            channel = ?,
            provider_slug = ?,
            provider_name = ?,
            credentials = ?,
            status = ?,
            webhook_url = ?,
            webhook_secret = ?
        WHERE id = ?
    ", [
        $channel,
        $slug,
        $name,
        $credentials_json,
        $status,
        $webhook_url,
        $webhook_sec,
        $provider_id
    ]);
} else {
    DB::execute("
        INSERT INTO message_providers
        (channel, provider_slug, provider_name, credentials, status, webhook_url, webhook_secret)
        VALUES (?,?,?,?,?,?,?)
    ", [
        $channel,
        $slug,
        $name,
        $credentials_json,
        $status,
        $webhook_url,
        $webhook_sec
    ]);
}

header('Location: message-providers.php?success=1');
exit;