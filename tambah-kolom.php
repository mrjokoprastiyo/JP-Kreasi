<?php
require_once __DIR__ . '/core/db.php';

try {

    DB::execute("
        ALTER TABLE payments 
        ADD COLUMN subtotal INT NOT NULL DEFAULT 0,
        ADD COLUMN tax INT NOT NULL DEFAULT 0,
        ADD COLUMN fee INT NOT NULL DEFAULT 0
    ");

    echo "✔ Kolom subtotal, tax, fee berhasil ditambahkan<br>";
    echo "🎉 Migration berhasil";

} catch (PDOException $e) {
    echo "❌ Migration gagal: " . $e->getMessage();
}