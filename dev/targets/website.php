<?php

/**
 * Handler pengiriman file ke Custom Webhook / Website Endpoint
 */
function sendWebsite(array $p): array
{
    $cred = $p['config']['target']['credentials'] ?? [];
    $dest = $p['config']['target']['destination'] ?? [];

    if (empty($dest['endpoint'])) {
        throw new Exception('Website target endpoint is missing');
    }

    // 1. Siapkan file sementara dari binary data
    $tmpFile = tempnam(sys_get_temp_dir(), 'web_');
    file_put_contents($tmpFile, $p['file']);

    // 2. Siapkan Payload (Kombinasi File dan Metadata)
    $postData = [
        'file'      => new CURLFile($tmpFile, $p['mime_type'], $p['filename']),
        'filename'  => $p['filename'],
        'format'    => $p['format'],
        'source'    => 'automation_engine'
    ];

    // 3. Siapkan Header (Support Auth jika user punya Token sendiri)
    $headers = [];
    if (!empty($cred['api_key'])) {
        $headers[] = "X-API-KEY: {$cred['api_key']}";
    }
    if (!empty($cred['token'])) {
        $headers[] = "Authorization: Bearer {$cred['token']}";
    }

    // 4. Eksekusi Kirim ke Endpoint User
    $ch = curl_init($dest['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postData, // Mengirim sebagai multipart/form-data
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Hapus file sementara
    if (file_exists($tmpFile)) {
        unlink($tmpFile);
    }

    if ($err) {
        throw new Exception("Website CURL Error: " . $err);
    }

    // Website tujuan dianggap sukses jika return 2xx
    if ($httpCode >= 400) {
        throw new Exception("Website API returned Error Code $httpCode: " . $res);
    }

    return [
        'sent'      => true,
        'http_code' => $httpCode,
        'response'  => json_decode($res, true) ?? $res
    ];
}
