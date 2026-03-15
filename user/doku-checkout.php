<?php
session_start();

require_once '../config.php';
require_once '../core/db.php';
require_once '../core/settings-loader.php';



/* ======================================================
   1. VALIDASI ORDER
====================================================== */

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    die("Order ID tidak ditemukan");
}

$order = DB::fetch(
    "SELECT 
        p.*,
        (p.subtotal + p.tax + p.fee) AS amount,
        u.email,
        u.fullname AS user_fullname
     FROM payments p
     JOIN users u ON p.user_id = u.id
     WHERE p.order_id = ?
     LIMIT 1",
    [$order_id]
);

if (!$order) {
    die("Order tidak ditemukan");
}

if ($order['status'] !== 'pending') {
    die("Order sudah diproses");
}

$totalAmount = (int)$order['amount'];

if ($totalAmount <= 0) {
    die("Invalid order amount: " . $totalAmount);
}



/* ======================================================
   2. KONFIGURASI DOKU
====================================================== */

$clientId  = trim(setting('payment-doku-client_id'));
$sharedKey = trim(setting('payment-doku-shared_key'));
$mode      = setting('payment-doku-mode', 'sandbox');

if (!$clientId || !$sharedKey) {
    die("DOKU belum dikonfigurasi");
}

$baseUrl = ($mode === 'live')
    ? "https://api.doku.com"
    : "https://api-sandbox.doku.com";

$requestTarget = "/checkout/v1/payment";



/* ======================================================
   3. GENERATE REQUEST META
====================================================== */

$requestId = bin2hex(random_bytes(16));
$timestamp = gmdate("Y-m-d\TH:i:s\Z");



/* ======================================================
   4. BUILD REQUEST BODY
====================================================== */

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];

$callbackUrl = $protocol . $host . "/api/callback/doku-success.php?order_id=" . $order['order_id'];
$notificationUrl = $protocol . $host . "/api/webhook/doku.php";

$body = [

    "order" => [
        "invoice_number" => (string)$order['order_id'],
        "amount"         => $totalAmount
    ],

    "payment" => [
        "payment_due_date" => 60
    ],

    "customer" => [
        "id"    => "USR-" . $order['user_id'],
        "name"  => $order['user_fullname'] ?: "Customer",
        "email" => $order['email']
    ],

    "callback_url" => $callbackUrl,
    "notification_url" => $notificationUrl
];

$jsonBody = json_encode($body, JSON_UNESCAPED_SLASHES);



/* ======================================================
   5. GENERATE DIGEST
====================================================== */

$digest = base64_encode(
    hash('sha256', $jsonBody, true)
);



/* ======================================================
   6. BUILD SIGNATURE STRING (WAJIB 5 BARIS)
====================================================== */

$signatureRaw =
    "Client-Id:" . $clientId . "\n" .
    "Request-Id:" . $requestId . "\n" .
    "Request-Timestamp:" . $timestamp . "\n" .
    "Request-Target:" . $requestTarget . "\n" .
    "Digest:" . $digest;



/* ======================================================
   7. GENERATE SIGNATURE
====================================================== */

$signature = base64_encode(
    hash_hmac('sha256', $signatureRaw, $sharedKey, true)
);

$signatureHeader = "HMACSHA256=" . $signature;



/* ======================================================
   8. SEND REQUEST KE DOKU
====================================================== */

$url = $baseUrl . $requestTarget;

$headers = [
    "Content-Type: application/json",
    "Client-Id: $clientId",
    "Request-Id: $requestId",
    "Request-Timestamp: $timestamp",
    "Digest: $digest",
    "Signature: $signatureHeader"
];

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => $jsonBody,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error    = curl_error($ch);

curl_close($ch);

$result = json_decode($response, true);



/* ======================================================
   9. SUCCESS → REDIRECT KE DOKU
====================================================== */

if ($httpCode === 200 && isset($result['response']['payment']['url'])) {

    header("Location: " . $result['response']['payment']['url']);
    exit;
}


/* ======================================================
   10. USER-FRIENDLY ERROR HANDLING
====================================================== */

// Deteksi Jenis Error secara internal (untuk log server)
if ($error) {
    error_log("DOKU CURL ERROR: " . $error);
}

// Cek apakah timeout atau gagal sistem
$is_timeout = (strpos($error, 'timed out') !== false || $httpCode === 0);
$display_msg = $is_timeout 
    ? "Koneksi ke sistem pembayaran sedang sibuk." 
    : "Mohon maaf, pembayaran sedang tidak dapat diproses.";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembayaran Terkendala</title>
    <style>
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: #f8fafc; color: #1e293b; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .error-card { background: white; width: 100%; max-width: 400px; padding: 40px 30px; border-radius: 24px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); text-align: center; }
        .icon-box { background: #fee2e2; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: #ef4444; font-size: 40px; }
        h2 { font-size: 1.5rem; margin-bottom: 10px; color: #0f172a; }
        p { color: #64748b; line-height: 1.6; margin-bottom: 30px; font-size: 0.95rem; }
        .btn-back { background: #4f46e5; color: white; text-decoration: none; padding: 14px 28px; border-radius: 12px; font-weight: 600; display: inline-block; transition: all 0.3s; }
        .btn-back:hover { background: #4338ca; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(79, 70, 229, 0.3); }
        .support-link { margin-top: 25px; display: block; font-size: 0.85rem; color: #94a3b8; text-decoration: none; }
        .support-link:hover { color: #4f46e5; }
    </style>
</head>
<body>

<div class="error-card">
    <div class="icon-box">
        <?= $is_timeout ? '⏱️' : '⚠️' ?>
    </div>
    <h2>Waduh, Maaf Ya!</h2>
    <p>
        <?= $display_msg ?><br>
        Silakan coba beberapa saat lagi atau hubungi bantuan jika kendala berlanjut.
    </p>
    
    <a href="payment.php?client_id=<?= $order['client_id'] ?>" class="btn-back">
        Coba Lagi
    </a>

    <a href="/support" class="support-link">Punya pertanyaan? Hubungi Bantuan</a>
</div>

</body>
</html>
