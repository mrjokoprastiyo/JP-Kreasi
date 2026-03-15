<?php

define('VERIFY_TOKEN', 'tanicerdas_verify_token');

define('CHATGPT_API_KEY', $api_key);
define('GEMINI_API_KEY', $api_key_gemini);

define('MAX_FREE_CONVERSATIONS', 500);

$access_token = '';
$page_id = '869539731743347';

$bad_words = ['kontol', 'memek', 'jembut', 'peler', 'tempek', 'pepek', 'puki'];

// ------------------- KONFIGURASI DATABASE --------------------

function get_db_connection() {
    $servername = "localhost";  // atau nama host database kamu
    $username = "situs";     // nama pengguna MySQL
    $password = "Jokoprastiyo@1979";     // kata sandi MySQL
    $dbname = "my_situs";  // nama database

    // Buat koneksi
    $conn = new mysqli($servername, $username, $password, $dbname);
    // mysqli_query($conn, "SET time_zone = '+07:00'");
    $conn->query("SET time_zone = '+07:00'");
    // Cek koneksi
    if ($conn->connect_error) {
        die("Koneksi gagal: " . $conn->connect_error);
    }

    return $conn;
}

function create_users_table() {
    $conn = get_db_connection();

    // Cek apakah tabel sudah ada
    $check_table_sql = "SHOW TABLES LIKE 'user_data_tanicerdas'";
    $result = $conn->query($check_table_sql);

    if ($result->num_rows == 0) {
    // Buat tabel user_data jika belum ada
    $sql = "CREATE TABLE IF NOT EXISTS user_data_tanicerdas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        sender_id VARCHAR(100) NOT NULL,
        platform ENUM('facebook', 'telegram', 'whatsapp') DEFAULT 'facebook',
        status VARCHAR(50),
        last_message TEXT,
        message_count INT DEFAULT 0,
        chat_admin BOOLEAN DEFAULT FALSE,
        limited BOOLEAN DEFAULT FALSE,
        follower BOOLEAN DEFAULT FALSE,
        shared INT DEFAULT 0,
        unshared INT DEFAULT 0,
        ratings INT DEFAULT 0,
        unratings INT DEFAULT 0,
        post_id VARCHAR(100),
        post_message_text TEXT,
        post_reaction_count INT DEFAULT 0,
        comment_id VARCHAR(100),
        parent_comment_message TEXT,
        ai_comment_reply TEXT,
        comment_reaction_count INT DEFAULT 0,
        comments_total INT DEFAULT 0,
        uncomments INT DEFAULT 0,
        reactions_total INT DEFAULT 0,
        unreactions INT DEFAULT 0,
        last_interaction TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

        if ($conn->query($sql) === TRUE) {
            echo "Table user_data created successfully";
        } else {
            echo "Error creating table: " . $conn->error;
        }
    } else {
        // Tabel sudah ada, tidak perlu membuat ulang
        // Tidak menampilkan pesan apapun
    }

    $conn->close();
}
// Cek apakah tabel sudah ada, jika tidak buat tabel
create_users_table();

function alter_user_data_table() {
    $conn = get_db_connection();

    $sql = "ALTER TABLE user_data_tanicerdas
            ADD conversation_count INT DEFAULT 0,
            ADD waiting_for_confirmation BOOLEAN DEFAULT 0,
            ADD waiting_for_admin BOOLEAN DEFAULT 0";

    if ($conn->query($sql) === TRUE) {
        echo "Table user_data altered successfully";
    } else {
        echo "Error altering table: " . $conn->error;
    }

    $conn->close();
}
// Jika tabel sudah ada, dan ingin menambahkan kolom baru
// alter_user_data_table();

// Fungsi dummy untuk mendapatkan data pengguna
function get_user_data($sender_id) {
    $conn = get_db_connection();  // Koneksi ke database

    // Query untuk mendapatkan data pengguna berdasarkan sender_id
    $stmt = $conn->prepare("SELECT * FROM user_data_tanicerdas WHERE sender_id = ?");
    $stmt->bind_param("s", $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();

    // Jika pengguna ditemukan, kembalikan datanya
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();  // Kembalikan data sebagai array asosiatif
    } else {
        // Jika pengguna tidak ditemukan, kembalikan data default untuk pengguna baru
        return [
            'status' => 'uji_coba',
            'last_message' => '',
            'message_count' => 0,
            'chat_admin' => false,
            'limited' => false,
            'follower' => false,
            'shared' => 0,
            'unshared' => 0,
            'ratings' => 0,
            'unratings' => 0,
            'post_id' => '',
            'post_message_text' => '',
            'post_reaction_count' => 0,
            'comment_id' => '',
            'parent_comment_message' => '',
            'ai_comment_reply' => '',
            'comment_reaction_count' => 0,
            'comments_total' => 0,
            'uncomments' => 0,
            'reactions_total' => 0,
            'unreactions' => 0,
        ];
    }
}

