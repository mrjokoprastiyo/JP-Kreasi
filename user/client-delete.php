<?php
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

$user_id = $_SESSION['user']['id'];
$id = (int)($_GET['id'] ?? 0);

DB::execute(
    "DELETE FROM clients WHERE id = ? AND user_id = ?",
    [$id, $user_id]
);

header("Location: client-dashboard.php");
exit;