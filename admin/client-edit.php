<?php
/* ======================================================
   BOOTSTRAP
====================================================== */
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/settings-loader.php';
require_once '../core/helpers/html.php';

Auth::check();

$user_id = $_SESSION['user']['id'];

/* ======================================================
   HELPER FUNCTIONS
====================================================== */
function ex($v) {
    return htmlspecialchars($v ?? '');
}

function sc($key, $default = '') {
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
if (!$client_id) die('Client tidak ditemukan');

$client = DB::fetch(
    "SELECT * FROM clients WHERE id = ? AND user_id = ?",
    [$client_id, $user_id]
);

if (!$client) die('Client tidak valid');

$product = DB::fetch(
    "SELECT * FROM products WHERE id = ?",
    [$client['product_id']]
);

if (!$product) die('Produk tidak valid');

$flow = resolveFlow($product);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    match ($flow) {
        'CHATBOT_WEB' => updateChatbotWeb($client_id, $product),
        'CHATBOT_CHANNEL' => updateChatbotChannel($client_id, $product),
        'AUTOMATION_NOTIFICATION' => updateAutomationNotification($client_id, $product),
        default => die('Flow tidak valid')
    };
}

/* ===============================
   UPDATE FUNCTIONS
================================ */

/* -------- CHATBOT WEBSITE -------- */
function updateChatbotWeb(int $client_id, array $product): void
{
    $current = DB::fetch("SELECT * FROM clients WHERE id = ?", [$client_id]);
    if (!$current) {
        die('Client tidak ditemukan');
    }

    $action = $_POST['action'] ?? 'save';

    /* =========================================
       1️⃣ KHUSUS: ADMIN UBAH STATUS SAJA
    ========================================= */
    if ($action === 'client_status') {

        if (!Auth::isAdmin()) {
            die('Unauthorized');
        }

        $status     = $_POST['client_status'] ?? $current['status'];
        $expired_at = $_POST['client_expired_at'] ?: null;

        DB::exec(
            "UPDATE clients
             SET status = ?, expired_at = ?, updated_at = NOW()
             WHERE id = ?",
            [$status, $expired_at, $client_id]
        );

        redirect("client-detail.php?id={$client_id}&status_updated=1");
        exit;
    }

    /* =========================================
       2️⃣ NORMAL SAVE (TANPA SENTUH STATUS)
    ========================================= */

    $ai_config_id = (int)($_POST['ai_config_id'] ?? 0);
    if ($ai_config_id <= 0) {
        die('AI Configuration tidak valid');
    }

    // Boolean normalization
    $notif_badge         = isset($_POST['notif_badge']) ? 1 : 0;
    $notif_popup         = isset($_POST['notif_popup']) ? 1 : 0;
    $notif_sound_enabled = isset($_POST['notif_sound_enabled']) ? 1 : 0;

    // Upload asset
    $avatar = uploadAsset('bot_avatar', 'avatar', ['jpg','jpeg','png'], 1024 * 1024);
    $icon   = uploadAsset('widget_icon', 'icon', ['svg','png'], 200 * 1024);
    $sound  = uploadAsset('notif_sound', 'sound', ['mp3'], 2 * 1024 * 1024);

    DB::exec(
        "UPDATE clients SET
            name                = ?,
            domain              = ?,
            ai_config_id        = ?,
            prompt              = ?,
            bot_name            = ?,
            bot_desc            = ?,
            bot_avatar          = ?,
            bot_greeting        = ?,
            widget_icon         = ?,
            widget_background   = ?,
            notif_badge         = ?,
            notif_popup         = ?,
            notif_sound_enabled = ?,
            notif_sound         = ?,
            updated_at          = NOW()
         WHERE id = ?",
        [
            $_POST['name'],
            $_POST['domain'],
            $ai_config_id,
            $_POST['prompt'],
            $_POST['bot_name'],
            $_POST['bot_desc'],
            $avatar ?? $current['bot_avatar'],
            $_POST['bot_greeting'],
            $icon   ?? $current['widget_icon'],
            $_POST['widget_background'],
            $notif_badge,
            $notif_popup,
            $notif_sound_enabled,
            $sound ?? $current['notif_sound'],
            $client_id
        ]
    );

    redirect("client-detail.php?id={$client_id}&updated=1");
    exit;
}

