<?php
require_once __DIR__.'/core/auth.php';
require_once __DIR__.'/core/db.php';
require_once __DIR__.'/core/mailer.php';

session_start();

$msg  = "";
$type = "info";

if($_SERVER['REQUEST_METHOD']==='POST'){

    if(!Auth::verifyCSRF($_POST['csrf'] ?? '')){
        die("CSRF validation failed");
    }

    $email = trim($_POST['email'] ?? '');
    $ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    /* ===============================
       VALIDASI EMAIL
    =============================== */

    if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
        $msg  = "Format email tidak valid.";
        $type = "error";
    }

    else{

        /* ===============================
           CLEANUP RATE LIMIT LAMA
        =============================== */

        DB::exec(
        "DELETE FROM reset_rate_limit
         WHERE created_at < (NOW() - INTERVAL 1 DAY)"
        );

        /* ===============================
           COOLDOWN 60 DETIK
        =============================== */

        $cooldown = DB::fetchColumn(
        "SELECT COUNT(*)
         FROM reset_rate_limit
         WHERE email=?
         AND created_at > (NOW() - INTERVAL 60 SECOND)",
        [$email]
        );

        if($cooldown > 0){

            $msg  = "Tunggu 60 detik sebelum request lagi.";
            $type = "warning";

        }

        else{

            /* ===============================
               RATE LIMIT 5 / 15 MENIT
            =============================== */

            $count = DB::fetchColumn(
            "SELECT COUNT(*)
             FROM reset_rate_limit
             WHERE (email=? OR ip_address=?)
             AND created_at > (NOW() - INTERVAL 15 MINUTE)",
            [$email,$ip]
            );

            if($count >= 5){

                $msg  = "Terlalu banyak permintaan. Coba lagi nanti.";
                $type = "error";

            }

            else{

                /* ===============================
                   CEK USER
                =============================== */

                $user = DB::fetch(
                "SELECT id,email
                 FROM users
                 WHERE email=?",
                [$email]
                );

                if($user){

                    $token = bin2hex(random_bytes(32));
                    $hash  = password_hash($token,PASSWORD_DEFAULT);

                    DB::exec(
                    "INSERT INTO password_resets
                    (user_id,token_hash,expires_at,ip_address)
                    VALUES(
                        ?,?,
                        DATE_ADD(NOW(),INTERVAL 30 MINUTE),
                        ?
                    )",
                    [$user['id'],$hash,$ip]
                    );

                    $link = "https://".$_SERVER['HTTP_HOST'].
                            "/reset-password.php?token=".$token;

                    Mailer::sendResetPassword($email,$link);

                    DB::exec(
                    "INSERT INTO security_logs(event,email,ip_address)
                    VALUES('password_reset_requested',?,?)",
                    [$email,$ip]
                    );
                }

                /* ===============================
                   SIMPAN RATE LIMIT
                =============================== */

                DB::exec(
                "INSERT INTO reset_rate_limit(email,ip_address)
                 VALUES(?,?)",
                [$email,$ip]
                );

                $msg  = "Jika email terdaftar, link reset telah dikirim.";
                $type = "success";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>

<meta charset="UTF-8">
<title>Lupa Password</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<style>
*,
*::before,
*::after{
box-sizing:border-box;
}

body{
margin:0;
min-height:100vh;
display:flex;
align-items:center;
justify-content:center;
background:#f3f4f6;
font-family:system-ui,-apple-system,Segoe UI,sans-serif;
}

.card{
width:100%;
max-width:420px;
background:#fff;
padding:2.5rem;
border-radius:14px;
box-shadow:0 15px 40px rgba(0,0,0,.1);
}

h1{
margin-bottom:8px;
}

p.desc{
color:#666;
font-size:.9rem;
margin-bottom:24px;
}

input{
width:100%;
padding:.85rem;
margin-bottom:14px;
border-radius:8px;
border:1px solid #ddd;
}

button{
width:100%;
padding:.9rem;
border:none;
border-radius:8px;
background:#000;
color:#fff;
font-weight:600;
cursor:pointer;
}

button:hover{
opacity:.9;
}

/* ALERT MESSAGE */

.alert{
padding:12px;
border-radius:8px;
margin-bottom:18px;
font-size:.9rem;
line-height:1.4;
}

.alert-success{
background:#e9f7ef;
color:#0f5132;
border:1px solid #badbcc;
}

.alert-error{
background:#fdecea;
color:#842029;
border:1px solid #f5c2c7;
}

.alert-warning{
background:#fff3cd;
color:#664d03;
border:1px solid #ffecb5;
}

.alert-info{
background:#e7f1ff;
color:#084298;
border:1px solid #b6d4fe;
}

.footer{
margin-top:18px;
font-size:.85rem;
text-align:center;
}

.footer a{
color:#000;
text-decoration:none;
font-weight:600;
}

</style>

</head>

<body>

<div class="card">

<h1>Lupa Password</h1>

<p class="desc">
Masukkan email akun Anda untuk menerima link reset password.
</p>

<?php if($msg): ?>

<div class="alert alert-<?=$type?>">
<?=htmlspecialchars($msg)?>
</div>

<?php endif; ?>

<form method="post" autocomplete="off">

<input type="hidden" name="csrf" value="<?=Auth::csrf()?>">

<input
type="email"
name="email"
placeholder="Email"
required
>

<button type="submit">
Kirim Link Reset
</button>

</form>

<div class="footer">
<a href="/login.php">Kembali ke Login</a>
</div>

</div>

</body>
</html>