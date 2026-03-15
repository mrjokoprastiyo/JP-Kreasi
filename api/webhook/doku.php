<?php
/**
 * DOKU Webhook Handler (Production Ready)
 * URL: https://yourdomain.com/api/callback/doku.php
 */

ignore_user_abort(true);
set_time_limit(30);

require_once '../../config.php';
require_once '../../core/db.php';
require_once '../../core/settings-loader.php';

header('Content-Type: application/json');

// ============================================
// CONFIG
// ============================================

$clientId  = trim(setting('payment-doku-client_id'));
$sharedKey = trim(setting('payment-doku-shared_key'));

$logFile = __DIR__ . '/doku-webhook.log';

// ============================================
// HELPER: LOG
// ============================================

function dokuLog($msg)
{
    global $logFile;

    file_put_contents(
        $logFile,
        "[" . date('Y-m-d H:i:s') . "] " . $msg . "\n",
        FILE_APPEND
    );
}

// ============================================
// 1. GET BODY
// ============================================

$incomingJson = file_get_contents('php://input');

if (!$incomingJson) {

    dokuLog("EMPTY BODY");

    http_response_code(400);
    echo json_encode(['status'=>'ERROR','message'=>'Empty body']);
    exit;
}

$data = json_decode($incomingJson, true);

if (!$data) {

    dokuLog("INVALID JSON: " . $incomingJson);

    http_response_code(400);
    echo json_encode(['status'=>'ERROR','message'=>'Invalid JSON']);
    exit;
}

// ... (Bagian awal tetap sama)

// ============================================
// 2. GET HEADERS (Normalization)
// ============================================

$headers = array_change_key_case(getallheaders(), CASE_LOWER);

$signatureHeader = $headers['signature'] ?? '';
$requestId       = $headers['request-id'] ?? '';
$requestTime     = $headers['request-timestamp'] ?? '';

// DOKU Webhook biasanya menyertakan header ini, jika tidak, pakai URI
$requestTarget   = $_SERVER['REQUEST_URI']; 

// ============================================
// 3. VERIFY SIGNATURE (IMPORTANT)
// ============================================

$digest = base64_encode(hash('sha256', $incomingJson, true));

// String Signature untuk Webhook DOKU harus sesuai urutan ini:
$signatureRaw =
      "Client-Id:" . $clientId . "\n"
    . "Request-Id:" . $requestId . "\n"
    . "Request-Timestamp:" . $requestTime . "\n"
    . "Request-Target:" . $requestTarget . "\n" // Gunakan URI lengkap (path + query)
    . "Digest:" . $digest;

$expectedSignature = "HMACSHA256=" . base64_encode(
    hash_hmac('sha256', $signatureRaw, $sharedKey, true)
);

// Verify signature
if (!hash_equals($expectedSignature, $signatureHeader)) {

    dokuLog("INVALID SIGNATURE");
    dokuLog("Expected: " . $expectedSignature);
    dokuLog("Received: " . $signatureHeader);

    http_response_code(401);
    echo json_encode([
        'status'=>'ERROR',
        'message'=>'Invalid signature'
    ]);
    exit;
}

// ============================================
// 4. EXTRACT DATA
// ============================================

$invoiceNumber = $data['order']['invoice_number'] ?? null;
$status        = $data['transaction']['status'] ?? null;
$amount        = $data['order']['amount'] ?? 0;

// Validate required
if (!$invoiceNumber) {

    dokuLog("MISSING INVOICE");

    http_response_code(400);
    echo json_encode(['status'=>'ERROR','message'=>'Missing invoice']);
    exit;
}

dokuLog("Webhook received: Invoice=" . $invoiceNumber . " Status=" . $status);

// ============================================
// 5. FETCH PAYMENT
// ============================================

$payment = DB::fetch(
    "SELECT * FROM payments WHERE order_id = ? LIMIT 1",
    [$invoiceNumber]
);

if (!$payment) {

    dokuLog("PAYMENT NOT FOUND: " . $invoiceNumber);

    http_response_code(200);
    echo json_encode(['status'=>'NOT_FOUND']);
    exit;
}

// ============================================
// 6. IDEMPOTENCY CHECK
// ============================================

if ($payment['status'] === 'paid') {

    dokuLog("ALREADY COMPLETED: " . $invoiceNumber);

    http_response_code(200);
    echo json_encode(['status'=>'ALREADY_PROCESSED']);
    exit;
}

// ============================================
// 7. PROCESS SUCCESS
// ============================================

if ($status === 'SUCCESS') {

    try {

        DB::begin();

        // Lock row (important)
        DB::execute(
            "SELECT id FROM payments WHERE id = ? FOR UPDATE",
            [$payment['id']]
        );

        // Update payment
        DB::execute(
            "UPDATE payments
             SET status = 'paid',
                 paid_at = NOW()
             WHERE id = ?",
            [$payment['id']]
        );

        // Activate client
        DB::execute(
            "UPDATE clients
             SET status = 'active',
                 expired_at =
                    CASE
                        WHEN expired_at > NOW()
                        THEN DATE_ADD(expired_at, INTERVAL 30 DAY)
                        ELSE DATE_ADD(NOW(), INTERVAL 30 DAY)
                    END
             WHERE id = ?",
            [$payment['client_id']]
        );

        DB::commit();

        dokuLog("PAYMENT SUCCESS: " . $invoiceNumber);

        http_response_code(200);
        echo json_encode(['status'=>'SUCCESS']);

        exit;

    } catch (Exception $e) {

        DB::rollback();

        dokuLog("DB ERROR: " . $e->getMessage());

        http_response_code(500);
        echo json_encode(['status'=>'ERROR']);

        exit;
    }
}

// ============================================
// 8. HANDLE OTHER STATUS
// ============================================

dokuLog("STATUS IGNORED: " . $status);

http_response_code(200);
echo json_encode(['status'=>'IGNORED']);