function update_user_data_dynamic($sender_id, $fields_to_update) {
    $page_id = '869539731743347';
    if ($sender_id === $page_id) {
        return false;
    }
    $conn = get_db_connection();  // Koneksi ke database

    // Periksa apakah pengguna sudah ada di database
    $stmt = $conn->prepare("SELECT * FROM user_data_tanicerdas WHERE sender_id = ?");
    if ($stmt === false) {
        file_put_contents('log_db_error.txt', "Prepare failed: " . $conn->error . "\n", FILE_APPEND);
        return false;
    }

    $stmt->bind_param("s", $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close(); // Tutup statement pertama setelah selesai

    // Bangun bagian SQL dinamis berdasarkan fields yang diberikan
    $set_clause = [];
    $params = [];
    $param_types = "";

    foreach ($fields_to_update as $field => $value) {
        $set_clause[] = "$field = ?";
        $params[] = $value;

        // Tentukan tipe data untuk bind_param
        if (is_int($value)) {
            $param_types .= "i";  // Integer
        } elseif (is_string($value)) {
            $param_types .= "s";  // String
        } elseif (is_bool($value)) {
            $param_types .= "i";  // Boolean sebagai Integer (1 atau 0)
            $value = $value ? 1 : 0;  // Ubah boolean menjadi integer
        }
    }

    // Tambahkan `last_interaction` secara otomatis ke dalam update
    $set_clause[] = "last_interaction = NOW()";
    $set_clause_string = implode(", ", $set_clause);

    if ($result->num_rows > 0) {
        // Siapkan query UPDATE jika pengguna sudah ada
        $stmt = $conn->prepare("UPDATE user_data_tanicerdas SET $set_clause_string WHERE sender_id = ?");
        if ($stmt === false) {
            file_put_contents('log_db_error.txt', "Prepare failed (UPDATE): " . $conn->error . "\n", FILE_APPEND);
            return false;
        }

        $param_types .= "s";  // Tambahkan tipe untuk `sender_id`
        $params[] = $sender_id;
        $stmt->bind_param($param_types, ...$params);
    } else {
        // Siapkan query INSERT jika pengguna belum ada
        $columns = implode(", ", array_keys($fields_to_update));
        $placeholders = implode(", ", array_fill(0, count($fields_to_update), "?"));
        $stmt = $conn->prepare("INSERT INTO user_data_tanicerdas (sender_id, $columns, last_interaction) VALUES (?, $placeholders, NOW())");
        if ($stmt === false) {
            file_put_contents('log_db_error.txt', "Prepare failed (INSERT): " . $conn->error . "\n", FILE_APPEND);
            return false;
        }

        $param_types = "s" . $param_types;  // Tambahkan tipe untuk `sender_id`
        array_unshift($params, $sender_id);  // Tambahkan sender_id di awal
        $stmt->bind_param($param_types, ...$params);
    }

    // Eksekusi query dan log hasilnya
    if ($stmt->execute()) {
        file_put_contents('log_db_success.txt', "Update/Insert berhasil untuk $sender_id dengan data: " . json_encode($fields_to_update) . "\n", FILE_APPEND);
        return true;  // Update/Insert berhasil
    } else {
        file_put_contents('log_db_error.txt', "Execute failed: " . $stmt->error . "\n", FILE_APPEND);
        return false; // Gagal
    }
}

function increment_user_data($sender_id, $fields_to_increment) {
    $conn = get_db_connection();  // Koneksi ke database

    // Ambil nilai yang ada dari database
    $stmt = $conn->prepare("SELECT sender_id, status, last_message, message_count, chat_admin, limited, follower, shared, unshared, ratings, unratings, post_id, post_message_text, post_reaction_count, comment_id, parent_comment_message, ai_comment_reply, comment_reaction_count, comments_total, uncomments, reactions_total, unreactions FROM user_data_tanicerdas WHERE sender_id = ?");
    $stmt->bind_param("s", $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Siapkan array untuk menampung nilai yang akan di-update
        $fields_to_update = [];

        // Periksa field mana yang perlu di-increment
        foreach ($fields_to_increment as $field => $increment_value) {
            // Inisialisasi nilai kolom menjadi 0 jika tidak ada di $row
            if (!isset($row[$field])) {
                $row[$field] = 0;
            }
            // Tambahkan nilai increment
            $fields_to_update[$field] = $row[$field] + $increment_value;
        }

        // Panggil fungsi dynamic update untuk menyimpan perubahan
        return update_user_data_dynamic($sender_id, $fields_to_update);
    } else {
        // Inisialisasi data pengguna jika tidak ditemukan
        $default_data = array_fill_keys(array_keys($fields_to_increment), 0);
        foreach ($fields_to_increment as $field => $increment_value) {
            $default_data[$field] = $increment_value;
        }

        // Simpan data baru dengan nilai increment yang diminta
        return update_user_data_dynamic($sender_id, $default_data);
    }
}

function check_and_update_limit($sender_id) {
    $conn = get_db_connection();  // Koneksi ke database
    
    // Ambil nilai stars_received, has_shared, dan text_message_count dari database
    $stmt = $conn->prepare("SELECT status, message_count, follower, shared, ratings FROM user_data_tanicerdas WHERE sender_id = ?");
    $stmt->bind_param("s", $sender_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Ambil nilai dari hasil query
        $row = $result->fetch_assoc();
        $message_count = $row['message_count'];
        $follower = $row['follower'];
        $shared = $row['shared'];
        $ratings = $row['ratings'];
        $is_subscribed = is_follower($active_conversation_id);

        // Cek apakah pengguna sudah subscribe
        // if ($is_subscribed) {
            // $fields_to_update = ['follower' => true];
            // update_user_data_dynamic($sender_id, $fields_to_update);

            // Cek limit berdasarkan jumlah pesan dan share
            if ($message_count >= MAX_FREE_CONVERSATIONS) {
                if ($shared >= 0) {
                    // Jika sudah share, status jadi bebas
                    $fields_to_update = ['status' => 'bebas', 'limited' => false];
                } elseif ($ratings > 0) {
                    // Jika sudah dapat bintang, status jadi bebas
                    $fields_to_update = ['status' => 'bebas', 'limited' => false];
                } else {
                    // Jika tidak share dan tidak ada bintang, status ditangguhkan
                    $fields_to_update = ['status' => 'ditangguhkan', 'limited' => true];
                }
            } else {
                // Jika masih dalam batas pesan gratis
                // $fields_to_update = ['status' => 'uji_coba', 'limited' => false];
            }

            // Update user data dengan status baru
            update_user_data_dynamic($sender_id, $fields_to_update);
            return $fields_to_update['limited'];  // Mengembalikan status apakah masih terkena limit atau tidak
        }

        // Jika pengguna belum dibatas pesan gratis
        if (MAX_FREE_CONVERSATIONS >= $message_count) {
            update_user_data_dynamic($sender_id, ['status' => 'uji_coba', 'limited' => false]);
            return $fields_to_update['limited'];  // Pengguna terbatas atau tidak
        // }

        // Jika pengguna belum subscribe dan melebihi batas pesan gratis
        if ($message_count >= MAX_FREE_CONVERSATIONS) {
            if ($shared == 0 || $ratings == 0) {
                // Jika belum share dan tidak ada bintang, status ditangguhkan
                $fields_to_update = ['status' => 'ditangguhkan', 'limited' => true];
            } else {
                // Jika sudah share atau dapat bintang, status bebas
                $fields_to_update = ['status' => 'bebas', 'limited' => false];
            }
            update_user_data_dynamic($sender_id, $fields_to_update);
            return $fields_to_update['limited'];  // Pengguna terbatas atau tidak
        }
    }
    
    return false;  // Pengguna tidak ditemukan atau tidak terbatas
}

function update_item_status($sender_id, $item) {
    $fields_to_update = [];

    if ($item === 'rating') {
        // Update ratings menjadi 1 (atau tambahkan sesuai jumlah star)
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT ratings FROM user_data_tanicerdas WHERE sender_id = ?");
        $stmt->bind_param("s", $sender_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $ratings = $row['ratings'] + 1; // Tambahkan star yang diterima
            $fields_to_update['ratings'] = $ratings;
        } else {
            $fields_to_update['ratings'] = 1; // Jika pengguna baru
        }
    }

    // Hanya jika ada update yang perlu dilakukan
    if (!empty($fields_to_update)) {
        update_user_data_dynamic($sender_id, $fields_to_update);
    }
}

function update_user_status($sender_id, $new_status) {
    $file_path = 'user_data.json';  // Lokasi file JSON

    // Cek apakah file ada
    if (!file_exists($file_path)) {
        // Jika file tidak ada, buat file kosong
        file_put_contents($file_path, json_encode([]));
    }

    // Baca isi file JSON
    $json_data = file_get_contents($file_path);
    $users = json_decode($json_data, true);

    // Perbarui status pengguna
    $users[$sender_id] = [
        'status' => $new_status,
        ' conversation_count' => 0,
        'waiting_for_confirmation' => false,
        'waiting_for_admin' => false,
        'is_limited' => false,
        'is_subscribed' => false,
        'has_shared' => false,
        'stars_received' => 0,
        'last_interaction' => date('Y-m-d H:i:s')  // Simpan juga waktu terakhir interaksi
    ];

    // Simpan kembali ke file JSON
    file_put_contents($file_path, json_encode($users));
}

// ------------------------ DON'T CHANGE --------------------------

$input = json_decode(file_get_contents('php://input'), true);

function get_sender_id($input) {
    if (isset($input['entry'][0]['messaging'][0]['sender']['id'])) {
        return $input['entry'][0]['messaging'][0]['sender']['id'];
    } elseif (isset($input['entry'][0]['changes'][0]['value']['from']['id'])) {
        return $input['entry'][0]['changes'][0]['value']['from']['id'];
    }
    return null;
}
// Panggil fungsi untuk mendapatkan sender_id
$sender_id = get_sender_id($input);

function get_sender_name($input) {
    if (isset($input['entry'][0]['messaging'][0]['sender']['name'])) {
        return $input['entry'][0]['messaging'][0]['sender']['name'];
    } elseif (isset($input['entry'][0]['changes'][0]['value']['from']['name'])) {
        return $input['entry'][0]['changes'][0]['value']['from']['name'];
    }
    return null;
}
// Panggil fungsi untuk mendapatkan sender_id
$sender_name = get_sender_name($input);

// Fungsi untuk menentukan access token berdasarkan sender_id
function determine_access_token_from_messaging($input, $page_id) {
    global $user_token, $page_token, $access_token;

    // Ambil recipient_id dari input event
    $recipient_id = $input['entry'][0]['messaging'][0]['recipient']['id'] ?? null;

    // Jika recipient_id adalah ID halaman, gunakan page_token
    if ($recipient_id === $page_id) {
        $access_token = $page_token;  // Token untuk halaman
    } else {
        $access_token = $user_token;  // Token untuk profil
    }
}
determine_access_token_from_messaging($input, $page_id);

function determine_access_token_from_changes($input, $page_id) {
    global $user_token, $page_token, $access_token;

    // Ambil page_id dari changes (untuk event non-messaging)
    $entry_id = $input['entry'][0]['id'] ?? null;

    // Jika page_id cocok dengan ID halaman, gunakan page_token
    if ($entry_id === $page_id) {
        $access_token = $page_token;  // Token untuk halaman
    } else {
        $access_token = $user_token;  // Token untuk profil
    }
}
determine_access_token_from_changes($input, $page_id);

// -------------------------------------------------------------------

// Fungsi untuk mengambil semua active sender dari percakapan
function getSenderData($page_id) {
    global $access_token;
    $url = "https://graph.facebook.com/v22.0/me/conversations?fields=participants&access_token={$access_token}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }

    curl_close($ch);

    $conversations = json_decode($response, true);

    if (isset($conversations['error'])) {
        echo "Error dari API: " . $conversations['error']['message'];
        return null;
    }

    // Array untuk menyimpan semua active sender yang ditemukan
    $sender_datas = [];

    if (isset($conversations['data'])) {
        foreach ($conversations['data'] as $conversation) {
            foreach ($conversation['participants']['data'] as $participant) {
                if ($participant['id'] != $page_id) {
                    // Tambahkan peserta ke array active_senders
                    $sender_datas[] = [
                        'participant_data_id' => $participant['id'],
                        'participant_data_name' => $participant['name'],
                        'conversation_data_id' => $conversation['id']
                    ];
                }
            }
        }
    }

    // Kembalikan array berisi semua peserta
    return $sender_datas;
}

$sender_datas = getSenderData($page_id);

function getActiveSender($sender_datas, $sender_id) {
    $participant_data_id = null;
    $participant_data_name = null;
    $conversation_data_id = null;

    if ($sender_datas) {
        foreach ($sender_datas as $sender) {
            if ($sender['participant_data_id'] == $sender_id && $sender_id !== $page_id) {
                $participant_data_id = $sender['participant_data_id'];
                $participant_data_name = $sender['participant_data_name'];
                $conversation_data_id = $sender['conversation_data_id'];
            }
        }
    }
    return [
        'active_participant_id' => $participant_data_id,
        'active_participant_name' => $participant_data_name,
        'active_conversation_id' => $conversation_data_id
    ];
}

$active_sender = getActiveSender($sender_datas, $sender_id);

    if ($active_sender) {
        $active_participant_id = $active_sender['active_participant_id'];
        $active_participant_name = $active_sender['active_participant_name'];
        $active_conversation_id = $active_sender['active_conversation_id'];
    }

// -------------------------------------------------------------------

function is_follower($active_conversation_id) {
    global $access_token;
    $url = "https://graph.facebook.com/v22.0/{$active_conversation_id}?fields=is_subscribed&access_token={$access_token}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if(curl_errno($ch)) {
        $error_msg = curl_error($ch);
        file_put_contents("log_curl_errors.txt", $error_msg);
    }

    curl_close($ch);
    $data = json_decode($response, true);
    
    return isset($data['is_subscribed']) ? $data['is_subscribed'] : null;
}

