<?php
ini_set('allow_url_fopen', 1);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$url = "http://jpkreasi.byethost16.com/CHATBOT-V2/test.php";

$data = json_encode([
    "visitor_id" => "x",
    "message"    => "__load__"
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $data,
    CURLOPT_HTTPHEADER     => [
        "Content-Type: application/json",
        "X-API-KEY: 8dab3c49469b208ef7b1fb8ec91f34703877e8b911bf0002",
        "Content-Length: " . strlen($data)
    ],
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "CURL ERROR: " . $error;
} else {
    echo $response;
}