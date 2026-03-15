<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Pastikan path autoload benar sesuai struktur folder Anda
require_once __DIR__ . '/../../vendor/autoload.php';

/**
 * Handler Email - Client/System Mode
 * Menerima $p (payload) berisi: sender, file binary, filename, mime_type, destination
 */
function sendEmail(array $p) 
{
    $sender = $p['sender'] ?? [];
    $dest   = $p['destination'] ?? [];
    
    // Validasi Kredensial SMTP
    if (empty($sender['smtp_host']) || empty($sender['smtp_user'])) {
        throw new Exception('Email SMTP credentials missing in system provider');
    }

    // Validasi Tujuan
    $toEmail = $dest['email'] ?? '';
    if (!$toEmail) {
        throw new Exception('Destination email address missing');
    }

    $mail = new PHPMailer(true);

    try {
        // --- Server Settings ---
        $mail->isSMTP();
        $mail->Host       = $sender['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $sender['smtp_user'];
        $mail->Password   = $sender['smtp_pass'];
        $mail->SMTPSecure = $sender['encryption'] ?? PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $sender['smtp_port'] ?? 587;
        $mail->CharSet    = 'UTF-8';

        // --- Recipients ---
        $fromEmail = $sender['from_email'] ?? $sender['smtp_user'];
        $fromName  = $sender['from_name'] ?? 'Automation System';
        $mail->setFrom($fromEmail, $fromName);
        $mail->addAddress($toEmail);

        // --- Attachment ---
        // Menggunakan addStringAttachment karena data sudah berbentuk binary (string)
        $mail->addStringAttachment(
            $p['file'], 
            $p['filename'], 
            PHPMailer::ENCODING_BASE64, 
            $p['mime_type']
        );

        // --- Content ---
        $mail->isHTML(true);
        $mail->Subject = $sender['subject'] ?? 'Laporan Otomatis: ' . $p['filename'];
        $mail->Body    = $sender['body_html'] ?? "Halo,<br><br>Terlampir adalah file ekspor terbaru Anda: <b>{$p['filename']}</b>.<br><br>Salam,<br>Otomasi Sistem";
        $mail->AltBody = strip_tags($mail->Body);

        $mail->send();

        return [
            'sent' => true,
            'to'   => $toEmail,
            'file' => $p['filename']
        ];

    } catch (Exception $e) {
        throw new Exception("PHPMailer Error: " . ($mail->ErrorInfo ?: $e->getMessage()));
    }
}
