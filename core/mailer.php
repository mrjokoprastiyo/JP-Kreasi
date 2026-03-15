<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../core/settings-loader.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{

/* ===================================
   CORE MAIL FUNCTION
=================================== */
public static function send($to, $subject, $body): bool
{

$mail = new PHPMailer(true);

try {

    $host       = setting('email-smtp-host');
    $port       = setting('email-smtp-port',587);
    $username   = setting('email-smtp-username');
    $password   = setting('email-smtp-password');
    $encryption = setting('email-smtp-encryption','tls');

    $fromName   = setting('email-from-name','JP System');
    $fromEmail  = setting('email-from-address','noreply@domain.com');

    $mail->isSMTP();
    $mail->Host       = $host;
    $mail->SMTPAuth   = true;

    $mail->Username   = $username;
    $mail->Password   = $password;

    $mail->SMTPSecure = $encryption === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;

    $mail->Port = $port;

    $mail->setFrom($fromEmail,$fromName);
    $mail->addReplyTo($fromEmail,$fromName);

    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    $mail->addAddress($to);

    $mail->isHTML(true);

    $mail->Subject = $subject;
    $mail->Body    = self::layout($body);

    $mail->send();

    return true;

}catch(Exception $e){

error_log("MAIL ERROR: ".$mail->ErrorInfo);
return false;

}

}

/* ===================================
   GLOBAL EMAIL LAYOUT
=================================== */

private static function layout($content)
{

$siteName = setting('site-name','JP System');
$logo     = setting('site-logo','');

/* DETECT SITE URL */

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$domain = $_SERVER['HTTP_HOST'] ?? 'localhost';

// $siteUrl = $scheme.'://'.$domain;
$siteUrl = 'https://gress.altervista.org';

/* LOGO URL */

$logoUrl = $logo ? $siteUrl.$logo : '';

return "

<div style='background:#f4f6f8;padding:40px;font-family:Arial'>

<div style='max-width:600px;margin:auto;background:#fff;
border-radius:10px;padding:30px'>

".($logoUrl ? "<img src='$logoUrl' style='height:40px'><br><br>" : "")."

$content

<hr style='margin-top:40px'>

<p style='font-size:12px;color:#777'>
© ".date('Y')." $siteName
</p>

</div>

</div>

";

}

/* ===================================
   OTP EMAIL
=================================== */

public static function sendOTP($email,$otp)
{

$body = "

<h2>Kode OTP Anda</h2>

<p>Gunakan kode berikut untuk verifikasi akun Anda:</p>

<div style='font-size:32px;
letter-spacing:6px;
font-weight:bold;
margin:20px 0'>

$otp

</div>

<p>Kode ini akan kadaluarsa dalam beberapa menit.</p>

";

return self::send($email,"Kode OTP",$body);

}

/* ===================================
   RESET PASSWORD EMAIL
=================================== */

public static function sendResetPassword($email,$link)
{

$body = "

<h2>Reset Password</h2>

<p>Klik tombol di bawah untuk membuat password baru.</p>

<a href='$link'
style='background:#000;
color:#fff;
padding:12px 20px;
border-radius:6px;
text-decoration:none;
display:inline-block;
margin-top:15px'>

Reset Password

</a>

<p style='margin-top:20px;font-size:13px'>
Link berlaku selama 30 menit.
</p>

";

return self::send($email,"Reset Password",$body);

}

/* ===================================
   WELCOME EMAIL
=================================== */

public static function sendWelcome($email,$name)
{

$body = "

<h2>Selamat Datang $name 👋</h2>

<p>Akun Anda berhasil dibuat.</p>

<p>Silakan login dan mulai menggunakan platform kami.</p>

";

return self::send($email,"Selamat Datang",$body);

}

/* ===================================
   INVOICE EMAIL
=================================== */

public static function sendInvoice($email,$invoiceNo,$amount,$link)
{

$body = "

<h2>Invoice #$invoiceNo</h2>

<p>Total pembayaran:</p>

<h3>Rp ".number_format($amount)."</h3>

<a href='$link'
style='background:#000;
color:#fff;
padding:12px 20px;
border-radius:6px;
text-decoration:none'>

Lihat Invoice

</a>

";

return self::send($email,"Invoice $invoiceNo",$body);

}

}