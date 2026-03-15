<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

/* ===============================
   AMBIL DATA POST
================================ */
$id            = isset($_POST['id']) ? (int)$_POST['id'] : null;
$provider_slug = trim($_POST['provider_slug'] ?? '');
$provider_name = trim($_POST['provider_name'] ?? '');
$model         = trim($_POST['model'] ?? '');
$api_token     = trim($_POST['api_token'] ?? '');
$status        = ($_POST['status'] ?? 'inactive') === 'active'
                 ? 'active'
                 : 'inactive';

/* ===============================
   VALIDASI DASAR
================================ */
if ($provider_slug === '' || $provider_name === '' || $model === '') {
    header("Location: ai-editor.php?error=empty");
    exit;
}

/* ===============================
   UPDATE (EDIT MODE)
================================ */
if ($id) {

    DB::execute("
        UPDATE ai_configs SET
            provider_slug = ?,
            provider_name = ?,
            model         = ?,
            api_token     = ?,
            status        = ?,
            updated_at    = NOW()
        WHERE id = ?
        LIMIT 1
    ", [
        $provider_slug,
        $provider_name,
        $model,
        $api_token,
        $status,
        $id
    ]);

}
/* ===============================
   INSERT (ADD MODE)
================================ */
else {

    DB::execute("
        INSERT INTO ai_configs
        (post_type, provider_slug, provider_name, model, api_token, status, created_at, updated_at)
        VALUES
        ('ai_provider', ?, ?, ?, ?, ?, NOW(), NOW())
    ", [
        $provider_slug,
        $provider_name,
        $model,
        $api_token,
        $status
    ]);
}

/* ===============================
   REDIRECT
================================ */
header("Location: ai-editor.php?success=1");
exit;