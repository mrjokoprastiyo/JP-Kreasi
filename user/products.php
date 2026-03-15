<?php
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();
$user_idx = $_SESSION['user']['id'];
$user_id = $_SESSION['verify_user_id'] ?? 0;
/* ===============================
   AMBIL PRODUK AKTIF (V2 LOGIC)
================================ */
$products = DB::fetchAll("
    SELECT *
    FROM products
    WHERE status = 'active'
    ORDER BY created_at DESC
");

include 'header.php';
include 'sidebar.php';
?>

<main class="user-content">
  <h1>Pilih Produk</h1>
  <p class="subtitle">Silakan pilih produk yang ingin Anda gunakan</p>

  <div class="product-grid">

  <?php if (!$products): ?>
    <p>Produk belum tersedia</p>
  <?php else: ?>

  <?php foreach ($products as $p): ?>

    <?php
    /* ===============================
       TARGET URL (V2 LOGIC — TIDAK DIUBAH)
    ================================ */
    $targetUrl = '#';

    if ($p['category'] === 'desain') {
        $targetUrl = 'order.php?product_id=' . $p['id'];
    } elseif ($p['category'] === 'automation') {
        $targetUrl = 'order.php?product_id=' . $p['id'];
    }
    ?>

<div class="product-card">

  <div class="product-header">
    <h3><?= htmlspecialchars($p['name']) ?></h3>
    <span class="badge"><?= strtoupper($p['service']) ?></span>
  </div>

  <p class="category">
    <?= ucfirst($p['category']) ?>
    <?php if (!empty($p['sub_category'])): ?>
      / <?= ucfirst($p['sub_category']) ?>
    <?php endif; ?>
  </p>

  <?php if (!empty($p['product_type'])): ?>
    <span class="badge type <?= htmlspecialchars($p['product_type']) ?>">
      <?= strtoupper($p['product_type']) ?>
    </span>
  <?php endif; ?>

  <?php if ($p['category'] === 'automation' && !empty($p['tier'])): ?>
    <div class="tier">Tier <?= htmlspecialchars($p['tier']) ?></div>
  <?php endif; ?>

  <div class="price">
    <div class="price-idr">
        Rp <?= number_format($p['price_idr'], 0, ',', '.') ?>
    </div>
    <?php if ($p['price_usd'] > 0): ?>
    <div class="price-usd" style="font-size: 0.9rem; color: #0070ba; font-weight: 600; margin-top: 2px;">
        $<?= number_format($p['price_usd'], 2) ?> USD
    </div>
    <?php endif; ?>
  </div>

  <?php if ($p['category'] !=== 'desain'): ?>
  <div class="duration">
    <?= (int)$p['duration'] ?> hari masa aktif
  </div>
<?php endif; ?>

  <a class="btn" href="<?= $targetUrl ?>">
    Pilih Produk
  </a>
</div>

  <?php endforeach; ?>
  <?php endif; ?>

  </div>
</main>

<style>
.badge.type.system {
  background:#10b981;
  color:white;
}

.badge.type.client {
  background:#6366f1;
  color:white;
}
</style>
<style>
/* Style tambahan untuk price grid */
.price {
    margin: 15px 0;
    display: flex;
    flex-direction: column;
}

.price-idr {
    font-size: 1.4rem;
    font-weight: 800;
    color: #1e293b;
}

.duration {
    font-size: 0.85rem;
    color: #64748b;
    margin-bottom: 20px;
}

.product-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid #e2e8f0;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.05);
    border-color: #4f46e5;
}
</style>

<?php include 'footer.php'; ?>