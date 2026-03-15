<?php
echo "<pre>";

require 'core/db.php';

try {



        DB::exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45)
        ) ENGINE=InnoDB");      

        DB::exec("
        CREATE TABLE IF NOT EXISTS reset_rate_limit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190),
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");      

        DB::exec("
        CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event VARCHAR(50),
            email VARCHAR(255),
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");      

    echo "✅ chat_logs & chat_sessions berhasil dibuat\n";

} catch (Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage();
}