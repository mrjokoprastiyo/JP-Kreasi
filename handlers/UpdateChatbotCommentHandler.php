<?php

require_once __DIR__ . '/../core/settings-loader.php';

class UpdateChatbotCommentHandler
{
    private static string $version;

    /* =========================================================
     * INIT
     * =======================================================*/
    private static function init(): void
    {
        self::$version = setting('meta-app-version','v18.0');
    }

    /* =========================================================
     * MAIN HANDLER (UPDATE)
     * =======================================================*/
    public static function handle(int $user_id): never
    {
        self::init();
        DB::begin();

        try {

            $client_id = (int)($_POST['client_id'] ?? 0);

            $client = DB::fetch(
                "SELECT * FROM clients WHERE id=? AND user_id=? AND service='comment'",
                [$client_id, $user_id]
            );

            if (!$client) {
                throw new Exception("Client Comment Bot tidak ditemukan.");
            }

            $cred = json_decode($client['credentials'], true) ?? [];
            $meta = json_decode($client['meta'], true) ?? [];

            // =============================
            // REFRESH TOKEN META
            // =============================
            if (!empty($_SESSION['meta_login']['user_token'])) {

                $newToken = self::refreshPageToken(
                    $cred['page_id'] ?? '',
                    $_SESSION['meta_login']['user_token']
                );

                $cred['access_token'] = $newToken;

                self::subscribeWebhook(
                    $cred['page_id'] ?? '',
                    $newToken
                );

                unset($_SESSION['meta_login']);
            }

            // =============================
            // UPDATE AI ENGINE
            // =============================
            $meta['ai_engine'] = [
                'provider' => trim($_POST['ai_provider'] ?? ''),
                'model'    => trim($_POST['ai_model'] ?? '')
            ];

            // =============================
            // UPDATE DATABASE
            // =============================
            DB::execute(
                "UPDATE clients 
                 SET name=?, credentials=?, meta=? 
                 WHERE id=?",
                [
                    trim($_POST['name'] ?? $client['name']),
                    json_encode($cred),
                    json_encode($meta),
                    $client_id
                ]
            );

            DB::commit();

            redirect("client-detail.php?id={$client_id}&status=updated");
            exit;

        } catch (Throwable $e) {
            DB::rollback();
            throw new Exception("Update Comment Bot Error: " . $e->getMessage());
        }
    }

    /* =========================================================
     * REFRESH PAGE TOKEN META
     * =======================================================*/
    private static function refreshPageToken(string $page_id, string $user_token): string
    {
        if (!$page_id) throw new Exception("Page ID tidak ditemukan untuk refresh token.");

        $url = "https://graph.facebook.com/" . self::$version . "/me/accounts?" .
            http_build_query([
                'fields'       => 'id,access_token',
                'access_token' => $user_token
            ]);

        $res = self::curl($url);

        foreach ($res['data'] ?? [] as $page) {
            if ($page['id'] === $page_id) {
                return $page['access_token'];
            }
        }

        throw new Exception("Page ID tidak ditemukan saat refresh token.");
    }

    /* =========================================================
     * SUBSCRIBE WEBHOOK COMMENT
     * =======================================================*/
    private static function subscribeWebhook(string $page_id, string $token): void
    {
        if (!$page_id || !$token) return;

        $url = "https://graph.facebook.com/" . self::$version . "/{$page_id}/subscribed_apps";

        $res = self::curl($url, [
            'subscribed_fields' => 'feed,mention',
            'access_token'      => $token
        ]);

        if (!($res['success'] ?? false)) {
            throw new Exception("Gagal subscribe webhook feed/mention.");
        }
    }

    /* =========================================================
     * CURL HELPER
     * =======================================================*/
    private static function curl(string $url, array $post = null): array
    {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 20
        ]);

        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }

        $response = curl_exec($ch);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Curl Error: " . $error);
        }

        curl_close($ch);

        $decoded = json_decode($response, true);
        if (isset($decoded['error'])) {
            throw new Exception($decoded['error']['message'] ?? 'Graph API Error');
        }

        return $decoded ?? [];
    }
}