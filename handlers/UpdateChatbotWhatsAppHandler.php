<?php

require_once __DIR__ . '/../core/settings-loader.php';

class UpdateChatbotWhatsAppHandler
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
                "SELECT * FROM clients WHERE id=? AND user_id=? AND service='whatsapp'",
                [$client_id, $user_id]
            );

            if (!$client) {
                throw new Exception("Client WhatsApp tidak ditemukan.");
            }

            $cred = json_decode($client['credentials'], true) ?? [];
            $meta = json_decode($client['meta'], true) ?? [];

            // =============================
            // TOKEN REFRESH (jika login Meta)
            // =============================
            if (!empty($_SESSION['meta_login']['user_token'])) {

                $newToken = self::refreshWabaToken(
                    $cred['waba_id'] ?? '',
                    $_SESSION['meta_login']['user_token']
                );

                $cred['access_token'] = $newToken;

                self::subscribeWaba(
                    $cred['waba_id'] ?? '',
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
            throw new Exception("Update WhatsApp Error: " . $e->getMessage());
        }
    }

    /* =========================================================
     * TOKEN REFRESH META
     * =======================================================*/
    private static function refreshWabaToken(string $waba_id, string $user_token): string
    {
        $url = "https://graph.facebook.com/" . self::$version . "/me/accounts?" .
            http_build_query([
                'fields'       => 'id,access_token',
                'access_token' => $user_token
            ]);

        $res = self::curl($url);

        foreach ($res['data'] ?? [] as $page) {
            if ($page['id'] === $waba_id) {
                return $page['access_token'];
            }
        }

        throw new Exception("WABA ID tidak ditemukan saat refresh token.");
    }

    /* =========================================================
     * SUBSCRIBE WABA
     * =======================================================*/
    private static function subscribeWaba(string $waba_id, string $token): void
    {
        if (!$waba_id || !$token) return;

        $url = "https://graph.facebook.com/" . self::$version . "/{$waba_id}/subscribed_apps";

        $res = self::curl($url, [
            'access_token' => $token
        ]);

        if (!($res['success'] ?? false)) {
            throw new Exception("Gagal subscribe WABA.");
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