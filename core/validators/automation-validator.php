<?php

function validateAutomation(array $client, array $ctx): void
{
    if (!$client['automation_enabled']) {
        response(['error' => 'Automation disabled'], 403);
    }

    if ($client['automation_used'] >= $client['automation_limit']) {
        response(['error' => 'Automation limit reached'], 429);
    }

    if (!empty($client['spreadsheet_id'])) {
        $reqId = $ctx['spreadsheet_id'] ?? null;

        if ($reqId !== $client['spreadsheet_id']) {
            logSecurityAlert($client, $reqId);
            response(['error' => 'Unauthorized Spreadsheet ID'], 403);
        }
    }

    DB::exec(
        "UPDATE clients 
         SET automation_used = automation_used + 1 
         WHERE id = ?",
        [$client['id']]
    );
}

function logSecurityAlert(array $client, ?string $attempted): void
{
    DB::exec(
        "INSERT INTO security_alerts
         (client_id, api_key, attempted_ss_id, registered_ss_id, ip_address, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())",
        [
            $client['id'],
            $client['api_key'],
            $attempted,
            $client['spreadsheet_id'],
            $_SERVER['REMOTE_ADDR'] ?? null
        ]
    );
}