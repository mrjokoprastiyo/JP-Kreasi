<?php
require_once __DIR__.'/core/auth.php';
require_once __DIR__.'/core/db.php';

session_start();

$token = trim($_GET['token'] ?? '');
$ip    = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$error = "";
$success = false;
$user_id = null;
$reset_id = null;

/* ===============================
   VALIDASI TOKEN
================================ */

if(!$token){
die("Token tidak valid.");
}

$resets = DB::fetchAll(
"SELECT id,user_id,token_hash 
 FROM password_resets
 WHERE used=0
 AND expires_at > NOW()"
);

foreach($resets as $row){

if(password_verify($token,$row['token_hash'])){
$user_id  = $row['user_id'];
$reset_id = $row['id'];
break;
}

}

if(!$user_id){
die("Token tidak valid atau kadaluarsa.");
}


/* ===============================
   FORM SUBMIT
================================ */

if($_SERVER['REQUEST_METHOD']==='POST'){

if(!Auth::verifyCSRF($_POST['csrf'] ?? '')){
die("CSRF validation failed.");
}

$pass  = $_POST['password'] ?? '';
$pass2 = $_POST['password_confirm'] ?? '';

/* VALIDASI */

if(!$pass || !$pass2){
$error = "Semua field wajib diisi.";
}

elseif($pass !== $pass2){
$error = "Konfirmasi password tidak cocok.";
}

elseif(strlen($pass) < 8
|| !preg_match('/[A-Z]/',$pass)
|| !preg_match('/[0-9]/',$pass)
){
$error = "Password minimal 8 karakter, huruf besar, dan angka.";
}

else{

try{

$hash = password_hash($pass,PASSWORD_DEFAULT);

DB::begin();

/* UPDATE PASSWORD */

DB::exec(
"UPDATE users SET password=? WHERE id=?",
[$hash,$user_id]
);

/* INVALID TOKEN */

DB::exec(
"UPDATE password_resets SET used=1 WHERE id=?",
[$reset_id]
);

/* DELETE TOKEN LAIN */

DB::exec(
"DELETE FROM password_resets WHERE user_id=?",
[$user_id]
);

/* SECURITY LOG */

DB::exec(
"INSERT INTO security_logs(event,email,ip_address)
VALUES(
'password_reset_success',
(SELECT email FROM users WHERE id=?),
?)",
[$user_id,$ip]
);

DB::commit();

/* AUTO LOGIN */

$user = DB::fetch(
"SELECT * FROM users WHERE id=?",
[$user_id]
);

session_regenerate_id(true);

$_SESSION['user'] = $user;

$success = true;

}catch(Exception $e){

DB::rollback();

$error = "Terjadi kesalahan sistem.";

}

}

}


/* ===============================
   REDIRECT SETELAH SUKSES
================================ */

if($success){

if($_SESSION['user']['role'] === 'admin'){
header("Location:/admin/index.php");
}else{
header("Location:/user/index.php");
}

exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Reset Password</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">

<style>

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
}

.msg-error{
background:#fdecea;
color:#842029;
padding:10px;
border-radius:8px;
margin-bottom:14px;
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
color:#198754;
}

.password-wrap{
position:relative;
}

.toggle{
position:absolute;
right:12px;
top:14px;
cursor:pointer;
color:#999;
}

</style>
</head>
<body>

<div class="card">

<h1>Reset Password</h1>
<p>Buat password baru untuk akun Anda.</p>

<?php if($error): ?>
<div class="msg-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" autocomplete="off">

<input type="hidden" name="csrf" value="<?=Auth::csrf()?>">

<div class="password-wrap">
<input type="password" id="p1" name="password"
placeholder="Password Baru" required>

<i class="ri-eye-line toggle" onclick="toggle('p1',this)"></i>
</div>

<div class="strength-bar">
<div id="strength-fill"></div>
</div>

<div class="rules">
<div id="rule-length">• minimal 8 karakter</div>
<div id="rule-upper">• huruf besar</div>
<div id="rule-number">• angka</div>
<div id="rule-symbol">• simbol</div>
</div>

<div class="password-wrap">
<input type="password" id="p2"
name="password_confirm"
placeholder="Konfirmasi Password"
required>

<i class="ri-eye-line toggle" onclick="toggle('p2',this)"></i>
</div>

<button type="submit">Reset Password</button>

</form>

</div>

<script>

/* TOGGLE PASSWORD */

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

/* PASSWORD STRENGTH */

const pass=document.getElementById("p1");
const bar=document.getElementById("strength-fill");

const ruleLength=document.getElementById("rule-length");
const ruleUpper=document.getElementById("rule-upper");
const ruleNumber=document.getElementById("rule-number");
const ruleSymbol=document.getElementById("rule-symbol");

pass.addEventListener("input",function(){

let p=this.value;
let score=0;

if(p.length>=8){
score++;
ruleLength.classList.add("rule-ok");
}else{
ruleLength.classList.remove("rule-ok");
}

if(/[A-Z]/.test(p)){
score++;
ruleUpper.classList.add("rule-ok");
}else{
ruleUpper.classList.remove("rule-ok");
}

if(/[0-9]/.test(p)){
score++;
ruleNumber.classList.add("rule-ok");
}else{
ruleNumber.classList.remove("rule-ok");
}

if(/[^A-Za-z0-9]/.test(p)){
score++;
ruleSymbol.classList.add("rule-ok");
}else{
ruleSymbol.classList.remove("rule-ok");
}

let percent=(score/4)*100;

bar.style.width=percent+"%";

if(percent<40){
bar.style.background="#dc3545";
}
else if(percent<70){
bar.style.background="#ffc107";
}
else{
bar.style.background="#198754";
}

});


/* CONFIRM PASSWORD */

const confirm=document.getElementById("p2");

confirm.addEventListener("input",function(){

let p1=pass.value;
let p2=this.value;

if(!p2)return;

if(p1!==p2){
this.style.borderColor="#dc3545";
}else{
this.style.borderColor="#198754";
}

});

</script>

</body>
</html>