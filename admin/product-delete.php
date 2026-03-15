<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

/* ===============================
   VALIDASI ID
================================ */
$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die('Invalid product ID');
}

/* ===============================
   DELETE PRODUCT
================================ */
DB::execute("
    DELETE FROM products WHERE id = ?
", [$id]);

header("Location: product-list.php?deleted=1");
exit;