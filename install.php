<?php      
session_start();      
      
/* ===============================      
   PREVENT REINSTALL      
================================ */      
if (file_exists(__DIR__ . '/config.php')) {      
    exit('Aplikasi sudah terinstall.');      
}      
      
$step  = $_SESSION['install_step'] ?? 1;      
$error = "";      

/* ===============================      
   NAVIGATION: BACK TO STEP 1      
================================ */      
if (isset($_GET['back']) && $_SESSION['install_step'] == 2) {
    $_SESSION['install_step'] = 1;
    header("Location: install.php");
    exit;
}
      
/* ===============================      
   RESET      
================================ */      
if (isset($_GET['reset'])) {      
    session_destroy();      
    header("Location: install.php");      
    exit;      
}      
      
/* ===============================      
   HELPER      
================================ */      
function ensureTable(PDO $pdo, string $sql)      
{      
    $pdo->exec($sql);      
}      
      
/* ===============================      
   STEP 1 : DB SETUP      
================================ */      
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['host'])) {      
      
    try {      
        $host = trim($_POST['host']);      
        $db   = trim($_POST['dbname']);      
        $user = trim($_POST['user']);      
        $pass = $_POST['password'];      
      
        if (!$host || !$db || !$user) {      
            throw new Exception("Data database tidak lengkap.");      
        }      
      
        $pdo = new PDO(      
            "mysql:host={$host};charset=utf8mb4",      
            $user,      
            $pass,      
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]      
        );      
      
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db`      
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");      
        $pdo->exec("USE `$db`");      
      
        $_SESSION['db'] = compact('host','db','user','pass');      
        $_SESSION['install_step'] = 2;      
      
        header("Location: install.php");      
        exit;      
      
    } catch (Throwable $e) {      
        $error = "Koneksi Gagal: " . $e->getMessage();      
        $step = 1;      
    }      
}      
      
/* ===============================      
   STEP 2 : INSTALL SYSTEM      
================================ */      
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_email'])) {      
      
    try {      
        if (!isset($_SESSION['db'])) {
            throw new Exception("Sesi database hilang. Silakan kembali ke Step 1.");
        }

        $db = $_SESSION['db'];      
      
        $pdo = new PDO(      
            "mysql:host={$db['host']};dbname={$db['db']};charset=utf8mb4",      
            $db['user'],      
            $db['pass'],      
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]      
        );      
      
        /* ================= USERS ================= */      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS users (      
            id INT AUTO_INCREMENT PRIMARY KEY,      
            username VARCHAR(50) UNIQUE,      
            password VARCHAR(255),      
            email VARCHAR(150) UNIQUE,
            fullname VARCHAR(100),      
            role ENUM('admin','user') DEFAULT 'user',
            otp_code VARCHAR(6),
            otp_expire DATETIME,
            otp_last_sent DATETIME,
            otp_attempt INT DEFAULT 0,
            email_verified TINYINT(1) DEFAULT 0,
            status ENUM('pending','active','blocked') DEFAULT 'pending',      
            last_login DATETIME NULL,      
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP      
        ) ENGINE=InnoDB");      
      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45)
        ) ENGINE=InnoDB");      

        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS reset_rate_limit (
            id INT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190),
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");      


        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS login_attempts (
            identity VARCHAR(100) PRIMARY KEY,
            attempts INT DEFAULT 0,
            last_attempt DATETIME
        ) ENGINE=InnoDB");      

        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS security_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            event VARCHAR(50),
            email VARCHAR(255),
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");      

        /* ================= PRODUCTS ================= */      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS products (      
            id INT AUTO_INCREMENT PRIMARY KEY,      
            name VARCHAR(100),      
            description TEXT,
            category VARCHAR(50),      
            sub_category VARCHAR(50),      
            product_type ENUM('system','client') NULL,
            service VARCHAR(20) NULL,
            tier INT DEFAULT 1,
            duration INT,      
            price_idr DECIMAL(15,2),      
            price_usd DECIMAL(10,2),
            rate_limit INT,
            rate_period ENUM('minute','hour','day','month'),
            rate_strategy ENUM('fixed','soft'),
            status ENUM('active','inactive') DEFAULT 'active',      
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP      
        ) ENGINE=InnoDB");      
      
        ensureTable($pdo, "
        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            product_id INT,
            currency ENUM('IDR','USD'),
            amount DECIMAL(12,2),
            status ENUM('pending','paid','expired') DEFAULT 'pending',
            payment_method VARCHAR(50),
            midtrans_order_id VARCHAR(100),
            expired_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");      

        ensureTable($pdo, "   
        CREATE TABLE IF NOT EXISTS ai_configs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            post_type VARCHAR(50) NOT NULL,
            provider_slug VARCHAR(50) NOT NULL,
            provider_name VARCHAR(100),
            api_token TEXT NOT NULL,
            model VARCHAR(100) NOT NULL,
            status ENUM('active','inactive') DEFAULT 'active',
            meta JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP 
                ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_provider_model (provider_slug, model)
        ) ENGINE=InnoDB");

        /* ================= CLIENTS ================= */
        ensureTable($pdo, "
        CREATE TABLE IF NOT EXISTS clients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            product_type ENUM('system','client'),
            name VARCHAR(100),
            domain TEXT,
            service VARCHAR(50) NOT NULL,
            provider VARCHAR(50) NOT NULL,
            page_id VARCHAR(50) NULL,
            phone_number_id VARCHAR(50) NULL,
            credentials TEXT NOT NULL COMMENT 'JSON encrypted: token, id, secret, dll',
            api_key VARCHAR(64) UNIQUE,
            UNIQUE KEY unique_service_page (service, page_id),
            UNIQUE KEY unique_service_phone (service, phone_number_id),
            UNIQUE KEY unique_api_key (api_key),
            ai_config_id INT UNSIGNED NULL,
            prompt TEXT,
            bot_name VARCHAR(100),
            bot_desc VARCHAR(255),
            bot_avatar VARCHAR(255),
            bot_greeting TEXT,
            widget_icon TEXT COMMENT 'SVG string or icon URL',
            widget_background VARCHAR(255),
            notif_badge TINYINT(1) DEFAULT 1,
            notif_popup TINYINT(1) DEFAULT 0,
            notif_sound_enabled TINYINT(1) DEFAULT 0,
            notif_sound TEXT,
            webhook_secret VARCHAR(100),
            webhook_verified_at DATETIME,
            last_message_at DATETIME,
            message_count INT DEFAULT 0,
            last_notified VARCHAR(50) DEFAULT NULL,
            activated_at DATETIME NULL,
            renewed_at DATETIME NULL,
            status ENUM('pending','active','suspended','expired') DEFAULT 'pending',
            expired_at DATETIME,
            meta JSON,
            design_result JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_client_ai_config 
            FOREIGN KEY (ai_config_id) REFERENCES ai_configs(id)
            ON UPDATE CASCADE ON DELETE RESTRICT,
            INDEX(user_id),
            INDEX(product_id),
            INDEX(service),
            INDEX(provider),
            INDEX(status)
        ) ENGINE=InnoDB
        ");

        // Tabel Log Chat (Diseragamkan namanya)
        ensureTable($pdo, " 
CREATE TABLE IF NOT EXISTS chat_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visitor_id VARCHAR(64),
            role ENUM('user','assistant'),
            message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB");      

        ensureTable($pdo, "      
CREATE TABLE IF NOT EXISTS security_alerts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            api_key VARCHAR(100),
            attempted_ss_id VARCHAR(255),
            registered_ss_id VARCHAR(255),
            ip_address VARCHAR(50),
            created_at DATETIME
        ) ENGINE=InnoDB"); 

        ensureTable($pdo, "      
CREATE TABLE IF NOT EXISTS request_nonce (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nonce VARCHAR(64) NOT NULL UNIQUE,
            created_at DATETIME NOT NULL,
            INDEX (created_at)
        ) ENGINE=InnoDB"); 

        /* ================= PAYMENTS ================= */      
        ensureTable($pdo, "      
CREATE TABLE IF NOT EXISTS payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            client_id INT NOT NULL,
            product_id INT NOT NULL,
            gateway ENUM('doku','duitku','paypal') NOT NULL,
            order_id VARCHAR(100) UNIQUE NOT NULL,
            transaction_id VARCHAR(100) NULL,
            amount DECIMAL(12,2) NOT NULL,
            amount_usd DECIMAL(10,2) NOT NULL,
            currency VARCHAR(10) NOT NULL,
            subtotal INT NOT NULL DEFAULT 0,
            tax INT NOT NULL DEFAULT 0,
            fee INT NOT NULL DEFAULT 0,
            status ENUM('pending','paid','failed','expired','refunded') DEFAULT 'pending',
            raw_response JSON NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            paid_at DATETIME NULL,
            INDEX(user_id),
            INDEX(client_id),
            INDEX(order_id),
            INDEX(status)
        ) ENGINE=InnoDB");      

        ensureTable($pdo, "   
        CREATE TABLE IF NOT EXISTS message_providers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            channel ENUM('messenger','whatsapp','telegram','email') NOT NULL,
            provider_slug VARCHAR(50) NOT NULL, 
            provider_name VARCHAR(100),
            credentials JSON NOT NULL,
            status ENUM('active','inactive') DEFAULT 'active',
            webhook_url TEXT NULL,
            webhook_secret VARCHAR(100) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP 
                ON UPDATE CURRENT_TIMESTAMP,
            INDEX(channel),
            INDEX(provider_slug),
            INDEX(status)
        ) ENGINE=InnoDB");

        /* ================= SETTINGS ================= */      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS settings (      
            id INT AUTO_INCREMENT PRIMARY KEY,      
            setting_key VARCHAR(100) UNIQUE,      
            setting_value TEXT,      
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP       
                ON UPDATE CURRENT_TIMESTAMP      
        ) ENGINE=InnoDB");      
  
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS user_data_tanicerdas (
            id INT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            sender_id VARCHAR(100) NOT NULL,
            platform ENUM('facebook', 'telegram', 'whatsapp') DEFAULT 'facebook',
            status VARCHAR(50) DEFAULT 'uji_coba',
            last_message TEXT,
            message_count INT DEFAULT 0,
            conversation_count INT DEFAULT 0,
            chat_admin BOOLEAN DEFAULT FALSE,
            limited BOOLEAN DEFAULT FALSE,
            follower BOOLEAN DEFAULT FALSE,
            waiting_for_confirmation BOOLEAN DEFAULT FALSE,
            waiting_for_admin BOOLEAN DEFAULT FALSE,
            shared INT DEFAULT 0,
            unshared INT DEFAULT 0,
            ratings INT DEFAULT 0,
            unratings INT DEFAULT 0,
            post_id VARCHAR(100),
            post_message_text TEXT,
            post_reaction_count INT DEFAULT 0,
            comment_id VARCHAR(100),
            parent_comment_message TEXT,
            ai_comment_reply TEXT,
            comment_reaction_count INT DEFAULT 0,
            comments_total INT DEFAULT 0,
            uncomments INT DEFAULT 0,
            reactions_total INT DEFAULT 0,
            unreactions INT DEFAULT 0,
            last_interaction DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY idx_client_user_platform (client_id, sender_id, platform)
        ) ENGINE=InnoDB");

        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS chat_history (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            client_id INT NOT NULL,
            sender_id VARCHAR(100) NOT NULL,
            role ENUM('user','assistant') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_client_sender (client_id, sender_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB");      

        /* ================= EMAIL SETTINGS ================= */      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS email_settings (      
            id INT AUTO_INCREMENT PRIMARY KEY,      
            client_id INT NOT NULL,      
            from_name VARCHAR(100) DEFAULT 'Bot Notification',      
            from_email VARCHAR(150) DEFAULT 'noreply@domain.com',      
            reply_to VARCHAR(150),      
            admin_email VARCHAR(150) NOT NULL,      
            client_email VARCHAR(150),      
            enable_admin_notify TINYINT(1) DEFAULT 1,      
            enable_client_notify TINYINT(1) DEFAULT 1,     
            daily_limit INT DEFAULT 100,      
            sent_today INT DEFAULT 0,      
            status ENUM('active','paused') DEFAULT 'active',      
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,      
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP      
        ) ENGINE=InnoDB");      
      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS email_triggers (      
            id INT AUTO_INCREMENT PRIMARY KEY,      
            client_id INT NOT NULL,      
            trigger_key VARCHAR(50),      
            description VARCHAR(255),      
            enable_send TINYINT(1) DEFAULT 1,      
            send_to ENUM('admin','client','both') DEFAULT 'admin',      
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP      
        ) ENGINE=InnoDB");      
      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS email_templates (      
            id INT AUTO_INCREMENT PRIMARY KEY,      
            client_id INT NOT NULL,      
            template_key VARCHAR(50),      
            subject VARCHAR(255),      
            body_html TEXT,      
            body_text TEXT,      
            is_active TINYINT(1) DEFAULT 1,      
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,      
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP      
        ) ENGINE=InnoDB");      
      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS email_logs (      
            id BIGINT AUTO_INCREMENT PRIMARY KEY,      
            client_id INT NOT NULL,      
            to_email VARCHAR(150),      
            subject VARCHAR(255),      
            trigger_key VARCHAR(50),      
            status ENUM('sent','failed','queued') DEFAULT 'queued',      
            error_message TEXT,      
            sent_at DATETIME,      
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP      
        ) ENGINE=InnoDB");      
      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS email_queue (      
            id BIGINT AUTO_INCREMENT PRIMARY KEY,      
            client_id INT NOT NULL,      
            trigger_key VARCHAR(50),      
            payload JSON,       
            status ENUM('pending','processing','sent','failed') DEFAULT 'pending',      
            retry_count INT DEFAULT 0,       
            scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP,      
            processed_at DATETIME      
        ) ENGINE=InnoDB");      
      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS email_verifications (      
            id BIGINT AUTO_INCREMENT PRIMARY KEY,      
            user_id BIGINT NOT NULL,      
            email VARCHAR(150),      
            token VARCHAR(100),        
            expires_at DATETIME,      
            verified_at DATETIME,       
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,      
            INDEX(email),      
            INDEX(token)      
        ) ENGINE=InnoDB");      
      
        /* ================= NOTIFICATION CHANNELS ================= */      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS notification_channels (      
            id INT AUTO_INCREMENT PRIMARY KEY,      
            client_id INT NOT NULL,      
            channel ENUM('email','whatsapp','telegram','messenger'),      
            provider VARCHAR(50),      
            credentials JSON,      
            is_active TINYINT(1) DEFAULT 0,       
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,      
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,        
            INDEX(client_id),      
            INDEX(channel)      
        ) ENGINE=InnoDB");      
      
        ensureTable($pdo, "      
        CREATE TABLE IF NOT EXISTS message_templates (      
            id INT AUTO_INCREMENT PRIMARY KEY,      
            client_id INT NOT NULL,        
            template_key VARCHAR(50),      
            channel ENUM('email','whatsapp','telegram','messenger'),      
            subject VARCHAR(255),      
            body TEXT,      
            is_active TINYINT(1) DEFAULT 1,         
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,      
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,         
            UNIQUE KEY(client_id,template_key,channel)      
        ) ENGINE=InnoDB");      
      
        /* ================= ADMIN ================= */      
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'");      
        if (!$stmt->fetchColumn()) {      
            $pdo->prepare("      
                INSERT INTO users (username,password,email,fullname,role)      
                VALUES (?,?,?,?, 'admin')      
            ")->execute([      
                $_POST['admin_username'],      
                password_hash($_POST['admin_password'], PASSWORD_DEFAULT),      
                $_POST['admin_email'],      
                $_POST['admin_fullname']      
            ]);      
        }      
      
        /* ===============================
           DEFAULT SYSTEM SETTINGS
================================ */
        $defaults = [

            // SITE
            'site-name' => $_POST['site_name'] ?? 'JP System',
            'site-tagline'  => $_POST['site_tagline'] ?? 'Automation & AI Platform',
            // EMAIL OTP
            'email-otp-enabled' => '1',
            'email-otp-expired_minutes' => '10',

            // SMTP
            'email-smtp-host' => '',
            'email-smtp-port' => '587',
            'email-smtp-username' => '',
            'email-smtp-password' => '',
            'email-smtp-encryption' => 'tls',
            'email-from-name' => 'JP System',
            'email-from-address' => 'noreply@domain.com',

            // PAYMENT - PAYPAL
            'payment-paypal-enabled' => '0',
            'payment-paypal-client_id' => '',
            'payment-paypal-secret' => '',
            'payment-paypal-mode' => 'sandbox',

            // PAYMENT - QRIS
            'payment-qris-enabled' => '0',
            'payment-qris-merchant_id' => '',
            'payment-qris-api_key' => '',
            'payment-qris-callback_url' => '',

            // PAYMENT - DANA
            'payment-dana-enabled' => '0',
            'payment-dana-merchant_id' => '',
            'payment-dana-api_key' => '',
            'payment-dana-callback_url' => '',

    'chatbot-web-prompt' =>
        'Kamu adalah asisten AI yang ramah dan membantu.',

    'chatbot-web-bot_name' =>
        'JP Assistant',

    'chatbot-web-bot_desc' =>
        'Asisten virtual untuk membantu pengunjung website.',

    // ✅ avatar = IMAGE URL
    'chatbot-web-bot_avatar' =>
        '/assets/avatar/bot.png',

    'chatbot-web-bot_greeting' =>
        'Halo 👋 Ada yang bisa saya bantu?',

    // ✅ icon = SVG
    'chatbot-web-widget_icon' =>
        '/assets/icon/chat.png',

    'chatbot-web-widget_bg' =>
        '#000',

    // ✅ sound = MP3 URL
    'chatbot-web-notif_sound' =>
        '/assets/sound/Message-tone.mp3',

// badge merah di icon
'chatbot-web-notif_badge' => '1',

// popup bubble kecil (tooltip)
'chatbot-web-notif_popup' => '0',

// sound notifikasi
'chatbot-web-notif_sound_enabled' => '0',
        ];

        $stmt = $pdo->prepare("
            INSERT IGNORE INTO settings (setting_key, setting_value)
            VALUES (:key, :value)
        ");

        foreach ($defaults as $key => $value) {
            $stmt->execute([
                ':key'   => $key,
                ':value' => $value
            ]);
        }

        $pdo->exec("      
            INSERT IGNORE INTO ai_configs 
(post_type, provider_slug, provider_name, api_token, model, status) VALUES
            ('ai_provider','openai','OpenAI','', 'gpt-4o-mini','active'),
            ('ai_provider','gemini','Google Gemini','', 'gemini-pro','active'),
            ('ai_provider','cohere','Cohere','', 'command-a-03-2025','active'),
            ('ai_provider','groq','Groq','', 'llama3-70b-8192','active');
        ");

/* ======================================================
   SEED PRODUCTS (FINAL & LENGKAP)
   Ditambahkan kolom price_usd & format rapi
====================================================== */

$pdo->exec("
INSERT IGNORE INTO products 
(
    name, 
    description, 
    category, 
    sub_category, 
    service, 
    product_type, 
    tier, 
    duration, 
    price_idr, 
    price_usd, 
    status
) 
VALUES

-- ================= CHATBOT WEBSITE =================
(
    'Chatbot Website',
    'Chatbot AI untuk website dengan widget siap pakai. Cocok untuk CS dan lead capture.',
    'automation', 'chatbot', 'website', NULL, 1, 30, 
    150000.00, 9.99, 'active'
),

-- ================= SYSTEM AUTOMATION =================
(
    'System Automation Telegram',
    'Notifikasi Google Sheet ke Telegram. Bot & token disediakan sistem.',
    'automation', 'notification', 'telegram', 'system', 1, 30, 
    300000.00, 19.99, 'active'
),
(
    'System Automation WhatsApp',
    'Notifikasi Google Sheet ke WhatsApp. Sistem menanggung API.',
    'automation', 'notification', 'whatsapp', 'system', 1, 30, 
    600000.00, 39.99, 'active'
),
(
    'System Automation Messenger',
    'Notifikasi Messenger otomatis dari Google Sheet.',
    'automation', 'notification', 'messenger', 'system', 1, 30, 
    450000.00, 29.99, 'active'
),
(
    'System Automation Email',
    'Notifikasi email otomatis menggunakan sistem.',
    'automation', 'notification', 'email', 'system', 1, 30, 
    250000.00, 15.99, 'active'
),

-- ================= CLIENT AUTOMATION =================
(
    'Client Automation Telegram',
    'Notifikasi Google Sheet ke Telegram menggunakan bot milik client.',
    'automation', 'notification', 'telegram', 'client', 1, 30, 
    150000.00, 9.99, 'active'
),
(
    'Client Automation WhatsApp',
    'Notifikasi WhatsApp dengan API milik client.',
    'automation', 'notification', 'whatsapp', 'client', 1, 30, 
    250000.00, 15.99, 'active'
),
(
    'Client Automation Messenger',
    'Notifikasi Messenger menggunakan access token client.',
    'automation', 'notification', 'messenger', 'client', 1, 30, 
    200000.00, 12.99, 'active'
),
(
    'Client Automation Email',
    'Notifikasi email menggunakan SMTP milik client.',
    'automation', 'notification', 'email', 'client', 1, 30, 
    100000.00, 6.99, 'active'
),

-- ================= DESAIN =================
(
    'Desain Logo Profesional',
    'Jasa desain logo profesional termasuk revisi dan file siap pakai.',
    'desain', 'logo', NULL, NULL, NULL, NULL, 
    750000.00, 49.99, 'active'
);
");

        /* ================= CONFIG ================= */      
        $configContent = "<?php\n"
                       . "define('DB_HOST','{$db['host']}');\n"
                       . "define('DB_NAME','{$db['db']}');\n"
                       . "define('DB_USER','{$db['user']}');\n"
                       . "define('DB_PASS','{$db['pass']}');\n";

        file_put_contents(__DIR__.'/config.php', $configContent);      
      
        session_destroy();      
        header("Location: login.php?installed=1");      
        exit;      
      
    } catch (Throwable $e) {      
        $error = "Terjadi Kesalahan: " . $e->getMessage();      
        $step = 2;      
    }      
}      
?>
<!DOCTYPE html>
<html lang="en">      
<head>      
  <meta charset="UTF-8">      
  <title>Setup Wizard - Step <?= $step ?></title>      
  <meta name="viewport" content="width=device-width, initial-scale=1">      
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">      
  <style>      
    body { font-family: 'Segoe UI', sans-serif; background: #f0f2f5; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }      
    .form-box { background: #fff; width: 100%; max-width: 420px; padding: 2.5em; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); }      
    h2 { margin-top: 0; color: #1a1a1a; font-size: 1.5em; display: flex; align-items: center; gap: 10px; }      
    p { color: #666; font-size: 0.9em; margin-bottom: 2em; }      
    input { width: 100%; padding: 0.8em; margin-bottom: 1.2em; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; font-size: 14px; }      
    button { width: 100%; padding: 0.9em; background: #000; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.3s; margin-top: 10px; }      
    button:hover { background: #333; }      
    .btn-back { display: block; text-align: center; margin-top: 15px; color: #666; text-decoration: none; font-size: 0.85em; }
    .btn-back:hover { color: #000; text-decoration: underline; }
    .error { background: #ffebee; color: #c62828; padding: 0.8em; border-radius: 6px; margin-bottom: 1.5em; font-size: 0.85em; border: 1px solid #ffcdd2; word-break: break-word; }      
    .password-wrapper { position: relative; }      
    .toggle-pass { position: absolute; right: 12px; top: 13px; cursor: pointer; color: #999; }      
  </style>      
</head>      
<body>      
  <div class="form-box">      
    <?php if ($step === 1): ?>      
      <h2><i class="ri-database-2-line"></i> Database Setup</h2>      
      <p>Masukkan detail koneksi database MySQL Anda.</p>      
      <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>      
      <form method="post">      
        <input type="text" name="host" value="<?= $_SESSION['db']['host'] ?? 'localhost' ?>" placeholder="Host (ex: localhost)" required>      
        <input type="text" name="dbname" value="<?= $_SESSION['db']['db'] ?? '' ?>" placeholder="Database Name" required>      
        <input type="text" name="user" value="<?= $_SESSION['db']['user'] ?? '' ?>" placeholder="Username" required>      
        <div class="password-wrapper">      
          <input type="password" id="db_pass" name="password" value="<?= $_SESSION['db']['pass'] ?? '' ?>" placeholder="Password">      
          <i class="ri-eye-line toggle-pass" onclick="toggle('db_pass', this)"></i>      
        </div>      
        <button type="submit">Hubungkan Database</button>      
      </form>      
    <?php else: ?>      
      <h2><i class="ri-user-settings-line"></i> Admin & Site Info</h2>      
      <p>Konfigurasi identitas web dan akun administrator.</p>      
      <?php if ($error): ?><div class="error"><?= $error ?></div><?php endif; ?>      
      <form method="post">      
        <input type="text" name="site_name" placeholder="Nama Aplikasi Chat" required>      
        <input type="email" name="admin_email" placeholder="Email Admin" required>      
        <input type="text" name="admin_fullname" placeholder="Nama Lengkap" required>      
        <input type="text" name="admin_username" placeholder="Username Admin" required>      
        <div class="password-wrapper">      
          <input type="password" id="adm_pass" name="admin_password" placeholder="Password Admin" required>      
          <i class="ri-eye-line toggle-pass" onclick="toggle('adm_pass', this)"></i>      
        </div>      
        <button type="submit">Selesaikan Instalasi</button>
        <a href="?back=1" class="btn-back"><i class="ri-arrow-left-line"></i> Kembali ke Database Setup</a>
      </form>      
    <?php endif; ?>
  </div>      

  <script>      
    function toggle(id, el) {      
      const x = document.getElementById(id);      
      if (x.type === "password") {      
        x.type = "text";      
        el.className = "ri-eye-off-line toggle-pass";      
      } else {      
        x.type = "password";      
        el.className = "ri-eye-line toggle-pass";      
      }      
    }      
  </script>      
</body>      
</html>
