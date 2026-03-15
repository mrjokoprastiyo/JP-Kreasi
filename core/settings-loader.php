<?php
/**
 * SETTINGS LOADER (GLOBAL)
 * WAJIB satu-satunya sumber setting
 */

if (!class_exists('DB')) {
    require_once __DIR__ . '/db.php';
}

$__SETTINGS = [];

$rows = DB::fetchAll("
    SELECT setting_key, setting_value
    FROM settings
");

foreach ($rows as $row) {
    $__SETTINGS[$row['setting_key']] = $row['setting_value'];
}

if (!function_exists('setting')) {
    function setting(string $key, $default = '') {
        global $__SETTINGS;
        return htmlspecialchars($__SETTINGS[$key] ?? $default);
    }
}

if (!function_exists('setting_raw')) {
    function setting_raw(string $key, $default = null) {
        global $__SETTINGS;
        return $__SETTINGS[$key] ?? $default;
    }
}