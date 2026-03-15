<?php
session_start();
require_once '../core/auth.php';
require_once '../core/db.php';
require_once '../core/settings-loader.php';

Auth::check();
$user_id = $_SESSION['user']['id'];
$client_id = $_GET['client_id'] ?? null;

if (!$client_id) die('Invalid request');

/**
 * ============================================
 * HELPER FUNCTIONS & DATA LOADING
 * ============================================
 */
function generateOrderId() { return 'ORD-' . time() . '-' . bin2hex(random_bytes(3)); }

$client = DB::fetch("SELECT * FROM clients WHERE id = ? AND user_id = ?", [$client_id, $user_id]);
if (!$client) die('Client tidak valid');

$product = DB::fetch("SELECT * FROM products WHERE id = ?", [$client['product_id']]);
if (!$product) die('Produk tidak valid');

/**
 * ============================================
 * CALCULATE BOTH SCHEMES
 * ============================================
 */

// 1. DOKU / IDR Logic
$subtotal_idr = (int)$product['price_idr'];
$d_tax_pct    = (float) setting('payment-doku-tax-percent', 11);
$d_fee_flat   = (int) setting('payment-doku-fee-flat', 4500);
$d_fee_pct    = (float) setting('payment-doku-fee-percent', 0);

$tax_idr      = (int) round($subtotal_idr * ($d_tax_pct / 100));
$fee_idr      = $d_fee_flat + (int) round($subtotal_idr * ($d_fee_pct / 100));
$total_idr    = $subtotal_idr + $tax_idr + $fee_idr;

// 2. PAYPAL / USD Logic
$subtotal_usd = (float)$product['price_usd'];
$p_tax_pct    = (float) setting('payment-paypal-tax-percent', 0);
$p_fee_flat   = (float) setting('payment-paypal-fee-flat', 0.30);
$p_fee_pct    = (float) setting('payment-paypal-fee-percent', 4.4);

$tax_usd      = round($subtotal_usd * ($p_tax_pct / 100), 2);
$fee_usd      = round($p_fee_flat + ($subtotal_usd * ($p_fee_pct / 100)), 2);
$total_usd    = $subtotal_usd + $tax_usd + $fee_usd;

/**
 * ============================================
 * SAVE / UPDATE ORDER
 * ============================================
 */
/**
 * ============================================
 * SAVE / UPDATE ORDER
 * ============================================
 */
$existing = DB::fetch("SELECT id, order_id FROM payments WHERE client_id = ? AND status = 'pending' LIMIT 1", [$client_id]);

if ($existing) {
    $order_id = $existing['order_id'];
    
    // UPDATE nominal terbaru agar sinkron dengan kalkulasi di atas
    DB::execute(
        "UPDATE payments SET 
            subtotal = ?, 
            tax = ?, 
            fee = ?, 
            amount = ?, 
            amount_usd = ? 
         WHERE id = ?",
        [$subtotal_idr, $tax_idr, $fee_idr, $total_idr, $total_usd, $existing['id']]
    );
    
} else {
    $order_id = generateOrderId();
    DB::execute(
        "INSERT INTO payments (user_id, client_id, product_id, order_id, subtotal, tax, fee, amount, amount_usd, currency, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'IDR', 'pending', NOW())",
        [$user_id, $client['id'], $product['id'], $order_id, $subtotal_idr, $tax_idr, $fee_idr, $total_idr, $total_usd]
    );
}

$site_logo = setting('site-logo', '');
?>

