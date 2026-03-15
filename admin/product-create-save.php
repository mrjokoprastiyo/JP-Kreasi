<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

/* ===============================
   CATEGORY
================================ */

$category = $_POST['category_select'] === '__new__'
    ? trim($_POST['category_new'])
    : trim($_POST['category_select']);

$sub_category = $_POST['sub_category_select'] === '__new__'
    ? trim($_POST['sub_category_new'])
    : trim($_POST['sub_category_select']);

if(!$category || !$sub_category){
die('Category dan Sub Category wajib diisi');
}

/* ===============================
   BASIC DATA
================================ */

$name        = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');

if(!$name){
die('Nama produk wajib diisi');
}

$service = trim($_POST['service'] ?? '');
$service = $service !== '' ? $service : null;

$product_type = trim($_POST['product_type'] ?? '');
$product_type = $product_type !== '' ? $product_type : null;

$tier = isset($_POST['tier']) && $_POST['tier'] !== ''
    ? (int)$_POST['tier']
    : null;

$status = $_POST['status'] ?? 'active';

/* ===============================
   VARIANTS
================================ */

$durations  = $_POST['duration'] ?? [];
$prices_idr = $_POST['price_idr'] ?? [];
$prices_usd = $_POST['price_usd'] ?? [];

if(empty($durations)){
die('Minimal 1 variasi harga wajib ada');
}

/* ===============================
   INSERT LOOP
================================ */

foreach($durations as $i => $duration){

$duration = (int)$duration;

$price_idr = isset($prices_idr[$i])
    ? (float)$prices_idr[$i]
    : 0;

$price_usd = isset($prices_usd[$i])
    ? (float)$prices_usd[$i]
    : 0;

if($duration <= 0){
continue;
}

DB::execute("
INSERT INTO products
(
 name,
 description,
 category,
 sub_category,
 service,
 product_type,
 tier,
 duration,
 price_idr,
 price_usd,
 status
)
VALUES
(
 ?,?,?,?,?,?,?,?,?,?,?
)
",[
$name,
$description,
$category,
$sub_category,
$service,
$product_type,
$tier,
$duration,
$price_idr,
$price_usd,
$status
]);

}

header("Location: product-list.php?created=1");
exit;