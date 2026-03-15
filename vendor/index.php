<?php
require_once __DIR__.'/core/auth.php';

if (Auth::check()) {
    if (Auth::isAdmin()) {
        header("Location: /admin/dashboard.php");
    } else {
        header("Location: /user/dashboard.php");
    }
    exit;
}
