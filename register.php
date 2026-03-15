<?php
require_once __DIR__ . '/core/auth.php';

if (Auth::check()) {
    header("Location: /login.php");
    exit;
}

$error = "";
$success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if(!Auth::verifyCSRF($_POST['csrf'] ?? '')){
        die("CSRF validation failed");
    }

    $fullname = trim($_POST['fullname'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $pass1    = $_POST['password'] ?? '';
    $pass2    = $_POST['password_confirm'] ?? '';

    if (!$fullname || !$username || !$email || !$pass1 || !$pass2) {
        $error = "Semua field wajib diisi.";
    }

    elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $error = "Username hanya boleh huruf, angka, underscore (3-20 karakter).";
    }

    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Format email tidak valid.";
    }

    elseif (strlen($pass1) < 6) {
        $error = "Password minimal 6 karakter.";
    }

    elseif ($pass1 !== $pass2) {
        $error = "Konfirmasi password tidak cocok.";
    }

    else {

        $result = Auth::register([
            'fullname' => $fullname,
            'username' => $username,
            'email'    => $email,
            'password' => $pass1
        ]);

        if ($result['status']) {

            // simpan user id sementara untuk proses OTP
            $_SESSION['verify_user_id'] = $result['user_id'];

            // redirect ke halaman verifikasi
            header("Location: verify-otp.php");
            exit;

        } else {
            $error = $result['msg'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Register Akun</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">

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

h1{margin-bottom:8px}

p{
color:#666;
font-size:.9rem;
margin-bottom:24px
}

.input-group{
position:relative;
margin-bottom:12px;
}

.input-group input{
width:100%;
padding:.85rem;
padding-right:42px;
border-radius:8px;
border:1px solid #ddd;
transition:.2s;
font-size:14px;
display:block;
}

.input-group input.valid{
border-color:#16a34a;
background:#f0fdf4;
}

.input-group input.invalid{
border-color:#dc2626;
background:#fef2f2;
}

.input-icon{
position:absolute;
right:12px;
top:11px;
font-size:18px;
}

.icon-ok{color:#16a34a}
.icon-error{color:#dc2626}

.icon-loading{
width:16px;
height:16px;
border:2px solid #ddd;
border-top:2px solid #000;
border-radius:50%;
animation:spin .6s linear infinite;
}

@keyframes spin{
to{transform:rotate(360deg)}
}

.msg-small{
font-size:.8rem;
margin-top:-8px;
margin-bottom:12px;
min-height:16px;
}

.msg-ok{color:#16a34a}
.msg-error{color:#dc2626}

.password-wrap{
position:relative;
}

.toggle{
position:absolute;
right:12px;
top:12px;
cursor:pointer;
color:#999;
}

.strength-bar{
height:6px;
background:#eee;
border-radius:4px;
margin-bottom:10px;
overflow:hidden;
}

#strength-fill{
height:100%;
width:0%;
transition:.3s;
}

.rules{
font-size:.8rem;
margin-bottom:12px;
color:#888;
}

.rule-ok{
color:#16a34a;
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

.msg-error-box{
background:#fdecea;
color:#842029;
padding:10px;
border-radius:8px;
margin-bottom:14px;
}

.msg-success{
background:#e9f7ef;
color:#0f5132;
padding:10px;
border-radius:8px;
margin-bottom:14px;
}

.footer{
margin-top:18px;
font-size:.85rem;
text-align:center;
}

.footer a{
font-weight:600;
color:#000;
text-decoration:none;
}

</style>
</head>
<body>

<div class="card">

<h1>Buat Akun</h1>
<p>Daftar untuk mulai menggunakan layanan.</p>

<?php if ($error): ?>
<div class="msg-error-box"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="msg-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="post" autocomplete="off">

<input type="hidden" name="csrf" value="<?= Auth::csrf() ?>">

<div class="input-group">
<input name="fullname" placeholder="Nama Lengkap" required>
</div>

<div class="input-group">
<input name="username" id="username" placeholder="Username" required>
<span id="user-icon" class="input-icon"></span>
</div>
<div id="user-msg" class="msg-small"></div>

<div class="input-group">
<input type="email" name="email" id="email" placeholder="Email" required>
<span id="email-icon" class="input-icon"></span>
</div>
<div id="email-msg" class="msg-small"></div>

<div class="password-wrap input-group">
<input type="password" id="p1" name="password" placeholder="Password" required>
<i class="ri-eye-line toggle" onclick="toggle('p1',this)"></i>
</div>

<div class="strength-bar">
<div id="strength-fill"></div>
</div>

<div class="rules">
<div id="rule-length">• minimal 6 karakter</div>
<div id="rule-upper">• huruf besar</div>
<div id="rule-number">• angka</div>
<div id="rule-symbol">• simbol</div>
</div>

<div class="password-wrap input-group">
<input type="password" id="p2" name="password_confirm" placeholder="Konfirmasi Password" required>
<i class="ri-eye-line toggle" onclick="toggle('p2',this)"></i>
<span id="pass2-icon" class="input-icon"></span>
</div>

<button>Daftar</button>

</form>

<div class="footer">
Sudah punya akun? <a href="login.php">Login</a>
</div>

</div>

<script>

/* toggle password */

function toggle(id,el){
const x=document.getElementById(id);
if(x.type==="password"){
x.type="text";
el.className="ri-eye-off-line toggle";
}else{
x.type="password";
el.className="ri-eye-line toggle";
}
}

/* debounce */

function debounce(fn,delay=500){
let t;
return(...args)=>{
clearTimeout(t);
t=setTimeout(()=>fn.apply(this,args),delay);
};
}

/* username check */

const userInput=document.getElementById("username");
const userMsg=document.getElementById("user-msg");
const userIcon=document.getElementById("user-icon");

const checkUsername=debounce(function(){

let val=userInput.value.trim();

if(!val){
userMsg.textContent="";
userInput.classList.remove("valid","invalid");
userIcon.innerHTML="";
return;
}

if(!/^[a-zA-Z0-9_]{3,20}$/.test(val)){
userMsg.textContent="3-20 huruf, angka, underscore";
userMsg.className="msg-small msg-error";
userInput.classList.add("invalid");
userInput.classList.remove("valid");
userIcon.innerHTML="✕";
userIcon.className="input-icon icon-error";
return;
}

userIcon.innerHTML='<span class="icon-loading"></span>';
userMsg.textContent="Memeriksa username...";

fetch("/api/check-user.php?username="+encodeURIComponent(val))
.then(r=>r.json())
.then(data=>{

if(data.username_exists){

userMsg.textContent="Username sudah digunakan";
userMsg.className="msg-small msg-error";
userInput.classList.add("invalid");
userIcon.innerHTML="✕";
userIcon.className="input-icon icon-error";

}else{

userMsg.textContent="Username tersedia";
userMsg.className="msg-small msg-ok";
userInput.classList.add("valid");
userIcon.innerHTML="✓";
userIcon.className="input-icon icon-ok";

}

});

},500);

userInput.addEventListener("input",checkUsername);

/* email check */

const emailInput=document.getElementById("email");
const emailMsg=document.getElementById("email-msg");
const emailIcon=document.getElementById("email-icon");

const emailRegex=/^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const checkEmail=debounce(function(){

let val=emailInput.value.trim();

if(!val){
emailMsg.textContent="";
emailIcon.innerHTML="";
return;
}

if(!emailRegex.test(val)){
emailMsg.textContent="Format email belum valid";
emailMsg.className="msg-small msg-error";
emailInput.classList.add("invalid");
emailIcon.innerHTML="✕";
emailIcon.className="input-icon icon-error";
return;
}

emailIcon.innerHTML='<span class="icon-loading"></span>';
emailMsg.textContent="Memeriksa email...";

fetch("/api/check-user.php?email="+encodeURIComponent(val))
.then(r=>r.json())
.then(data=>{

if(data.email_exists){

emailMsg.textContent="Email sudah terdaftar";
emailMsg.className="msg-small msg-error";
emailInput.classList.add("invalid");
emailIcon.innerHTML="✕";
emailIcon.className="input-icon icon-error";

}else{

emailMsg.textContent="Email tersedia";
emailMsg.className="msg-small msg-ok";
emailInput.classList.add("valid");
emailIcon.innerHTML="✓";
emailIcon.className="input-icon icon-ok";

}

});

},500);

emailInput.addEventListener("input",checkEmail);

/* password strength */

const pass=document.getElementById("p1");
const bar=document.getElementById("strength-fill");

const ruleLength=document.getElementById("rule-length");
const ruleUpper=document.getElementById("rule-upper");
const ruleNumber=document.getElementById("rule-number");
const ruleSymbol=document.getElementById("rule-symbol");

pass.addEventListener("input",function(){

let p=this.value;
let score=0;

if(p.length>=6){score++;ruleLength.classList.add("rule-ok");}
else{ruleLength.classList.remove("rule-ok");}

if(/[A-Z]/.test(p)){score++;ruleUpper.classList.add("rule-ok");}
else{ruleUpper.classList.remove("rule-ok");}

if(/[0-9]/.test(p)){score++;ruleNumber.classList.add("rule-ok");}
else{ruleNumber.classList.remove("rule-ok");}

if(/[^A-Za-z0-9]/.test(p)){score++;ruleSymbol.classList.add("rule-ok");}
else{ruleSymbol.classList.remove("rule-ok");}

let percent=(score/4)*100;
bar.style.width=percent+"%";

if(percent<40) bar.style.background="#dc2626";
else if(percent<70) bar.style.background="#f59e0b";
else bar.style.background="#16a34a";

});

/* confirm password */

const confirm=document.getElementById("p2");
const pass2Icon=document.getElementById("pass2-icon");

confirm.addEventListener("input",function(){

if(this.value!==pass.value){
this.classList.add("invalid");
pass2Icon.innerHTML="✕";
pass2Icon.className="input-icon icon-error";
}else{
this.classList.add("valid");
pass2Icon.innerHTML="✓";
pass2Icon.className="input-icon icon-ok";
}

});

</script>

</body>
</html>