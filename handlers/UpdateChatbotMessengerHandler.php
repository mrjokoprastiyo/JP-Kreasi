<?php

require_once __DIR__ . '/../core/settings-loader.php';

class UpdateChatbotMessengerHandler
{
    private static string $version;

    private static function init(): void
    {
        self::$version = setting('meta-app-version','v18.0');
    }

    public static function handle(int $user_id): never
    {
        self::init();
        DB::begin();

        try {

            $client_id = (int)($_POST['client_id'] ?? 0);

            $client = DB::fetch(
                "SELECT * FROM clients WHERE id=? AND user_id=? AND service='messenger'",
                [$client_id, $user_id]
            );

            if (!$client) {
                throw new Exception("Client tidak ditemukan.");
            }

            $cred = json_decode($client['credentials'], true) ?? [];
            $meta = json_decode($client['meta'], true) ?? [];

            // =============================
            // TOKEN REFRESH MODE
            // =============================
            if (!empty($_SESSION['meta_login']['user_token'])) {

                $newToken = self::refreshPageToken(
                    $cred['page_id'],
                    $_SESSION['meta_login']['user_token']
                );

                $cred['access_token'] = $newToken;

                self::subscribeWebhook(
                    $cred['page_id'],
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

            DB::execute(
                "UPDATE clients 
                 SET name=?, credentials=?, meta=? 
                 WHERE id=?",
                [
                    trim($_POST['name']),
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
            throw new Exception("Update Messenger Error: ".$e->getMessage());
        }
    }

    private static function refreshPageToken(string $page_id, string $user_token): string
    {
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

        throw new Exception("Page tidak ditemukan saat refresh token.");
    }

    private static function subscribeWebhook(string $page_id, string $page_token): void
    {
        $url = "https://graph.facebook.com/" . self::$version . "/{$page_id}/subscribed_apps";

        $res = self::curl($url, [
            'subscribed_fields' => 'messages,messaging_postbacks,feed',
            'access_token'      => $page_token
        ]);

        if (!($res['success'] ?? false)) {
            throw new Exception("Gagal subscribe ulang webhook.");
        }
    }

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
        curl_close($ch);

        return json_decode($response,true) ?? [];
    }
}