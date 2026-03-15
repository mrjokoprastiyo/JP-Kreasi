<?php

function validateDomain(array $client): void
{
    $requestDomain = getRequestDomain();

    if (!$requestDomain) {
        response([
            'error'  => 'Domain validation failed',
            'reason' => 'Origin not detected'
        ], 403);
    }

    $allowedDomains = parseAllowedDomains($client['domain'] ?? '');

    if (empty($allowedDomains)) {
        return; // no restriction
    }

    $requestDomain = strtolower($requestDomain);

    foreach ($allowedDomains as $domain) {
        if (
            $requestDomain === $domain ||
            str_ends_with($requestDomain, '.' . $domain)
        ) {
            return; // valid
        }
    }

    response([
        'reply' => 'API key not allowed for this domain'
    ], 403);
}

function parseAllowedDomains(string $raw): array
{
    if ($raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        $domains = $decoded;
    } else {
        $domains = preg_split(
            '/[\r\n,]+/',
            $raw,
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    }

    return array_map(
        fn($d) => strtolower(trim($d)),
        $domains
    );
}

function getRequestDomain(): ?string
{
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        return parse_url($_SERVER['HTTP_ORIGIN'], PHP_URL_HOST);
    }

    if (!empty($_SERVER['HTTP_REFERER'])) {
        return parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    }

    return null;
}