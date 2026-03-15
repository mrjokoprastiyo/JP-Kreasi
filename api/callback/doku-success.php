<?php
require_once '../../config.php';
require_once '../../core/db.php';

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    die("Order ID tidak valid.");
}

// Ambil data pembayaran untuk mendapatkan client_id
$payment = DB::fetch("SELECT client_id, status FROM payments WHERE order_id = ?", [$order_id]);

if (!$payment) {
    die("Data pembayaran tidak ditemukan.");
}

/**
 * Note: Untuk DOKU, status 'success' biasanya diupdate oleh file callback/webhook
 * secara asinkron. Di sini kita cukup memberikan delay 2-3 detik lalu redirect.
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Memproses Pembayaran...</title>
    <style>
        body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; background: #f4f7f6; }
        .loader-card { text-align: center; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .spinner { border: 4px solid #f3f3f3; border-top: 4px solid #4f46e5; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        h2 { color: #1e293b; margin-bottom: 10px; }
        p { color: #64748b; }
    </style>
    <meta http-equiv="refresh" content="3;url=/user/client-detail.php?id=<?= $payment['client_id'] ?>">
</head>
<body>
    <div class="loader-card">
        <div class="spinner"></div>
        <h2>Terima Kasih!</h2>
        <p>Kami sedang memverifikasi pembayaran Anda.<br>Halaman akan dialihkan secara otomatis...</p>
        <a href="../../user/client-detail.php?id=<?= $payment['client_id'] ?>" style="color: #4f46e5; font-size: 0.9rem; text-decoration: none;">Klik di sini jika tidak beralih</a>
    </div>
</body>
</html>
