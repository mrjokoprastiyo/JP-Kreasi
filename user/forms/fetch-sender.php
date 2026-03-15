<?php
/**
 * fetch-sender.php (Pagination Ready)
 * 
 * Mengambil semua participant (PSID) dari seluruh conversation
 * menggunakan paging Graph API sampai selesai.
 * Output: JSON
 */

session_start();
header('Content-Type: application/json');

// =======================
// VALIDASI INPUT
// =======================
$page_id = $_GET['page_id'] ?? null;
$access_token = $_GET['token'] ?? null;

if (!$page_id || !$access_token) {
    echo json_encode([
        'error' => 'Page ID dan Access Token diperlukan'
    ]);
    exit;
}

// =======================
// HELPER CURL
// =======================
function curlGet($url) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['error' => $error];
    }

    curl_close($ch);
    return json_decode($response, true);
}

// =======================
// AMBIL SEMUA SENDER (PAGINATION)
// =======================
function getAllSenderData($page_id, $access_token) {

    $baseUrl = "https://graph.facebook.com/v22.0/me/conversations";
    $query = http_build_query([
        'fields' => 'participants',
        'limit'  => 50,
        'access_token' => $access_token
    ]);

    $url = $baseUrl . '?' . $query;

    $allParticipants = [];
    $uniqueIds = [];

    while ($url) {

        $result = curlGet($url);

        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        if (!empty($result['data'])) {
            foreach ($result['data'] as $conversation) {

                if (!empty($conversation['participants']['data'])) {
                    foreach ($conversation['participants']['data'] as $participant) {

                        // Skip page itu sendiri
                        if ($participant['id'] == $page_id) {
                            continue;
                        }

                        // Hindari duplikat participant
                        if (isset($uniqueIds[$participant['id']])) {
                            continue;
                        }

                        $uniqueIds[$participant['id']] = true;

                        $allParticipants[] = [
                            'participant_id'   => $participant['id'],
                            'participant_name' => $participant['name'] ?? 'Unknown',
                            'conversation_id'  => $conversation['id'] ?? null
                        ];
                    }
                }
            }
        }

        // Pagination next
        $url = $result['paging']['next'] ?? null;
    }

    return $allParticipants;
}

// =======================
// EKSEKUSI
// =======================
$senders = getAllSenderData($page_id, $access_token);

if (isset($senders['error'])) {
    echo json_encode([
        'error' => 'Error dari API: ' . $senders['error']
    ]);
    exit;
}

// Jika kosong → berikan warning UX
if (empty($senders)) {
    echo json_encode([
        'warning' => '⚠️ Tidak ada PSID aktif. Silakan kirim pesan terlebih dahulu ke Page ini agar muncul sebagai target automation.',
        'data' => []
    ]);
    exit;
}

// Normal response (array langsung untuk kompatibel dengan JS lama)
echo json_encode($senders);