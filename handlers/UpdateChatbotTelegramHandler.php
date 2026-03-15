<?php

class UpdateChatbotTelegramHandler
{
    /* =========================================================
     * MAIN HANDLER
     * =======================================================*/
    public static function handle(int $user_id): never
    {
        DB::begin();

        try {
            $clientId = (int)($_POST['client_id'] ?? 0);

            $client = DB::fetch(
                "SELECT * FROM clients WHERE id=? AND user_id=? AND service='telegram'",
                [$clientId, $user_id]
            );

            if (!$client) {
                throw new Exception("Client Telegram Bot tidak ditemukan.");
            }

            $bot_token = trim($_POST['bot_token'] ?? '');
            if (empty($bot_token)) {
                throw new Exception("Bot Token Telegram wajib diisi!");
            }

            /* ================= VALIDATE TOKEN ================= */
            $botInfo = self::getBotInfo($bot_token);

            $credentials = [
                'bot_token'    => $bot_token,
                'bot_username' => $botInfo['username'] ?? '',
                'bot_name'     => $botInfo['first_name'] ?? $_POST['name']
            ];

            /* ================= META CONFIG ================= */
            $meta = json_decode($client['meta'], true) ?? [];
            $meta['ai_engine'] = [
                'provider' => trim($_POST['ai_provider'] ?? ''),
                'model'    => trim($_POST['ai_model'] ?? '')
            ];

            /* ================= UPDATE DATABASE ================= */
            DB::execute(
                "UPDATE clients SET
                    name=?,
                    credentials=?,
                    meta=?,
                    updated_at=NOW()
                 WHERE id=?",
                [
                    trim($_POST['name'] ?? $client['name']),
                    json_encode($credentials, JSON_UNESCAPED_SLASHES),
                    json_encode($meta, JSON_UNESCAPED_SLASHES),
                    $clientId
                ]
            );

            /* ================= UPDATE WEBHOOK ================= */
            $apiKey = $client['api_key']; // tetap gunakan api_key lama
            $webhookUrl = "https://" . $_SERVER['HTTP_HOST'] .
                          "/webhook.php?api_key=" . $apiKey;

            self::setTelegramWebhook($bot_token, $webhookUrl);

            DB::commit();

            redirect("client-detail.php?id={$clientId}&msg=telegram_updated");
            exit;

        } catch (Throwable $e) {
            DB::rollback();
            throw new Exception("Update Telegram Bot Error: " . $e->getMessage());
        }
    }

    /* =========================================================
       VALIDATE BOT TOKEN
    ========================================================= */
    private static function getBotInfo(string $token): array
    {
        $url = "https://api.telegram.org/bot{$token}/getMe";

        $res = @file_get_contents($url);
        if (!$res) throw new Exception("Token Telegram tidak valid atau koneksi gagal.");

        $json = json_decode($res, true);
        if (empty($json['ok'])) {
            throw new Exception("Telegram Error: " . ($json['description'] ?? 'Unknown error'));
        }

        return $json['result'];
    }

    /* =========================================================
       SET TELEGRAM WEBHOOK
    ========================================================= */
    private static function setTelegramWebhook(string $token, string $url): void
    {
        $apiUrl = "https://api.telegram.org/bot{$token}/setWebhook?" .
                  http_build_query([
                      'url' => $url,
                      'drop_pending_updates' => true
                  ]);

        $res = @file_get_contents($apiUrl);
        if (!$res) {
            error_log("Telegram Webhook Fail: No response");
            return;
        }

        $json = json_decode($res, true);
        if (empty($json['ok'])) {
            error_log("Telegram Webhook Fail: " . ($json['description'] ?? 'Unknown'));
        }
    }
}