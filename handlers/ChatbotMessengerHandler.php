<?php

require_once __DIR__ . '/../core/settings-loader.php';

class ChatbotMessengerHandler
{
    private static string $app_id;
    private static string $app_secret;
    private static string $version;

    /* =====================================================
     * INIT
     * ===================================================== */
    private static function init(): void
    {
        self::$app_id     = setting('meta-app-id');
        self::$app_secret = setting('meta-app-secret');
        self::$version    = setting('meta-app-version', 'v18.0');

        if (!self::$app_id || !self::$app_secret) {
            throw new Exception("Konfigurasi Meta belum disetting.");
        }
    }

    /* =====================================================
     * MAIN HANDLER
     * ===================================================== */
    public static function handle(int $user_id, array $product): never
    {
        self::init();
        DB::begin();

        try {

            if (empty($_SESSION['meta_login']['user_token'])) {
                throw new Exception("Login Meta belum dilakukan.");
            }

            $init = ClientService::initialStatus($product);

            $creds = self::buildCredentials();

            self::validatePageAccess($creds);

            self::ensureNotRegistered($creds['page_id']);

            $meta = self::buildMetaConfig($init);

            $clientId = self::insertClient(
                $user_id,
                $product['id'],
                $creds,
                $init,
                $meta
            );

            self::subscribePage($creds['page_id'], $creds['access_token']);

            unset($_SESSION['meta_login']);

            DB::commit();
            redirect("client-detail.php?id={$clientId}&status=success");
            exit;

        } catch (Throwable $e) {
            DB::rollback();
            throw new Exception("Messenger Handler: " . $e->getMessage());
        }
    }

    /* =====================================================
     * BUILD DATA
     * ===================================================== */
    private static function buildCredentials(): array
    {
        $page_id    = trim($_POST['page_id'] ?? '');
        $page_token = trim($_POST['page_access_token'] ?? '');
        $page_name  = trim($_POST['page_name'] ?? '');

        if (!$page_id || !$page_token) {
            throw new Exception("Facebook Page belum dipilih.");
        }

        return [
            'page_id'      => $page_id,
            'page_name'    => $page_name,
            'access_token' => $page_token,
            'user_token'   => $_SESSION['meta_login']['user_token']
        ];
    }

    private static function buildMetaConfig(array $init): array
    {
        $meta = $init['meta'] ?? [];

        $meta['ai_engine'] = [
            'provider' => $_POST['ai_provider'] ?? '',
            'model'    => $_POST['ai_model'] ?? ''
        ];

        return $meta;
    }

    /* =====================================================
     * VALIDATION
     * ===================================================== */
    private static function validatePageAccess(array $creds): void
    {
        self::validatePageToken($creds['page_id'], $creds['access_token']);
        self::validateOwnership($creds['page_id'], $creds['user_token']);
    }

    private static function validatePageToken(string $page_id, string $token): void
    {
        $url = self::graphUrl("{$page_id}", [
            'fields'       => 'id',
            'access_token' => $token
        ]);

        $res = self::curl($url);

        if (!isset($res['id'])) {
            throw new Exception("Page Token tidak valid.");
        }
    }

    private static function validateOwnership(string $page_id, string $user_token): void
    {
        $url = self::graphUrl("me/accounts", [
            'fields'       => 'id',
            'access_token' => $user_token
        ]);

        $res = self::curl($url);

        $ids = array_column($res['data'] ?? [], 'id');

        if (!in_array($page_id, $ids)) {
            throw new Exception("Page tidak dimiliki oleh akun login.");
        }
    }

    private static function ensureNotRegistered(string $page_id): void
    {
        $exists = DB::fetch(
            "SELECT id FROM clients WHERE service = 'messenger' AND page_id = ? LIMIT 1",
            [$page_id]
        );

        if ($exists) {
            throw new Exception("Facebook Page sudah terhubung dengan bot lain.");
        }
    }

    /* =====================================================
     * DATABASE
     * ===================================================== */
    private static function insertClient(
        int $user_id,
        int $product_id,
        array $creds,
        array $init,
        array $meta
    ): int {

        DB::execute(
            "INSERT INTO clients 
            (user_id, product_id, name, service, provider, page_id, credentials, api_key, status, expired_at, meta) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [
                $user_id,
                $product_id,
                trim($_POST['name'] ?? 'Messenger Bot'),
                'messenger',
                'meta',
                $creds['page_id'],
                json_encode($creds),
                ClientService::apiKey(),
                $init['status'],
                $init['expired_at'],
                json_encode($meta)
            ]
        );

        return DB::lastId();
    }

    /* =====================================================
     * GRAPH ACTION
     * ===================================================== */
    private static function subscribePage(string $page_id, string $token): void
    {
        $url = self::graphUrl("{$page_id}/subscribed_apps");

        $res = self::curl($url, [
            'subscribed_fields' => 'messages,messaging_postbacks,feed',
            'access_token'      => $token
        ]);

        if (!($res['success'] ?? false)) {
            throw new Exception("Gagal mendaftarkan webhook ke Facebook Page.");
        }
    }

    /* =====================================================
     * UTIL
     * ===================================================== */
    private static function graphUrl(string $endpoint, array $params = []): string
    {
        return "https://graph.facebook.com/" . self::$version . "/" . $endpoint
            . ($params ? '?' . http_build_query($params) : '');
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

        if (curl_errno($ch)) {
            throw new Exception("CURL Error: " . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true) ?? [];
    }
}