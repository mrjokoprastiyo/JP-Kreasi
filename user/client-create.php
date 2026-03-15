<?php
/* ======================================================
   BOOTSTRAP
====================================================== */
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/settings-loader.php';

Auth::check();

$user_id = $_SESSION['user']['id'];

/* ======================================================
   HELPER FUNCTIONS
====================================================== */
function e($v) {
    return htmlspecialchars($v ?? '');
}

function s($key, $default = '') {
    return htmlspecialchars(setting($key) ?? $default);
}

/* ===============================
   AI CONFIG (ADMIN)
================================ */
$aiConfigs = DB::fetchAll("
    SELECT id, provider_name, model
    FROM ai_configs
    WHERE post_type = 'ai_provider'
      AND status = 'active'
    ORDER BY provider_name ASC, model ASC
");

$defaults = [
    'prompt'       => setting('chatbot-web-prompt'),
    'bot_name'     => setting('chatbot-web-bot_name'),
    'bot_desc'     => setting('chatbot-web-bot_desc'),
    'bot_greeting' => setting('chatbot-web-bot_greeting'),
    'widget_background'    => setting('chatbot-web-widget_background') ?: '#2563eb',
    'bot_avatar'   => setting('chatbot-web-bot_avatar'),
    'widget_icon'  => setting('chatbot-web-widget_icon'),
    'notif_sound'  => setting('chatbot-web-notif_sound'),
];

// ===============================
// FINAL PREVIEW ASSETS (CLIENT → SETTING)
// ===============================

$previewBotAvatar = $client['bot_avatar']
    ?: setting('chatbot-web-bot_avatar');

$previewWidgetIcon = $client['widget_icon']
    ?: setting('chatbot-web-widget_icon');

$previewNotifSound = $client['notif_sound']
    ?: setting('chatbot-web-notif_sound');

// normalize ke URL
$previewBotAvatar  = $previewBotAvatar  ? asset_url($previewBotAvatar)  : null;
$previewWidgetIcon = $previewWidgetIcon ? asset_url($previewWidgetIcon) : null;
$previewNotifSound = $previewNotifSound ? asset_url($previewNotifSound) : null;

/* ======================================================
   ASSET HELPERS
====================================================== */
function detect_scheme(): string {
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        ($_SERVER['SERVER_PORT'] ?? null) == 443 ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
         $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ) return 'https';
    return 'http';
}

function app_base_path(): string {
    $docRoot = rtrim(realpath($_SERVER['DOCUMENT_ROOT']), '/');
    $current = realpath(__DIR__);

    while ($current && strpos($current, $docRoot) === 0) {
        if (is_dir($current . '/user')) {
            return str_replace($docRoot, '', $current);
        }
        $parent = dirname($current);
        if ($parent === $current) break;
        $current = $parent;
    }
    return '';
}

function asset_base_dir(): string {
    return rtrim($_SERVER['DOCUMENT_ROOT'], '/') . app_base_path() . '/assets';
}

function asset_url(?string $path): string {
    if (!$path) return '';
    if (preg_match('#^https?://#i', $path)) return $path;

    return detect_scheme() . '://' . $_SERVER['HTTP_HOST']
         . app_base_path()
         . '/' . ltrim($path, '/');
}

function uploadAsset(
    string $field,
    string $subDir,
    array  $allowed,
    int    $maxSize
): ?string {

    if (empty($_FILES[$field]['name'])) return null;

    $file = $_FILES[$field];

    if ($file['size'] > $maxSize) {
        throw new Exception("File $field terlalu besar");
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) {
        throw new Exception("Format $field tidak didukung");
    }

    $baseDir = asset_base_dir() . '/' . trim($subDir, '/');
    if (!is_dir($baseDir)) mkdir($baseDir, 0755, true);

    $filename = uniqid($field . '_') . '.' . $ext;
    $target   = $baseDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception("Upload $field gagal");
    }

    return '/assets/' . trim($subDir, '/') . '/' . $filename;
}

function resolveFlow(array $product): string {
    if ($product['category'] === 'desain') return 'DESIGN_ORDER';

    if ($product['category'] === 'automation') {
        if ($product['sub_category'] === 'chatbot' && $product['service'] === 'website')
            return 'CHATBOT_WEB';

        if ($product['sub_category'] === 'chatbot')
            return 'CHATBOT_CHANNEL';

        if ($product['sub_category'] === 'notification')
            return 'AUTOMATION_NOTIFICATION';
    }

    throw new Exception('Flow tidak dikenali');
}

$client_id = $_GET['id'] ?? null;
// if (!$client_id) die('Client tidak ditemukan');

$client = DB::fetch(
    "SELECT * FROM clients WHERE id = ? AND user_id = ?",
    [$client_id, $user_id]
);

// if (!$client) die('Client tidak valid');

/* ===============================
   AMBIL PRODUK
================================ */
$product_id = $_GET['product_id'] ?? null;
if (!$product_id) {
    die('Produk tidak ditemukan');
}

$product = DB::fetch(
    "SELECT * FROM products WHERE id = ?",
    [$product_id]
);

