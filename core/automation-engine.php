<?php
class AutomationEngine {
    
    // 1. TRANSFORM: Mengolah data mentah menjadi pesan/format jadi
    public static function transform($mode, $config, $rawData) {
        if ($mode === 'template') {
            $template = $config['template'];
            // Mengganti {{key}} dengan nilai dari data mentah
            foreach ($rawData as $key => $val) {
                $template = str_replace("{{{$key}}}", $val, $template);
            }
            return $template;
        }
        
        if ($mode === 'mapping') {
            $mapping = json_decode($config['mapping'], true);
            $result = [];
            foreach ($mapping as $targetKey => $sourceKey) {
                $result[$targetKey] = $rawData[$sourceKey] ?? null;
            }
            return $result;
        }

        return $rawData; // Default: tanpa perubahan
    }

    public static function executeTarget($type, $config, $data) {
        // Pastikan data pesan berbentuk string jika targetnya adalah chat/email
        $message = is_array($data) ? json_encode($data, JSON_PRETTY_PRINT) : $data;

        switch ($type) {
            case 'chat':
                $channel = $config['channel'] ?? 'whatsapp';
                if ($channel === 'whatsapp') {
                    return self::sendWhatsApp($config['target_phone'] ?? '', $message);
                } 
                if ($channel === 'telegram') {
                    return self::sendTelegram($config['chat_id'] ?? '', $message);
                }
                if ($channel === 'messenger') {
                    return self::sendMessenger($config['page_scoped_id'] ?? '', $message);
                }
                break;

            case 'website':
                // Target website biasanya berupa Webhook (mengirim data JSON ke URL lain)
                return self::sendToWebhook($config['target_url'] ?? '', $data);

            case 'email':
                return self::sendEmail($config['to'] ?? '', $message);
        }
        
        return "Target type not recognized";
    }

    /* ---------------- CHANNEL: WHATSAPP ---------------- */
    private static function sendWhatsApp($target, $msg) {
        $provider = setting('whatsapp_provider', 'fonnte');
        $token    = setting('whatsapp_token');
        
        // Logika driver (Fonnte/Wablas/Twilio) yang sudah kita buat sebelumnya
        // ... call driver method ...
        return "WA Sent via $provider";
    }

    /* ---------------- CHANNEL: TELEGRAM ---------------- */
    private static function sendTelegram($chatId, $msg) {
        $botToken = setting('telegram_bot_token');
        if (!$botToken || !$chatId) return "Telegram config missing";

        $url = "https://api.telegram.org/bot$botToken/sendMessage";
        $payload = [
            'chat_id' => $chatId,
            'text' => $msg,
            'parse_mode' => 'HTML'
        ];

        return self::curlRequest($url, $payload);
    }

    /* ---------------- CHANNEL: MESSENGER ---------------- */
    private static function sendMessenger($psid, $msg) {
        $accessToken = setting('messenger_access_token');
        if (!$accessToken || !$psid) return "Messenger config missing";

        $url = "https://graph.facebook.com/v12.0/me/messages?access_token=$accessToken";
        $payload = [
            'recipient' => ['id' => $psid],
            'message' => ['text' => $msg]
        ];

        return self::curlRequest($url, $payload, ['Content-Type: application/json']);
    }

    /* ---------------- TARGET: WEBSITE (WEBHOOK) ---------------- */
    private static function sendToWebhook($url, $data) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) return "Invalid Webhook URL";
        
        // Website target biasanya mengharapkan data JSON asli (bukan string template)
        return self::curlRequest($url, $data, ['Content-Type: application/json']);
    }

    /* ---------------- HELPER: CURL ---------------- */
    private static function curlRequest($url, $data, $headers = []) {
        $curl = curl_init($url);
        $payload = is_array($data) ? json_encode($data) : $data;

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($curl);
        curl_close($curl);
        return $response;
    }
}
