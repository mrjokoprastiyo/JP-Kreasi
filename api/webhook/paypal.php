<?php
/**
 * PayPal Webhook Handler
 * Menangani aktivasi otomatis jika user menutup browser sebelum redirect sukses.
 */

require_once '../../config.php';
require_once '../../core/db.php';
require_once '../../core/settings-loader.php';

// 1. Ambil Data Webhook
$incomingJson = file_get_contents('php://input');
$data = json_decode($incomingJson, true);

if (!$data) {
    http_response_code(400);
    exit;
}

$eventType = $data['event_type'];
// PayPal Order ID (Token)
$paypalToken = $data['resource']['id'] ?? null;
// Custom Order ID yang kita kirim sebagai reference_id
$orderId = $data['resource']['purchase_units'][0]['reference_id'] ?? null;

// Kita hanya proses jika eventnya adalah Approval
if ($eventType !== 'CHECKOUT.ORDER.APPROVED') {
    http_response_code(200); // Balas 200 agar PayPal berhenti mengirim event lain
    exit;
}

// 2. Cek database apakah order ini masih 'pending'
$payment = DB::fetch("SELECT * FROM payments WHERE order_id = ? AND status = 'pending' LIMIT 1", [$orderId]);

if ($payment) {
    /* ======================================================
       PROSES AUTO-CAPTURE (Jalur Belakang)
    ====================================================== */
    $clientId     = trim(setting('payment-paypal-client_id'));
    $clientSecret = trim(setting('payment-paypal-secret'));
    $mode         = setting('payment-paypal-mode', 'sandbox');
    $baseUrl      = ($mode === 'live') ? "https://api-m.paypal.com" : "https://api-m.sandbox.paypal.com";

    // A. Get Access Token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . "/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ":" . $clientSecret);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    $authData = json_decode(curl_exec($ch), true);
    $accessToken = $authData['access_token'] ?? null;

    if ($accessToken) {
        // B. Eksekusi Capture
        curl_setopt($ch, CURLOPT_URL, $baseUrl . "/v2/checkout/orders/" . $paypalToken . "/capture");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, "{}");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer " . $accessToken
        ]);
        
        $captureResponse = curl_exec($ch);
        $captureResult = json_decode($captureResponse, true);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // C. Jika Capture Sukses, Aktivasi Client
        if ($httpCode === 201 && ($captureResult['status'] ?? '') === 'COMPLETED') {
            try {
                DB::begin();
                
                // Update Payment
                DB::execute(
                    "UPDATE payments SET status = 'paid', paid_at = NOW(), transaction_id = ? WHERE id = ?",
                    [$captureResult['id'], $payment['id']]
                );

                // Activate Client
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
            } catch (Exception $e) {
                if(DB::inTransaction()) DB::rollback();
            }
        }
    }
}

// Apapun hasilnya, balas PayPal dengan 200 OK agar mereka tidak mengirim ulang
http_response_code(200);
echo json_encode(['status' => 'received']);