// -------------------------------------------------------------------

function handle_event($input, $active_conversation_id, $page_id) {
    global $access_token;

    $sender_id = get_sender_id($input);
    $sender_name = get_sender_name($input);
    $user_data = get_user_data($sender_id);

    // Ambil sender_id dan data terkait jika ada
    if (isset($input['entry'][0]['changes'][0]['value']['from']['id'])) {
        $value = $input['entry'][0]['changes'][0]['value'];
        // $sender_name = $value['from']['name'];
        $item = $value['item'];
        $verb = $value['verb'];
        $message_text = $value['message'];
        $post_id = $value['post_id'];
        $comment_id = $value['comment_id'];
        $page_name = "Saya sebel sama situ sebab situ suka senyum senyum sama suami saya";
// -------------------------------------------------------------------
        if ($post_id) {
            $post_url = "https://graph.facebook.com/v22.0/{$post_id}?fields=message&access_token={$access_token}";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $post_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $post_response = curl_exec($ch);
            curl_close($ch);
            $post_data = json_decode($post_response, true);

            $post_message_text = $post_data['message'] ?? null;
            // update_user_data_dynamic($sender_id, ['post_id' => $post_id, 'post_message_text' => $post_message_text]);

            // Logging untuk debug
    file_put_contents('log_Post_Data.txt', "Post Data: " . json_encode($post_data) . "\n", FILE_APPEND);
        }

        if ($comment_id) {
            $comment_url = "https://graph.facebook.com/v22.0/{$comment_id}?fields=message,from,parent&access_token={$access_token}";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $comment_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $result = curl_exec($ch);
            curl_close($ch);
            $comment_data = json_decode($result, true);

            $comment_message_text = $comment_data['message'] ?? '';

            $comment_from_id = $comment_data['from']['id'] ?? null;
            // $parent_comment_id = $comment_data['parent']['id'] ?? null;
            if ($comment_from_id = $page_id) {
                // $parent_comment_message = $comment_data['parent']['message'];
                $parent_comment_id = $comment_data['parent']['id'];
            }
            // update_user_data_dynamic($sender_id, ['comment_id' => $comment_id, 'comment_message_text' => $comment_message_text]);

            // Logging untuk debug
    file_put_contents('log_Comment_Data.txt', "Comment Data: " . json_encode($comment_data) . "\n", FILE_APPEND);
            // file_put_contents('log_Parent_Comment_Data.txt', "Parent Comment Data: " . $parent_comment_message_text . "\n", FILE_APPEND);
        }

        if ($parent_comment_id) {
            $parent_comment_url = "https://graph.facebook.com/v22.0/{$parent_comment_id}?fields=message&access_token={$access_token}";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $parent_comment_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $parent_result = curl_exec($ch);
            curl_close($ch);
            $parent_comment_data = json_decode($parent_result, true);

            $parent_comment_message = $parent_comment_data['message'] ?? '';

            $parent_comment_message_text = remove_page_name($page_name, $parent_comment_message);

        }
// -------------------------------------------------------------------
        // Jika event follow (subscribe)
        if (isset($value['verb']) && $value['verb'] === 'subscribe') {
            $follower_id = $value['from']['id'];
            // Kirim pesan terima kasih atas follow
            $message = "Terima kasih telah mengikuti halaman kami!";
            send_text_message_if($sender_id, $message);
            update_user_data_dynamic($sender_id, ['follower' => true]);
        }

        // Jika event unfollow (unsubscribe)
        elseif (isset($value['verb']) && $value['verb'] === 'unsubscribe') {
            $unfollower_id = $value['from']['id'];
            // Kirim pesan untuk unfollow (Opsional, jika ingin ada tindak lanjut)
            $message = "Maaf melihat Anda berhenti mengikuti kami. Jika ada umpan balik, jangan ragu untuk memberi tahu kami.";
            send_text_message_if($sender_id, $message);
            update_user_data_dynamic($sender_id, ['follower' => false]);
        }

        // Gunakan switch case untuk memudahkan manajemen event
        switch ($item) {
            case 'share':
                if ($verb === 'add') {
                    send_text_message_if($sender_id, "{$sender_name}, Terima kasih telah membagikan postingan halaman kami!");
                    increment_user_data($sender_id, ['shared' => 1]);
                } elseif ($verb === 'remove') {
                    increment_user_data($sender_id, ['unshared' => 1]);
                }
                break;

            case 'rating':
                if ($verb === 'add') {
                    send_text_message_if($sender_id, "{$sender_name}, Terima kasih telah memberi rating halaman kami!");
                    update_item_status($sender_id, $item);
                } elseif ($verb === 'remove') {
                    increment_user_data($sender_id, ['unratings' => 1]);
                }
                break;

            case 'reaction':
                $reaction_type = $value['reaction_type'];
                $reaction_type_like = get_reaction_emoji($reaction_type);
                
                if ($verb === 'add') {
                    send_text_message_if($sender_id, "{$sender_name}, Makasih ya.. udah kasih tanggapan {$reaction_type_like} di postingan kami!");
                    increment_user_data($sender_id, ['reactions_total' => 1]);
                } elseif ($verb === 'remove') {
                    increment_user_data($sender_id, ['unreactions' => 1]);
                }
                break;

            case 'comment':
                $comment_message = isset($value['message']) && isset($value['comment_id']) ? $value['message'] : null;

                if ($sender_id === $page_id) {
                    return false;
                }

                if ($verb === 'add') {
                    if (contains_bad_words($message_text)) {
                        send_comment($value['comment_id'], "Maaf, komentar {$sender_name} mengandung kata-kata yang kurang pantas.");
                        increment_user_data($sender_id, ['comments_count' => 1]);
                        $bad_words_reply = "Maaf, komentar {$sender_name} mengandung kata-kata yang kurang pantas.";
                        update_user_data_dynamic($sender_id, ['ai_comment_reply' => $bad_words_reply]);
                    } else {
                        send_reaction($comment_id);

                        if ($comment_message) {
                            $clean_message = remove_page_name($page_name, $comment_message);

                            $stored_comment_id = $user_data['comment_id'];
                            if ($stored_comment_id !== $comment_id) {
                                update_user_data_dynamic($sender_id, ['comment_id' => $comment_id]);
                            }
                            $stored_post_id = $user_data['post_id'];
                            if ($stored_post_id !== $post_id) {
                                $comment_message_short = '';
                                update_user_data_dynamic($sender_id, ['post_id' => $post_id]);
                            } 

                            if (!empty(trim($clean_message))) {
                                if ($comment_from_id == $page_id && $user_data['post_id'] == $post_id) {
                                    $parent_comment_message_reply = $user_data['ai_comment_reply'] ?? '';
                                } else {
                                    $parent_comment_message_reply = $parent_comment_message_text;
                                }
                                $comment_message_reply = $comment_message_text;

                                $parent_comment_message_short = $parent_comment_message_reply;
                                $comment_message_short = $comment_message_reply;
                                $post_message_short = $post_message_text;

                                if (!empty($parent_comment_message_short) && $user_data['post_id'] == $post_id) {
                                    $last_reply = "$parent_comment_message_short";
                                } elseif (!empty($post_message_short)) {
                                    $last_reply = "$post_message_short";
                                } else {
    $last_reply = "";
                                }

                                $ai_comment_reply = chatgpt_reply($last_reply, $clean_message);
                                file_put_contents('log_last_reply.txt', "Full Context: $last_reply\n", FILE_APPEND);
                            }
                        }

                        $personalized_reply = personalize_reply($ai_comment_reply, $sender_name);
                        file_put_contents('log_LAST_reply.txt', "Last Reply: $last_reply\n", FILE_APPEND);
                        send_comment($comment_id, $personalized_reply);
                        increment_user_data($sender_id, ['comments_total' => 1]);
                        file_put_contents('log_AI_REPLY.txt', "Response: $ai_comment_reply, To: $sender_name\n", FILE_APPEND);

                        $chatgpt_respon = $chatgpt_response_data['choices'][0]['message']['content'];
                        $chatgpt_comment_reply =  $user_data['ai_comment_reply'];

                        if (isset($ai_comment_reply)) {
                            update_user_data_dynamic($sender_id, ['ai_comment_reply' => $personalized_reply]);
                        }
                    }
                } elseif ($verb === 'remove') {
                    increment_user_data($sender_id, ['uncomments' => 1]);
                }
                break;

// Baru

case 'post':
    if ($verb === 'add') {
        $post_id = $value['post_id'] ?? null;
        $sender_id = $value['from']['id'] ?? null;
        $sender_name = $value['from']['name'] ?? 'Pengguna';
        $post_message = $value['message'] ?? '';

        // Cegah respon ke postingan sendiri
        if ($sender_id === $page_id) {
            return false;
        }

        // Kirim reaksi (like) otomatis ke postingan
        if ($post_id) {
            send_post_reaction($post_id); // Fungsi ini sama seperti untuk komentar
        }

        // Simpan atau log aktivitas
        file_put_contents('log_post.txt', "Post by $sender_name: $post_message\n", FILE_APPEND);

        // Jika ingin membalas sebagai komentar ke postingan
        $reply = chatgpt_reply('', $post_message); // Konteks kosong, langsung respon ke isi posting
        $personalized = personalize_reply($reply, $sender_name);
        send_post_comment($post_id, $personalized);

        // Simpan data user
        increment_user_data($sender_id, ['posts_total' => 1]);
        update_user_data_dynamic($sender_id, ['last_post_id' => $post_id, 'last_post_message' => $post_message]);
    } elseif ($verb === 'remove') {
        increment_user_data($sender_id, ['unposts' => 1]);
    }
    break;

// 

            case 'profile':
                if ($verb === 'follow') {
                    send_text_message_if($sender_id, "{$sender_name}, Terima kasih telah mengikuti halaman kami!");
                    update_user_data_dynamic($sender_id, ['follower' => true]);
                } elseif ($verb === 'remove') {
                    send_text_message_if($sender_id, "{$sender_name}, Kami sedih melihat Kamu pergi, semoga bisa berjumpa lagi.");
                    update_user_data_dynamic($sender_id, ['follower' => false]);
                }
                break;
            
            case 'page':
                if ($verb === 'follow') {
                    send_text_message_if($sender_id, "{$sender_name}, Terima kasih telah mengikuti halaman kami!");
                    update_user_data_dynamic($sender_id, ['follower' => true]);
                } elseif ($verb === 'remove') {
                    send_text_message_if($sender_id, "{$sender_name}, Kami sedih melihat Kamu pergi, semoga bisa berjumpa lagi.");
                    update_user_data_dynamic($sender_id, ['follower' => false]);
                }

                if ($verb === 'subscribe') {
                    send_text_message_if($sender_id, "{$sender_name}, Terima kasih telah mengikuti halaman kami!");
                    update_user_data_dynamic($sender_id, ['follower' => true]);
                } elseif ($verb === 'unsubscribe') {
                    send_text_message_if($sender_id, "{$sender_name}, Kami sedih melihat Kamu pergi, semoga bisa berjumpa lagi.");
                    update_user_data_dynamic($sender_id, ['follower' => false]);
                }
                break;
            
            default:
                // Tidak ada tindakan untuk event lainnya
                break;
        }
    }
}

