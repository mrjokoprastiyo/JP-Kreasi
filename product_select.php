<?php
session_start();
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();
$user_id = $_SESSION['user']['id'];

// ambil produk aktif
$products = DB::fetchAll("
SELECT * FROM products
WHERE status='active'
ORDER BY category, sub_category
");

// produk dari landing (jika ada)
$selected = $_GET['product'] ?? null;

include 'header.php';
include 'sidebar.php';
?>

<main class="user-content">
  <h1>Pilih Produk</h1>
  <p class="subtitle">Silakan pilih produk yang ingin Anda gunakan</p>

  <div class="product-grid">

  <?php foreach($products as $p): 
    $active = ($selected == $p['id']) ? 'active' : '';
  ?>
    <div class="product-card <?= $active ?>">
      <div class="product-header">
        <h3><?= $p['name'] ?></h3>
        <span class="badge"><?= strtoupper($p['service']) ?></span>
      </div>

      <p class="category">
        <?= ucfirst($p['category']) ?> / <?= ucfirst($p['sub_category']) ?>
      </p>

      <div class="price">
        Rp <?= number_format($p['price_idr'],0,',','.') ?>
      </div>
      <div class="duration"><?= $p['duration'] ?> hari</div>

      <a class="btn" href="client_create.php?product_id=<?= $p['id'] ?>">
        Pilih Produk
      </a>
    </div>
  <?php endforeach; ?>

  </div>
</main>

<?php include 'footer.php'; ?>