if (!$product) {
    die('Produk tidak valid');
}

/**
 * Helper untuk menentukan status awal dan masa berlaku
 */
function determineInitialStatus(array $product): array
{
    $tier     = (int)($product['tier'] ?? 0);
    $duration = (int)($product['duration'] ?? 0);
    $now      = date('Y-m-d H:i:s');

    // Ada trial
    if ($tier > 0) {
        return [
            'status'      => 'pending', // BELUM BAYAR
            'expired_at'  => null,      // BELUM ADA MASA BERBAYAR
            'meta' => [
                'trial' => [
                    'started_at' => $now,
                    'ended_at'   => date('Y-m-d H:i:s', strtotime("+{$tier} days"))
                ]
            ]
        ];
    }

    // Tidak ada trial
    return [
        'status'     => 'pending',
        'expired_at' => null,
        'meta'       => []
    ];
}

/* ===============================
   GENERATE API KEY
================================ */
function generateApiKey(): string
{
    return bin2hex(random_bytes(24));
}

$flow = resolveFlow($product);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    match ($flow) {
        'DESIGN_ORDER' => handleDesignOrder($user_id, $product),
        'CHATBOT_WEB' => handleChatbotWeb($user_id, $product),
        'CHATBOT_CHANNEL' => handleChatbotChannel($user_id, $product),
        'AUTOMATION_NOTIFICATION' => handleAutomationNotification($user_id, $product),
        default => die('Flow tidak valid')
    };
}

/* ===============================
   HANDLER FUNCTIONS
================================ */

/* -------- DESIGN ORDER -------- */
function handleDesignOrder(int $user_id, array $product): void
{
    /* ===============================
       UPLOAD REFERENSI FILE
    ================================ */
    $referenceFile = null;

    try {
        $referenceFile = uploadAsset(
            'reference_file',
            'design-reference',
            ['jpg','jpeg','png','pdf','zip','rar'],
            5 * 1024 * 1024
        );
    } catch (Exception $e) {
        die($e->getMessage());
    }

    /* ===============================
       STATUS AWAL
    ================================ */
    $init = determineInitialStatus($product);

    /* ===============================
       SIMPAN KE CLIENTS
    ================================ */
    DB::execute("
        INSERT INTO clients (
            user_id,
            product_id,
            name,
            service,
            provider,
            credentials,
            status,
            expired_at,
            meta,
            api_key
        ) VALUES (?,?,?,?,?,?,?,?,?,?)
    ", [
        $user_id,
        $product['id'],

        // Nama client / project
        $_POST['customer_name'] . ' - ' . $_POST['design_type'],

        // DESIGN SERVICE
        $product['service'],
        'manual',

        // credentials → DETAIL PESANAN
        json_encode([
            'customer_name'  => $_POST['customer_name'],
            'customer_email' => $_POST['customer_email'],
            'customer_wa'    => $_POST['customer_wa'],

            'design_type' => $_POST['design_type'],
            'size'        => $_POST['size'],
            'reference'   => $_POST['reference'],
            'description' => $_POST['description'],
            'deadline'    => $_POST['deadline'],
            'note'        => $_POST['note'],

            'reference_file' => $referenceFile
        ], JSON_UNESCAPED_SLASHES),

        $init['status'],        // pending
        $init['expired_at'],    // null
        json_encode($init['meta']),
        generateApiKey()
    ]);

    $clientId = DB::lastId();

    /* ===============================
       REDIRECT KE WHATSAPP
    ================================ */
    $msg = "
🧾 ORDER DESAIN VISUAL
━━━━━━━━━━━━━━
Produk: {$product['name']}

Nama: {$_POST['customer_name']}
Email: {$_POST['customer_email']}
WA: {$_POST['customer_wa']}

Jenis: {$_POST['design_type']}
Ukuran: {$_POST['size']}
Referensi: {$_POST['reference']}

Deskripsi:
{$_POST['description']}

Deadline: {$_POST['deadline']}
Catatan:
{$_POST['note']}

Client ID: {$clientId}
━━━━━━━━━━━━━━
";

    $waAdmin = setting('admin-contact-whatsapp'); // nomor admin
    $waUrl = "https://api.whatsapp.com/send?phone={$waAdmin}&text=" . rawurlencode($msg);

    // KIRIM REDIRECT PAKSA
    echo "<!DOCTYPE html><html><head>";
    echo "<meta http-equiv='refresh' content='0;url=" . $waUrl . "'>";
    echo "</head><body>";
    echo "<script type='text/javascript'>window.location.href='" . $waUrl . "';</script>";
    echo "Pindah ke WhatsApp... <a href='" . $waUrl . "'>Klik di sini jika tidak otomatis</a>";
    echo "</body></html>";
    
    // ob_end_flush(); // Buang semua buffer ke browser
    exit; 
}