// Fungsi untuk mendapatkan emoji dari jenis reaksi
function get_reaction_emoji($reaction_type) {
    $emojis = [
        'like' => '👍',
        'love' => '❤️',
        'wow' => '😮',
        'haha' => '😆',
        'sad' => '😪',
        'angry' => '😡'
    ];
    
    return $emojis[$reaction_type] ?? '';
}

$page_name = "Tani Cerdas";
function remove_page_name($page_name, $message_text) {
    return str_replace($page_name, '', $message_text);
}

function personalize_reply($reply, $sender_name) {
    // Ambil hanya nama depan dari sender_name
    $first_name = explode(' ', trim($sender_name))[0];

    // Ganti hanya kata "Kamu", "kamu", "Anda", dan "anda" yang berdiri sendiri
    $personalized_reply = preg_replace('/\b(Kau|kau|Engkau|engkau|Kamu|kamu|Anda|anda)\b/', $first_name, $reply);

    return $personalized_reply;
}

function get_last_reply($sender_id) {
    $user_data = get_user_data($sender_id);
    return isset($user_data['last_reply']) ? $user_data['last_reply'] : "Saya akan membalas komentar anda segera";
}

function send_reaction($comment_id) {
    global $access_token;
    $url = "https://graph.facebook.com/v22.0/{$comment_id}/likes?access_token={$access_token}";
    send_post_request($url);
}

