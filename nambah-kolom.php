<?php
require_once __DIR__ . '/core/db.php';

try {

    DB::execute("
ALTER TABLE users 
MODIFY status ENUM('pending','active','blocked') DEFAULT 'pending';
    ");
            
    DB::execute("
        ALTER TABLE users 
        ADD COLUMN otp_attempt INT DEFAULT 0
    ");

    echo "✔ Kolom subtotal, tax, fee berhasil ditambahkan<br>";
    echo "🎉 Migration berhasil";

} catch (PDOException $e) {
    echo "❌ Migration gagal: " . $e->getMessage();
}