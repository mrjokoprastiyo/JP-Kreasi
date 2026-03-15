<?php
/* ===============================
   BASIC PHP CONFIG
================================ */
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

/* ===============================
   SIMPLE DEBUG LOGGER
================================ */
define('DEBUG_LOG', true);
define('DEBUG_FILE', __DIR__ . '/debug.txt');

function debug_log($msg) {
    if (!DEBUG_LOG) return;
    file_put_contents(
        DEBUG_FILE,
        '[' . date('Y-m-d H:i:s') . '] ' . print_r($msg, true) . PHP_EOL,
        FILE_APPEND
    );
}

/* ===============================
   CORS & RESPONSE HEADER
================================ */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY");
header("Content-Type: application/json; charset=utf-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/* ===============================
   DEPENDENCY
================================ */
require_once 'core/db.php';
require_once 'ai.php';
require_once 'core/client-validator.php';
require_once 'core/client-status.php';

/* ===============================
   HELPER RESPONSE
================================ */
function response(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ===============================
   READ & VALIDATE INPUT
================================ */
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

debug_log(['RAW_INPUT' => $raw]);

if (!is_array($data)) {
    response(['error' => 'Invalid JSON body'], 400);
}

$visitor_id = trim($data['visitor_id'] ?? '');
$message    = trim($data['message'] ?? '');
$api_key    = $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($api_key === '' || $visitor_id === '') {
    response(['error' => 'Missing API key or visitor ID'], 401);
}

/* ===============================
   VALIDATE CLIENT (FAST FAIL)
================================ */
$client = validateClientByApiKey($api_key);

/* ===============================
   BOT & WIDGET CONFIG (SAFE DEFAULT)
================================ */
function detect_scheme(): string {
    if (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
         $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    ) {
        return 'https';
    }
    return 'http';
}

function asset_url(?string $path): string
{
    if (!$path) {
        return '';
    }

    // Absolute URL → return langsung
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    // Normalisasi path
    $path = ltrim($path, '/');

    // Detect scheme
    $scheme = detect_scheme();

    // Detect base dir (subfolder safe)
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

    return $scheme . '://' . $_SERVER['HTTP_HOST'] . $scriptDir . '/' . $path;
}

$config = [
    'bot_name' => $client['bot_name'] ?: 'Assistant',
    'bot_desc' => $client['bot_desc'] ?: 'Online',
    'bot_avatar' => asset_url($client['bot_avatar'] ?: '/assets/avatar/bot.png'),
    'widget_icon' => asset_url($client['widget_icon'] ?: '/assets/icon/chat.png'),
    'widget_background' => $client['widget_background'] ?: '#000000',

    'notif' => [
      'badge' => (bool)$client['notif_badge'],
      'popup' => (bool)$client['notif_popup'],
      'sound' => (bool)$client['notif_sound_enabled'],
      'sound_url' => asset_url($client['notif_sound'] ?: '/assets/sound/notif.mp3'),
    ]
];

/* ===============================
   INIT / LOAD (SMART GREETING)
================================ */
if ($message === '__init__' || $message === '__load__') {

    try {

        $history = DB::fetchAll(
            "SELECT role, message
             FROM chat_logs
             WHERE visitor_id = ?
             ORDER BY created_at ASC",
            [$visitor_id]
        );

    } catch (Throwable $e) {

        debug_log(['DB_HISTORY_ERROR' => $e->getMessage()]);
        $history = [];

    }

    $payload = [
        'type'   => 'config',
        'config' => $config
    ];

    $hasHistory = !empty($history);

    if ($hasHistory) {
        $payload['history'] = $history;
    }

    /* ===============================
       SMART GREETING LOGIC
    ============================== */

    $timeGreetingX = getTimeGreeting();
    $timezone = $data['timezone'] ?? 'UTC';
    $timeGreeting = getTimeGreeting($timezone);

    if (!$hasHistory) {

        // Visitor baru

        $greeting = $client['bot_greeting']
            ?: "$timeGreeting! Saya {$config['bot_name']}. Ada yang bisa saya bantu?";

        try {

            DB::execute(
                "INSERT INTO chat_logs (visitor_id, role, message)
                 VALUES (?, 'assistant', ?)",
                [$visitor_id, $greeting]
            );

        } catch (Throwable $e) {

            debug_log(['DB_GREETING_ERROR' => $e->getMessage()]);
        }

        $payload['reply'] = $greeting;

    } else {

        // Returning visitor

        $returnGreeting = "$timeGreeting 👋 Senang melihat Anda kembali.";

        $payload['returning'] = true;
        $payload['reply'] = $returnGreeting;

    }

    response($payload);
}

$status = resolveClientServiceStatus($client);
if (!$status['service_active']) {
    response([
        'reply' => 'Layanan ini sedang tidak aktif. Silakan aktifkan atau perpanjang layanan, atau hubungi developer Anda untuk bantuan.'
    ], 403);
}

/* ===============================
   LOAD CONTEXT (STRICT SEQUENCE)
================================ */
try {
    $context = DB::fetchAll(
        "SELECT role, message
         FROM chat_logs
         WHERE visitor_id = ?
         ORDER BY id DESC
         LIMIT 10",
        [$visitor_id]
    );

    $context = array_reverse($context);
} catch (Throwable $e) {
    debug_log(['DB_CONTEXT_ERROR' => $e->getMessage()]);
    $context = [];
}

/* ===============================
   AI CALL
================================ */
$reply = ai_reply(
    (int) $client['id'],
    $client['prompt'],
    $client['model'],
    $message,
    $context
);

debug_log([
    'AI_INPUT' => $message,
    'AI_REPLY' => $reply
]);

/* ===============================
   SAVE CHAT (ATOMIC)
================================ */
try {
    DB::connect()->beginTransaction();

    DB::execute(
        "INSERT INTO chat_logs (visitor_id, role, message)
         VALUES
         (?, 'user', ?),
         (?, 'assistant', ?)",
        [
            $visitor_id, $message,
            $visitor_id, $reply
        ]
    );

    DB::connect()->commit();
} catch (Throwable $e) {
    DB::connect()->rollBack();
    debug_log(['DB_SAVE_ERROR' => $e->getMessage()]);
}

/* ===============================
   FINAL RESPONSE
================================ */
response([
    'reply'    => $reply,
    'bot_name' => $config['bot_name']
]);