function send_comment($comment_id, $message_text) {
    global $access_token;
    $url = "https://graph.facebook.com/{$comment_id}/comments?access_token={$access_token}&message=" . urlencode($message_text);
    send_post_request($url);
}

function send_post_reaction($post_id) {
    global $access_token;
    $url = "https://graph.facebook.com/v22.0/{$post_id}/likes?access_token={$access_token}";
    send_post_request($url);
}

function send_post_comment($post_id, $message_text) {
    global $access_token;
    $url = "https://graph.facebook.com/{$post_id}/comments?access_token={$access_token}&message=" . urlencode($message_text);
    send_post_request($url);
}

function send_post_request($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true); // Karena kita akan melakukan POST request
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    }

    file_put_contents('log_api_response.txt', "URL: $url\nResponse: $response\n", FILE_APPEND);

    curl_close($ch);
    return $response;
}

// ===================================================================
// VERIFY TOKEN
// ===================================================================

function verify_token($token) {
    return $token === VERIFY_TOKEN;
}

if (
    isset($input['hub_mode']) &&
    $input['hub_mode'] === 'subscribe' &&
    verify_token($input['hub_verify_token'])
) {
    echo $input['hub_challenge'];
    exit;
}



// ===================================================================
// 1. CEK GET STARTED PALING AWAL (WAJIB DI ATAS HANDLE EVENT/INPUT)
// ===================================================================

 // if (
    // isset($input['entry'][0]['messaging'][0]['postback']['payload']) &&
    // $input['entry'][0]['messaging'][0]['postback']['payload'] === 'GET_STARTED_PAYLOAD'
