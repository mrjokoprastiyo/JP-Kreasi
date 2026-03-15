<?php
session_start();
require_once '../../config.php';
require_once '../../core/db.php';
require_once '../../core/settings-loader.php';

$order_id = $_GET['order_id'] ?? null;
$paypal_token = $_GET['token'] ?? null; 

if (!$order_id || !$paypal_token) {
    die("Data transaksi tidak lengkap.");
}

// 1. Ambil data payment (Cek status pending agar tidak double process)
$payment = DB::fetch("SELECT * FROM payments WHERE order_id = ? AND status = 'pending' LIMIT 1", [$order_id]);

if (!$payment) {
    // Jika tidak ditemukan atau sudah dibayar, lempar ke dashboard
    header("Location: /../../user/dashboard.php"); 
    exit;
}

/* ===============================
   2. GET ACCESS TOKEN
=============================== */
$clientId     = trim(setting('payment-paypal-client_id'));
$clientSecret = trim(setting('payment-paypal-secret'));
$mode         = setting('payment-paypal-mode', 'sandbox');
$baseUrl      = ($mode === 'live') ? "https://api-m.paypal.com" : "https://api-m.sandbox.paypal.com";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . "/v1/oauth2/token");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $clientId . ":" . $clientSecret);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
$authData = json_decode(curl_exec($ch), true);
$accessToken = $authData['access_token'] ?? null;

if (!$accessToken) die("Gagal mendapatkan akses token PayPal.");

/* ===============================
   3. CAPTURE ORDER
=============================== */
$emptyBody = "{}"; 
curl_setopt($ch, CURLOPT_URL, $baseUrl . "/v2/checkout/orders/" . $paypal_token . "/capture");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $emptyBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer " . $accessToken,
    "PayPal-Request-Id: " . $order_id
]);

$captureResponse = curl_exec($ch);
$captureResult = json_decode($captureResponse, true);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

/* ===============================
   4. HANDLE RESULT & ACTIVATION
=============================== */
if ($httpCode === 201 && isset($captureResult['status']) && $captureResult['status'] === 'COMPLETED') {
    
    try {
        DB::begin();

        // A. Update Payment (Gunakan status 'paid' sesuai ENUM tabelmu)
        DB::execute(
            "UPDATE payments SET 
                status = 'paid', 
                paid_at = NOW(), 
                transaction_id = ?, 
                raw_response = ? 
             WHERE id = ?",
            [
                $captureResult['id'] ?? $paypal_token, 
                json_encode($captureResult), 
                $payment['id']
            ]
        );

        // B. Activate & Renew Client
        // Menggunakan kolom: status, activated_at, renewed_at, expired_at
        DB::execute(
            "UPDATE clients 
             SET status = 'active', 
                 activated_at = IFNULL(activated_at, NOW()),
                 renewed_at = NOW(),
                 expired_at = CASE 
                    WHEN expired_at > NOW() THEN DATE_ADD(expired_at, INTERVAL 30 DAY)
                    ELSE DATE_ADD(NOW(), INTERVAL 30 DAY)
                 END 
             WHERE id = ?",
            [$payment['client_id']]
        );

        DB::commit();

        // Gunakan path absolut dari domain
        header("Location: /../../user/client-detail.php?id=" . $payment['client_id'] . "&status=paid");
        exit;

    } catch (Exception $e) {
        if(DB::inTransaction()) DB::rollback();
        // Log error asli ke file, tampilkan pesan user friendly
        error_log("DB Activation Error: " . $e->getMessage());
        die("Database Error: " . $e->getMessage()); 
    }

} else {
    error_log("PayPal Capture Failed: " . $captureResponse);
    header("Location: /../../user/payment.php?client_id=" . $payment['client_id'] . "&error=failed");
    exit;
}
