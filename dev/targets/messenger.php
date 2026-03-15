<?php

/**
 * Handler pengiriman file ke Facebook Messenger
 * Menggunakan Graph API v19.0+
 */
function sendMessenger(array $p): array
{
    // Ambil kredensial dari struktur payload yang baru (p['config'])
    $cred = $p['config']['target']['credentials'] ?? [];
    $dest = $p['config']['target']['destination'] ?? [];

    if (empty($cred['page_token'])) {
        throw new Exception('Messenger page_token missing');
    }

    if (empty($dest['psid'])) {
        throw new Exception('Messenger psid missing');
    }

    if (empty($p['file'])) {
        throw new Exception('Messenger binary file data is empty');
    }

    // Endpoint pengiriman pesan Meta Graph API
    $url = "https://graph.facebook.com/v19.0/me/messages?access_token=" . $cred['page_token'];

    // Simpan file sementara ke lokal server agar bisa diupload via CURLFile
    $tmpFile = tempnam(sys_get_temp_dir(), 'msg_');
    file_put_contents($tmpFile, $p['file']);

    /**
     * Catatan: Messenger membutuhkan 'message' dalam format JSON string
     * jika dicampur dengan pengiriman file fisik (multipart)
     */
    $messagePayload = [
        'recipient' => json_encode([
            'id' => $dest['psid']
        ]),
        'message' => json_encode([
            'text' => '📎 File otomatis dari Google Sheets'
        ]),
        'filedata' => new CURLFile(
            $tmpFile,
            $p['mime_type'],
            $p['filename']
        )
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $messagePayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    // Hapus file sementara setelah proses selesai
    if (file_exists($tmpFile)) {
        unlink($tmpFile);
    }

    if ($err) {
        throw new Exception('Messenger curl error: ' . $err);
    }

    $json = json_decode($res, true);

    if (isset($json['error'])) {
        $msg = $json['error']['message'] ?? 'Unknown Messenger API error';
        throw new Exception('Messenger API error: ' . $msg);
    }

    return [
        'sent'        => true,
        'psid'        => $dest['psid'],
        'message_id'  => $json['message_id'] ?? null,
        'file'        => $p['filename']
    ];
}