// ) {

    // Beri welcome — ini action sesudah klik tombol GET STARTED
    // send_first_welcome($sender_id, $page_token);

    // Munculkan Persistent Menu
    // persistent_menu($page_token);

    // return;
// }



// ===================================================================
// 2. HANDLE EVENT (share, comment, reaction, rating)
// ===================================================================

handle_event($input, $active_conversation_id, $page_id);
persistent_menu($page_token);
set_get_started_button($page_token);

// ===================================================================
// 3. JIKA SUDAH ADA CONVERSATION AKTIF → MASUK ke INPUT LOGIC
// ===================================================================

if (!empty($active_conversation_id)) {

    handle_input(
        $input,
        $active_sender,
        $sender_id,
        $active_participant_id,
        $active_participant_name,
        $active_conversation_id
    );

    return;
// } else {
    // set_get_started_button($page_token);
}

// -------------------------------------------------------------------

// Fungsi dummy untuk memeriksa kata tak pantas
function contains_bad_words($message) {
    global $bad_words;
    foreach ($bad_words as $word) {
        if (stripos($message, $word) !== false) {
            return true;
        }
    }
    return false;
}

function handle_input($input, $active_sender, $sender_id, $active_participant_id, $active_participant_name, $active_conversation_id) {
    if (!$input) {
        return;
    }

    $is_subscribed = is_follower($active_conversation_id);
    $page_id = '869539731743347'; // Ganti dengan page ID milikmu

    if (isset($input['entry'])) {
        foreach ($input['entry'] as $entry) {

            handle_message_or_payload($input, $page_id, $active_sender, $sender_id, $active_participant_id, $active_participant_name, $active_conversation_id);
        }
    }
}

function handle_message_or_payload($input, $page_id, $active_sender, $sender_id, $active_participant_id, $active_participant_name, $active_conversation_id) {
    if (!isset($input['entry'][0]['messaging'][0])) {
        return;
    }

    $is_subscribed = is_follower($active_conversation_id);
    $message_text = $input['entry'][0]['messaging'][0]['message']['text'] ?? null;
    $payload = $input['entry'][0]['messaging'][0]['postback']['payload'] ?? null;

    if (isset($input['entry'][0]['messaging'][0]['message']['text']) || isset($input['entry'][0]['messaging'][0]['postback']['payload'])) {
        $sender_id = get_sender_id($input);
        $user_data = get_user_data($sender_id);

        if ($sender_id !== $page_id) {
            // handle_follower_status($sender_id, $is_subscribed, $active_participant_name);
            if ($message_text) {
                process_message($sender_id, $message_text, $user_data, $active_participant_name);
            } elseif ($payload) {
                process_payload($sender_id, $payload, $user_data, $is_subscribed, $active_participant_name);
            }
        }
    }
}

