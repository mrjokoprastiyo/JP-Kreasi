<?php
require_once __DIR__.'/core/mailer.php';
require_once __DIR__.'/core/settings-loader.php';

$result = null;

if($_SERVER['REQUEST_METHOD']=='POST'){

$email = trim($_POST['email'] ?? '');
$type  = $_POST['type'] ?? '';

if($email){

switch($type){

case 'otp':

$otp = rand(100000,999999);
$result = Mailer::sendOTP($email,$otp);

break;

case 'reset':

$link = "https://".$_SERVER['HTTP_HOST']."/reset-password.php?token=testtoken123";
$result = Mailer::sendResetPassword($email,$link);

break;

case 'welcome':

$result = Mailer::sendWelcome($email,"Test User");

break;

case 'invoice':

$result = Mailer::sendInvoice(
$email,
"INV-".rand(1000,9999),
150000,
"https://".$_SERVER['HTTP_HOST']."/invoice/123"
);

break;

}

}

}
?>

<!DOCTYPE html>
<html>
<head>

<title>Mailer Test</title>

<meta name="viewport" content="width=device-width,initial-scale=1">

<style>

body{
font-family:Arial;
background:#f4f6f8;
display:flex;
align-items:center;
justify-content:center;
height:100vh;
}

.box{
background:#fff;
padding:30px;
border-radius:10px;
width:340px;
box-shadow:0 4px 10px rgba(0,0,0,0.05);
}

input,select,button{
width:100%;
padding:10px;
margin-top:10px;
}

button{
background:#000;
color:#fff;
border:0;
border-radius:6px;
cursor:pointer;
}

.result{
margin-top:15px;
padding:10px;
border-radius:6px;
background:#f0f0f0;
}

</style>

</head>

<body>

<div class="box">

<h2>Test Mailer</h2>

<form method="post">

<input type="email" name="email" placeholder="Email tujuan" required>

<select name="type">

<option value="otp">Send OTP</option>
<option value="reset">Reset Password</option>
<option value="welcome">Welcome Email</option>
<option value="invoice">Invoice Email</option>

</select>

<button type="submit">Kirim Email</button>

</form>

<?php if($result !== null): ?>

<div class="result">

<?php if($result): ?>
✅ Email berhasil dikirim
<?php else: ?>
❌ Email gagal dikirim
<?php endif; ?>

</div>

<?php endif; ?>

</div>

</body>
</html>