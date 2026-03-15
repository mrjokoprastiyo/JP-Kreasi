<?php
require_once __DIR__ . '/../core/auth.php';

/* Wajib login */
if (!Auth::check()) {
    header("Location: /login.php");
    exit;
}

/* Redirect dashboard user */
header("Location: /user/dashboard.php");
exit;