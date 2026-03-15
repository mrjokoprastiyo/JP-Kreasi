<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Pastikan path vendor benar sesuai struktur folder Anda
require_once __DIR__ . '/../../../vendor/autoload.php';

function sendEmail(array $p): array
{
    // 1. Ambil Config dari Global Payload
    $cred = $p['config']['target']['credentials'] ?? [];
    $dest = $p['config']['target']['destination'] ?? [];

    // 2. Validasi Kredensial SMTP
    $required = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass', 'from_email'];
    foreach ($required as $r) {
        if (empty($cred[$r])) {
            throw new Exception("Email credential missing: $r");
        }
    }

    if (empty($dest['to'])) {
        throw new Exception("Email destination 'to' is missing");
    }

    if (empty($p['file'])) {
        throw new Exception("Email attachment (binary data) is empty");
    }

    // 3. Konfigurasi PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Server Settings
        $mail->isSMTP();
        $mail->Host       = $cred['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $cred['smtp_user'];
        $mail->Password   = $cred['smtp_pass'];
        $mail->Port       = $cred['smtp_port'];
        
        // Handle SMTPSecure (ssl atau tls)
        $secure = $cred['smtp_secure'] ?? 'tls';
        $mail->SMTPSecure = ($secure === 'ssl') ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;

        // Recipients
        $mail->setFrom($cred['from_email'], $cred['from_name'] ?? 'Sheet Automation');
        $mail->addAddress($dest['to']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $dest['subject'] ?? ('Otomasi File: ' . $p['filename']);
        $mail->Body    = $dest['body'] ?? "Halo,<br><br>Berikut terlampir file <b>{$p['filename']}</b> yang dikirimkan secara otomatis dari Google Sheets.";
        $mail->AltBody = strip_tags($mail->Body);

        // 4. Attachment dari Binary (Tanpa Simpan File)
        $mail->addStringAttachment(
            $p['file'],      // Data binary dari Google
            $p['filename'],  // Nama file asli
            'base64',        // Encoding
            $p['mime_type']  // Mime type (application/pdf, dsb)
        );

        $mail->send();

        return [
            'sent' => true,
            'to'   => $dest['to'],
            'file' => $p['filename']
        ];

    } catch (Exception $e) {
        throw new Exception("PHPMailer Error: " . $mail->ErrorInfo);
    }
}
