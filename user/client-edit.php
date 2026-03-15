<?php
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/settings-loader.php';

require_once '../core/helpers/html.php';
require_once '../core/helpers/asset.php';
require_once '../core/helpers/redirect.php';
require_once '../core/helpers/product-flow.php';

require_once '../core/services/ClientService.php';

// Update handlers
require_once '../handlers/UpdateDesignOrderHandler.php';
require_once '../handlers/UpdateChatbotWebHandler.php';
require_once '../handlers/UpdateChatbotMessengerHandler.php';
require_once '../handlers/UpdateChatbotCommentHandler.php';
require_once '../handlers/UpdateChatbotWhatsAppHandler.php';
require_once '../handlers/UpdateChatbotTelegramHandler.php';
require_once '../handlers/UpdateAutomationHandler.php';

Auth::check();

$user_id   = $_SESSION['user']['id'];
$client_id = (int)($_GET['id'] ?? 0);

if (!$client_id)
    die('Client tidak valid');

// 🔥 Load CLIENT dulu (bukan product langsung)
$client = DB::fetch(
    "SELECT * FROM clients WHERE id=? AND user_id=?",
    [$client_id, $user_id]
);

if (!$client)
    die('Client tidak ditemukan');

// Ambil product dari client
$product = DB::fetch(
    "SELECT * FROM products WHERE id=?",
    [$client['product_id']]
);

if (!$product)
    die('Produk tidak ditemukan');

$flow = resolveFlow($product);


// ===============================
// HANDLE UPDATE
// ===============================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    match ($flow)
    {
        'DESIGN_ORDER'
            => UpdateDesignOrderHandler::handle($user_id, $client, $product),

        'CHATBOT_WEB'
            => UpdateChatbotWebHandler::handle($user_id, $client, $product),

        'CHATBOT_CHANNEL'
            => match ($client['service'])
            {
                'messenger'
                    => UpdateChatbotMessengerHandler::handle($user_id, $client, $product),

                'comment'
                    => UpdateChatbotCommentHandler::handle($user_id, $client, $product),

                'whatsapp'
                    => UpdateChatbotWhatsAppHandler::handle($user_id, $client, $product),

                'telegram'
                    => UpdateChatbotTelegramHandler::handle($user_id, $client, $product),

                default => die('Service tidak valid')
            },

        'AUTOMATION_NOTIFICATION'
            => UpdateAutomationHandler::handle($user_id, $client, $product),

        default => die('Flow tidak valid')
    };
}

// ===============================
// VIEW
// ===============================

include 'header.php';
include 'sidebar.php';

match ($flow)
{
    FLOW_DESIGN
        => include 'forms/design-order-edit.php',

    FLOW_CHATBOT_WEB
        => include 'forms/chatbot-web-edit.php',

    FLOW_CHATBOT_CHANNEL
        => include 'forms/chatbot-channel-edit.php',

    FLOW_AUTOMATION
        => include 'forms/automation-edit.php',

    default => die('Form tidak tersedia')
};

include 'footer.php';