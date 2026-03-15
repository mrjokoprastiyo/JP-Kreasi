<?php

/**
 * Handler Messenger - Client/System Mode
 * Menerima satu parameter array $p berisi: sender, file, filename, mime_type, destination
 */
function sendMessenger(array $p) 
{
    $sender = $p['sender'] ?? [];
    $dest   = $p['destination'] ?? [];
    
    $token = $sender['access_token'] ?? null;
    $psid  = $dest['psid'] ?? null;

    if (!$token) throw new Exception('Messenger access_token missing in system provider');
    if (!$psid) throw new Exception('Messenger PSID missing in destination');

    // Simpan data binary ke file sementara untuk proses upload
    $tmp = tempnam(sys_get_temp_dir(), 'fb_');
    file_put_contents($tmp, $p['file']);

    try {
        $url = "https://graph.facebook.com/v19.0/me/messages?access_token={$token}";

        /**
         * Pengiriman File ke Messenger via API memerlukan:
         * 1. recipient: ID pengguna (PSID) dalam bentuk JSON string
         * 2. message: Informasi attachment dalam bentuk JSON string
         * 3. filedata: Objek CURLFile yang berisi file fisik
         */
        $payload = [
            'recipient' => json_encode(['id' => $psid]),
            'message'   => json_encode([
                'attachment' => [
                    'type' => 'file',
                    'payload' => ['is_reusable' => false]
                ]
            ]),
            'filedata' => new CURLFile($tmp, $p['mime_type'], $p['filename'])
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $payload, // Otomatis mengirim sebagai multipart/form-data
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT        => 30
        ]);

        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err) throw new Exception("Messenger Connection Error: " . $err);

        $json = json_decode($res, true);
        if (isset($json['error'])) {
            throw new Exception("Messenger API Error: " . ($json['error']['message'] ?? 'Unknown error'));
        }

        return $json;

    } finally {
        // Hapus file temporary agar tidak membebani server
        if (file_exists($tmp)) {
            unlink($tmp);
        }
    }
}
