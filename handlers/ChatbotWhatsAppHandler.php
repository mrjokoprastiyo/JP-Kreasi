<?php

require_once __DIR__ . '/../core/settings-loader.php';

class ChatbotWhatsAppHandler
{
    private static string $app_id;
    private static string $app_secret;
    private static string $version;

    private static function init(): void
    {
        self::$app_id     = setting('meta-app-id');
        self::$app_secret = setting('meta-app-secret');
        self::$version    = setting('meta-app-version', 'v18.0');

        if (!self::$app_id || !self::$app_secret) {
            throw new Exception("Konfigurasi Meta belum lengkap.");
        }
    }

    public static function handle(int $user_id, array $product): never
    {
        self::init();
        DB::begin();

        try {

            $init     = ClientService::initialStatus($product);
            $provider = $_POST['provider'] ?? 'meta';

            $credentials = ($provider === 'meta')
                ? self::buildMetaCredentials()
                : self::buildThirdPartyCredentials();

            if ($provider === 'meta') {
                self::validatePhone($credentials['phone_number_id'], $credentials['access_token']);
                self::ensureNotRegistered($credentials['phone_number_id']);
            }

            $meta = [
                'ai_engine' => [
                    'provider' => $_POST['ai_provider'] ?? '',
                    'model'    => $_POST['ai_model'] ?? ''
                ]
            ];

            DB::execute(
                "INSERT INTO clients
                (user_id, product_id, name, service, provider, phone_number_id, credentials, api_key, status, expired_at, meta)
                VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                [
                    $user_id,
                    $product['id'],
                    trim($_POST['name'] ?? 'WhatsApp Bot'),
                    'whatsapp',
                    $provider,
                    $credentials['phone_number_id'] ?? null,
                    json_encode($credentials),
                    ClientService::apiKey(),
                    $init['status'],
                    $init['expired_at'],
                    json_encode($meta)
                ]
            );

            $id = DB::lastId();

            if ($provider === 'meta') {
                self::subscribeWaba($credentials['waba_id'], $credentials['access_token']);
            }

            DB::commit();
            redirect("client-detail.php?id={$id}&status=success");
            exit;

        } catch (Throwable $e) {
            DB::rollback();
            throw new Exception("WhatsApp Handler: " . $e->getMessage());
        }
    }

    private static function buildMetaCredentials(): array
    {
        $token    = $_POST['access_token'] ?? '';
        $phone_id = $_POST['phone_number_id'] ?? '';
        $waba_id  = $_POST['waba_id'] ?? '';

        if (!$token || !$phone_id || !$waba_id) {
            throw new Exception("Data WhatsApp Cloud API tidak lengkap.");
        }

        $longToken = self::exchangeToken($token);

        return [
            'phone_number_id' => $phone_id,
            'waba_id'         => $waba_id,
            'access_token'    => $longToken
        ];
    }

    private static function buildThirdPartyCredentials(): array
    {
        $apiKey   = $_POST['access_token'] ?? '';
        $deviceId = $_POST['phone_number_id'] ?? '';

        if (!$apiKey) {
            throw new Exception("API Key provider tidak boleh kosong.");
        }

        return [
            'api_key'   => $apiKey,
            'device_id' => $deviceId
        ];
    }

    private static function validatePhone(string $phone_id, string $token): void
    {
        $url = self::graphUrl($phone_id, [
            'fields'       => 'id',
            'access_token' => $token
        ]);

        $res = self::curl($url);

        if (($res['id'] ?? null) !== $phone_id) {
            throw new Exception("Phone Number ID tidak valid.");
        }
    }

    private static function ensureNotRegistered(string $phone_id): void
    {
        $exists = DB::fetch(
            "SELECT id FROM clients WHERE service='whatsapp' AND phone_number_id=? LIMIT 1",
            [$phone_id]
        );

        if ($exists) {
            throw new Exception("WhatsApp number sudah terdaftar.");
        }
    }

    private static function exchangeToken(string $token): string
    {
        $url = self::graphUrl("oauth/access_token", [
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => self::$app_id,
            'client_secret'     => self::$app_secret,
            'fb_exchange_token' => $token
        ]);

        $res = self::curl($url);

        return $res['access_token'] ?? $token;
    }

    private static function subscribeWaba(string $waba_id, string $token): void
    {
        $url = self::graphUrl("{$waba_id}/subscribed_apps");

        $res = self::curl($url, ['access_token' => $token]);

        if (!($res['success'] ?? false)) {
            throw new Exception("Gagal subscribe WABA.");
        }
    }

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
            CURLOPT_SSL_VERIFYPEER => true
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