<!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?= htmlspecialchars($product['name']) ?></title>
    <style>
        :root { --primary: #4f46e5; --bg: #f3f4f6; --doku: #e11919; --paypal: #0070ba; }
        body { font-family: system-ui, sans-serif; background: var(--bg); margin:0; display:flex; align-items:center; justify-content:center; min-height:100vh; color: #1e293b; }
        .card { background:white; width:100%; max-width:450px; padding:30px; border-radius:20px; box-shadow:0 10px 25px rgba(0,0,0,0.05); }
        .logo { max-height:45px; display:block; margin: 0 auto 20px; }
        .title { font-weight:bold; font-size: 1.1rem; text-align:center; margin-bottom:25px; }
        
        /* Tab Styles */
        .payment-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; }
        .tab { flex: 1; text-align: center; padding: 10px; cursor: pointer; border-radius: 8px; font-weight: 600; font-size: 0.9rem; color: #64748b; transition: 0.3s; }
        .tab.active { background: #eff6ff; color: var(--primary); }

        .breakdown { background: #f8fafc; padding: 15px; border-radius: 12px; border: 1px solid #e2e8f0; }
        .row { display:flex; justify-content:space-between; margin-bottom:10px; font-size: 0.95rem; }
        .total { font-weight:bold; font-size:1.2rem; color:var(--primary); border-top: 1px solid #cbd5e1; margin-top: 10px; padding-top: 10px; }
        
        .method-box { display: none; margin-top: 20px; }
        .method-box.active { display: block; }

        button { width:100%; padding:15px; border:none; border-radius:12px; cursor:pointer; font-weight:bold; font-size: 1rem; transition: 0.2s; }
        button:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .btn-doku { background: var(--doku); color:white; }
        .btn-paypal { background: var(--paypal); color:white; }
        .helper-text { font-size: 0.75rem; color: #94a3b8; text-align: center; margin-top: 15px; }
    </style>
</head>
<body>

<div class="card">

<?php if (isset($_GET['error'])): ?>
    <div style="background: #fee2e2; color: #b91c1c; padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-size: 0.9rem; border: 1px solid #fecaca; max-width: 450px;">
        <?php 
            if ($_GET['error'] == 'payment_incomplete') {
                echo "⚠️ <strong>Pembayaran Gagal:</strong> Saldo tidak mencukupi atau transaksi ditolak oleh bank.";
            } else {
                echo "⚠️ Terjadi kesalahan saat memproses pembayaran. Silakan coba lagi.";
            }
        ?>
    </div>
<?php endif; ?>


    <?php if ($site_logo): ?>
        <img src="<?= $site_logo ?>" class="logo">
    <?php endif; ?>

    <div class="title">Checkout: <?= htmlspecialchars($product['name']) ?></div>

    <div class="payment-tabs">
        <div class="tab active" onclick="switchTab('doku')">🇮🇩 Local Payment</div>
        <div class="tab" onclick="switchTab('paypal')">🌎 International</div>
    </div>

    <div id="box-doku" class="method-box active">
        <div class="breakdown">
            <div class="row"><span>Layanan</span><span>Rp <?= number_format($subtotal_idr, 0, ',', '.') ?></span></div>
            <div class="row"><span>PPN (<?= $d_tax_pct ?>%)</span><span>Rp <?= number_format($tax_idr, 0, ',', '.') ?></span></div>
            <div class="row"><span>Biaya Transaksi</span><span>Rp <?= number_format($fee_idr, 0, ',', '.') ?></span></div>
            <div class="row total"><span>Total</span><span>Rp <?= number_format($total_idr, 0, ',', '.') ?></span></div>
        </div>
        <button class="btn-doku" onclick="payDoku()" style="margin-top:20px">Bayar via DOKU / QRIS</button>
    </div>

    <div id="box-paypal" class="method-box">
        <div class="breakdown">
            <div class="row"><span>Service Price</span><span>$<?= number_format($subtotal_usd, 2) ?></span></div>
            <div class="row"><span>Tax (<?= $p_tax_pct ?>%)</span><span>$<?= number_format($tax_usd, 2) ?></span></div>
            <div class="row"><span>Merchant Fee</span><span>$<?= number_format($fee_usd, 2) ?></span></div>
            <div class="row total"><span>Total</span><span>$<?= number_format($total_usd, 2) ?> USD</span></div>
        </div>
        <?php if (setting('payment-paypal-enabled') == '1' && $subtotal_usd > 0): ?>
            <button class="btn-paypal" onclick="payPaypal()" style="margin-top:20px">Pay with PayPal</button>
        <?php else: ?>
            <button class="btn-paypal" disabled style="background:#cbd5e1; cursor:not-allowed; margin-top:20px">PayPal Unavailable</button>
        <?php endif; ?>
    </div>

    <div class="helper-text">Secure Transaction • Order ID: <?= $order_id ?></div>
</div>

<script>
function switchTab(method) {
    // Switch tabs styling
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    event.currentTarget.classList.add('active');

    // Switch boxes
    document.querySelectorAll('.method-box').forEach(b => b.classList.remove('active'));
    document.getElementById('box-' + method).classList.add('active');
}

function payDoku() { location.href = "doku-checkout.php?order_id=<?= $order_id ?>"; }
function payPaypal() { location.href = "paypal-create.php?order_id=<?= $order_id ?>"; }
</script>

</body>
</html>
