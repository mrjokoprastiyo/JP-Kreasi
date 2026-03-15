<?php

class AutomationHandler
{
    private static $meta_app_id;
    private static $meta_secret;
    private static $meta_version;

    private static function initMeta()
    {
        self::$meta_app_id  = setting('meta-app-id');
        self::$meta_secret  = setting('meta-app-secret');
        self::$meta_version = setting('meta-app-version', 'v22.0');
    }

    // =====================================================
    // UPDATE AUTOMATION
    // =====================================================
    public static function update(int $userId, array $product, int $clientId): never
    {
        self::initMeta();

        $payload = $_POST;
        $service = $product['service'];

        DB::begin();

        try {

            // ==========================
            // VALIDASI CLIENT MILIK USER
            // ==========================
            $client = DB::fetch(
                "SELECT * FROM clients WHERE id = ? AND user_id = ?",
                [$clientId, $userId]
            );

            if (!$client) {
                throw new Exception("Automation tidak ditemukan.");
            }

            $oldCredentials = json_decode($client['credentials'], true) ?? [];
            $oldMeta        = json_decode($client['meta'], true) ?? [];

            // ==========================
            // VALIDASI PSID
            // ==========================
            if (in_array($service, ['messenger', 'whatsapp'])) {

                $psid = $payload['target']['destination']['psid'] ?? null;

                if (!$psid) {
                    throw new Exception("PSID belum dipilih.");
                }
            }

            // ==========================
            // RESOLVE CREDENTIALS BARU
            // ==========================
            $credentials = self::resolveCredentialsUpdate(
                $product,
                $payload,
                $oldCredentials
            );

            // ==========================
            // META BARU
            // ==========================
            $newMeta = [
                'source' => $payload['source'] ?? [],
                'file'   => $payload['file'] ?? [],
                'target' => $payload['target']['destination'] ?? []
            ];

            // ==========================
            // UPDATE DATABASE
            // ==========================
            DB::execute("
                UPDATE clients SET
                    name        = ?,
                    credentials = ?,
                    meta        = ?,
                    updated_at  = NOW()
                WHERE id = ? AND user_id = ?
            ", [
                $payload['name'] ?? $client['name'],
                json_encode($credentials),
                json_encode($newMeta),
                $clientId,
                $userId
            ]);

            // ==========================
            // RESUBSCRIBE WEBHOOK JIKA PAGE BERUBAH
            // ==========================
            if (
                $service === 'messenger' &&
                isset($credentials['page_id'], $credentials['page_access_token'])
            ) {

                if (
                    !isset($oldCredentials['page_id']) ||
                    $oldCredentials['page_id'] !== $credentials['page_id']
                ) {
                    self::subscribeWebhook(
                        $credentials['page_id'],
                        $credentials['page_access_token']
                    );
                }
            }

            DB::commit();

            unset($_SESSION['meta_login']);

            redirect("client-detail.php?id={$clientId}&status=updated");

        } catch (Throwable $e) {

            DB::rollback();
            die("Update Automation Error: " . $e->getMessage());
        }
    }

    // =====================================================
    // RESOLVE CREDENTIALS UNTUK UPDATE
    // =====================================================
    private static function resolveCredentialsUpdate(
        array $product,
        array $payload,
        array $oldCredentials
    ): array {

        $service = $product['service'];

        // SYSTEM MANAGED → tidak berubah
        if ($product['product_type'] === 'system') {
            return $oldCredentials;
        }

        // ======================
        // MESSENGER
        // ======================
        if ($service === 'messenger') {

            if (!isset($_SESSION['meta_login'])) {
                return $oldCredentials; // tidak reconnect → pakai lama
            }

            if (!empty($payload['page_access_token'])) {

                return [
                    'page_id' => $payload['page_id'],
                    'page_access_token' => self::exchangeLongLivedToken(
                        $payload['page_access_token']
                    )
                ];
            }

            return $oldCredentials;
        }

        // ======================
        // WHATSAPP
        // ======================
        if ($service === 'whatsapp') {

            if (!isset($_SESSION['meta_login'])) {
                return $oldCredentials;
            }

            return [
                'access_token' => self::exchangeLongLivedToken(
                    $_SESSION['meta_login']['user_token']
                )
            ];
        }

        // ======================
        // TELEGRAM
        // ======================
        if ($service === 'telegram') {

            return [
                'bot_token' =>
                    $payload['target']['credentials']['bot_token']
                    ?? $oldCredentials['bot_token']
            ];
        }

        return $oldCredentials;
    }

    // =====================================================
    // EXCHANGE LONG LIVED TOKEN
    // =====================================================
    private static function exchangeLongLivedToken($shortToken)
    {
        $url = "https://graph.facebook.com/" . self::$meta_version . "/oauth/access_token?" .
            http_build_query([
                'grant_type'        => 'fb_exchange_token',
                'client_id'         => self::$meta_app_id,
                'client_secret'     => self::$meta_secret,
                'fb_exchange_token' => $shortToken
            ]);

        $res = json_decode(@file_get_contents($url), true);

        return $res['access_token'] ?? $shortToken;
    }

    // =====================================================
    // SUBSCRIBE WEBHOOK
    // =====================================================
    private static function subscribeWebhook($pageId, $token)
    {
        $url = "https://graph.facebook.com/" . self::$meta_version . "/{$pageId}/subscribed_apps";

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'subscribed_fields' => 'messages,messaging_postbacks',
            'access_token'      => $token
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
        curl_close($ch);
    }
}