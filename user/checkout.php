<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/settings-loader.php';

Auth::check();

// $user_id = $_SESSION['user']['id'];

$product_id = $_GET['product_id'] ?? null;
// $client_id  = $_GET['client'] ?? null;

if (!$product_id && !$client_id) {
    die('Checkout tidak valid');
}

/* ===============================
   LOAD ADMIN CONTACT SETTINGS
================================ */
$admin_wa = setting('admin-contact-whatsapp');
$admin_ms = setting('admin-contact-messenger_username');

/* ===============================
   MODE DESAIN
================================ */
if ($product_id && isset($_SESSION['design_order'])) {

    $product = DB::fetch(
        "SELECT * FROM products WHERE id = ?",
        [$product_id]
    );

    if (!$product || $product['category'] !== 'desain') {
        die('Produk desain tidak valid');
    }

    $brief = $_SESSION['design_order']['brief'] ?? '';

    include 'header.php';
    include 'sidebar.php';
    ?>

    <main class="user-content">

        <h1>Checkout Desain</h1>

        <div class="card">
            <h3><?= htmlspecialchars($product['name']) ?></h3>
            <p>
                <b>Harga:</b>
                Rp<?= number_format($product['price_idr'], 0, ',', '.') ?>
            </p>

            <label>Kebutuhan Anda</label>
            <textarea readonly><?= htmlspecialchars($brief) ?></textarea>
        </div>

        <div class="card highlight">

            <h3>Langkah Selanjutnya</h3>

            <p>
                Untuk hasil terbaik, silakan lanjutkan komunikasi dengan admin kami
                agar kebutuhan desain dapat dipahami secara detail.
            </p>

            <div class="cta">

                <?php if (!empty($admin_wa)): ?>
                    <a class="btn green" target="_blank"
                       href="https://wa.me/<?= preg_replace('/\D/', '', $admin_wa) ?>">
                        💬 Chat via WhatsApp
                    </a>
                <?php endif; ?>

                <?php if (!empty($admin_ms)): ?>
                    <a class="btn blue" target="_blank"
                       href="https://m.me/<?= htmlspecialchars($admin_ms) ?>">
                        💬 Chat via Messenger
                    </a>
                <?php endif; ?>

            </div>

        </div>

    </main>

    <?php
    include 'footer.php';
    exit;
}

/* ===============================
   MODE LAIN (NANTI)
================================ */
// die('Mode checkout belum tersedia');