<?php
require_once __DIR__ . '/../core/db.php';
session_start();

if(!isset($_SESSION['check_rate'])){
$_SESSION['check_rate']=0;
}

$_SESSION['check_rate']++;

if($_SESSION['check_rate']>100){
http_response_code(429);
exit;
}

$username = trim($_GET['username'] ?? '');
$email    = trim($_GET['email'] ?? '');

$response = [];

if($username){

    $exists = DB::fetchColumn(
        "SELECT id FROM users WHERE username=? LIMIT 1",
        [$username]
    );

    $response['username_exists'] = $exists ? true : false;
}

if($email){

    $exists = DB::fetchColumn(
        "SELECT id FROM users WHERE email=? LIMIT 1",
        [$email]
    );

    $response['email_exists'] = $exists ? true : false;
}

header('Content-Type: application/json');
echo json_encode($response);