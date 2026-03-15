<?php
/**
 * Authentication Core (Security Upgrade)
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);

class Auth
{

/* ===============================
   CONFIG SECURITY
================================ */

private const LOGIN_MAX_ATTEMPT = 5;
private const LOGIN_BLOCK_TIME  = 300; // 5 menit

private const OTP_MAX_ATTEMPT = 5;

private const SESSION_IDLE_TIMEOUT = 1800; // 30 menit


/* ===============================
   BASIC SESSION
================================ */

public static function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

public static function id(): ?int
{
    return $_SESSION['user']['id'] ?? null;
}

public static function role(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}


/* ===============================
   SESSION CHECK + SECURITY
================================ */

public static function check(): bool
{

    if (empty($_SESSION['user']['id'])) {
        return false;
    }

    /* ---- idle timeout ---- */

    if (!empty($_SESSION['last_activity'])) {

        if (time() - $_SESSION['last_activity'] > self::SESSION_IDLE_TIMEOUT) {
            self::destroySession();
            return false;
        }

    }

    $_SESSION['last_activity'] = time();


    /* ---- session hijack protection ---- */

    $fingerprint = self::sessionFingerprint();

    if (!isset($_SESSION['fingerprint'])) {
        $_SESSION['fingerprint'] = $fingerprint;
    }

    if ($_SESSION['fingerprint'] !== $fingerprint) {
        self::destroySession();
        return false;
    }


    /* ---- validate user in database ---- */

    $user = DB::fetch(
        "SELECT id,status FROM users WHERE id=? LIMIT 1",
        [$_SESSION['user']['id']]
    );

    if (!$user || $user['status'] !== 'active') {

        self::destroySession();
        return false;
    }

    return true;
}

public static function isAdmin(): bool
{
    return self::check() && self::role() === 'admin';
}


/* ===============================
   CSRF
================================ */

public static function csrf(): string
{

    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

public static function verifyCSRF($token): bool
{
    return isset($_SESSION['csrf']) &&
           hash_equals($_SESSION['csrf'], $token);
}


/* ===============================
   LOGIN RATE LIMIT
================================ */

private static function loginAttempts(string $identity): int
{

    $row = DB::fetch(
        "SELECT attempts,last_attempt
         FROM login_attempts
         WHERE identity=?",
        [$identity]
    );

    if (!$row) return 0;

    if (strtotime($row['last_attempt']) + self::LOGIN_BLOCK_TIME < time()) {

        DB::exec(
            "DELETE FROM login_attempts WHERE identity=?",
            [$identity]
        );

        return 0;
    }

    return (int)$row['attempts'];
}

private static function recordLoginAttempt(string $identity): void
{

    DB::exec(
        "INSERT INTO login_attempts(identity,attempts,last_attempt)
        VALUES(?,1,NOW())
        ON DUPLICATE KEY UPDATE
        attempts=attempts+1,
        last_attempt=NOW()",
        [$identity]
    );
}

private static function clearLoginAttempts(string $identity): void
{
    DB::exec(
        "DELETE FROM login_attempts WHERE identity=?",
        [$identity]
    );
}


/* ===============================
   LOGIN
================================ */

public static function login(string $identity, string $password): array
{

    $identity = strtolower(trim($identity));
    $password = trim($password);

    if (!$identity || !$password) {
        return ['status'=>false,'msg'=>'Username/email dan password wajib diisi'];
    }

    /* ---- rate limit ---- */

    if (self::loginAttempts($identity) >= self::LOGIN_MAX_ATTEMPT) {
        return ['status'=>false,'msg'=>'Terlalu banyak percobaan login. Coba lagi 5 menit.'];
    }


    $user = DB::fetch(
        "SELECT id,username,email,password,fullname,role,status,email_verified
         FROM users
         WHERE username=? OR email=?
         LIMIT 1",
        [$identity,$identity]
    );

    if (!$user) {

        self::recordLoginAttempt($identity);

        return ['status'=>false,'msg'=>'User tidak ditemukan'];
    }

    if (!password_verify($password,$user['password'])) {

        self::recordLoginAttempt($identity);

        return ['status'=>false,'msg'=>'Password salah'];
    }

    if ($user['role'] !== 'admin' && empty($user['email_verified'])) {
        return ['status'=>false,'msg'=>'Silakan verifikasi email terlebih dahulu'];
    }

    if (($user['status'] ?? 'active') !== 'active') {
        return ['status'=>false,'msg'=>'Akun tidak aktif'];
    }


    /* ---- login success ---- */

    self::clearLoginAttempts($identity);

    session_regenerate_id(true);

    $_SESSION['fingerprint'] = self::sessionFingerprint();
    $_SESSION['last_activity'] = time();

    $_SESSION['user'] = [
        'id'       => (int)$user['id'],
        'username' => $user['username'],
        'email'    => $user['email'],
        'fullname' => $user['fullname'] ?? '',
        'role'     => $user['role']
    ];

    DB::exec(
        "UPDATE users SET last_login=NOW() WHERE id=?",
        [$user['id']]
    );

    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {

        $hash = password_hash($password, PASSWORD_DEFAULT);

        DB::exec(
            "UPDATE users SET password=? WHERE id=?",
            [$hash, $user['id']]
        );
    }

    return ['status'=>true,'user'=>$_SESSION['user']];
}


/* ===============================
   REGISTER
================================ */

public static function register(array $data): array
{

    $username = strtolower(trim($data['username'] ?? ''));
    $email    = strtolower(trim($data['email'] ?? ''));
    $fullname = trim($data['fullname'] ?? '');
    $password = $data['password'] ?? '';

    if (!$username || !$email || !$password || !$fullname) {
        return ['status'=>false,'msg'=>'Semua field wajib diisi'];
    }

    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/',$username)) {
        return ['status'=>false,'msg'=>'Username hanya huruf angka underscore'];
    }

    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) {
        return ['status'=>false,'msg'=>'Format email tidak valid'];
    }

    if (strlen($password) < 6) {
        return ['status'=>false,'msg'=>'Password minimal 6 karakter'];
    }

    if (DB::fetch("SELECT id FROM users WHERE username=?",[$username])) {
        return ['status'=>false,'msg'=>'Username sudah digunakan'];
    }

    if (DB::fetch("SELECT id FROM users WHERE email=?",[$email])) {
        return ['status'=>false,'msg'=>'Email sudah terdaftar'];
    }

    DB::exec(
        "INSERT INTO users
        (fullname,username,email,password,role,status,created_at)
        VALUES (?,?,?,?, 'user','pending',NOW())",
        [
            $fullname,
            $username,
            $email,
            password_hash($password,PASSWORD_DEFAULT)
        ]
    );

    $user_id = (int)DB::lastInsertId();

    $otp = self::generateOTP($user_id);

    Mailer::sendOTP($email,$otp);

    return [
        'status'=>true,
        'user_id'=>$user_id
    ];
}


