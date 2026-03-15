<?php

require_once __DIR__ . '/../ai.php';

class MessengerHandler
{
    private int $client_id;
    private string $page_id;
    private string $access_token;
    private ?string $page_name;
    private array $ui;

    public function __construct(
        int $client_id,
        string $page_id,
        string $access_token,
        ?string $page_name = null,
        array $credentials = []
    ) {
        $this->client_id   = $client_id;
        $this->page_id     = $page_id;
        $this->access_token = $access_token;
        $this->page_name   = $page_name;
        $this->ui          = $credentials['ui'] ?? [];
    }

    /**
     * Entry point utama untuk Messaging Event
     */
    public function handle(array $input, array $active_sender = []): void
    {
        $messaging = $input['entry'][0]['messaging'][0] ?? null;
        if (!$messaging) {
            return;
        }

        $sender_id = $messaging['sender']['id'] ?? null;
        if (!$sender_id) {
            return;
        }

        // Conversation ID sekarang mengikuti sender_id
        $conversation_id = $sender_id;

        // Cegah respon ke diri sendiri
        if ($sender_id === $this->page_id) {
            return;
        }

        $active_name = $active_sender['active_participant_name'] ?? 'Sahabat';

        // Ambil data user dari DB
        $user_data = UserEngine::get(
            $this->client_id,
            $sender_id,
            'facebook'
        );

        $message_text = $messaging['message']['text'] ?? null;
        $payload      = $messaging['postback']['payload'] ?? null;

        if ($message_text) {
            $this->processMessage(
                $sender_id,
                $conversation_id,
                $message_text,
                $user_data,
                $active_name
            );
        } elseif ($payload) {
            $this->processPayload(
                $sender_id,
                $conversation_id,
                $payload,
                $user_data,
                $active_name
            );
        }
    }

    /**
     * Pemrosesan pesan teks
     */
    private function processMessage(
        string $sender_id,
        string $conversation_id,
        string $text,
        array $user,
        string $name
    ): void {
        $is_limited = UserEngine::checkLimit(
            $this->client_id,
            $sender_id,
            'facebook'
        );

        if (empty($user['chat_admin'])) {
            $this->replyWithAI(
                $sender_id,
                $conversation_id,
                $text,
                $name,
                $is_limited
            );
        } else {
            $this->replyWithAdmin(
                $sender_id,
                $name,
                $is_limited
            );
        }
    }

    /**
     * MODE AI
     */
    private function replyWithAI(
        string $sender_id,
        string $conversation_id,
        string $text,
        string $name,
        bool $is_limited
    ): void {

        UserEngine::increment(
            $this->client_id,
            $sender_id,
            ['message_count' => 1],
            'facebook'
        );

        if ($is_limited) {
            $this->sendLimitMessage($sender_id, $name);
            return;
        }

        // 1. Simpan pesan user
        DB::insert(
            "INSERT INTO chat_history (client_id, sender_id, role, message)
             VALUES (?, ?, 'user', ?)",
            [$this->client_id, $conversation_id, $text]
        );

        // 2. Ambil 10 history terakhir
        $rows = DB::fetchAll(
            "SELECT role, message
             FROM chat_history
             WHERE client_id = ?
             AND sender_id = ?
             ORDER BY id DESC
             LIMIT 10",
            [$this->client_id, $conversation_id]
        );

        $rows = array_reverse($rows);

        $history = [];
        foreach ($rows as $row) {
            $history[] = [
                'role'    => $row['role'],
                'message' => $row['message']
            ];
        }

        // 3. Call AI
        $ai_message = ai_reply(
            client_id: $this->client_id,
            prompt: null,
            model: null,
            message: $text,
            history: $history,
            provider: null
        );

        if (!$ai_message) {
            $ai_message = "Maaf, terjadi kesalahan pada sistem AI.";
        }

        $ai_message = $this->personalize($ai_message, $name);

        // 4. Simpan balasan AI
        DB::insert(
            "INSERT INTO chat_history (client_id, sender_id, role, message)
             VALUES (?, ?, 'assistant', ?)",
            [$this->client_id, $conversation_id, $ai_message]
        );

        // 5. Cleanup max 50 message
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
            [
                $this->client_id,
                $conversation_id,
                $this->client_id,
                $conversation_id
            ]
        );