/* -------- CHATBOT WEBSITE -------- */
function handleChatbotWeb(int $user_id, array $product): void
{
$ai_config_id = (int) ($_POST['ai_config_id'] ?? 0);

if ($ai_config_id <= 0) {
    die('AI Configuration tidak valid');
}

// validasi ulang (anti manipulasi)
$exists = DB::fetch(
    "SELECT id FROM ai_configs WHERE id = ? AND status = 'active' LIMIT 1",
    [$ai_config_id]
);

if (!$exists) {
    die('AI Configuration tidak ditemukan');
}

    // ================= UPLOAD FILE =================
    $avatar = uploadAsset('bot_avatar', 'avatar', ['jpg','jpeg','png'], 1024 * 1024);
    $icon   = uploadAsset('widget_icon', 'icon', ['svg','png'], 200 * 1024);
    $sound  = uploadAsset('notif_sound', 'sound', ['mp3'], 2 * 1024 * 1024);

    $notif_badge = (int) setting('chatbot-web-notif_badge');
    $notif_popup = (int) setting('chatbot-web-notif_popup');
    $notif_sound_enabled = (int) setting('chatbot-web-notif_sound_enabled');

    // ================= INSERT =================
    $init = determineInitialStatus($product);

    DB::execute("
        INSERT INTO clients (
            user_id, product_id, name, domain,
            service, provider, credentials,
            ai_config_id,
            prompt, bot_name, bot_desc, bot_avatar, bot_greeting,
            widget_icon, widget_background, notif_badge, notif_popup, notif_sound_enabled, notif_sound,
            api_key, status, expired_at, meta
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ", [
        $user_id,
        $product['id'],
        $_POST['name'],
        $_POST['domain'],
        'web',
        'internal',
        json_encode([]),

        (int)$_POST['ai_config_id'],

        $_POST['prompt'],
        $_POST['bot_name'],
        $_POST['bot_desc'],
        $avatar,
        $_POST['bot_greeting'],
        $icon,
        $_POST['widget_background'],
        $notif_badge,
        $notif_popup,
        $notif_sound_enabled,
        $sound,

        generateApiKey(),
        $init['status'],
        $init['expired_at'],
        json_encode($init['meta'])
    ]);

    header("Location: client-detail.php?id=" . DB::lastId());
    exit;
}

/* -------- CHATBOT CHANNEL -------- */
function handleChatbotChannel(int $user_id, array $product): void
{
    $ai_provider = $_POST['ai_provider'] ?? '';
    $ai_model    = $_POST['ai_model'] ?? '';

    if (!validateAiConfig($ai_provider, $ai_model)) {
        die('AI Provider atau Model tidak valid');
    }

    $init = determineInitialStatus($product);

    $credentials = [];

    if ($product['service'] === 'messenger') {
        $credentials = [
            'page_name' => $_POST['page_name'] ?? null,
            'page_id'   => $_POST['page_id'] ?? null
        ];
    }

    if ($product['service'] === 'telegram') {
        $credentials = [
            'bot_username' => $_POST['bot_username'] ?? null
        ];
    }

    if ($product['service'] === 'whatsapp') {
        $credentials = [
            'note' => 'WA belum dikonfigurasi'
        ];
    }

DB::execute("
    INSERT INTO clients (
        user_id, product_id, name,
        service, provider, credentials,
        ai_config_id,
        api_key, status, expired_at, meta
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?)
", [
    $user_id,
    $product['id'],
    $_POST['name'],
    $product['service'],
    $_POST['provider'] ?? 'meta',
    json_encode($credentials),

    $ai_config_id,

    generateApiKey(),
    $init['status'],
    $init['expired_at'],
    json_encode($init['meta'])
]);

    header("Location: client-detail.php?id=" . DB::lastId());
    exit;
}

/* -------- AUTOMATION NOTIFICATION -------- */
function handleAutomationNotification(int $user_id, array $product): void
{
    $init = determineInitialStatus($product);

    $credentials = [];

    if ($product['product_type'] === 'client') {
        // client WAJIB isi token
        $credentials = $_POST['config'] ?? [];

        if (empty($credentials)) {
            die('Konfigurasi client tidak lengkap');
        }
    } else {
        // system managed → minimal config
        $credentials = [
            'mode' => 'system',
            'note' => 'Token & sender dikelola sistem'
        ];
    }

    DB::execute("
        INSERT INTO clients (
            user_id, product_id, name,
            service, provider, credentials,
            api_key, status, expired_at, meta
        ) VALUES (?,?,?,?,?,?,?,?,?,?)
    ", [
        $user_id,
        $product['id'],
        $_POST['name'],
        $product['service'],
        $_POST['provider'] ?? 'system',
        json_encode($credentials),

        generateApiKey(),
        $init['status'],
        $init['expired_at'],
        json_encode($init['meta'])
    ]);

    header("Location: client-detail.php?id=" . DB::lastId());
    exit;
}

include 'header.php';
include 'sidebar.php';

match ($flow) {
    'DESIGN_ORDER' => include 'forms/design-order.php',
    'CHATBOT_WEB' => include 'forms/chatbot-web.php',
    'CHATBOT_CHANNEL' => include 'forms/chatbot-channel.php',
    'AUTOMATION_NOTIFICATION' => include 'forms/automation.php',
    default => die('Form tidak tersedia')
};

include 'footer.php';