<?php

/**
 * Hitung status trial & layanan client
 *
 * @param array $client  Row dari tabel clients
 * @return array
 */
function resolveClientServiceStatus(array $client): array
{
    $meta = [];

    if (!empty($client['meta'])) {
        $meta = json_decode($client['meta'], true) ?: [];
    }

    /* ===============================
       STATUS TRIAL
    ================================ */

    $trialActive = false;
    $trialEnded  = null;

    if (!empty($meta['trial']['ended_at'])) {
        try {
            $now      = new DateTime();
            $trialEnd = new DateTime($meta['trial']['ended_at']);

            $trialActive = ($now <= $trialEnd);
            $trialEnded  = $trialEnd->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $trialActive = false;
        }
    }

    /* ===============================
       STATUS SERVICE
    ================================ */

    $serviceActive = (
        $client['status'] === 'active'
        || $trialActive
    );

    return [
        'service_active' => $serviceActive,
        'trial_active'   => $trialActive,
        'trial_ended_at' => $trialEnded
    ];
}