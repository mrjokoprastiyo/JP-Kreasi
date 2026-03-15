<?php
require_once __DIR__.'/../core/auth.php';

header('Content-Type: application/json');

if(!Auth::verifyCSRF($_POST['csrf'] ?? '')){
    echo json_encode(['status'=>false,'msg'=>'CSRF gagal']);
    exit;
}

$user_id = $_SESSION['verify_user_id'] ?? 0;
$otp     = trim($_POST['otp'] ?? '');

if(!$user_id){
    echo json_encode(['status'=>false,'msg'=>'Session tidak valid']);
    exit;
}

$result = Auth::verifyOTP($user_id,$otp);

if($result){

    // OTP sukses → hapus session
    unset($_SESSION['verify_user_id']);

    echo json_encode(['status'=>true]);
    exit;
}

echo json_encode(['status'=>false,'msg'=>'OTP salah']);