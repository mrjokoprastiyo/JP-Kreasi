<?php
session_start();

require_once 'config.php';
require_once 'core/auth.php';
require_once 'core/db.php';

// Auth::check();

$user_id  = $_SESSION['verify_user_id'] ?? 0;

$isLogged = !empty($_SESSION['user']);

/* ===============================
   FLOW FILTER
================================ */
$flowFilter = $_GET['flow'] ?? '';

$flowMap = [
    'DESIGN_ORDER' => [
        'label' => 'Desain',
        'where' => "category = 'desain'"
    ],
    'CHATBOT_WEB' => [
        'label' => 'Chatbot Website',
        'where' => "category='automation' AND sub_category='chatbot' AND service='website'"
    ],
    'CHATBOT_CHANNEL' => [
        'label' => 'Chatbot Channel',
        'where' => "category='automation' AND sub_category='chatbot' AND service!='website'"
    ],
    'AUTOMATION_NOTIFICATION' => [
        'label' => 'Automation Notifikasi',
        'where' => "category='automation' AND sub_category='notification'"
    ]
];

/* ===============================
   QUERY FILTER BUILDER
================================ */
$where  = "status = 'active'";
$params = [];

if ($flowFilter && isset($flowMap[$flowFilter])) {
    $where .= " AND " . $flowMap[$flowFilter]['where'];
}

/* ===============================
   AMBIL PRODUK
================================ */
$products = DB::fetchAll("
    SELECT *
    FROM products
    WHERE $where
    ORDER BY created_at DESC
", $params);

/* ===============================
   GROUP PRODUK BERDASARKAN NAMA
================================ */

$groupedProducts = [];

foreach ($products as $p) {

    $key = strtolower(trim($p['name']));

    if (!isset($groupedProducts[$key])) {
        $groupedProducts[$key] = [
            'name' => $p['name'],
            'category' => $p['category'],
            'sub_category' => $p['sub_category'],
            'service' => $p['service'],
            'product_type' => $p['product_type'],
            'tier' => $p['tier'],
            'variants' => []
        ];
    }

    $groupedProducts[$key]['variants'][] = $p;
}

/* ===============================
   SORT VARIANT BERDASARKAN DURASI
================================ */

foreach ($groupedProducts as &$g) {

    usort($g['variants'], function($a,$b){
        return (int)$a['duration'] <=> (int)$b['duration'];
    });

}

unset($g);

/* ===============================
   LOAD HEADER
================================ */

if ($isLogged) {
    include 'user/header.php';
} else {
    include 'user/header-public.php';
}

include 'user/sidebar.php';

?>

<main class="user-content">

<h1>Pilih Produk</h1>

<?php if ($flowFilter && isset($flowMap[$flowFilter])): ?>

<p class="subtitle">
Menampilkan layanan: <strong><?= htmlspecialchars($flowMap[$flowFilter]['label']) ?></strong>
</p>

<div style="margin-bottom:20px">
<a href="products.php" class="btn-secondary">← Lihat Semua Produk</a>
</div>

<?php else: ?>

<p class="subtitle">
Silakan pilih produk yang ingin Anda gunakan
</p>

<?php endif; ?>


<div class="product-grid">

<?php if (!$groupedProducts): ?>

<p>Produk belum tersedia</p>

<?php else: ?>

<?php foreach ($groupedProducts as $product): ?>

<div class="product-card">

<div class="product-header">

<h3><?= htmlspecialchars($product['name']) ?></h3>

<?php if ($product['category'] !== 'desain' && !empty($product['service'])): ?>
<span class="badge"><?= strtoupper(htmlspecialchars($product['service'])) ?></span>
<?php endif; ?>

</div>

<p class="category">

<?= ucfirst(htmlspecialchars($product['category'])) ?>

<?php if (!empty($product['sub_category'])): ?>
/ <?= ucfirst(htmlspecialchars($product['sub_category'])) ?>
<?php endif; ?>

</p>

<?php if ($product['category'] === 'automation' && !empty($product['product_type'])): ?>

<span class="badge type <?= htmlspecialchars($product['product_type']) ?>">
<?= strtoupper(htmlspecialchars($product['product_type'])) ?>
</span>

<?php endif; ?>


<?php if ($product['category'] === 'automation' && !empty($product['tier'])): ?>

<div class="tier">
Tier <?= htmlspecialchars($product['tier']) ?>
</div>

<?php endif; ?>


<div class="variant-list">

<?php foreach ($product['variants'] as $v): ?>

<?php

if ($isLogged) {
    $targetUrl = 'user/order.php?product_id=' . (int)$v['id'];
    $btnText = 'Pilih Paket';
} else {
    $targetUrl = 'login.php';
    $btnText = 'Login untuk Memesan';
}

?>

<div class="variant">

<div class="variant-duration">

<?php if ($product['category'] !== 'desain'): ?>
<?= (int)$v['duration'] ?> hari
<?php else: ?>
Paket
<?php endif; ?>

</div>

<div class="variant-price">

<div class="price-idr">
Rp <?= number_format($v['price_idr'], 0, ',', '.') ?>
</div>

<?php if ($v['price_usd'] > 0): ?>

<div class="price-usd">
$<?= number_format($v['price_usd'], 2) ?> USD
</div>

<?php endif; ?>

</div>

<a class="btn variant-btn" href="<?= $targetUrl ?>">
<?= $btnText ?>
</a>

</div>

<?php endforeach; ?>

</div>

</div>

<?php endforeach; ?>

<?php endif; ?>

</div>

</main>


<style>

.badge.type.system{
background:#10b981;
color:white;
}

.badge.type.client{
background:#6366f1;
color:white;
}

.price-idr{
font-size:1.2rem;
font-weight:800;
color:#1e293b;
}

.price-usd{
font-size:0.8rem;
color:#0070ba;
font-weight:600;
margin-top:2px;
}

.product-card{
transition:transform .2s, box-shadow .2s;
border:1px solid #e2e8f0;
padding:20px;
}

.product-card:hover{
transform:translateY(-5px);
box-shadow:0 10px 20px rgba(0,0,0,.05);
border-color:#4f46e5;
}

.variant-list{
margin-top:20px;
border-top:1px solid #e5e7eb;
}

.variant{
display:flex;
justify-content:space-between;
align-items:center;
padding:12px 0;
border-bottom:1px solid #f1f5f9;
}

.variant-duration{
font-size:0.9rem;
color:#64748b;
min-width:90px;
}

.variant-price{
flex:1;
padding-left:10px;
}

.variant-btn{
white-space:nowrap;
}

</style>

<?php include 'user/footer.php'; ?>