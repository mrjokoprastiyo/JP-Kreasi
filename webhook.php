<?php

declare(strict_types=1);

require_once __DIR__ . '/core/db.php';
require_once __DIR__ . '/core/validators/common-validator.php';
require_once __DIR__ . '/core/client-status.php';
require_once __DIR__ . '/core/UserEngine.php';
require_once __DIR__ . '/core/PayloadHelper.php';
require_once __DIR__ . '/services/MessengerHandler.php';
require_once __DIR__ . '/services/FBEventHandler.php';
require_once __DIR__ . '/services/WhatsAppHandler.php';
require_once __DIR__ . '/services/TelegramHandler.php';
require_once __DIR__ . '/core/settings-loader.php';

/*
|--------------------------------------------------------------------------
| GLOBAL SETTINGS
|--------------------------------------------------------------------------
*/

define('VERIFY_TOKEN', setting('meta-verify-token', 'GLOBAL_VERIFY_TOKEN'));
define('FB_APP_SECRET', setting('meta-app-secret'));


/*
|--------------------------------------------------------------------------
| 1. META WEBHOOK VERIFICATION (GET ONLY)
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if (
        ($_GET['hub_mode'] ?? '') === 'subscribe' &&
        ($_GET['hub_verify_token'] ?? '') === VERIFY_TOKEN
    ) {
        echo $_GET['hub_challenge'];
        exit;
    }

    http_response_code(403);
    exit;
}


/*
|--------------------------------------------------------------------------
| 2. RECEIVE RAW PAYLOAD
|--------------------------------------------------------------------------
*/

$raw = file_get_contents('php://input');

if (!$raw) {
    http_response_code(400);
    exit;
}

$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    exit;
}


/*
|--------------------------------------------------------------------------
| 3. DETECT SERVICE
|--------------------------------------------------------------------------
*/

$service = detectService($input);

if (!$service) {
    http_response_code(404);
    exit;
}


/*
|--------------------------------------------------------------------------
| 4. META SIGNATURE VALIDATION (FB + WA ONLY)
|--------------------------------------------------------------------------
*/

if (in_array($service, ['messenger', 'comment', 'whatsapp'], true)) {

    if (!validateMetaSignature($raw)) {
        error_log("Webhook Security: Invalid Meta Signature [$service]");
        http_response_code(403);
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| 5. FIND CLIENT (RELATIONAL ONLY)
|--------------------------------------------------------------------------
*/

$client = findClient($service, $input);

if (!$client) {
    error_log("Webhook: Client not found [$service]");
    http_response_code(404);
    exit;
}

/* VALIDATE CLIENT */
validateCommon($client);

/* SERVICE STATUS */
$status = resolveClientServiceStatus($client);

if (!$status['service_active']) {
    http_response_code(200);
    exit;
}

$credentials = json_decode($client['credentials'], true) ?? [];

$ui = $credentials['ui'] ?? [];


/*
|--------------------------------------------------------------------------
| 6. ROUTE TO HANDLER
|--------------------------------------------------------------------------
*/

try {

    switch ($service) {

        case 'messenger':

           $handler = new MessengerHandler(
                (int)$client['id'],
                $client['page_id'],
                $credentials['access_token'] ?? '',
                $credentials['page_name'] ?? null,
                $ui
            );

            $senderData = PayloadHelper::parse($input);

            $handler->handle(
                $input,
                [
                    "active_participant_name" => $senderData['sender_name'] ?? null
                ]
            );

            break;


        case 'comment':

            $handler = new FBEventHandler(
                (int)$client['id'],
                $client['page_id'],
                $credentials['access_token'] ?? '',
                $credentials['page_name'] ?? 'Page'
            );

            $handler->handle($input);
            break;


        case 'whatsapp':

            $handler = new WhatsAppHandler(
                (int)$client['id'],
                $credentials
            );

            $handler->handle($input);
            break;


        case 'telegram':

            $handler = new TelegramHandler(
                (int)$client['id'],
                $credentials
            );

            $handler->handle($input);
            break;
    }

} catch (Throwable $e) {

    error_log("Webhook Fatal [$service]: " . $e->getMessage());
    http_response_code(500);
    exit;
}

http_response_code(200);
exit;


/*
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
|--------------------------------------------------------------------------
*/


function detectService(array $input): ?string
{
    if (!isset($input['object'])) {

        // Telegram update format
        if (isset($input['message']) || isset($input['callback_query'])) {
            return 'telegram';
        }

        return null;
    }

    if ($input['object'] === 'whatsapp_business_account') {
        return 'whatsapp';
    }

    if ($input['object'] === 'page') {

        $entry = $input['entry'][0] ?? [];

        if (!empty($entry['messaging'])) {
            return 'messenger';
        }

        if (
            !empty($entry['changes'][0]['field']) &&
            in_array($entry['changes'][0]['field'], ['feed', 'mention'], true)
        ) {
            return 'comment';
        }
    }

    return null;
}



function validateMetaSignature(string $payload): bool
{
    $header = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? null;

    if (!$header || !FB_APP_SECRET) {
        return false;
    }

    if (!str_contains($header, '=')) {
        return false;
    }

    [, $signature] = explode('=', $header, 2);

    $expected = hash_hmac('sha256', $payload, FB_APP_SECRET);

    return hash_equals($expected, $signature);
}



function findClient(string $service, array $input): ?array
{
    $identifier = extractIdentifier($service, $input);

    if (!$identifier) {
        return null;
    }

    switch ($service) {

        case 'messenger':
        case 'comment':

            return DB::fetch(
                "SELECT * FROM clients
                 WHERE service = ?
                 AND page_id = ?
                 AND status = 'active'
                 LIMIT 1",
                [$service, $identifier]
            );


        case 'whatsapp':

            return DB::fetch(
                "SELECT * FROM clients
                 WHERE service = 'whatsapp'
                 AND phone_number_id = ?
                 AND status = 'active'
                 LIMIT 1",
                [$identifier]
            );


        case 'telegram':

            return DB::fetch(
                "SELECT * FROM clients
                 WHERE service = 'telegram'
                 AND api_key = ?
                 AND status = 'active'
                 LIMIT 1",
                [$identifier]
            );
    }

    return null;
}



function extractIdentifier(string $service, array $input): ?string
{
    switch ($service) {

        case 'messenger':
        case 'comment':
            return $input['entry'][0]['id'] ?? null;

        case 'whatsapp':
            return $input['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null;

        case 'telegram':
            return $_GET['api_key'] ?? null;
    }

    return null;
}