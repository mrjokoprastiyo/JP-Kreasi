<?php
require_once __DIR__ . '/core/db.php'; // sesuaikan path

$defaults = [
    'chatbot-web-notif_badge'          => '1',
    'chatbot-web-notif_popup'          => '0',
    'chatbot-web-notif_sound_enabled'  => '0',
];

foreach ($defaults as $key => $value) {

    // cek apakah setting sudah ada
    $exists = DB::fetchColumn(
        "SELECT COUNT(*) FROM settings WHERE setting_key = ?",
        [$key]
    );

    if (!$exists) {
        DB::execute(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)",
            [$key, $value]
        );
        echo "✅ Setting <b>$key</b> ditambahkan<br>";
    } else {
        echo "ℹ️ Setting <b>$key</b> sudah ada, dilewati<br>";
    }
}

echo "<hr>Selesai.";