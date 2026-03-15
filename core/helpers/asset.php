<?php

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

function uploads_base_dir(): string {
    return rtrim($_SERVER['DOCUMENT_ROOT'], '/') . app_base_path() . '/uploads/design/preview';
}

function uploads_url(?string $path): string {
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