function handle_follower_status($sender_id, $is_subscribed, $active_participant_name) {  
    if ($is_subscribed === true) {  
        // update_user_data_dynamic($sender_id, ['follower' => true]);  
    } else {  
        send_text_message_if($sender_id, "Selamat datang {$active_participant_name}! Silakan mulai obrolannya.");  
        send_button_message_if($sender_id, "{$active_participant_name}, jangan lupa like & follow halaman kami!", [  
            ["type" => "web_url", "url" => "https://www.facebook.com/tanicerdaz", "title" => "FOLLOW PAGE"]  
        ]);  
        update_user_data_dynamic($sender_id, ['follower' => false]);  
    }  
}  

function process_message($sender_id, $message_text, $user_data, $active_participant_name) {  
    check_and_update_limit($sender_id);  

    if (contains_bad_words($message_text)) {  
        send_text_message_if($sender_id, "Maaf {$active_participant_name}, pesan kamu mengandung kata yang tidak pantas.");  
    } elseif ($user_data['chat_admin'] == false) {  
        increment_and_reply($sender_id, $user_data, $active_participant_name, $message_text);  
    } elseif ($user_data['chat_admin'] == true) {  
        increment_and_reply_admin($sender_id, $user_data, $active_participant_name);  
    } else {  
        if (is_follower($active_conversation_id) === false) {  
            send_follow_prompt($sender_id);  
        }  
    }  
}  

function increment_and_reply($sender_id, $user_data, $active_participant_name, $message_text) {  
    increment_user_data($sender_id, ['message_count' => 1]);  

    if ($user_data['limited'] == false) {  
        $last_message = get_last_message($sender_id);  

        if (!empty($last_message)) {  
            $last_reply = $last_message;  
        } else {  
            $last_reply = "";  
        }  

        $clean_message = $message_text;  
        $ai_message = gemini_reply($last_reply, $clean_message);  

        $personalized_message = personalize_message($ai_message, $active_participant_name);  
        send_text_message_if($sender_id, $personalized_message);  
        update_user_data_dynamic($sender_id, ['last_message' => $personalized_message]);  

        send_button_message_if($sender_id, "Butuh bantuan lainnya?", [  
            ['type' => 'postback', 'title' => 'CHAT DENGAN TIM', 'payload' => 'CHAT_WITH_ADMIN']  
        ]);  
    } else {  
        send_limit_message($sender_id, $active_participant_name);  
    }  
}  

function increment_and_reply_admin($sender_id, $user_data, $active_participant_name) {  
    increment_user_data($sender_id, ['message_count' => 1]);  

    if ($user_data['limited'] == false) {  
        send_text_message_if($sender_id, "{$active_participant_name}, permintaan kamu sedang diteruskan ke tim JP Desainer Kreatif. Kami akan membalas secepatnya.");  
        send_button_message_if($sender_id, "Mulai proyek kamu sekarang dengan Assistant kami!", [  
            ['type' => 'postback', 'title' => 'DESAIN & CHATBOT ASSISTANT', 'payload' => 'CHAT_WITH_ASSISTANT']  
        ]);  
    } else {  
        send_limit_message($sender_id, $active_participant_name);  
    }  
}  

function process_payload($sender_id, $payload, $user_data, $is_subscribed, $active_participant_name) {  

    if ($payload == 'CHAT_WITH_ADMIN') {  
        update_user_data_dynamic($sender_id, ['chat_admin' => true]);  
        send_text_message_if($sender_id, "{$active_participant_name}, silakan tulis kebutuhan atau detail proyek kamu. Tim JP Desainer Kreatif akan membalas secepatnya.");  
    } elseif ($payload == 'CHAT_WITH_ASSISTANT') {  
        update_user_data_dynamic($sender_id, ['chat_admin' => false]);  
        send_text_message_if($sender_id, "{$active_participant_name}, silakan tuliskan apa yang kamu butuhkan. Assistant JP Desainer Kreatif akan membantu menjelaskan layanan, harga awal, dan proses pemesanan.");  
    } else {  
        if (is_follower($active_conversation_id) === false) {  
            send_follow_prompt($sender_id);  
        }  
    }  
}  

function send_limit_message($sender_id, $active_participant_name) {  
    send_text_message_if($sender_id, "{$active_participant_name}, akses ke fitur ini sedang dibatasi.");  
    send_button_message_if($sender_id, "Dukung JP Desainer Kreatif untuk melanjutkan percakapan:", [  
        ['type' => 'web_url', 'url' => 'https://www.facebook.com/tanicerdaz', 'title' => 'DUKUNG']  
    ]);  
}  

function send_follow_prompt($sender_id) {  
    send_text_message_if($sender_id, "Untuk melanjutkan, silakan follow halaman JP Desainer Kreatif terlebih dahulu.");  
    send_button_message_if($sender_id, "Ikuti halaman kami untuk melanjutkan percakapan:", [  
        ['type' => 'web_url', 'url' => 'https://www.facebook.com/tanicerdaz', 'title' => 'Follow Page']  
    ]);  
}  

function personalize_message($reply, $active_participant_name) {  
    $first_name = explode(' ', trim($active_participant_name))[0];  
    $personalized_reply = preg_replace('/\b(Kamu|kamu|Anda|anda|You|you)\b/', $first_name, $reply);  
    return $personalized_reply;  
}

function get_last_message($sender_id) {
    $user_data = get_user_data($sender_id);
    return isset($user_data['last_message']) ? $user_data['last_message'] : "Hai, saya Assistant JP Desainer Kreatif. Ada yang bisa saya bantu hari ini?";
}

function send_text_message_if($sender_id, $message) {
    if ($sender_id !== null) {
        send_text_message($sender_id, $message);
    }
}

// Fungsi dummy untuk mengirim pesan teks
function send_text_message($sender_id, $message) {
    global $access_token;
    $url = "https://graph.facebook.com/v22.0/me/messages?access_token={$access_token}";
    $jsonData = [
        'recipient' => ['id' => $sender_id],
        'message' => ['text' => $message]
    ];
    send_request($url, $jsonData);
}

