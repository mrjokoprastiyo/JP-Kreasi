<?php

class UserEngine {

    /**
     * 1. GET USER DATA
     * Mengambil data profil lengkap atau default jika belum ada.
     */
    public static function get($client_id, $sender_id, $platform = 'facebook') {
        $user = DB::fetch(
            "SELECT * FROM user_data_tanicerdas WHERE client_id = ? AND sender_id = ? AND platform = ?",
            [$client_id, $sender_id, $platform]
        );

        if ($user) return $user;

        // Default Template jika user baru
        return [
            'status' => 'uji_coba',
            'message_count' => 0,
            'chat_admin' => 0,
            'limited' => 0,
            'shared' => 0,
            'ratings' => 0,
            'comments_total' => 0
            // Tambahkan default lainnya jika diperlukan
        ];
    }

    /**
     * 2. UPDATE DYNAMIC (UPSERT)
     * Update kolom apa saja atau Insert jika data belum ada.
     */
    public static function update($client_id, $sender_id, $fields, $platform = 'facebook', $page_id = null) {
        if ($page_id && $sender_id === $page_id) return false;

        $exists = DB::fetch(
            "SELECT id FROM user_data_tanicerdas WHERE client_id = ? AND sender_id = ? AND platform = ?",
            [$client_id, $sender_id, $platform]
        );

        if ($exists) {
            $set_clause = [];
            $params = [];
            foreach ($fields as $field => $value) {
                $set_clause[] = "$field = ?";
                $params[] = is_bool($value) ? ($value ? 1 : 0) : $value;
            }
            $params[] = $client_id;
            $params[] = $sender_id;
            $params[] = $platform;

            $sql = "UPDATE user_data_tanicerdas SET " . implode(", ", $set_clause) . ", last_interaction = NOW() 
                    WHERE client_id = ? AND sender_id = ? AND platform = ?";
            return DB::exec($sql, $params);
        } else {
            $fields['client_id'] = $client_id;
            $fields['sender_id'] = $sender_id;
            $fields['platform']  = $platform;
            
            $columns = implode(", ", array_keys($fields));
            $placeholders = implode(", ", array_fill(0, count($fields), "?"));
            $params = array_values($fields);

            $sql = "INSERT INTO user_data_tanicerdas ($columns, last_interaction) VALUES ($placeholders, NOW())";
            return DB::exec($sql, $params);
        }
    }

    /**
     * 3. ATOMIC INCREMENT
     * Menambah nilai angka secara aman di database.
     */
    public static function increment($client_id, $sender_id, $fields_to_inc, $platform = 'facebook') {
        $user = DB::fetch("SELECT id FROM user_data_tanicerdas WHERE client_id=? AND sender_id=? AND platform=?", [$client_id, $sender_id, $platform]);

        if ($user) {
            $sets = []; $params = [];
            foreach ($fields_to_inc as $f => $v) {
                $sets[] = "$f = $f + ?";
                $params[] = $v;
            }
            $params = array_merge($params, [$client_id, $sender_id, $platform]);
            return DB::exec("UPDATE user_data_tanicerdas SET " . implode(", ", $sets) . " WHERE client_id=? AND sender_id=? AND platform=?", $params);
        } else {
            return self::update($client_id, $sender_id, $fields_to_inc, $platform);
        }
    }

    /**
     * 4. LIMIT CHECKER
     * Cek apakah user kena blokir limit pesan gratis.
     */
    public static function checkLimit($client_id, $sender_id, $platform = 'facebook', $max_free = 500) {
        $user = self::get($client_id, $sender_id, $platform);
        if (!isset($user['id'])) return false;

        $is_limited = false;
        $status = 'uji_coba';

        if ($user['message_count'] >= $max_free) {
            if ($user['shared'] > 0 || $user['ratings'] > 0) {
                $status = 'bebas';
                $is_limited = false;
            } else {
                $status = 'ditangguhkan';
                $is_limited = true;
            }
        }

        self::update($client_id, $sender_id, ['status' => $status, 'limited' => $is_limited], $platform);
        return $is_limited;
    }

    /**
     * 5. ITEM STATUS (Shortcut Rating/Share)
     */
    public static function updateItem($client_id, $sender_id, $item, $platform = 'facebook') {
        $map = ['rating' => 'ratings', 'share' => 'shared'];
        if (isset($map[$item])) {
            return self::increment($client_id, $sender_id, [$map[$item] => 1], $platform);
        }
        return false;
    }

    /**
     * 6. RESET/SET STATUS
     */
    public static function setStatus($client_id, $sender_id, $new_status, $platform = 'facebook') {
        return self::update($client_id, $sender_id, [
            'status' => $new_status,
            'conversation_count' => 0,
            'limited' => 0
        ], $platform);
    }
}
