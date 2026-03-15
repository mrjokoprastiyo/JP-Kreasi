<?php

function sendWebsite(
    array $cred,
    string $fileData,
    string $fileName
) {
    $tmp = tempnam(sys_get_temp_dir(), 'web');
    file_put_contents($tmp, $fileData);

    $payload = [
        'file' => new CURLFile($tmp, mime_content_type($tmp), $fileName)
    ];

    $ch = curl_init($cred['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$cred['api_token']}"
        ]
    ]);

    curl_exec($ch);
    curl_close($ch);
    unlink($tmp);
}