        // 6. Kirim balasan
        $this->sendText($sender_id, $ai_message);

        // 7. Optional button
        $this->sendButtons($sender_id, "Butuh bantuan lainnya?", [
            [
                'type'    => 'postback',
                'title'   => 'CHAT DENGAN TIM',
                'payload' => 'CHAT_WITH_ADMIN'
            ],
            [
                'type'    => 'postback',
                'title'   => 'MENU UTAMA',
                'payload' => 'MAIN_MENU'
            ]
        ]);
    }

    /**
     * MODE ADMIN
     */
    private function replyWithAdmin(
        string $sender_id,
        string $name,
        bool $is_limited
    ): void {

        UserEngine::increment(
            $this->client_id,
            $sender_id,
            ['message_count' => 1],
            'facebook'
        );

        if ($is_limited) {
            $this->sendLimitMessage($sender_id, $name);
            return;
        }

        $this->sendText(
            $sender_id,
            "$name, permintaan kamu sedang diteruskan ke tim JP Desainer Kreatif. Kami akan membalas secepatnya."
        );

        $this->sendButtons(
            $sender_id,
            "Mulai proyek kamu sekarang dengan Assistant kami!",
            [
                [
                    'type'    => 'postback',
                    'title'   => 'CHAT DENGAN ASSISTANT',
                    'payload' => 'CHAT_WITH_ASSISTANT'
                ]
            ]
        );
    }

    /**
     * POSTBACK HANDLER
     */
    private function processPayload(
        string $sender_id,
        string $conversation_id,
        string $payload,
        array $user,
        string $name
    ): void {

        if ($payload === 'CHAT_WITH_ADMIN') {

            UserEngine::update(
                $this->client_id,
                $sender_id,
                ['chat_admin' => true],
                'facebook'
            );

            $this->sendText(
                $sender_id,
                "$name, silakan tulis kebutuhan kamu. Tim kami akan membalas secepatnya."
            );
        }

        if ($payload === 'CHAT_WITH_ASSISTANT') {

            UserEngine::update(
                $this->client_id,
                $sender_id,
                ['chat_admin' => false],
                'facebook'
            );

            $this->sendText(
                $sender_id,
                "$name, silakan tanya apa saja. Assistant kami siap membantu."
            );
        }
    }

    /* =============================
       UTILITIES
    ============================== */

    private function sendText(string $sender_id, string $text): void
    {
        $this->postRequest([
            'recipient' => ['id' => $sender_id],
            'message'   => ['text' => $text]
        ]);
    }

    private function sendButtons(string $sender_id, string $text, array $buttons): void
    {
        $this->postRequest([
            'recipient' => ['id' => $sender_id],
            'message'   => [
                'attachment' => [
                    'type'    => 'template',
                    'payload' => [
                        'template_type' => 'button',
                        'text'          => $text,
                        'buttons'       => $buttons
                    ]
                ]
            ]
        ]);
    }

    private function sendLimitMessage(string $sender_id, string $name): void
    {
        $this->sendText(
            $sender_id,
            "$name, akses ke fitur ini sedang dibatasi."
        );

        $this->sendButtons(
            $sender_id,
            "Dukung kami untuk melanjutkan percakapan:",
            [
                [
                    'type'  => 'web_url',
                    'url'   => 'https://www.facebook.com/tanicerdaz',
                    'title' => 'DUKUNG'
                ]
            ]
        );
    }

    private function personalize(string $reply, string $name): string
    {
        $first_name = explode(' ', trim($name))[0];

        return preg_replace(
            '/\b(Kamu|kamu|Anda|anda|You|you)\b/i',
            $first_name,
            $reply
        );
    }

    private function postRequest(array $payload): void
    {
        $url = "https://graph.facebook.com/v22.0/me/messages?access_token={$this->access_token}";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}