<?php
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();
$user_id = $_SESSION['user']['id'];

// ===============================
// VALIDASI CLIENT
// ===============================
$client_id = $_GET['client_id'] ?? null;
if (!$client_id) die('Client tidak ditemukan');

$stmt = $pdo->prepare("
    SELECT c.*, p.name AS product_name, p.duration, p.price
    FROM clients c
    JOIN products p ON p.id = c.product_id
    WHERE c.id = ? AND c.user_id = ?
");
$stmt->execute([$client_id, $user_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) die('Client tidak valid');

// ===============================
// DATA PEMBAYARAN (ADMIN SET)
// ===============================
$paymentAccounts = [
    'dana'   => '0857-xxxx-xxxx a/n JP Kreasi',
    'qris'   => 'Scan QRIS di admin',
    'paypal' => 'paypal@jp-kreasi.com'
];
?>

<main class="user-content">
<h1>💳 Informasi Pembayaran</h1>
<p class="subtitle">
    Aktifkan layanan setelah pembayaran diverifikasi admin
</p>

<section class="card">
    <h3>Detail Layanan</h3>
    <p><strong>Nama Client:</strong> <?= htmlspecialchars($client['name']) ?></p>
    <p><strong>Produk:</strong> <?= htmlspecialchars($client['product_name']) ?></p>
    <p><strong>Durasi:</strong> <?= $client['duration'] ?> hari</p>
    <p><strong>Harga:</strong> Rp <?= number_format($client['price'],0,',','.') ?></p>
    <p><strong>Status:</strong> <span class="badge pending">Pending Payment</span></p>
</section>

<section class="card">
    <h3>Metode Pembayaran</h3>

    <div class="pay-box">
        <strong>DANA</strong>
        <p><?= $paymentAccounts['dana'] ?></p>
    </div>

    <div class="pay-box">
        <strong>QRIS</strong>
        <p>Scan QRIS (hubungi admin jika belum tersedia)</p>
    </div>

    <div class="pay-box">
        <strong>PayPal</strong>
        <p><?= $paymentAccounts['paypal'] ?></p>
    </div>
</section>

<section class="card highlight">
    <h3>📌 Setelah Transfer</h3>
    <ol>
        <li>Simpan bukti pembayaran</li>
        <li>Kirim ke admin via WhatsApp / Email</li>
        <li>Layanan aktif setelah diverifikasi</li>
    </ol>

    <p class="note">
        ⚠️ API Key & layanan hanya aktif setelah admin mengkonfirmasi pembayaran.
    </p>

    <a href="client-detail.php?id=<?= $client['id'] ?>" class="btn secondary">
        ← Kembali ke Detail Client
    </a>
</section>
</main>

<style>
.user-content { max-width: 900px; }
.subtitle { color:#6b7280; margin-bottom:20px; }

.card {
    background:#fff;
    padding:20px;
    border-radius:10px;
    box-shadow:0 6px 15px rgba(0,0,0,.05);
    margin-bottom:20px;
}

.pay-box {
    border:1px dashed #d1d5db;
    padding:15px;
    border-radius:8px;
    margin-bottom:10px;
    background:#f9fafb;
}

.highlight {
    background:#fefce8;
    border:1px solid #fde68a;
}

.badge.pending {
    background:#fbbf24;
    color:#78350f;
    padding:4px 8px;
    border-radius:6px;
    font-size:13px;
}

.note {
    color:#92400e;
    margin-top:10px;
}

.btn.secondary {
    display:inline-block;
    margin-top:15px;
    padding:10px 15px;
    background:#e5e7eb;
    border-radius:8px;
    text-decoration:none;
    color:#111827;
}
</style>