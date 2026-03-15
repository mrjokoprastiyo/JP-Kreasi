<?php
echo "<pre>";

require 'core/db.php';

try {

    // ===============================
    // chat_logs
    // ===============================
    DB::exec("
        CREATE TABLE IF NOT EXISTS login_attempts (
            identity VARCHAR(100) PRIMARY KEY,
            attempts INT DEFAULT 0,
            last_attempt DATETIME
        ) ENGINE=InnoDB");      
    echo "✅ chat_logs & chat_sessions berhasil dibuat\n";

} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage();
}