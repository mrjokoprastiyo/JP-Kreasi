<?php
header('Content-Type: application/json');

function respond($d, $c = 200) {
    http_response_code($c);
    echo json_encode($d);
    exit;
}

function safe($fn) {
    try { return ['ok' => true, 'result' => $fn()]; }
    catch (Throwable $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function dispatch($channel, $payload) {
    $file = __DIR__."/targets/$channel.php";
    if (!file_exists($file)) throw new Exception("Target $channel missing");

    require_once $file;
    $fn = "send".ucfirst($channel);
    if (!function_exists($fn)) throw new Exception("Function $fn not found");

    return $fn($payload);
}

/* ===== DATA ===== */
$req    = $GLOBALS['automation_payload'];
$client = $GLOBALS['client_data'];
$config = json_decode($client['credentials'], true);

$file = base64_decode($req['file_base64']);
if (!$file) respond(['error'=>'Invalid file'],422);

$format = $config['export_format'] ?? 'pdf';
$mime = [
  'pdf'=>'application/pdf',
  'xlsx'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
  'csv'=>'text/csv'
][$format] ?? 'application/octet-stream';

$payload = [
  'file'      => $file,
  'filename'  => $req['file_name'],
  'mime_type' => $mime,
  'config'    => $config
];

$targets = ($config['target']['channel'] ?? 'all') === 'all'
  ? ['telegram','whatsapp','messenger','email','website']
  : [$config['target']['channel']];

$result = [];
foreach ($targets as $t) {
    $result[$t] = safe(fn()=>dispatch($t,$payload));
}

respond([
  'flow' => 'AUTOMATION_NOTIFICATION',
  'results' => $result
]);