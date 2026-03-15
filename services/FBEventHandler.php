<?php
require_once __DIR__ . '/../ai.php';

class FBEventHandler
{
    private int $client_id;
    private string $page_id;
    private string $access_token;
    private string $page_name;

    public function __construct(
        int $client_id,
        string $page_id,
        string $access_token,
        string $page_name = "Bot"
    ) {
        $this->client_id   = $client_id;
        $this->page_id     = $page_id;
        $this->access_token = $access_token;
        $this->page_name   = $page_name;
    }

    public function handle(array $input): void
    {
        $value = $input['entry'][0]['changes'][0]['value'] ?? null;
        if (!$value) return;

        $sender_id   = $value['from']['id'] ?? null;
        $sender_name = $value['from']['name'] ?? 'User';

        if (!$sender_id || $sender_id === $this->page_id) return;

        $item       = $value['item'] ?? '';
        $verb       = $value['verb'] ?? '';
        $post_id    = $value['post_id'] ?? null;
        $comment_id = $value['comment_id'] ?? null;
        $message    = $value['message'] ?? '';

        if ($item === 'comment' && $verb === 'add') {
            $this->processComment(
                $sender_id,
                $sender_name,
                $post_id,
                $comment_id,
                $message
            );
        }
    }

    /* ===================================================== */

    private function processComment(
        string $sender_id,
        string $sender_name,
        ?string $post_id,
        string $comment_id,
        string $message
    ): void {

        // Auto like
        $this->sendReaction($comment_id);

        // 1️⃣ Ambil Post
        $post_message = '';
        if ($post_id) {
            $post = $this->graphRequest($post_id, ['fields' => 'message']);
            $post_message = $post['message'] ?? '';
        }

        // 2️⃣ Ambil Comment + Parent
        $parent_message = '';
        $comment = $this->graphRequest(
            $comment_id,
            ['fields' => 'message,from,parent']
        );

        $comment_from_id = $comment['from']['id'] ?? null;

        if ($comment_from_id == $this->page_id && isset($comment['parent']['id'])) {
            $parent_id = $comment['parent']['id'];

            $parent = $this->graphRequest(
                $parent_id,
                ['fields' => 'message']
            );

            $parent_message = $this->removePageName(
                $parent['message'] ?? ''
            );
        }

        // 3️⃣ Bersihkan message
        $clean_message = $this->removePageName($message);

        // 4️⃣ Ambil history
        $history = $this->loadHistory($sender_id);

        // 5️⃣ Tentukan LAST REPLY (inti logic lama)
        $last_reply = '';

        if (!empty($history)) {
            // Ambil last assistant reply
            foreach (array_reverse($history) as $h) {
                if ($h['role'] === 'assistant') {
                    $last_reply = $h['message'];
                    break;
                }
            }
        }

        if (!$last_reply) {
            if (!empty($parent_message)) {
                $last_reply = $parent_message;
            } elseif (!empty($post_message)) {
                $last_reply = $post_message;
            }
        }

        // 6️⃣ Inject last_reply sebagai history awal jika ada
        if ($last_reply) {
            $history[] = [
                'role' => 'assistant',
                'message' => $last_reply
            ];
        }

        // 7️⃣ Generate AI
        $reply = ai_reply(
            $this->client_id,
            null,
            null,
            $clean_message,
            $history
        );

        $reply = $this->personalize($reply, $sender_name);

        // 8️⃣ Kirim komentar
        $this->sendComment($comment_id, $reply);

        // 9️⃣ Simpan history
        $this->saveHistory($sender_id, $clean_message, $reply);
    }

    /* ===================================================== */
    /* ================= DATABASE =========================== */

    private function loadHistory(string $sender_id): array
    {
        return DB::fetchAll("
            SELECT role, message
            FROM chat_history
            WHERE client_id = ?
              AND sender_id = ?
            ORDER BY id ASC
            LIMIT 20
        ", [$this->client_id, $sender_id]) ?? [];
    }

    private function saveHistory(string $sender_id, string $user_msg, string $bot_msg): void
    {
        DB::insert("chat_history", [
            'client_id' => $this->client_id,
            'sender_id' => $sender_id,
            'role'      => 'user',
            'message'   => $user_msg
        ]);

        DB::insert("chat_history", [
            'client_id' => $this->client_id,
            'sender_id' => $sender_id,
            'role'      => 'assistant',
            'message'   => $bot_msg
        ]);
    }

    /* ===================================================== */
    /* ================= GRAPH API ========================== */

    private function graphRequest(string $endpoint, array $params = [], bool $post = false): array
    {
        $params['access_token'] = $this->access_token;
        $url = "https://graph.facebook.com/v22.0/{$endpoint}";

        $ch = curl_init($url . '?' . http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);
        }

        $res = curl_exec($ch);
        curl_close($ch);

        return json_decode($res, true) ?? [];
    }

    private function sendComment(string $comment_id, string $message): void
    {
        $this->graphRequest(
            "{$comment_id}/comments",
            ['message' => $message],
            true
        );
    }

    private function sendReaction(string $id): void
    {
        $this->graphRequest(
            "{$id}/likes",
            [],
            true
        );
    }

    /* ===================================================== */
    /* ================= HELPERS ============================ */

    private function removePageName(string $text): string
    {
        return trim(str_replace($this->page_name, '', $text));
    }

    private function personalize(string $reply, string $name): string
    {
        $first = explode(' ', trim($name))[0];

        return preg_replace(
            '/\b(Kau|kau|Kamu|kamu|Anda|anda|Engkau|engkau)\b/',
            $first,
            $reply
        );
    }
}