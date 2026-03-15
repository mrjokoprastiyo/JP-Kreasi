<?php

require_once '../core/auth.php';

header('Content-Type: application/json');

if(!Auth::verifyCSRF($_POST['csrf'] ?? '')){
    echo json_encode(['status'=>false,'msg'=>'CSRF invalid']);
    exit;
}

/* ambil dari session, bukan POST */
$user_id = $_SESSION['verify_user_id'] ?? 0;

if(!$user_id){
    echo json_encode([
        'status'=>false,
        'msg'=>'Session verifikasi tidak ditemukan'
    ]);
    exit;
}

$result = Auth::resendOTP($user_id);

echo json_encode($result);
exit;