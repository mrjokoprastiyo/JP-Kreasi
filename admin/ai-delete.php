<?php
// admin/ai-delete.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';

Auth::check();

/* ===============================
   VALIDATE ID
================================ */
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id <= 0) {
    header('Location: dashboard.php?error=invalid');
    exit;
}

/* ===============================
   FETCH DATA
================================ */
$row = DB::fetch(
    "SELECT id, status FROM ai_configs WHERE id = ?",
    [$id]
);

if (!$row) {
    header('Location: dashboard.php?error=notfound');
    exit;
}

/* ===============================
   PROTECT ACTIVE DATA
================================ */
if ($row['status'] === 'active') {
    header('Location: dashboard.php?error=active');
    exit;
}

/* ===============================
   DELETE
================================ */
DB::execute(
    "DELETE FROM ai_configs WHERE id = ?",
    [$id]
);

header('Location: dashboard.php?deleted=1');
exit;