<?php
require_once 'core/db.php';
require_once 'core/client-validator.php';
require_once 'core/client-status.php';
require_once 'core/request-guard.php';

header('Content-Type: application/json');

function response(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    response(['error' => 'Invalid JSON'], 400);
}

$client = validateClientByApiKey(
    $_SERVER['HTTP_X_API_KEY'] ?? '',
    [
        'flow' => 'AUTOMATION_NOTIFICATION',
        'spreadsheet_id' => $input['spreadsheet_id'] ?? null
    ]
);

$body = json_encode($input, JSON_UNESCAPED_UNICODE);

verifySignedRequest($body, $client['api_key']);

$status = resolveClientServiceStatus($client);

if (!$status['service_active']) {
    response([
        'error' => 'SERVICE_INACTIVE',
        'service' => $status
    ], 403);
}

/* ===== META REQUEST (PING DARI APPS SCRIPT) ===== */
if (!isset($input['file_base64'])) {
    $config = json_decode($client['credentials'], true);

    response([
        'export_format' => $config['export_format'] ?? 'pdf',
        'target'        => $config['target']['channel'] ?? 'all',
        'flow'          => 'AUTOMATION_NOTIFICATION',
        'service' => $status
    ]);
}

/* ===== FILE DELIVERY ===== */
$GLOBALS['automation_payload'] = $input;
$GLOBALS['client_data']        = $client;

require 'dev/automation.php';