/* ===============================
   OTP GENERATE
================================ */

public static function generateOTP(int $user_id): string
{

    $otp = (string)random_int(100000,999999);

    DB::exec(
        "UPDATE users
        SET otp_code=?,
            otp_attempt=0,
            otp_expire=DATE_ADD(NOW(),INTERVAL 5 MINUTE),
            otp_last_sent=NOW()
        WHERE id=?",
        [$otp,$user_id]
    );

    return $otp;
}


/* ===============================
   VERIFY OTP
================================ */

public static function verifyOTP(int $user_id,string $otp): bool
{

    $user = DB::fetch(
        "SELECT otp_code,otp_expire,otp_attempt
         FROM users
         WHERE id=?",
        [$user_id]
    );

    if (!$user) return false;

    if ($user['otp_attempt'] >= self::OTP_MAX_ATTEMPT) {
        return false;
    }

    if (!$user['otp_expire'] || strtotime($user['otp_expire']) < time()) {
        return false;
    }

    if ($user['otp_code'] !== $otp) {

        DB::exec(
            "UPDATE users SET otp_attempt=otp_attempt+1 WHERE id=?",
            [$user_id]
        );

        return false;
    }

    DB::exec(
        "UPDATE users
        SET email_verified=1,
            status='active',
            otp_code=NULL,
            otp_expire=NULL
        WHERE id=?",
        [$user_id]
    );

    return true;
}


/* ===============================
   RESEND OTP
================================ */

public static function resendOTP(int $user_id): array
{

    $user = DB::fetch(
        "SELECT email,otp_last_sent FROM users WHERE id=?",
        [$user_id]
    );

    if(!$user){
        return ['status'=>false,'msg'=>'User tidak ditemukan'];
    }

    if($user['otp_last_sent']){

        $last = strtotime($user['otp_last_sent']);
        $diff = time() - $last;

        if($diff < 60){
            return [
                'status'=>false,
                'msg'=>'Tunggu '.(60-$diff).' detik'
            ];
        }

    }

    $otp = self::generateOTP($user_id);

    Mailer::sendOTP($user['email'],$otp);

    return ['status'=>true];
}


/* ===============================
   REQUIRE LOGIN
================================ */

public static function requireLogin(): void
{
    if(!self::check()){
        header("Location: /login.php");
        exit;
    }
}


/* ===============================
   REQUIRE ADMIN
================================ */

public static function requireAdmin(): void
{
    self::requireLogin();

    if(!self::isAdmin()){
        http_response_code(403);
        exit('Access denied');
    }
}


/* ===============================
   LOGOUT
================================ */

public static function logout(): void
{

    self::destroySession();

    header("Location: /login.php");
    exit;
}


/* ===============================
   SESSION FINGERPRINT
================================ */

private static function sessionFingerprintX(): string
{
    return hash(
        'sha256',
        ($_SERVER['HTTP_USER_AGENT'] ?? '') .
        ($_SERVER['REMOTE_ADDR'] ?? '')
    );
}
private static function sessionFingerprint(): string
{
    return hash(
        'sha256',
        ($_SERVER['HTTP_USER_AGENT'] ?? '') .
        session_id()
    );
}

/* ===============================
   DESTROY SESSION
================================ */

private static function destroySession(): void
{

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {

        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

}