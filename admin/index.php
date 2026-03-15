<?php
require_once __DIR__ . '/../core/auth.php';

/* Wajib login */
if (!Auth::check()) {
    header("Location: /login.php");
    exit;
}

/* Harus admin */
if (!Auth::isAdmin()) {
    http_response_code(403);
    exit("Access denied");
}

/* Redirect ke dashboard admin */
header("Location: /admin/dashboard.php");
exit;