// Fungsi untuk mengirim pesan setelah pengecekan $sender_id
function send_button_message_if($sender_id, $text, $buttons) {
    if ($sender_id !== null) {
        send_button_message($sender_id, $text, $buttons);
    }
}

// Fungsi dummy untuk mengirim pesan dengan tombol
function send_button_message($sender_id, $text, $buttons) {
    global $access_token;
    $url = "https://graph.facebook.com/v22.0/me/messages?access_token={$access_token}";
    $jsonData = [
        'recipient' => ['id' => $sender_id],
        'message' => [
            'attachment' => [
                'type' => 'template',
                'payload' => [
                    'template_type' => 'button',
                    'text' => $text,
                    'buttons' => $buttons
                ]
            ]
        ]
    ];
    send_request($url, $jsonData);
}

// Fungsi umum untuk mengirimkan request ke Facebook API
function send_request($url, $jsonData) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    } else {
        echo 'Response: ' . $response;
    }

    curl_close($ch);
}

function chatgpt_reply ($last_reply, $clean_message) {
    if (empty($last_reply)) {
        // $last_reply = "Hi, I’m the JP Desainer Kreatif Assistant. How can I help you today?";
    }
    $url = 'https://api.cohere.com/v1/chat';
    $data = [
        "message" => $clean_message,
        "chat_history" => [
            [
                "message" => $last_reply,
                "role" => "CHATBOT"
            ]
        ],
        "model" => "command-a-03-2025",
        "preamble" => ""
    ];
// 63gHUKx224qoZD5mPpQKrs9kfaEocsrXRUPPxvuY
    $headers = [
        "accept: application/json",
        "content-type: application/json",
        "Authorization: bearer " . CHATGPT_API_KEY
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    file_put_contents('log_API_KEY.txt', "Detected: " . print_r(CHATGPT_API_KEY, true) . "\n", FILE_APPEND);
    file_put_contents('log_RESPONSE.txt', "Response: " . print_r($response, true) . "\n", FILE_APPEND);
    file_put_contents('log_RESPONSE_postData.txt', "Response: " . print_r($data, true) . "\n", FILE_APPEND);

    curl_close($ch);

    $result = json_decode($response, true);
    // Cek error cURL
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    } else {
        if (isset($result['text'])) {
            return trim($result['text']);
        } else {
            return "Anda 🙏";
            file_put_contents("log_RESPONSE_error.txt", print_r($result, true));
        }
    }
}


function gemini_reply ($last_reply, $clean_message) {
    if (empty($last_reply)) {
        // $last_reply = "Hi, I’m the JP Desainer Kreatif Assistant. How can I help you today?";
    }
    $url = 'https://api.cohere.com/v1/chat';
    $data = [
        "message" => $clean_message,
        "chat_history" => [
            [
                "message" => $last_reply,
                "role" => "CHATBOT"
            ]
        ],
        "model" => "command-a-03-2025",
        "preamble" => ""
    ];
// 63gHUKx224qoZD5mPpQKrs9kfaEocsrXRUPPxvuY
    $headers = [
        "accept: application/json",
        "content-type: application/json",
        "Authorization: bearer " . CHATGPT_API_KEY
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    file_put_contents('log_API_KEY.txt', "Detected: " . print_r(CHATGPT_API_KEY, true) . "\n", FILE_APPEND);
    file_put_contents('log_RESPONSE.txt', "Response: " . print_r($response, true) . "\n", FILE_APPEND);
    file_put_contents('log_RESPONSE_postData.txt', "Response: " . print_r($data, true) . "\n", FILE_APPEND);

    curl_close($ch);

    $result = json_decode($response, true);
    // Cek error cURL
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
    } else {
        if (isset($result['text'])) {
            return trim($result['text']);
        } else {
            return "Anda 🙏";
            file_put_contents("log_RESPONSE_error.txt", print_r($result, true));
        }
    }
}


// -------------------------------------------------------------------

function set_get_started_button($page_token) {
    global $access_token;

    $url = "https://graph.facebook.com/v22.0/me/messenger_profile?access_token={$page_token}";

    $jsonData = [
        "get_started" => [
            "payload" => "GET_STARTED_PAYLOAD"
        ]
    ];

    send_welcome_request($url, $jsonData);
}

function send_first_welcome($sender_id, $page_token) {
    global $access_token;

    $message = [
        "recipient" => ["id" => $sender_id],
        "message" => [
            "text" => "Halo Bos 👋 Selamat datang di JP Desainer Kreatif!\n\nSilakan gunakan menu di bawah untuk mulai."
        ]
    ];

    $url = "https://graph.facebook.com/v22.0/me/messages?access_token={$page_token}";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function persistent_menu($page_token) {
    global $access_token;

    $url = "https://graph.facebook.com/v22.0/me/messenger_profile?access_token={$page_token}";

    $persistent_menu_array = [
        [
            "locale" => "default",
            "composer_input_disabled" => false,
            "call_to_actions" => [
                [
                    "type" => "postback",
                    "title" => "CHAT WITH TEAM",
                    "payload" => "CHAT_WITH_ADMIN"
                ],
                [
                    "type" => "postback",
                    "title" => "DESIGN & CHATBOT ASSISTANT",
                    "payload" => "CHAT_WITH_ASSISTANT"
                ]
            ]
        ],
        [
            "locale" => "id_ID",
            "composer_input_disabled" => false,
            "call_to_actions" => [
                [
                    "type" => "postback",
                    "title" => "CHAT TIM KAMI",
                    "payload" => "CHAT_WITH_ADMIN"
                ],
                [
                    "type" => "postback",
                    "title" => "ASISTEN DESAIN & CHATBOT",
                    "payload" => "CHAT_WITH_ASSISTANT"
                ]
            ]
        ]
    ];

    $jsonData = [
        "persistent_menu" => $persistent_menu_array
    ];

    send_welcome_request($url, $jsonData);
}

// Fungsi umum untuk mengirimkan request ke Facebook API
function send_welcome_request($url, $jsonData) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($jsonData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    } else {
        echo 'Response: ' . $response;
    }

    curl_close($ch);
}

// set_get_started_button($page_token);
