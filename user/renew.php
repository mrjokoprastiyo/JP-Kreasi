<?php
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

$client_id = $_POST['client_id'] ?? null;
if (!$client_id) die('Invalid request');

// Redirect ke halaman pembayaran
header("Location: payment.php?client_id=".$client_id);
exit;