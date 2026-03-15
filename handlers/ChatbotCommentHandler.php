<?php

require_once __DIR__ . '/../core/settings-loader.php';

class ChatbotCommentHandler
{
    private static string $version;

    private static function init(): void
    {
        self::$version = setting('meta-app-version', 'v18.0');

        if (!setting('meta-app-id') || !setting('meta-app-secret')) {
            throw new Exception("Konfigurasi Meta belum lengkap di System Settings.");
        }
    }

    public static function handle(int $user_id, array $product): never
    {
        self::init();
        DB::begin();

        try {
            $init  = ClientService::initialStatus($product);
            $creds = self::resolveCredentials();

            self::validatePageToken($creds['page_id'], $creds['access_token']);
            self::ensureNotRegistered($creds['page_id']);

            $meta = $init['meta'] ?? [];
            $meta['ai_engine'] = [
                'provider' => $_POST['ai_provider'] ?? '',
                'model'    => $_POST['ai_model'] ?? ''
            ];

            $api_key = ClientService::apiKey();

            DB::execute(
                "INSERT INTO clients
                (user_id, product_id, name, service, provider, page_id, credentials, api_key, status, expired_at, meta)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $user_id,
                    $product['id'],
                    trim($_POST['name'] ?? 'Comment Bot'),
                    'comment',
                    'meta',
                    $creds['page_id'],
                    json_encode($creds),
                    $api_key,
                    $init['status'],
                    $init['expired_at'],
                    json_encode($meta)
                ]
            );

            $client_id = DB::lastId();

            self::subscribeToFeed($creds['page_id'], $creds['access_token']);

            unset($_SESSION['meta_login']);

            DB::commit();

            redirect("client-detail.php?id={$client_id}&status=success");
            exit;

        } catch (Throwable $e) {
            DB::rollback();
            throw new Exception("Comment Handler: " . $e->getMessage());
        }
    }

    private static function resolveCredentials(): array
    {
        $session = $_SESSION['meta_login'] ?? null;

        if (!$session || empty($session['user_token'])) {
            throw new Exception("Sesi Meta tidak ditemukan. Silakan login ulang.");
        }

        $page_id = $_POST['page_id'] ?? '';
        if (!$page_id) {
            throw new Exception("Silakan pilih Facebook Page.");
        }

        $url = "https://graph.facebook.com/" . self::$version . "/me/accounts?" .
            http_build_query([
                'fields'       => 'id,name,access_token',
                'access_token' => $session['user_token']
            ]);

        $res = self::curl($url);

        foreach ($res['data'] ?? [] as $page) {
            if ($page['id'] === $page_id) {
                return [
                    'page_id'      => $page_id,
                    'page_name'    => $page['name'],
                    'access_token' => $page['access_token']
                ];
            }
        }

        throw new Exception("Page tidak ditemukan pada akun login.");
    }

    private static function validatePageToken(string $page_id, string $page_token): void
    {
        $url = "https://graph.facebook.com/" . self::$version . "/{$page_id}?" .
            http_build_query([
                'fields'       => 'id',
                'access_token' => $page_token
            ]);

        $res = self::curl($url);

        if (($res['id'] ?? null) !== $page_id) {
            throw new Exception("Page token tidak valid.");
        }
    }

    private static function ensureNotRegistered(string $page_id): void
    {
        $exists = DB::fetch(
            "SELECT id FROM clients WHERE service='comment' AND page_id=? LIMIT 1",
            [$page_id]
        );

        if ($exists) {
            throw new Exception("Bot sudah terdaftar untuk Page ini.");
        }
    }

    private static function subscribeToFeed(string $page_id, string $page_token): void
    {
        $url = "https://graph.facebook.com/" . self::$version . "/{$page_id}/subscribed_apps";

        $res = self::curl($url, [
            'subscribed_fields' => 'feed',
            'access_token'      => $page_token
        ]);

        if (!($res['success'] ?? false)) {
            throw new Exception("Gagal subscribe webhook feed.");
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