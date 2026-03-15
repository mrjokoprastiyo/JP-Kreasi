<?php            
ini_set('display_errors', 0);            
error_reporting(E_ALL);            
            
header("Content-Type: application/json");            
            
require_once '../core/db.php';            
require_once '../core/client-validator.php';            
            
/* ===============================            
   HELPER RESPONSE            
================================ */            
function response($data, $code = 200) {            
    http_response_code($code);            
    echo json_encode($data, JSON_UNESCAPED_UNICODE);            
    exit;            
}            
            
/* ===============================            
   API KEY (CLIENT BASED)            
================================ */            
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';            
if (!$apiKey) response(['error' => 'Missing API key'], 401);            
            
$client = validateClientByApiKey($apiKey);            
if (!$client) response(['error' => 'Invalid client'], 403);            
            
if ($client['status'] !== 'active') {            
    response(['error' => 'Client not active'], 403);            
}            
            
/* ===============================            
   VALIDATE SERVICE            
================================ */            
if ($client['service'] === 'website') {            
    response(['error' => 'Website service not supported for automation'], 400);            
}            
            
/* ===============================            
   LOAD CONFIG FROM CLIENT            
================================ */            
$config = json_decode($client['credentials'], true);            
if (!$config) {            
    response(['error' => 'Invalid automation config'], 500);            
}            
            
$source = $config['source'] ?? null;            
$target = $config['target']['destination'] ?? null;            
            
if (!$source || !$target) {            
    response(['error' => 'Incomplete automation config'], 422);            
}            
            
/* ===============================            
   SOURCE HANDLER            
================================ */            
function fetchGoogleSheet(array $cfg): array {            
    $id  = $cfg['spreadsheet_id'];            
    $gid = $cfg['gid'] ?? 0;            
            
    return [            
        'pdf'  => "https://docs.google.com/spreadsheets/d/$id/export?format=pdf",            
        'xlsx' => "https://docs.google.com/spreadsheets/d/$id/export?format=xlsx",            
        'csv'  => "https://docs.google.com/spreadsheets/d/$id/export?format=csv&gid=$gid",            
        'html' => "https://docs.google.com/spreadsheets/d/$id/export?format=html"            
    ];            
}            
            
$urls = fetchGoogleSheet($source);            
            
$req = is_array($GLOBALS['automation_payload'] ?? null)  
    ? $GLOBALS['automation_payload']  
    : [];  
  
$fileType = $req['output']['format']  
    ?? $source['file_type']  
    ?? 'pdf';  
  
if (!isset($urls[$fileType])) {  
    response(['error' => 'Unsupported file type'], 400);  
}  
  
$fileUrl = $urls[$fileType];  
  
$context = stream_context_create([  
    'http' => ['timeout' => 15],  
    'ssl'  => ['verify_peer' => true]  
]);  
  
$fileData = @file_get_contents($fileUrl, false, $context);  
  
if ($fileData === false) {  
    response(['error' => 'Failed to fetch source file'], 500);  
}  
  
$fileName = "automation.$fileType";  
            
/* ===============================            
   RESOLVE SENDER CREDENTIAL            
================================ */            
$senderCred = [];            
            
/**            
 * SYSTEM MODE            
 * ambil token dari message_providers            
 */            
if ($client['provider'] === 'system') {            
            
    $provider = DB::fetch(            
        "SELECT * FROM message_providers            
         WHERE channel = ? AND status = 'active'            
         LIMIT 1",            
        [$client['service']]            
    );            
            
    if (!$provider) {            
        response(['error' => 'System provider not found'], 500);            
    }            
            
    $senderCred = json_decode($provider['credentials'], true);            
            
/**            
 * CLIENT MODE            
 * token milik client            
 */            
} else {            
            
    if (empty($config['sender'])) {            
        response(['error' => 'Client sender credential missing'], 422);            
    }            
            
    $senderCred = $config['sender'];            
}            
            
/* ===============================            
   TARGET DISPATCHER            
================================ */            
$service = $req['target']['channel']          
    ?? $client['service'];          
          
switch ($service) {          
            
    case 'telegram':            
        require_once 'targets/telegram.php';            
        sendTelegram(            
            $senderCred,            
            $target['chat_id'],            
            $fileData,            
            $fileName,            
            $fileType            
        );            
        break;            
            
    case 'whatsapp':            
        require_once 'targets/whatsapp.php';            
        sendWhatsApp(            
            $senderCred,            
            $target['phone'],            
            $fileUrl,            
            $fileName            
        );            
        break;            
            
    case 'email':            
        require_once 'targets/email.php';            
        sendEmail(            
            $senderCred,            
            $target['email'],            
            $fileData,            
            $fileName            
        );            
        break;            
            
    case 'messenger':            
        require_once 'targets/messenger.php';            
        sendMessenger(            
            $senderCred,            
            $target['psid'],            
            $fileUrl            
        );            
        break;            
            
    case 'website':            
        require_once 'targets/website.php';            
        sendWebsite(            
            $senderCred,            
            $fileData,            
            $fileName            
        );            
        break;            
            
    default:            
        response(['error' => 'Unsupported channel'], 400);            
}            
            
response(['status' => 'ok']);