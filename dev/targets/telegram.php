<?php

function sendTelegram(array $p): array {
    $cred = $p['config']['target']['credentials'] ?? [];
    $dest = $p['config']['target']['destination'] ?? [];
    
    // 1. Validasi awal agar tidak error saat curl
    if (empty($cred['token']) || empty($dest['chat_id'])) {
        throw new Exception("Telegram credentials (token/chat_id) missing");
    }

    if (empty($p['file'])) {
        throw new Exception("File data is empty, nothing to send to Telegram");
    }

    $url = "https://api.telegram.org/bot{$cred['token']}/sendDocument";
    
    // 2. Gunakan temp file untuk CURLFile
    $tmpFile = tempnam(sys_get_temp_dir(), 'tele_');
    file_put_contents($tmpFile, $p['file']);

    $postData = [
        'chat_id'  => $dest['chat_id'],
        'document' => new CURLFile($tmpFile, $p['mime_type'], $p['filename']),
        'caption'  => "✅ *Otomatisasi Berhasil*\nFile: `{$p['filename']}`",
        'parse_mode' => 'Markdown' // Agar caption lebih cantik
    ];

    // 3. Eksekusi CURL
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    
    // Hapus file segera setelah kirim
    if (file_exists($tmpFile)) {
        unlink($tmpFile);
    }

    if ($err) {
        throw new Exception("Telegram Curl Error: " . $err);
    }

    $result = json_decode($res, true);
    
    if (!$result || !($result['ok'] ?? false)) {
        $msg = $result['description'] ?? 'Unknown Telegram API Error';
        throw new Exception("Telegram API Error: " . $msg);
    }

    return [
        'sent'      => true,
        'message_id' => $result['result']['message_id'] ?? null,
        'file'      => $p['filename']
    ];
}
