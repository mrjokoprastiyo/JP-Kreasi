<?php
require_once __DIR__ . '/../ai.php';

declare(strict_types=1);

class WhatsAppHandler
{
    private int $client_id;
    private array $credentials;
    private string $access_token;
    private string $phone_number_id;
    private array $ui;

    public function __construct(int $client_id, array $credentials)
    {
        $this->client_id       = $client_id;
        $this->credentials     = $credentials;
        $this->access_token    = $credentials['access_token'] ?? '';
        $this->phone_number_id = $credentials['phone_number_id'] ?? '';
        $this->ui              = $credentials['ui'] ?? [];
    }

    /* =====================================================
     * MAIN ENTRY
     * ===================================================== */
    public function handle(array $input): void
    {
        $value = $input['entry'][0]['changes'][0]['value'] ?? null;
        if (!$value) return;

        if (empty($value['messages'][0])) return;

        $message_data = $value['messages'][0];

        $sender_id = $message_data['from'] ?? null;
        $type      = $message_data['type'] ?? null;

        if (!$sender_id) return;

        if ($type !== 'text') return;

        $text = $message_data['text']['body'] ?? null;
        if (!$text) return;

        $this->processMessage($sender_id, $text);
    }

    /* =====================================================
     * PROCESS MESSAGE
     * ===================================================== */
    private function processMessage(string $sender_id, string $text): void
    {
        $user = UserEngine::get($this->client_id, $sender_id, 'whatsapp');

        $is_limited = UserEngine::checkLimit(
            $this->client_id,
            $sender_id,
            'whatsapp'
        );

        // Increment selalu
        UserEngine::increment(
            $this->client_id,
            $sender_id,
            ['message_count' => 1],
            'whatsapp'
        );

        if ($is_limited) {
            $limitText = $this->ui['limit_text']
                ?? "Akses sedang dibatasi.";

            $this->sendText($sender_id, $limitText);
            return;
        }

        if ($user['chat_admin'] ?? false) {
            $this->handleAdminMode($sender_id);
            return;
        }

        $this->handleAI($sender_id, $text);
    }

    /* =====================================================
     * AI MODE
     * ===================================================== */
    private function handleAI(string $sender_id, string $text): void
    {
        // 1️⃣ Simpan user message
        DB::insert(
            "INSERT INTO chat_history (client_id, sender_id, role, message)
             VALUES (?, ?, 'user', ?)",
            [$this->client_id, $sender_id, $text]
        );

        // 2️⃣ Ambil history 20 terakhir
        $rows = DB::fetchAll(
            "SELECT role, message
             FROM chat_history
             WHERE client_id = ?
             AND sender_id = ?
             ORDER BY id DESC
             LIMIT 20",
            [$this->client_id, $sender_id]
        );

        $history = array_reverse($rows ?? []);

        // 3️⃣ Call AI
        $reply = ai_reply(
            $this->client_id,
            null,
            null,
            $text,
            $history
        );

        if (!$reply) {
            $reply = $this->ui['ai_error_text']
                 ?? "Maaf, terjadi kesalahan sistem.";
        }

        // 4️⃣ Simpan reply
        DB::insert(
            "INSERT INTO chat_history (client_id, sender_id, role, message)
             VALUES (?, ?, 'assistant', ?)",
            [$this->client_id, $sender_id, $reply]
        );

        // 5️⃣ Cleanup max 50
        DB::execute(
            "DELETE FROM chat_history
             WHERE client_id = ?
             AND sender_id = ?
             AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM chat_history
                    WHERE client_id = ?
                    AND sender_id = ?
                    ORDER BY id DESC
                    LIMIT 50
                ) t
             )",
            [$this->client_id, $sender_id, $this->client_id, $sender_id]
        );

        // 6️⃣ Kirim WA reply
        $this->sendText($sender_id, $reply);
    }

    /* =====================================================
     * ADMIN MODE
     * ===================================================== */
    private function handleAdminMode(string $sender_id): void
    {
        $adminText = $this->ui['admin_forward_text']
            ?? "Pesan kamu diteruskan ke tim kami. Mohon tunggu ya 🙏";

        $this->sendText($sender_id, $adminText);
    }

    /* =====================================================
     * SEND MESSAGE
     * ===================================================== */
    private function sendText(string $to, string $text): void
    {
        $url = "https://graph.facebook.com/v22.0/{$this->phone_number_id}/messages";

        $payload = [
            "messaging_product" => "whatsapp",
            "to"   => $to,
            "type" => "text",
            "text" => [
                "body" => $text
            ]
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Authorization: Bearer {$this->access_token}"
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_exec($ch);
        curl_close($ch);
    }
}