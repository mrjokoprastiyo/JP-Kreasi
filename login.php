<?php
require_once __DIR__.'/core/auth.php';

if (Auth::check()) {
    if (Auth::isAdmin()) {
        header("Location: /admin/index.php");
    } else {
        header("Location: /user/index.php");
    }
    exit;
}

$error="";

if($_SERVER['REQUEST_METHOD']==='POST'){

    if(!Auth::verifyCSRF($_POST['csrf'] ?? '')){
        die("CSRF validation failed");
    }

    $login=Auth::login(
        $_POST['identity'] ?? '',
        $_POST['password'] ?? ''
    );

    if($login['status']){

        if($login['user']['role']==='admin'){
            header("Location: /admin/index.php");
        }else{
            header("Location: /user/index.php");
        }

        exit;
    }

    $error=$login['msg'];

    // if(!$user['email_verified']){
        // return ['status'=>false,'msg'=>'Email belum diverifikasi'];
    // }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">

<style>
*,
*::before,
*::after{
box-sizing:border-box;
}
body {
    margin: 0;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f3f4f6;
    font-family: system-ui, -apple-system, Segoe UI, sans-serif;
}
.card {
    width: 100%;
    max-width: 400px;
    background: #fff;
    padding: 2.5rem;
    border-radius: 14px;
    box-shadow: 0 15px 40px rgba(0,0,0,.1);
}
h1 {
    margin: 0 0 8px;
    font-size: 1.6rem;
}
p {
    margin: 0 0 24px;
    color: #666;
    font-size: .9rem;
}
input {
    width: 100%;
    padding: .85rem;
    margin-bottom: 14px;
    border-radius: 8px;
    border: 1px solid #ddd;
    font-size: 14px;
}
button {
    width: 100%;
    padding: .9rem;
    border: none;
    border-radius: 8px;
    background: #000;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
}
button:hover {
    background: #222;
}
.msg-error {
    background: #fdecea;
    border: 1px solid #f5c2c7;
    color: #842029;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 14px;
    font-size: .85rem;
}
.password-wrap {
    position: relative;
}
.toggle {
    position: absolute;
    right: 12px;
    top: 14px;
    cursor: pointer;
    color: #999;
}
.footer {
    margin-top: 18px;
    font-size: .85rem;
    text-align: center;
}
.footer a {
    color: #000;
    text-decoration: none;
    font-weight: 600;
}
</style>
</head>
<body>

<div class="card">
    <h1>Login</h1>
    <p>Masuk ke dashboard Anda.</p>

    <?php if ($error): ?>
        <div class="msg-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf" value="<?= Auth::csrf() ?>">

        <input type="text" name="identity" placeholder="Username atau Email" required>

        <div class="password-wrap">
            <input type="password" id="pass" name="password" placeholder="Password" required>
            <i class="ri-eye-line toggle" onclick="toggle('pass', this)"></i>
        </div>

        <button type="submit">Login</button>
    </form>

    <div class="footer">
        Belum punya akun? <a href="register.php">Daftar</a>
    </div>

    <div class="footer">
        <a href="forgot-password.php">Lupa password?</a>
    </div>
</div>

<script>
function toggle(id, el) {
    const x = document.getElementById(id);
    if (x.type === "password") {
        x.type = "text";
        el.className = "ri-eye-off-line toggle";
    } else {
        x.type = "password";
        el.className = "ri-eye-line toggle";
    }
}
</script>

</body>
</html>