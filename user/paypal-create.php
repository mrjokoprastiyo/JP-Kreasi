<?php
session_start();

require_once '../config.php';
require_once '../core/db.php';
require_once '../core/settings-loader.php';

/* ======================================================
   1. VALIDASI ORDER
====================================================== */
$order_id = $_GET['order_id'] ?? null;
if (!$order_id) die("Order ID tidak ditemukan");

$order = DB::fetch(
    "SELECT p.*, u.email 
     FROM payments p 
     JOIN users u ON p.user_id = u.id 
     WHERE p.order_id = ? LIMIT 1",
    [$order_id]
);

if (!$order) die("Order tidak ditemukan");
if ($order['status'] !== 'pending') die("Order sudah diproses");

// Gunakan amount_usd yang sudah kita siapkan di tabel payments
$totalAmountUSD = number_format($order['amount_usd'], 2, '.', '');

if ($totalAmountUSD <= 0) {
    die("Invalid USD amount. Pastikan harga USD produk sudah diisi.");
}

/* ======================================================
   2. KONFIGURASI PAYPAL
====================================================== */
$clientId     = trim(setting('payment-paypal-client_id'));
$clientSecret = trim(setting('payment-paypal-secret'));
$mode         = setting('payment-paypal-mode', 'sandbox');

if (!$clientId || !$clientSecret) {
    die("PayPal belum dikonfigurasi di System Settings.");
}

$baseUrl = ($mode === 'live') 
    ? "https://api-m.paypal.com" 
    : "https://api-m.sandbox.paypal.com";

/* ======================================================
   3. GET ACCESS TOKEN (OAUTH2)
====================================================== */
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . "/v1/oauth2/token");
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $clientId . ":" . $clientSecret);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

$authResponse = curl_exec($ch);
$authData = json_decode($authResponse, true);
$accessToken = $authData['access_token'] ?? null;

if (!$accessToken) {
    error_log("PayPal Auth Error: " . $authResponse);
    die("Gagal autentikasi ke PayPal. Periksa Client ID & Secret.");
}

/* ======================================================
   4. CREATE ORDER REQUEST (API V2)
====================================================== */
$returnUrlX = "https://" . $_SERVER['HTTP_HOST'] . "/api/callback/paypal-success.php?order_id=" . $order_id;
$cancelUrlX = "https://" . $_SERVER['HTTP_HOST'] . "/user/payment.php?client_id=" . $order['client_id'];
/* ======================================================
   4. CREATE ORDER REQUEST (API V2)
====================================================== */
// Deteksi otomatis HTTP atau HTTPS
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

$returnUrl = $protocol . $host . "/api/callback/paypal-success.php?order_id=" . $order_id;
$cancelUrl = $protocol . $host . "/user/payment.php?client_id=" . $order['client_id'];

$orderData = [
    "intent" => "CAPTURE",
    "purchase_units" => [[
        "reference_id" => $order_id,
        "amount" => [
            "currency_code" => "USD",
            "value" => $totalAmountUSD
        ],
        "description" => "Payment for Order #" . $order_id
    ]],
    "application_context" => [
        "return_url" => $returnUrl,
        "cancel_url" => $cancelUrl,
        "brand_name" => setting('site-name', 'SaaS Platform'),
        "user_action" => "PAY_NOW"
    ]
];

curl_setopt($ch, CURLOPT_URL, $baseUrl . "/v2/checkout/orders");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $accessToken
]);

$orderResponse = curl_exec($ch);
$orderResult = json_decode($orderResponse, true);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

/* ======================================================
   5. REDIRECT KE PAYPAL APPROVAL
====================================================== */
if ($httpCode === 201 && isset($orderResult['links'])) {
    foreach ($orderResult['links'] as $link) {
        if ($link['rel'] === 'approve') {
            header("Location: " . $link['href']);
            exit;
        }
    }
}

// Jika gagal, tampilkan error UI yang rapi (seperti punya DOKU kamu)
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PayPal Error</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .error-card { background: white; padding: 40px; border-radius: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; max-width: 400px; }
        .btn-back { background: #0070ba; color: white; text-decoration: none; padding: 12px 24px; border-radius: 10px; display: inline-block; margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="error-card">
        <div style="font-size: 50px;">❌</div>
        <h2>PayPal Terkendala</h2>
        <p>Maaf, koneksi ke sistem PayPal gagal. Mohon coba lagi atau hubungi admin.</p>
        <a href="payment.php?client_id=<?= $order['client_id'] ?>" class="btn-back">Kembali</a>
    </div>
</body>
</html>
