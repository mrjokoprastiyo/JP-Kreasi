<?php
/* =========================================================
   CEK API CHATBOT – JP CHATBOT TEST TOOL (FULL DEBUG)
   Tujuan:
   - Pastikan API key valid
   - Pastikan data client & widget benar-benar dikirim dari DB
   - Deteksi masalah sebelum masuk ke widget JS
========================================================= */

/* ===============================
   API CONFIG
================================ */
$apiUrl = "http://jpkreasi.byethost16.com/CHATBOT-V2/ping.php"; // sesuaikan
$apiKey = "8dab3c49469b208ef7b1fb8ec91f34703877e8b911bf0002";

/* ===============================
   MODE TEST
   "__load__" / "__init__" → load config & greeting
   teks biasa               → chat normal
================================ */
$payload = [
    "visitor_id" => "debug_test_123",
    "message"    => "__load__"
    // "message" => "Halo, saya mau tanya layanan kamu"
];

/* ===============================
   CURL REQUEST
================================ */
$ch = curl_init($apiUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "Content-Type: application/json",
        "X-API-KEY: {$apiKey}"
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => false, // true jika SSL valid
    CURLOPT_SSL_VERIFYHOST => 0
]);

$response = curl_exec($ch);

/* ===============================
   HANDLE CURL ERROR
================================ */
if ($response === false) {
    echo "❌ CURL ERROR\n";
    echo curl_error($ch);
    curl_close($ch);
    exit;
}

/* ===============================
   HTTP INFO
================================ */
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

/* ===============================
   RAW OUTPUT
================================ */
echo "=====================================\n";
echo "JP CHATBOT DEBUG TOOL\n";
echo "=====================================\n";
echo "HTTP CODE : {$httpCode}\n";
echo "API URL   : {$apiUrl}\n";
echo "API KEY   : {$apiKey}\n";
echo "=====================================\n\n";

echo "RAW RESPONSE:\n";
echo $response . "\n\n";

/* ===============================
   JSON DECODE
================================ */
$json = json_decode($response, true);

if (!is_array($json)) {
    echo "❌ RESPONSE BUKAN JSON VALID\n";
    exit;
}

/* ===============================
   PARSED RESPONSE
================================ */
echo "=====================================\n";
echo "PARSED RESPONSE\n";
echo "=====================================\n";
print_r($json);

/* ===============================
   CONFIG VALIDATION
================================ */
echo "\n=====================================\n";
echo "CONFIG VALIDATION\n";
echo "=====================================\n";

if (!isset($json['config'])) {
    echo "❌ CONFIG TIDAK DIKIRIM DARI SERVER\n";
    exit;
}

$config = $json['config'];

$fields = [
    'bot_name',
    'bot_desc',
    'bot_avatar',
    'bot_greeting',
    'widget_icon',
    'widget_background',
    'notif_sound'
];

foreach ($fields as $f) {
    if (!isset($config[$f]) || $config[$f] === '') {
        echo "⚠️  {$f} : EMPTY / NULL\n";
    } else {
        echo "✅ {$f} : OK\n";
    }
}

/* ===============================
   HISTORY / GREETING CHECK
================================ */
echo "\n=====================================\n";
echo "CHAT STATE\n";
echo "=====================================\n";

if (isset($json['history'])) {
    echo "History ditemukan (" . count($json['history']) . " pesan)\n";
} elseif (isset($json['reply'])) {
    echo "Greeting dikirim:\n";
    echo $json['reply'] . "\n";
} else {
    echo "⚠️ Tidak ada history & tidak ada greeting\n";
}

echo "\n=====================================\n";
echo "DEBUG SELESAI\n";
echo "=====================================\n";