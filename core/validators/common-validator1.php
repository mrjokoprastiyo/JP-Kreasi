<?php

function validateCommon(array &$client): void
{
    if (
        $client['status'] === 'active'
        && !empty($client['expired_at'])
        && strtotime($client['expired_at']) < time()
    ) {
        DB::exec(
            "UPDATE clients SET status='expired' WHERE id=?",
            [$client['id']]
        );

        $client['status'] = 'expired';
    }

    if ($client['status'] !== 'active') {
        respond(['error' => 'SERVICE_EXPIRED'], 403);
    }
}