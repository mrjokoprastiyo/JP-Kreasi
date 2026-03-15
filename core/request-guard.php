<?php

function verifySignedRequest(array $body, string $apiKey): void
{
    $ts    = $_SERVER['HTTP_X_TS'] ?? null;
    $nonce = $_SERVER['HTTP_X_NONCE'] ?? null;
    $sig   = $_SERVER['HTTP_X_SIGNATURE'] ?? null;

    if (!$ts || !$nonce || !$sig) {
        respond(['error'=>'Missing security headers'], 403);
    }

    // === TIMESTAMP CHECK (5 menit) ===
    if (abs(time() - (int)$ts) > 300) {
        respond(['error'=>'Request expired'], 403);
    }

    // === NONCE CHECK ===
    if (isNonceUsed($nonce)) {
        respond(['error'=>'Replay detected'], 403);
    }

    markNonceUsed($nonce);

    // === SIGNATURE CHECK ===
    $base = $ts."\n".$nonce."\n".json_encode($body);
    $calc = base64_encode(hash_hmac('sha256', $base, $apiKey, true));

    if (!hash_equals($calc, $sig)) {
        respond(['error'=>'Invalid signature'], 403);
    }
}

function isNonceUsed(string $nonce): bool
{
    return (bool) DB::fetch(
        "SELECT 1 FROM request_nonce WHERE nonce=? LIMIT 1",
        [$nonce]
    );
}

function markNonceUsed(string $nonce): void
{
    DB::exec(
        "INSERT INTO request_nonce (nonce, created_at) VALUES (?, NOW())",
        [$nonce]
    );
}