<?php

/**
 * Handler Telegram - Client/System Mode
 * Menerima satu parameter array $p berisi: sender, file, filename, mime_type, destination
 */
function sendTelegram(array $p) 
{
    $sender = $p['sender'] ?? [];
    $dest   = $p['destination'] ?? [];
    
    $token  = $sender['bot_token'] ?? null;
    $chatId = $dest['chat_id'] ?? null;

    if (!$token) throw new Exception('Telegram bot_token missing in system provider');
    if (!$chatId) throw new Exception('Telegram chat_id missing in destination');

    /**
     * LOGIKA PENGIRIMAN
     * Jika format CSV atau HTML dan ukurannya kecil, bisa dikirim sebagai teks.
     * Namun untuk reliabilitas sistem, sangat disarankan tetap mengirim sebagai DOCUMENT.
     */
    
    // Simpan binary ke file sementara agar bisa di-upload oleh CURLFile
    $tmp = tempnam(sys_get_temp_dir(), 'tg_');
    file_put_contents($tmp, $p['file']);

    try {
        $url = "https://api.telegram.org/bot{$token}/sendDocument";
        
        $payload = [
            'chat_id'  => $chatId,
            'document' => new CURLFile($tmp, $p['mime_type'], $p['filename']),
            'caption'  => "Automation Export: " . $p['filename']
        ];

        $response = tgCurl($url, $payload);
        
        $json = json_decode($response, true);
        if (!$json || $json['ok'] !== true) {
            throw new Exception("Telegram API Error: " . ($json['description'] ?? 'Unknown error'));
        }

        return $json;

    } finally {
        // Selalu hapus file temporary setelah selesai/error
        if (file_exists($tmp)) {
            unlink($tmp);
        }
    }
}

/**
 * HELPER CURL KHUSUS TELEGRAM
 */
function tgCurl($url, $data) 
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $data, // Mengirim sebagai multipart/form-data
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30
    ]);
    
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) throw new Exception("Telegram Connection Error: " . $err);
    
    return $res;
}
