<?php
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/settings-loader.php';
require_once '../core/base.php';

Auth::check();

/* =========================================================
 * INPUT
 * =======================================================*/
$mode       = $_GET['mode'] ?? 'create'; // create | refresh
$product_id = (int)($_GET['product_id'] ?? 0);
$channel    = $_GET['channel'] ?? '';
$client_id  = (int)($_GET['client_id'] ?? 0);

/* =========================================================
 * VALID CHANNEL
 * =======================================================*/
if (!in_array($channel, ['messenger','comment','whatsapp'])) {
    die('Channel tidak valid.');
}

/* =========================================================
 * MODE: REFRESH TOKEN
 * =======================================================*/
if ($mode === 'refresh') {

    if (!$client_id) {
        die('Client ID tidak valid.');
    }

    // Validasi client milik user login
    $client = DB::fetch(
        "SELECT id, service FROM clients WHERE id=? AND user_id=? LIMIT 1",
        [$client_id, $_SESSION['user']['id']]
    );

    if (!$client) {
        die('Client tidak ditemukan atau bukan milik Anda.');
    }

    if ($client['service'] !== $channel) {
        die('Channel tidak sesuai dengan client.');
    }

    $_SESSION['refresh_client_id'] = $client_id;

    // product_id tidak diperlukan saat refresh
    $product_id = 0;
}

/* =========================================================
 * MODE: CREATE (NORMAL FLOW)
 * =======================================================*/
if ($mode === 'create') {

    if (!$product_id) {
        die('Product ID tidak valid.');
    }

    if (!$channel) {
        die('Channel tidak valid.');
    }
}

/* =========================================================
 * META APP CONFIG
 * =======================================================*/
$app_id  = setting('meta-app-id');
$version = setting('meta-app-version', 'v18.0');

if (!$app_id) {
    die('Meta App ID belum dikonfigurasi.');
}

/* =========================================================
 * STATE GENERATION
 * =======================================================*/
$state = bin2hex(random_bytes(32));

$_SESSION['meta_oauth'] = [
    'state'      => $state,
    'mode'       => $mode,
    'product_id' => $product_id,
    'channel'    => $channel,
    'created_at' => time()
];

/* =========================================================
 * SCOPE PER CHANNEL
 * =======================================================*/
$scopes = match ($channel) {

    'messenger' => [
        'pages_show_list',
        'pages_messaging',
        'pages_read_engagement',
        'pages_manage_metadata',
        'pages_manage_engagement'
    ],

    'comment' => [
        'pages_show_list',
        'pages_read_engagement',
        'pages_manage_engagement'
    ],

    'whatsapp' => [
        'whatsapp_business_management',
        'whatsapp_business_messaging'
    ],

    default => []
};

/* =========================================================
 * OAUTH REDIRECT
 * =======================================================*/
$params = [
    'client_id'     => $app_id,
    'redirect_uri'  => BASE_URL . "/user/meta-callback.php",
    'state'         => $state,
    'scope'         => implode(',', $scopes),
    'response_type' => 'code'
];

header("Location: https://www.facebook.com/{$version}/dialog/oauth?" . http_build_query($params));
exit;