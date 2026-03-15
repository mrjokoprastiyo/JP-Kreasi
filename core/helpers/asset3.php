<?php
// 1. Pastikan User ID tersedia (Contoh dari session)
$user_id = $_SESSION['verify_user_id'] ?? 0;
$user_idX   = $_SESSION['user']['id'];
$client_id = (int)($_GET['id'] ?? 0);


// 2. Ambil data client
// Jika Admin ingin mengedit milik siapa saja, hapus "AND user_id=?"
$client = DB::fetch("SELECT * FROM clients WHERE id=? AND user_id=?", [
    $client_id,
    $user_id
]);

// Jika data tidak ditemukan
if (!$client) {
    // Debug: echo "Mencari ID: $client_id untuk User: $user_id"; 
    die("Data tidak ditemukan atau Anda tidak memiliki akses ke sini.");
}

/* ===============================
   AI CONFIG & DEFAULTS
================================ */
$aiConfigs = DB::fetchAll("
    SELECT id, provider_name, model
    FROM ai_configs
    WHERE post_type = 'ai_provider' AND status = 'active'
    ORDER BY provider_name ASC, model ASC
");

// Helper untuk mengambil setting dengan fallback
function sx($key) { return setting($key); }

/* ===============================
   LOGIKA PREVIEW (SINKRON DENGAN ENGINE)
=============================== */

// Gunakan data client jika ada (!empty), jika tidak gunakan setting global
$rawAvatar = !empty($client['bot_avatar']) ? $client['bot_avatar'] : sx('chatbot-web-bot_avatar');
$rawIcon   = !empty($client['widget_icon']) ? $client['widget_icon'] : sx('chatbot-web-widget_icon');
$rawSound  = !empty($client['notif_sound']) ? $client['notif_sound'] : sx('chatbot-web-notif_sound');

$previewBotAvatar  = asset_url($rawAvatar);
$previewWidgetIcon = asset_url($rawIcon);
$previewNotifSound = asset_url($rawSound);

/* ======================================================
   ASSET HELPERS (Optimized)
====================================================== */

function detect_scheme(): string {
    return ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            ($_SERVER['SERVER_PORT'] ?? null) == 443 ||
            ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null) === 'https') ? 'https' : 'http';
}

function app_base_path(): string {
    static $cached_path = null;
    if ($cached_path !== null) return $cached_path;

    $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
    $current = str_replace('\\', '/', __DIR__);

    while ($current && strpos($current, $docRoot) === 0) {
        if (is_dir($current . '/user')) {
            return $cached_path = str_replace($docRoot, '', $current);
        }
        $parent = dirname($current);
        if ($parent === $current) break;
        $current = $parent;
    }
    return $cached_path = '';
}

function asset_url(?string $path): string {
    if (!$path) return '';
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $path = ltrim($path, '/'); // normalisasi

    return detect_scheme().'://'.$_SERVER['HTTP_HOST'].app_base_path().'/'.$path;
}

/**
 * Upload Handler
 * Menyimpan ke folder /assets/{subDir}
 */
function uploadAsset(string $field, string $subDir, array $allowed, int $maxSize): ?string {
    if (empty($_FILES[$field]['name'])) return null;

    $file = $_FILES[$field];
    if ($file['size'] > $maxSize) throw new Exception("File $field terlalu besar");

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) throw new Exception("Format $field tidak didukung");

    // Path fisik di server
    $baseDir = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . app_base_path() . '/assets/' . trim($subDir, '/');
    if (!is_dir($baseDir)) mkdir($baseDir, 0755, true);

    $filename = uniqid($field . '_') . '.' . $ext;
    $target   = $baseDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new Exception("Upload $field gagal");
    }

    // Simpan path relatif ke DB agar mudah dipindah-pindah
    return 'assets/' . trim($subDir, '/') . '/' . $filename;
}
