<?php
require_once '../../config.php';
require_once '../../core/auth.php';
require_once '../../core/db.php';

Auth::check();
header('Content-Type: application/json');

$id   = (int)($_POST['id'] ?? 0);
$type = $_POST['type'] ?? '';

if (!$id || !in_array($type, ['provider','model'])) {
    exit(json_encode(['error' => 'Invalid request']));
}

/* ===============================
   LOAD DATA
================================ */
$row = DB::fetch(
    "SELECT status, post_type FROM ai_configs WHERE id = ?",
    [$id]
);

if (!$row) {
    exit(json_encode(['error' => 'Data not found']));
}

/* ===============================
   PROTECT ACTIVE
================================ */
if ($row['status'] === 'active') {
    exit(json_encode(['error' => 'Tidak bisa menghapus data yang masih aktif']));
}

/* ===============================
   DELETE LOGIC
================================ */
if ($type === 'provider') {
    // hapus semua model dulu
    DB::execute(
        "DELETE FROM ai_configs WHERE provider_id = ?",
        [$id]
    );
}

// hapus provider / model
DB::execute(
    "DELETE FROM ai_configs WHERE id = ?",
    [$id]
);

echo json_encode(['success' => true]);