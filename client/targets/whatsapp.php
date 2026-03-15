<?php

/**
 * Handler WhatsApp - Client/System Mode
 * Menerima $p (payload) yang berisi sender, file binary, filename, dll.
 */
function sendWhatsApp(array $p) {
    $sender = $p['sender'] ?? [];
    $dest   = $p['destination'] ?? [];
    
    $phone = $dest['phone'] ?? '';
    if (!$phone) throw new Exception('WhatsApp destination phone missing');

    $type = $sender['provider_type'] ?? '';
    if (!$type) throw new Exception('WhatsApp provider_type missing in system credentials');

    // Menyiapkan file sementara karena provider butuh path file fisik untuk diupload
    $tmpFile = tempnam(sys_get_temp_dir(), 'was_');
    file_put_contents($tmpFile, $p['file']);

    try {
        $result = match ($type) {
            'meta'    => waMeta($sender['meta'], $phone, $tmpFile, $p['filename'], $p['mime_type']),
            'fonnte'  => waFonnte($sender['gateway'], $phone, $tmpFile, $p['filename']),
            'wablas'  => waWablas($sender['gateway'], $phone, $tmpFile, $p['filename']),
            default   => throw new Exception("Unknown WhatsApp provider: $type"),
        };
    } finally {
        // Pastikan file sementara dihapus setelah eksekusi selesai/error
        if (file_exists($tmpFile)) unlink($tmpFile);
    }

    return $result;
}

/* ===============================
   META CLOUD API (UPLOAD MODE)
================================ */
function waMeta($c, $phone, $tmpFile, $fileName, $mimeType) {
    // Step 1: Upload Media ke Meta dulu
    $uploadUrl = "https://graph.facebook.com/{$c['api_version']}/{$c['phone_number_id']}/media";
    $uploadRes = curlExec($uploadUrl, [
        'messaging_product' => 'whatsapp',
        'file' => new CURLFile($tmpFile, $mimeType, $fileName),
        'type' => $mimeType
    ], ["Authorization: Bearer {$c['access_token']}"]);

    $media = json_decode($uploadRes, true);
    if (empty($media['id'])) throw new Exception("Meta Upload Failed: " . $uploadRes);

    // Step 2: Kirim Pesan menggunakan Media ID
    $sendUrl = "https://graph.facebook.com/{$c['api_version']}/{$c['phone_number_id']}/messages";
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $phone,
        'type' => 'document',
        'document' => [
            'id' => $media['id'],
            'filename' => $fileName
        ]
    ];

    return curlExec($sendUrl, json_encode($payload), [
        "Authorization: Bearer {$c['access_token']}",
        "Content-Type: application/json"
    ]);
}

/* ===============================
   FONNTE (MULTIPART MODE)
================================ */
function waFonnte($c, $phone, $tmpFile, $fileName) {
    $payload = [
        'target'   => $phone,
        'file'     => new CURLFile($tmpFile, '', $fileName),
        'filename' => $fileName
    ];

    return curlExec('https://api.fonnte.com/send', $payload, [
        "Authorization: {$c['api_key']}"
    ]);
}

/* ===============================
   WABLAS (MULTIPART MODE)
================================ */
function waWablas($c, $phone, $tmpFile, $fileName) {
    $url = rtrim($c['base_url'], '/') . '/api/send-document';
    $payload = [
        'phone'    => $phone,
        'document' => new CURLFile($tmpFile, '', $fileName),
    ];

    return curlExec($url, $payload, [
        "Authorization: {$c['api_key']}"
    ]);
}

/* ===============================
   CURL CORE HELPER
================================ */
function curlExec($url, $body, array $headers) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS     => $body, // Otomatis handle Multipart jika body adalah array
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30
    ]);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) throw new Exception("CURL Error: " . $err);
    return $res;
}
