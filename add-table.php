<?php
echo "<pre>";

require 'core/db.php';

try {

    // ===============================
    // chat_logs
    // ===============================
    DB::exec("
        CREATE TABLE IF NOT EXISTS chat_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            visitor_id VARCHAR(64) NOT NULL,
            role ENUM('user','assistant','system') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(visitor_id)
        ) ENGINE=InnoDB
    ");

    // ===============================
    // chat_sessions
    // ===============================
    DB::exec("
        CREATE TABLE IF NOT EXISTS chat_sessions (
            visitor_id VARCHAR(64) PRIMARY KEY,
            last_message TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");

    echo "✅ chat_logs & chat_sessions berhasil dibuat\n";

} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage();
}