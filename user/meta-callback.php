<?php
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/settings-loader.php';
require_once '../core/base.php';

Auth::check();

/* =========================================================
 * VALIDASI SESSION OAUTH
 * =======================================================*/
$stored = $_SESSION['meta_oauth'] ?? null;

if (!$stored) {
    die('Sesi OAuth tidak ditemukan.');
}

if (isset($_GET['error'])) {
    unset($_SESSION['meta_oauth']);
    header("Location: order.php?product_id={$stored['product_id']}&status=cancelled");
    exit;
}

if (!isset($_GET['state']) || $_GET['state'] !== $stored['state']) {
    die('Validasi state gagal.');
}

if (!isset($_GET['code'])) {
    die('Authorization code tidak ditemukan.');
}

/* =========================================================
 * CONFIG
 * =======================================================*/
$app_id     = setting('meta-app-id');
$app_secret = setting('meta-app-secret');
$version    = setting('meta-app-version', 'v18.0');

/* =========================================================
 * CURL HELPER
 * =======================================================*/
function curlJson(string $url, array $post = null): array
{
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30
    ]);

    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $res = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }

    curl_close($ch);

    $json = json_decode($res, true);

    if (isset($json['error'])) {
        throw new Exception($json['error']['message'] ?? 'Graph API Error');
    }

    return $json ?? [];
}

/* =========================================================
 * OAUTH PROCESS
 * =======================================================*/
try {

    // STEP 1: Short token
    $short = curlJson(
        "https://graph.facebook.com/{$version}/oauth/access_token?" .
        http_build_query([
            'client_id'     => $app_id,
            'client_secret' => $app_secret,
            'redirect_uri'  => BASE_URL . "/user/meta-callback.php",
            'code'          => $_GET['code']
        ])
    );

    // STEP 2: Exchange ke Long-lived
    $long = curlJson(
        "https://graph.facebook.com/{$version}/oauth/access_token?" .
        http_build_query([
            'grant_type'        => 'fb_exchange_token',
            'client_id'         => $app_id,
            'client_secret'     => $app_secret,
            'fb_exchange_token' => $short['access_token']
        ])
    );

    $user_token = $long['access_token'];
    $expires_at = time() + ($long['expires_in'] ?? 0);
    $channel    = $stored['channel'];

    /* =========================================================
     * MODE 1: REFRESH TOKEN (IDENTITY LOCK)
     * =======================================================*/
    if (isset($_SESSION['refresh_client_id'])) {

        $client_id = (int) $_SESSION['refresh_client_id'];
        unset($_SESSION['refresh_client_id']);

        $client = DB::fetch(
            "SELECT id, credensial FROM clients WHERE id=? LIMIT 1",
            [$client_id]
        );

        if (!$client) {
            throw new Exception("Client tidak ditemukan.");
        }

        $cred = json_decode($client['credensial'], true);

        if (!$cred) {
            throw new Exception("Credential rusak.");
        }

        /* ================================
         * AMBIL DATA DARI GRAPH
         * ==============================*/

        // Messenger / Comment → ambil page
        if ($channel === 'messenger' || $channel === 'comment') {

            $pages = curlJson(
                "https://graph.facebook.com/{$version}/me/accounts?" .
                http_build_query([
                    'fields'       => 'id,name,access_token',
                    'access_token' => $user_token
                ])
            );

            $matched = null;

            foreach ($pages['data'] ?? [] as $p) {
                if ($p['id'] === $cred['page_id']) {
                    $matched = $p;
                    break;
                }
            }

            if (!$matched) {
                throw new Exception("Page tidak ditemukan di akun login.");
            }

            // 🔒 Identity Lock
            if ($cred['page_id'] !== $matched['id']) {
                throw new Exception("Tidak boleh mengganti Page.");
            }

            $cred['page_access_token'] = $matched['access_token'];
        }

        // WhatsApp Cloud API
        if ($channel === 'whatsapp') {

            $waba = curlJson(
                "https://graph.facebook.com/{$version}/me/whatsapp_business_accounts?" .
                http_build_query([
                    'fields'       => 'id',
                    'access_token' => $user_token
                ])
            );

            $found = false;

            foreach ($waba['data'] ?? [] as $acc) {

                $phones = curlJson(
                    "https://graph.facebook.com/{$version}/{$acc['id']}/phone_numbers?" .
                    http_build_query([
                        'fields'       => 'id',
                        'access_token' => $user_token
                    ])
                );

                foreach ($phones['data'] ?? [] as $phone) {
                    if ($phone['id'] === $cred['phone_number_id']) {
                        $found = true;
                        break 2;
                    }
                }
            }

            if (!$found) {
                throw new Exception("Nomor WhatsApp tidak ditemukan.");
            }

            // 🔒 Identity Lock
            $cred['access_token'] = $user_token;
        }

        DB::execute(
            "UPDATE clients SET credensial=? WHERE id=?",
            [json_encode($cred, JSON_UNESCAPED_SLASHES), $client_id]
        );

        header("Location: client-edit.php?id=" . $client_id . "&status=token_refreshed");
        exit;
    }

    /* =========================================================
     * MODE 2: LOGIN NORMAL (CREATE FLOW)
     * =======================================================*/
    $_SESSION['meta_login'] = [
        'user_token' => $user_token,
        'expires_at' => $expires_at,
        'product_id' => $stored['product_id'],
        'channel'    => $channel
    ];

    unset($_SESSION['meta_oauth']);

    header("Location: order.php?product_id={$stored['product_id']}");
    exit;

} catch (Exception $e) {

    unset($_SESSION['meta_oauth']);
    unset($_SESSION['refresh_client_id']);

    die("OAuth Error: " . $e->getMessage());
}