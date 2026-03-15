<?php
session_start();

// 1. Load Dependencies
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/settings-loader.php';

require_once '../core/helpers/html.php';
require_once '../core/helpers/asset.php';
require_once '../core/helpers/redirect.php';
require_once '../core/helpers/product-flow.php';

require_once '../core/services/ClientService.php';

require_once '../handlers/DesignOrderHandler.php';
require_once '../handlers/ChatbotWebHandler.php';
require_once '../handlers/ChatbotMessengerHandler.php';
require_once '../handlers/ChatbotCommentHandler.php';
require_once '../handlers/ChatbotWhatsAppHandler.php';
require_once '../handlers/ChatbotTelegramHandler.php';
require_once '../handlers/AutomationHandler.php';

// 2. Keamanan & Validasi Produk
Auth::check();

$user_id    = $_SESSION['user']['id'];
$product_id = (int)($_GET['product_id'] ?? 0);

if (!$product_id) {
    die('ID Produk diperlukan.');
}

$product = DB::fetch("SELECT * FROM products WHERE id = ?", [$product_id]);

if (!$product) {
    die('Produk tidak ditemukan atau tidak valid.');
}

// 3. Manajemen Sesi Meta (Refactor: Unset & Validasi)
if (isset($_SESSION['meta_login'])) {

    $s = $_SESSION['meta_login'];

    if (
        $s['product_id'] != $product_id ||
        $s['channel'] !== $product['service']
    ) {
        unset($_SESSION['meta_login']);
    }
}

// 4. Inisialisasi Alur (Flow)
$flow = resolveFlow($product);

// 5. Handling Form Submission (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        match ($flow) {
            'DESIGN_ORDER' => DesignOrderHandler::handle($user_id, $product),
            'CHATBOT_WEB'  => ChatbotWebHandler::handle($user_id, $product),
            'CHATBOT_CHANNEL' => match ($product['service']) {
                'messenger' => ChatbotMessengerHandler::handle($user_id, $product),
                'comment'   => ChatbotCommentHandler::handle($user_id, $product),
                'whatsapp'  => ChatbotWhatsAppHandler::handle($user_id, $product),
                'telegram'  => ChatbotTelegramHandler::handle($user_id, $product),
                default     => throw new Exception('Service tidak dikenali.')
            },
            'AUTOMATION_NOTIFICATION' => AutomationHandler::handle($user_id, $product),
            default => throw new Exception('Flow tidak valid.')
        };
        
        // Catatan: Unset meta_login_result sebaiknya dilakukan di dalam masing-masing 
        // Handler setelah sukses simpan ke DB agar data tidak hilang jika submit gagal.
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// 6. Pengambilan Data Meta Pages (Refactor: cURL)
$metaPages = [];

if (isset($_SESSION['meta_login'])) {

    $token   = $_SESSION['meta_login']['user_token'];
    $version = setting('meta-app-version', 'v18.0');

    $url = "https://graph.facebook.com/{$version}/me/accounts?" .
        http_build_query([
            'fields' => 'id,name,access_token',
            'access_token' => $token
        ]);

    $res = curlJson($url);

    if ($res && !isset($res['error'])) {
        $metaPages = $res['data'] ?? [];
    }
}

function curlJson($url)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30
    ]);

    $result = curl_exec($ch);

    if (curl_errno($ch)) {
        return null;
    }

    curl_close($ch);

    return json_decode($result, true);
}

// 7. Render View
include 'header.php';
include 'sidebar.php';

// Tampilkan pesan error jika ada
if (isset($error_message)) {
    echo "<div class='alert alert-danger'>Error: {$error_message}</div>";
}

// Memilih form berdasarkan Flow
match ($flow) {
    'DESIGN_ORDER'            => include 'forms/design-order.php',
    'CHATBOT_WEB'             => include 'forms/chatbot-web.php',
    'CHATBOT_CHANNEL'         => include 'forms/chatbot-channel.php',
    'AUTOMATION_NOTIFICATION' => include 'forms/automation.php',
    default                   => die('Halaman form tidak tersedia untuk tipe produk ini.')
};

include 'footer.php';
