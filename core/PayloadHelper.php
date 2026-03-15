<?php

class PayloadHelper {

    /**
     * DETEKSI PLATFORM & EKSTRAK DATA
     * Mengembalikan array standar: [platform, sender_id, sender_name, message, metadata]
     */
    public static function parse($input) {
        // 1. TELEGRAM
        if (isset($input['message']['from']['id'])) {
            return [
                'platform' => 'telegram',
                'sender_id' => (string)$input['message']['from']['id'],
                'sender_name' => $input['message']['from']['first_name'] ?? 'User Telegram',
                'message' => $input['message']['text'] ?? '',
                'type' => 'message'
            ];
        }

        // 2. WHATSAPP (Meta Cloud API)
        if (isset($input['entry'][0]['changes'][0]['value']['messages'][0])) {
            $val = $input['entry'][0]['changes'][0]['value'];
            return [
                'platform' => 'whatsapp',
                'sender_id' => $val['messages'][0]['from'],
                'sender_name' => $val['contacts'][0]['profile']['name'] ?? 'User WA',
                'message' => $val['messages'][0]['text']['body'] ?? '',
                'type' => 'message'
            ];
        }

        // 3. FACEBOOK MESSENGER
        if (isset($input['entry'][0]['messaging'][0])) {
            $msg = $input['entry'][0]['messaging'][0];
            return [
                'platform' => 'facebook',
                'sender_id' => $msg['sender']['id'],
                'sender_name' => null, // Messenger butuh hit API tambahan untuk nama
                'message' => $msg['message']['text'] ?? '',
                'type' => 'messenger'
            ];
        }

        // 4. FACEBOOK COMMENT
        if (isset($input['entry'][0]['changes'][0]['value']['comment_id'])) {
            $val = $input['entry'][0]['changes'][0]['value'];
            return [
                'platform' => 'facebook',
                'sender_id' => $val['from']['id'],
                'sender_name' => $val['from']['name'] ?? null,
                'message' => $val['message'] ?? '',
                'type' => 'comment',
                'post_id' => $val['post_id'] ?? null,
                'comment_id' => $val['comment_id'] ?? null
            ];
        }

        return null;
    }

    /**
     * TOKEN HANDLER (SaaS Version)
     * Mengambil token yang benar dari database client berdasarkan konteks
     */
    public static function getAccessToken($client_credentials, $is_page_context = true) {
        $creds = json_decode($client_credentials, true);
        
        // Jika FB, pilih antara Page Token atau User Token
        if (isset($creds['page_token'])) {
            return $is_page_context ? $creds['page_token'] : $creds['user_token'];
        }
        
        // Jika platform lain, ambil token utamanya
        return $creds['access_token'] ?? $creds['bot_token'] ?? null;
    }

    /**
     * IS FOLLOWER / SUBSCRIBED
     * Versi Universal untuk mengecek status langganan
     */
    public static function checkSubscription($sender_id, $access_token, $platform = 'facebook') {
        if ($platform !== 'facebook') return true; // Sementara bypass untuk platform lain

        $url = "https://graph.facebook.com/v22.0/{$sender_id}?fields=is_subscribed&access_token={$access_token}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $res = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return $res['is_subscribed'] ?? false;
    }
}
