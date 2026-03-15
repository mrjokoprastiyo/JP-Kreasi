<?php
/**
 * MIGRATION:
 * - Tambah enum 'design' pada products.service
 * - Ubah clients.service dari ENUM ke VARCHAR
 */

require_once __DIR__ . '/core/db.php';

try {
    
    // ===============================
    // UBAH clients.service → VARCHAR
    // ===============================

    DB::execute("
ALTER TABLE payments ADD COLUMN amount_usd DECIMAL(10,2) NOT NULL AFTER amount
    ");
    echo "✔ clients.ai_config_id berhasil diubah<br>";

    echo "<br>🎉 Migration berhasil";

} catch (PDOException $e) {
    echo "❌ Migration gagal: " . $e->getMessage();
}