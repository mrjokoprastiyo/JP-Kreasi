<?php

require_once 'helpers/client-flow.php';
require_once 'validators/common-validator.php';
require_once 'validators/domain-validator.php';
require_once 'validators/automation-validator.php';
require_once 'validators/time-validator.php';

function validateClientByApiKey(string $apiKey, array $context = []): array
{
    if ($apiKey === '') {
        response(['error' => 'Missing API key'], 401);
    }

    $client = DB::fetch(
        "SELECT * FROM clients WHERE api_key = ? LIMIT 1",
        [$apiKey]
    );

    if (!$client) {
        response(['error' => 'Invalid API key'], 403);
    }

    validateCommon($client);

    $flow = resolveClientFlow($client);

    match ($flow) {
        CLIENT_FLOW_CHATBOT_WEB     => validateDomain($client),
        CLIENT_FLOW_AUTOMATION      => validateAutomation($client, $context),
        CLIENT_FLOW_CHATBOT_CHANNEL => validateChannel($client, $context),
        default                     => respond(['error' => 'Unknown service type'], 400)
    };

    return $client;
}