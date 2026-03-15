<?php
require_once __DIR__ . '/core/db.php'; // sesuaikan path

$table = 'clients';

$columns = [
    'notif_badge' => "ALTER TABLE `$table` ADD COLUMN `notif_badge` TINYINT(1) DEFAULT 1 AFTER `widget_background`",
    'notif_popup' => "ALTER TABLE `$table` ADD COLUMN `notif_popup` TINYINT(1) DEFAULT 0 AFTER `notif_badge`",
    'notif_sound_enabled' => "ALTER TABLE `$table` ADD COLUMN `notif_sound_enabled` TINYINT(1) DEFAULT 0 AFTER `notif_popup`",
];

foreach ($columns as $col => $sql) {
    $exists = DB::fetchColumn(
        "SELECT COUNT(*) 
         FROM INFORMATION_SCHEMA.COLUMNS 
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?",
        [$table, $col]
    );

    if (!$exists) {
        DB::execute($sql);
        echo "✅ Kolom <b>$col</b> berhasil ditambahkan<br>";
    } else {
        echo "ℹ️ Kolom <b>$col</b> sudah ada, dilewati<br>";
    }
}

echo "<hr>Selesai.";