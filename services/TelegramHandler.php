<?php
require_once __DIR__ . '/../ai.php';

declare(strict_types=1);

class TelegramHandler
{
    private int $clientId;
    private array $credentials;
    private string $botToken;
    private string $apiBase;

    public function __construct(int $clientId, array $credentials)
    {
        $this->clientId    = $clientId;
        $this->credentials = $credentials;
        $this->botToken    = $credentials['bot_token'] ?? '';

        if (!$this->botToken) {
            throw new RuntimeException("Telegram bot_token missing");
        }

        $this->apiBase = "https://api.telegram.org/bot{$this->botToken}/";
    }

    /*
    |--------------------------------------------------------------------------
    | MAIN ENTRY
    |--------------------------------------------------------------------------
    */

    public function handle(array $payload): void
    {
        if (isset($payload['message'])) {
            $this->handleMessage($payload['message']);
            return;
        }

        if (isset($payload['callback_query'])) {
            $this->handleCallback($payload['callback_query']);
            return;
        }

        // ignore other updates (edited_message, channel_post, dll)
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE MESSAGE
    |--------------------------------------------------------------------------
    */

    private function handleMessage(array $message): void
    {
        $chatId   = $message['chat']['id'] ?? null;
        $userId   = $message['from']['id'] ?? null;
        $text     = $message['text'] ?? null;
        $username = $message['from']['username'] ?? null;
        $fullName = trim(
            ($message['from']['first_name'] ?? '') . ' ' .
            ($message['from']['last_name'] ?? '')
        );

        if (!$chatId || !$userId) {
            return;
        }

        // hanya proses text
        if (!$text) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | NORMALIZE PAYLOAD FOR ENGINE
        |--------------------------------------------------------------------------
        */

        $enginePayload = [
            'platform' => 'telegram',
            'sender_id' => (string)$userId,
            'chat_id' => (string)$chatId,
            'message' => $text,
            'sender_name' => $fullName ?: $username,
        ];

        /*
        |--------------------------------------------------------------------------
        | SEND TO USER ENGINE
        |--------------------------------------------------------------------------
        */

        $response = UserEngine::handleIncoming(
            $this->clientId,
            $enginePayload
        );

        if (!$response) {
            return;
        }

        $this->sendMessage($chatId, $response);
    }

    /*
    |--------------------------------------------------------------------------
    | HANDLE CALLBACK QUERY
    |--------------------------------------------------------------------------
    */

    private function handleCallback(array $callback): void
    {
        $chatId = $callback['message']['chat']['id'] ?? null;
        $userId = $callback['from']['id'] ?? null;
        $data   = $callback['data'] ?? null;
        $callbackId = $callback['id'] ?? null;

        if (!$chatId || !$userId) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | ACK CALLBACK (WAJIB)
        |--------------------------------------------------------------------------
        */

        if ($callbackId) {
            $this->answerCallbackQuery($callbackId);
        }

        if (!$data) {
            return;
        }

        $enginePayload = [
            'platform' => 'telegram',
            'sender_id' => (string)$userId,
            'chat_id' => (string)$chatId,
            'message' => $data,
            'is_callback' => true,
        ];

        $response = UserEngine::handleIncoming(
            $this->clientId,
            $enginePayload
        );

        if ($response) {
            $this->sendMessage($chatId, $response);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SEND MESSAGE
    |--------------------------------------------------------------------------
    */

    private function sendMessage(string|int $chatId, string $text): void
    {
        $this->apiRequest('sendMessage', [
            'chat_id' => $chatId,
            'text'    => $text,
            'parse_mode' => 'HTML'
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ANSWER CALLBACK QUERY
    |--------------------------------------------------------------------------
    */

    private function answerCallbackQuery(string $callbackId): void
    {
        $this->apiRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackId
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | TELEGRAM API REQUEST
    |--------------------------------------------------------------------------
    */

    private function apiRequest(string $method, array $params): void
    {
        $ch = curl_init($this->apiBase . $method);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            error_log("Telegram API Error: " . curl_error($ch));
        }

        curl_close($ch);
    }
}