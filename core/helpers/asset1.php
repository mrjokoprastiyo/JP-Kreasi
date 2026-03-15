<?php
function asset_base_dir(): string {
    return rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/assets';
}

function asset_url(string $path): string {
    if (preg_match('#^https?://#', $path)) return $path;
    return (isset($_SERVER['HTTPS']) ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST'] . $path;
}

function uploadAsset(string $field, string $dir, array $allowed, int $max): ?string {
    if (empty($_FILES[$field]['name'])) return null;

    $f = $_FILES[$field];
    if ($f['size'] > $max) throw new Exception("File terlalu besar");

    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed, true)) throw new Exception("Format tidak didukung");

    $base = asset_base_dir() . "/{$dir}";
    if (!is_dir($base)) mkdir($base, 0755, true);

    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $path = "{$base}/{$name}";

    if (!move_uploaded_file($f['tmp_name'], $path)) {
        throw new Exception("Upload gagal");
    }

    return "/assets/{$dir}/{$name}";
}