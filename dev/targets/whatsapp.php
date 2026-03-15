<?php

function sendWhatsApp(array $p): array
{
    // Mengambil config dari payload global p['config']
    $cred = $p['config']['target']['credentials'] ?? [];
    $dest = $p['config']['target']['destination'] ?? [];

    if (empty($cred['provider'])) {
        throw new Exception('WhatsApp provider missing in credentials');
    }

    // Mapping data untuk provider
    $data = [
        'token'     => $cred['token']    ?? '',
        'phone_id'  => $cred['phone_id'] ?? '', // Khusus Meta
        'base_url'  => $cred['base_url'] ?? '', // Khusus Gateway
        'to'        => $dest['phone']    ?? '',
        'file'      => $p['file'],              // Binary file
        'filename'  => $p['filename'],
        'mime_type' => $p['mime_type']
    ];

    return match ($cred['provider']) {
        'meta'    => waMeta($data),
        'gateway' => waGateway($data), // Contoh: Fonnte/Wamd
        default   => throw new Exception('Unsupported WhatsApp provider: ' . $cred['provider']),
    };
}

/* ===============================
   META WHATSAPP CLOUD API
   (Membutuhkan 2 Step: Upload Media -> Send Message)
================================ */
function waMeta(array $d): array
{
    // Meta Cloud API mengharuskan upload file ke server mereka dulu untuk mendapatkan media_id
    $uploadUrl = "https://graph.facebook.com/v19.0/{$d['phone_id']}/media";
    
    $tmpFile = tempnam(sys_get_temp_dir(), 'wa_');
    file_put_contents($tmpFile, $d['file']);

    // Step 1: Upload Media
    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer {$d['token']}"],
        CURLOPT_POSTFIELDS => [
            'messaging_product' => 'whatsapp',
            'file' => new CURLFile($tmpFile, $d['mime_type'], $d['filename']),
            'type' => $d['mime_type']
        ],
        CURLOPT_RETURNTRANSFER => true
    ]);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($res['id'])) {
        unlink($tmpFile);
        throw new Exception('WA Meta Upload failed: ' . json_encode($res));
    }

    // Step 2: Kirim Pesan dengan Media ID
    $sendUrl = "https://graph.facebook.com/v19.0/{$d['phone_id']}/messages";
    $payload = [
        'messaging_product' => 'whatsapp',
        'to' => $d['to'],
        'type' => 'document',
        'document' => [
            'id' => $res['id'],
            'filename' => $d['filename'],
            'caption' => '📄 File otomatis dari Google Sheets'
        ]
    ];

    $ch = curl_init($sendUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$d['token']}",
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true
    ]);
    $finalRes = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    unlink($tmpFile);

    return ['sent' => true, 'res' => $finalRes];
}

/* ===============================
   CUSTOM WA GATEWAY (Contoh: Fonnte)
   Mengirim binary file langsung
================================ */
function waGateway(array $d): array
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'wag_');
    file_put_contents($tmpFile, $d['file']);

    $ch = curl_init($d['base_url']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Authorization: {$d['token']}"],
        CURLOPT_POSTFIELDS => [
            'target'   => $d['to'],
            'document' => new CURLFile($tmpFile, $d['mime_type'], $d['filename']),
            'filename' => $d['filename']
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    unlink($tmpFile);

    if ($err) throw new Exception('WA Gateway Error: ' . $err);

    return ['sent' => true, 'response' => json_decode($res, true)];
}
