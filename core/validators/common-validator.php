<?php

function validateCommon(array &$client): void
{
    /* ===============================
       AUTO UPDATE EXPIRED STATUS
    ================================ */

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

    /* ===============================
       READ META
    ================================ */

    $meta = [];

    if (!empty($client['meta'])) {
        $meta = json_decode($client['meta'], true) ?: [];
    }

    /* ===============================
       TRIAL CHECK
    ================================ */

    $trialActive = false;

    if (!empty($meta['trial']['ended_at'])) {

        try {

            $now = new DateTime();
            $trialEnd = new DateTime($meta['trial']['ended_at']);

            if ($now <= $trialEnd) {
                $trialActive = true;
            }

        } catch (Throwable $e) {
            $trialActive = false;
        }
    }

    /* ===============================
       SERVICE RULE
    ================================ */

    // if ($client['status'] !== 'active' && !$trialActive) {

        // response([
            // 'error' => 'SERVICE_EXPIRED'
        // ], 403);

    // }

}