/* -------- CHATBOT CHANNEL -------- */
function updateChatbotChannel(int $client_id, array $product): void
{
    $current = DB::fetch("SELECT * FROM clients WHERE id = ?", [$client_id]);
    if (!$current) {
        die('Client tidak ditemukan');
    }

    $action = $_POST['action'] ?? 'save';

    /* =========================================
       1️⃣ KHUSUS: ADMIN UBAH STATUS SAJA
    ========================================= */
    if ($action === 'client_status') {

        if (!Auth::isAdmin()) {
            die('Unauthorized');
        }

        $status     = $_POST['client_status'] ?? $current['status'];
        $expired_at = $_POST['client_expired_at'] ?: null;

        DB::exec(
            "UPDATE clients
             SET status = ?, expired_at = ?, updated_at = NOW()
             WHERE id = ?",
            [$status, $expired_at, $client_id]
        );

        redirect("client-detail.php?id={$client_id}&status_updated=1");
        exit;
    }

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
        UPDATE clients SET
            name        = ?,
            provider    = ?,
            credentials = ?,
            updated_at  = NOW()
        WHERE id = ?
    ", [
        $_POST['name'],
        $_POST['provider'] ?? 'meta',
        json_encode($credentials),
        $client_id
    ]);

    redirect("client-detail.php?id={$client_id}&status_updated=1");
    exit;
}

/* -------- AUTOMATION NOTIFICATION -------- */
function updateAutomationNotification(int $client_id, array $product): void
{
    $current = DB::fetch("SELECT * FROM clients WHERE id = ?", [$client_id]);
    if (!$current) {
        die('Client tidak ditemukan');
    }

    $action = $_POST['action'] ?? 'save';

    /* =========================================
       1️⃣ KHUSUS: ADMIN UBAH STATUS SAJA
    ========================================= */
    if ($action === 'client_status') {

        if (!Auth::isAdmin()) {
            die('Unauthorized');
        }

        $status     = $_POST['client_status'] ?? $current['status'];
        $expired_at = $_POST['client_expired_at'] ?: null;

        DB::exec(
            "UPDATE clients
             SET status = ?, expired_at = ?, updated_at = NOW()
             WHERE id = ?",
            [$status, $expired_at, $client_id]
        );

        redirect("client-detail.php?id={$client_id}&status_updated=1");
        exit;
    }

    if ($product['product_type'] === 'client') {

        $credentials = $_POST['config'] ?? [];

        if (empty($credentials)) {
            die('Konfigurasi automation tidak lengkap');
        }

    } else {
        $credentials = [
            'mode' => 'system',
            'note' => 'Token & sender dikelola sistem'
        ];
    }

    DB::execute("
        UPDATE clients SET
            name        = ?,
            provider    = ?,
            credentials = ?,
            updated_at  = NOW()
        WHERE id = ?
    ", [
        $_POST['name'],
        $_POST['provider'] ?? 'system',
        json_encode($credentials),
        $client_id
    ]);

    redirect("client-detail.php?id={$client_id}&status_updated=1");
    exit;
}

include 'header.php';
include 'sidebar.php';

match ($flow) {
    'DESIGN_ORDER' => include '../user/forms/design-order.php',
    'CHATBOT_WEB' => include '../user/forms/chatbot-web-edit.php',
    'CHATBOT_CHANNEL' => include '../user/forms/chatbot-channel-edit.php',
    'AUTOMATION_NOTIFICATION' => include '../user/forms/automation-edit.php',
    default => die('Form tidak tersedia')
};

include 'footer.php';