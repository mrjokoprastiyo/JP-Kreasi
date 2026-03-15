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
MODIFY service VARCHAR(20) NULL,
MODIFY product_type ENUM('system','client') NULL
    ");

    echo "✔ products.service ENUM updated<br>";

    // ===============================
    // 2. UBAH clients.service → VARCHAR
    // ===============================

   

    echo "✔ clients.service converted to VARCHAR<br>";

    echo "<br>🎉 Migration berhasil";

} catch (PDOException $e) {
    echo "❌ Migration gagal: " . $e->getMessage();
}