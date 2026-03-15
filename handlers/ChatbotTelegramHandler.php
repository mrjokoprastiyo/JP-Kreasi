<?php

class ChatbotTelegramHandler
{
    public static function handle(int $user_id, array $product): never
    {
        DB::begin();

        try {
            $init = ClientService::initialStatus($product);
            $bot_token = trim($_POST['bot_token'] ?? '');

            if (!$bot_token) {
                throw new Exception("Bot Token Telegram wajib diisi.");
            }

            self::ensureNotRegistered($bot_token);

            $botInfo = self::getBotInfo($bot_token);

            $api_key = ClientService::apiKey();

            $webhookUrl = "https://" . $_SERVER['HTTP_HOST'] . "/webhook.php?api_key=" . $api_key;

            self::setWebhook($bot_token, $webhookUrl);

            $credentials = [
                'bot_token'    => $bot_token,
                'bot_username' => $botInfo['username'] ?? '',
                'bot_name'     => $botInfo['first_name'] ?? ''
            ];

            $meta = $init['meta'] ?? [];
            $meta['ai_engine'] = [
                'provider' => $_POST['ai_provider'] ?? '',
                'model'    => $_POST['ai_model'] ?? ''
            ];

            DB::execute(
                "INSERT INTO clients
                (user_id, product_id, name, service, provider, credentials, api_key, status, expired_at, meta)
                VALUES (?,?,?,?,?,?,?,?,?,?)",
                [
                    $user_id,
                    $product['id'],
                    trim($_POST['name'] ?? $credentials['bot_name']),
                    'telegram',
                    'telegram',
                    json_encode($credentials),
                    $api_key,
                    $init['status'],
                    $init['expired_at'],
                    json_encode($meta)
                ]
            );

            $id = DB::lastId();

            DB::commit();

            redirect("client-detail.php?id={$id}&status=telegram_active");
            exit;

        } catch (Throwable $e) {
            DB::rollback();
            throw new Exception("Telegram Handler: " . $e->getMessage());
        }
    }

    private static function ensureNotRegistered(string $bot_token): void
    {
        $exists = DB::fetch(
            "SELECT id FROM clients 
             WHERE service='telegram' 
             AND JSON_EXTRACT(credentials, '$.bot_token') = ? 
             LIMIT 1",
            [$bot_token]
        );

        if ($exists) {
            throw new Exception("Bot Telegram sudah terdaftar.");
        }
    }

    private static function getBotInfo(string $token): array
    {
        $url = "https://api.telegram.org/bot{$token}/getMe";
        $res = self::curl($url);

        if (!($res['ok'] ?? false)) {
            throw new Exception("Token Telegram tidak valid.");
        }

        return $res['result'];
    }

    private static function setWebhook(string $token, string $url): void
    {
        $apiUrl = "https://api.telegram.org/bot{$token}/setWebhook";

        $res = self::curl($apiUrl, [
            'url' => $url,
            'drop_pending_updates' => true
        ]);

        if (!($res['ok'] ?? false)) {
            throw new Exception("Gagal set Telegram webhook.");
        }
    }

    private static function curl(string $url, array $post = null): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30
        ]);

        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }
}