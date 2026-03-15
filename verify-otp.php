<?php
require_once __DIR__.'/core/auth.php';

$user_id = $_SESSION['verify_user_id'] ?? 0;

if(!$user_id){
    header("Location: register.php");
    exit;
}

$csrf = Auth::csrf();
?>
<!DOCTYPE html>
<html>
<head>

<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verifikasi OTP</title>

<style>

body{
font-family:Arial,Helvetica,sans-serif;
background:#f4f6f8;
height:100vh;
display:flex;
align-items:center;
justify-content:center;
margin:0;
}

.box{
background:#fff;
padding:30px;
border-radius:12px;
width:340px;
box-shadow:0 10px 30px rgba(0,0,0,0.08);
text-align:center;
}

h2{
margin-top:0;
}

.otp-box{
display:flex;
gap:10px;
justify-content:center;
margin:20px 0;
}

.otp-box input{

width:48px;
height:60px;
font-size:28px;
text-align:center;

border:2px solid #ddd;
border-radius:10px;

transition:.2s;

}

.otp-box input:focus{

border-color:#000;
transform:scale(1.05);

}

.otp-box input.filled{

border-color:#000;
background:#fafafa;

}

button{

width:100%;
padding:12px;

border:0;
border-radius:8px;

background:#000;
color:#fff;

font-weight:bold;

cursor:pointer;

}

button:disabled{
opacity:.5;
cursor:not-allowed;
}

#timer{
margin:15px 0;
font-size:14px;
color:#666;
}

/* MESSAGE BOX */

.msg{

margin-bottom:15px;
padding:12px;

border-radius:8px;
font-size:14px;

display:none;

}

.msg.error{

background:#fee2e2;
color:#991b1b;
display:block;

}

.msg.success{

background:#dcfce7;
color:#166534;
display:block;

}

</style>
</head>

<body>

<div class="box">

<input type="hidden" id="csrf" value="<?= $csrf ?>">

<h2>Masukkan Kode OTP</h2>

<div id="msg" class="msg"></div>

<div class="otp-box">

<input maxlength="1" inputmode="numeric" pattern="[0-9]*">
<input maxlength="1" inputmode="numeric" pattern="[0-9]*">
<input maxlength="1" inputmode="numeric" pattern="[0-9]*">
<input maxlength="1" inputmode="numeric" pattern="[0-9]*">
<input maxlength="1" inputmode="numeric" pattern="[0-9]*">
<input maxlength="1" inputmode="numeric" pattern="[0-9]*">

</div>

<button type="button" id="verify">Verifikasi</button>

<p id="timer"></p>

<button type="button" id="resend" disabled>Kirim Ulang OTP</button>

</div>

<script>

const uid = <?= $user_id ?>;
const csrf = document.getElementById("csrf").value;

const inputs = document.querySelectorAll(".otp-box input");

const msgBox = document.getElementById("msg");

let verifying=false;

/* MESSAGE */

function showMessage(text,type="error"){

msgBox.innerText=text;

msgBox.className="msg "+type;

}

/* AUTO FOCUS */

window.onload=()=>{

inputs[0].focus();

startTimer();

initWebOTP();

};

/* INPUT HANDLING */

inputs.forEach((input,index)=>{

input.addEventListener("input",(e)=>{

let val=e.target.value.replace(/\D/g,'');

e.target.value=val;

if(val){

input.classList.add("filled");

}else{

input.classList.remove("filled");

}

if(val && index < inputs.length-1){

inputs[index+1].focus();

}

checkAutoSubmit();

});

});

/* BACKSPACE NAVIGATION */

inputs.forEach((input,index)=>{

input.addEventListener("keydown",(e)=>{

if(e.key==="Backspace" && !input.value && index>0){

inputs[index-1].focus();

}

});

});

/* PASTE OTP */

inputs[0].addEventListener("paste",(e)=>{

let paste=(e.clipboardData||window.clipboardData)

.getData("text")

.replace(/\D/g,'');

if(paste.length===6){

inputs.forEach((input,i)=>{

input.value=paste[i] ?? '';

input.classList.add("filled");

});

checkAutoSubmit();

}

});

/* GET OTP */

function getOTP(){

let otp="";

inputs.forEach(i=>otp+=i.value);

return otp;

}

/* AUTO SUBMIT */

function checkAutoSubmit(){

if(getOTP().length===6){

verifyOTP();

}

}

/* VERIFY */

async function verifyOTP(){

if(verifying) return;

verifying=true;

disableInputs(true);

let otp=getOTP();

let res=await fetch("api/verify-otp.php",{

method:"POST",

headers:{
"Content-Type":"application/x-www-form-urlencoded"
},

body:`uid=${uid}&otp=${otp}&csrf=${csrf}`

});

let data=await res.json();

if(data.status){

showMessage("Verifikasi berhasil","success");

setTimeout(()=>{

location.href="login.php";

},1000);

}else{

showMessage("OTP salah atau sudah kadaluarsa","error");

disableInputs(false);

verifying=false;

}

}

document.getElementById("verify").onclick=verifyOTP;

/* DISABLE INPUT */

function disableInputs(state){

inputs.forEach(i=>i.disabled=state);

document.getElementById("verify").disabled=state;

}

/* TIMER */

let time=60;

function startTimer(){

const timerEl=document.getElementById("timer");

const resendBtn=document.getElementById("resend");

resendBtn.disabled=true;

let interval=setInterval(()=>{

time--;

timerEl.innerText="Kirim ulang OTP dalam "+time+" detik";

if(time<=0){

clearInterval(interval);

timerEl.innerText="";

resendBtn.disabled=false;

}

},1000);

}

/* RESEND */

document.getElementById("resend").onclick=async()=>{

let res=await fetch("api/resend-otp.php",{

method:"POST",

headers:{
"Content-Type":"application/x-www-form-urlencoded"
},

body:`uid=${uid}&csrf=${csrf}`

});

let data=await res.json();

if(data.status){

showMessage("OTP berhasil dikirim ulang","success");

time=60;

startTimer();

}else{

showMessage(data.msg || "Gagal kirim ulang OTP");

}

};

/* ANDROID SMS AUTO DETECT */

async function initWebOTP(){

if(!("OTPCredential" in window)) return;

try{

const otp=await navigator.credentials.get({

otp:{transport:["sms"]},

signal:AbortSignal.timeout(60000)

});

if(otp && otp.code){

let code=otp.code.replace(/\D/g,'');

if(code.length===6){

inputs.forEach((input,i)=>{

input.value=code[i];

input.classList.add("filled");

});

verifyOTP();

}

}

}catch(err){

}

}

</script>

</body>
</html>