<?php
/**
 * MIGRATION:
 * - Tambah enum 'design' pada products.service
 * - Ubah clients.service dari ENUM ke VARCHAR
 */

require_once __DIR__ . '/core/db.php';

try {
    // ===============================
    // 1. UPDATE ENUM products.service
    // ===============================

    DB::execute("
        ALTER TABLE products
        MODIFY service ENUM(
            'website',
            'whatsapp',
            'telegram',
            'email',
            'messenger',
            'design'
        )
    ");

    echo "✔ products.service ENUM updated<br>";

    // ===============================
    // 2. UBAH clients.service → VARCHAR
    // ===============================

    DB::execute("
        ALTER TABLE clients
        MODIFY service VARCHAR(50) NOT NULL
    ");

    echo "✔ clients.service converted to VARCHAR<br>";

    echo "<br>🎉 Migration berhasil";

} catch (PDOException $e) {
    echo "❌ Migration gagal: " . $e->getMessage();
}