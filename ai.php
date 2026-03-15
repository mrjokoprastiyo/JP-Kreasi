<?php
if (!function_exists('decrypt_api_token')) {
    function decrypt_api_token(string $token): string
    {
        return $token; // sementara (NO ENCRYPT)
    }
}

function ai_reply(
    int $client_id,
    ?string $prompt,
    ?string $model,
    string $message,
    array $history,
    ?string $provider = null
) {
    $config = require __DIR__ . '/provider.php';

    // ===============================
    // LOAD PROVIDER FROM DB (CLIENT BASED)
    // ===============================
    $row = DB::fetch("
        SELECT 
            a.provider_slug,
            a.model,
            a.api_token
        FROM clients c
        JOIN ai_configs a ON a.id = c.ai_config_id
        WHERE c.id = ?
          AND a.status = 'active'
        LIMIT 1
    ", [$client_id]);

    if (!$row) {
        return 'AI provider belum dikonfigurasi untuk client ini.';
    }

    // ===============================
    // RESOLVE CONFIG
    // ===============================
    $provider = $provider ?: $row['provider_slug'];
    $model    = $model    ?: $row['model'];
    $prompt   = $prompt   ?: $config['default_prompt'];

    // ===============================
    // SET API KEY (DB > ENV)
    // ===============================
    $keyName = $provider . '_key';

    if (!empty($row['api_token'])) {
        $config[$keyName] = decrypt_api_token($row['api_token']);
    }

    if (empty($config[$keyName])) {
        return 'API key belum diset untuk provider ' . $provider;
    }

    // ===============================
    // DISPATCH PROVIDER
    // ===============================
    return match ($provider) {
        'openai' => ai_openai($config, $prompt, $model, $message, $history),
        'groq'   => ai_groq($config, $prompt, $model, $message, $history),
        'gemini' => ai_gemini($config, $prompt, $model, $message, $history),
        'cohere' => ai_cohere($config, $prompt, $model, $message, $history),
        default  => 'Provider AI tidak dikenali.',
    };
}

function ai_openai($config, $prompt, $model, $message, $history)
{
    $messages = [];

    if ($prompt) {
        $messages[] = ['role' => 'system', 'content' => $prompt];
    }

    foreach ($history as $h) {
        $messages[] = [
            'role' => $h['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => $h['message']
        ];
    }

    $messages[] = ['role' => 'user', 'content' => $message];

    return curl_json(
        $config['openai_endpoint'],
        [
            'model'    => $model,
            'messages' => $messages
        ],
        [
            "Authorization: Bearer {$config['openai_key']}"
        ],
        fn($r) => $r['choices'][0]['message']['content'] ?? null,
        'OpenAI'
    );
}

function ai_groq($config, $prompt, $model, $message, $history)
{
    $config['openai_endpoint'] = $config['groq_endpoint'];
    $config['openai_key']      = $config['groq_key'];

    return ai_openai($config, $prompt, $model, $message, $history);
}

function ai_gemini($config, $prompt, $model, $message, $history)
{
    $contents = [];

    if ($prompt) {
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $prompt]]
        ];
    }

    foreach ($history as $h) {
        $contents[] = [
            'role' => $h['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $h['message']]]
        ];
    }

    $contents[] = [
        'role' => 'user',
        'parts' => [['text' => $message]]
    ];

    $endpoint = str_replace(
        '{model}',
        $model,
        $config['gemini_endpoint']
    ) . '?key=' . $config['gemini_key'];

    return curl_json(
        $endpoint,
        ['contents' => $contents],
        [],
        fn($r) => $r['candidates'][0]['content']['parts'][0]['text'] ?? null,
        'Gemini'
    );
}

function ai_cohere($config, $prompt, $model, $message, $history)
{
    $chat_history = [];
    foreach ($history as $h) {
        $chat_history[] = [
            'role' => $h['role'] === 'assistant' ? 'CHATBOT' : 'USER',
            'message' => $h['message']
        ];
    }

    return curl_json(
        $config['cohere_endpoint'],
        [
            'model'        => $model,
            'message'      => $message,
            'chat_history' => $chat_history,
            'preamble'     => $prompt
        ],
        [
            "Authorization: Bearer {$config['cohere_key']}"
            // Hapus Content-Type & Accept di sini karena sudah ada di baseHeaders curl_json
        ],
        fn($r) => $r['text'] ?? $r['message'] ?? null,
        'Cohere'
    );
}

function curl_json(
    string $url,
    array|string|null $payload = null,
    array $headers = [],
    callable $extractor = null,
    string $label = 'API',
    string $method = 'POST'
) {
    $ch = curl_init($url);

    $baseHeaders = [
        'Accept: application/json',
    ];

    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => array_merge($baseHeaders, $headers),
    ];

    if ($payload !== null) {
        if (is_array($payload)) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $options[CURLOPT_POSTFIELDS] = $json;
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
            $options[CURLOPT_HTTPHEADER][] = 'Content-Length: ' . strlen($json);
        } else {
            // raw payload (future use)
            $options[CURLOPT_POSTFIELDS] = $payload;
        }
    }

    curl_setopt_array($ch, $options);

    $response = curl_exec($ch);
    $errno    = curl_errno($ch);
    $error    = curl_error($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    debug_log([
        "{$label}_REQUEST" => [
            'url'     => $url,
            'method'  => $method,
            'payload' => $payload
        ],
        "{$label}_RESPONSE" => [
            'status' => $status,
            'error'  => $error,
            'raw'    => $response
        ]
    ]);

    if ($errno) {
        return "$label CURL error: $error";
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        return "$label response bukan JSON valid.";
    }

    return $extractor
        ? ($extractor($data) ?? "$label response kosong.")
        : $data;
}
