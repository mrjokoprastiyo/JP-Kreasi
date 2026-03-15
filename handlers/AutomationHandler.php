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

    public static function handle(int $userId, array $product): never
    {
        self::initMeta();

        $payload   = $_POST;
        $service   = $product['service'];
        $isWebsite = ($service === 'website');

        DB::begin();

        try {

            $init   = ClientService::initialStatus($product);
            $apiKey = ClientService::apiKey();

            // ==========================
            // RESOLVE CREDENTIALS
            // ==========================
            $credentials = self::resolveCredentials($product, $payload);

            // ==========================
            // VALIDASI PSID (Messenger / WA Meta)
            // ==========================
            if (in_array($service, ['messenger', 'whatsapp'])) {

                $psid = $payload['target']['destination']['psid'] ?? null;

                if (!$psid && !$isWebsite) {
                    throw new Exception("PSID belum dipilih. Silakan pilih target penerima.");
                }
            }

            // ==========================
            // INSERT CLIENT
            // ==========================
            DB::execute("
                INSERT INTO clients
                (user_id, product_id, name, service, provider, credentials, api_key, status, expired_at, meta)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ", [
                $userId,
                $product['id'],
                $payload['name'] ?? 'Untitled Automation',
                $service,
                $isWebsite ? 'manual' : ($product['product_type'] === 'system' ? 'system' : 'custom'),
                json_encode($credentials),
                $apiKey,
                $isWebsite ? 'pending' : $init['status'],
                $init['expired_at'],
                json_encode([
                    'source' => $payload['source'] ?? [],
                    'file'   => $payload['file'] ?? [],
                    'target' => $payload['target']['destination'] ?? []
                ])
            ]);

            $clientId = DB::lastId();

            // ==========================
            // SUBSCRIBE WEBHOOK (Messenger)
            // ==========================
            if (
                $service === 'messenger' &&
                isset($credentials['page_id'], $credentials['page_access_token'])
            ) {
                self::subscribeWebhook(
                    $credentials['page_id'],
                    $credentials['page_access_token']
                );
            }

            DB::commit();

            unset($_SESSION['meta_login']);

            if ($isWebsite) {
                self::redirectToWhatsapp($product, $payload, $clientId);
            }

            redirect("client-detail.php?id={$clientId}&status=success");

        } catch (Throwable $e) {
            DB::rollback();
            die("Automation Error: " . $e->getMessage());
        }
    }

    // =====================================================
    // RESOLVE CREDENTIALS (SELARAS FORM)
    // =====================================================
    private static function resolveCredentials(array $product, array $payload): array
    {
        $service = $product['service'];

        // ======================
        // SYSTEM MANAGED
        // ======================
        if ($product['product_type'] === 'system') {
            return ['mode' => 'managed_by_system'];
        }

        // ======================
        // MESSENGER
        // ======================
        if ($service === 'messenger') {

            if (!isset($_SESSION['meta_login'])) {
                throw new Exception("Facebook Page belum terkoneksi.");
            }

            if (empty($payload['page_id']) || empty($payload['page_access_token'])) {
                throw new Exception("Page ID atau Access Token tidak valid.");
            }

            return [
                'page_id'           => $payload['page_id'],
                'page_access_token' => self::exchangeLongLivedToken(
                    $payload['page_access_token']
                )
            ];
        }

        // ======================
        // WHATSAPP
        // ======================
        if ($service === 'whatsapp') {

            if (!isset($_SESSION['meta_login'])) {
                throw new Exception("WhatsApp belum terkoneksi.");
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
                'bot_token' => $payload['target']['credentials']['bot_token'] ?? null
            ];
        }

        // ======================
        // EMAIL / WEBSITE / OTHER
        // ======================
        return $payload['target']['credentials'] ?? [];
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

    // =====================================================
    // REDIRECT WEBSITE ORDER
    // =====================================================
    private static function redirectToWhatsapp(array $product, array $payload, int $clientId): never
    {
        $dest   = $payload['target']['destination'] ?? [];
        $source = $payload['source'] ?? [];

        $msg = "
🚀 ORDER AUTOMATION WEBSITE
━━━━━━━━━━━━━━
Produk: {$product['name']}
Automation Name: {$payload['name']}
Spreadsheet ID: {$source['spreadsheet_id']}
File Output: {$payload['file']['type']}

Endpoint: {$dest['endpoint']}
Metode: {$dest['method']}
Client ID: {$clientId}
━━━━━━━━━━━━━━
";

        $wa = setting('admin-contact-whatsapp');

        redirect("https://api.whatsapp.com/send?phone={$wa}&text=" . rawurlencode($msg));
        exit;
    }
}