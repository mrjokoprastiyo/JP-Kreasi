<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

/* ===============================
   AMBIL DATA PRODUK
================================ */
$products = DB::fetchAll("
    SELECT *
    FROM products
    ORDER BY created_at DESC
");

include "header.php";
include "sidebar.php";
?>

<main class="user-content">
<div class="settings-container">
    
<div class="page-header">
    <div>
        <h2 style="margin: 0;">📦 Product Management</h2>
        <p style="color: var(--text-muted); font-size: 0.85em; margin-top: 5px;">Kelola daftar layanan, tiering, dan harga langganan.</p>
    </div>
    <a href="product-create.php" class="btn-create">
        + Create New Product
    </a>
</div>

    <?php if (isset($_GET['updated'])): ?>
        <div style="background:#dcfce7; color:#166534; padding:15px; border-radius:10px; border:1px solid #bbf7d0; margin-bottom: 20px;">
            ✅ Data produk telah berhasil diperbarui.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
        <div style="background:#fef2f2; color:#991b1b; padding:15px; border-radius:10px; border:1px solid #fecaca; margin-bottom: 20px;">
            🗑️ Produk telah dihapus dari sistem.
        </div>
    <?php endif; ?>

    <section class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <!-- <thead> -->
<thead>
    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
        <th style="padding: 15px 20px; font-size: 0.8em; color: #64748b;">NO</th>
        <th style="padding: 15px 20px; font-size: 0.8em; color: #64748b;">PRODUCT NAME</th>
        <th style="padding: 15px 20px; font-size: 0.8em; color: #64748b;">CATEGORY</th>
        <th style="padding: 15px 20px; font-size: 0.8em; color: #64748b;">SERVICE</th>
        <th style="padding: 15px 20px; font-size: 0.8em; color: #64748b;">TIER/DURATION</th>
        <th style="padding: 15px 20px; font-size: 0.8em; color: #64748b;">PRICING (IDR/USD)</th>
        <th style="padding: 15px 20px; font-size: 0.8em; color: #64748b;">STATUS</th>
        <th style="padding: 15px 20px; font-size: 0.8em; color: #64748b;">ACTION</th>
    </tr>
</thead>
<tbody>
    <?php if (!$products): ?>
    <?php else: ?>
    <?php foreach ($products as $i => $p): ?>
    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
        <td style="padding: 15px 20px; color: #94a3b8;"><?= $i + 1 ?></td>
        <td style="padding: 15px 20px;">
            <span style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($p['name']) ?></span>
        </td>
        <td style="padding: 15px 20px;">
            <span class="category-tag"><?= htmlspecialchars($p['category']) ?></span>
            <br><small style="color: #94a3b8; font-size: 0.75em;"><?= htmlspecialchars($p['sub_category']) ?></small>
        </td>
        <td style="padding: 15px 20px;">
            <span style="text-transform: capitalize; font-size: 0.9em;"><?= $p['service'] ?></span>
        </td>
        <td style="padding: 15px 20px;">
            <span style="font-weight: 600; color: #475569;"><?= $p['tier'] ?></span>
            <br><small style="color: #64748b;"><?= $p['duration'] ?> Days</small>
        </td>
        
        <td style="padding: 15px 20px;">
            <div class="price-text" style="color: var(--text-main);">
                Rp <?= number_format($p['price_idr'], 0, ',', '.') ?>
            </div>
            <div style="font-size: 0.8em; color: #0070ba; font-weight: 600;">
                $<?= number_format($p['price_usd'], 2) ?> USD
            </div>
        </td>

        <td style="padding: 15px 20px;">
            <?php if($p['status'] === 'active'): ?>
                <span class="status-badge status-active">Active</span>
            <?php else: ?>
                <span class="status-badge status-inactive">Inactive</span>
            <?php endif; ?>
        </td>
        <td style="padding: 15px 20px; text-align: center;">
            <div style="display: flex; gap: 8px; justify-content: center;">
                <a href="product-edit.php?id=<?= $p['id'] ?>" class="btn-sm" style="text-decoration: none; color: var(--primary-color);">Edit</a>
                <a href="product-delete.php?id=<?= $p['id'] ?>" class="btn-sm" style="text-decoration: none; color: var(--danger); border-color: #fecaca;" onclick="return confirm('Hapus produk <?= htmlspecialchars($p['name']) ?>?')">Hapus</a>
            </div>
        </td>
    </tr>
    <?php endforeach; ?>
    <?php endif; ?>
</tbody>

            </table>
        </div>
    </section>

    <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center;">
        <p style="font-size: 0.85em; color: #64748b;">Total Products: <strong><?= count($products) ?></strong></p>
        <div class="actions">
            <a href="dashboard.php" style="text-decoration: none; color: #64748b; font-size: 0.85em;">← Back to Dashboard</a>
        </div>
    </div>
</div>
</main>

<style>
    /* 1. VARIABLES & RESET */
    :root {
        --primary-color: #4f46e5;
        --primary-hover: #4338ca;
        --border-color: #e2e8f0;
        --bg-card: #ffffff;
        --bg-light: #f8fafc;
        --bg-disabled: #f1f5f9;
        --text-main: #1e293b;
        --text-muted: #64748b;
        --danger: #ef4444;
        --success: #15803d;
        --warning: #92400e;
    }

    /* 2. LAYOUT & CONTAINERS */
    .settings-container {
        max-width: 1100px;
        margin: 0 auto;
        padding: 20px;
        font-family: 'Inter', system-ui, sans-serif;
    }

    .page-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 25px;
    }

    .page-header h2 { margin: 0; font-size: 1.5rem; color: var(--text-main); }

    /* 3. GRID SYSTEM */
    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
        margin-bottom: 25px;
    }

    .grid-inner { display: grid; gap: 15px; }
    .grid-2 { grid-template-columns: repeat(2, 1fr); }
    .grid-3 { grid-template-columns: repeat(3, 1fr); }
    .grid-4 { grid-template-columns: repeat(4, 1fr); }

    .card {
        background: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .card-full { grid-column: 1 / -1; }

    /* 4. FORM ELEMENTS */
    h3 {
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 1rem;
        color: var(--primary-color);
        display: flex;
        align-items: center;
        gap: 8px;
        border-bottom: 1px solid var(--bg-disabled);
        padding-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group { margin-bottom: 15px; }

    label {
        display: block;
        font-weight: 600;
        font-size: 0.85em;
        margin-bottom: 6px;
        color: var(--text-main);
    }

    input, select, textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 0.9em;
        box-sizing: border-box;
        transition: all 0.2s ease;
    }

    input:focus, select:focus, textarea:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
    }

    input:disabled, .input-disabled {
        background: var(--bg-disabled);
        color: var(--text-muted);
        cursor: not-allowed;
        font-weight: 600;
    }

    .stat-box {
        padding: 10px;
        background: var(--bg-disabled);
        border-radius: 8px;
        text-align: center;
        font-weight: bold;
        border: 1px solid var(--border-color);
    }

    /* Enhancement untuk Product List */
    .category-tag {
        display: inline-block;
        background: var(--bg-disabled);
        color: var(--text-muted);
        padding: 2px 8px;
        border-radius: 6px;
        font-size: 0.75em;
        font-weight: 600;
        text-transform: uppercase;
    }

    .price-text {
        font-family: 'JetBrains Mono', monospace; /* Kalau ada, kalau tidak pakai sans-serif */
        font-weight: 700;
        color: var(--text-main);
        font-size: 0.95em;
    }

    /* Memperbaiki alignment tabel */
    table th {
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    /* 5. BUTTONS */
    .btn-create, .btn-save {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        background: var(--primary-color);
        color: white;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }

    .btn-save { width: 100%; font-size: 1em; padding: 14px; }
    .btn-create:hover, .btn-save:hover { background: var(--primary-hover); transform: translateY(-1px); }
    
    .action-bar { margin-top: 20px; display: flex; gap: 10px; }
    .btn-reset { 
        background: #94a3b8; color: white; border: none; padding: 12px; 
        border-radius: 8px; cursor: pointer; flex: 1; font-weight: 600;
    }

    .btn-sm { 
        padding: 5px 10px; background: var(--bg-disabled); 
        border: 1px solid var(--border-color); border-radius: 6px; 
        cursor: pointer; font-size: 0.8em; 
    }

    .btn-sm:hover {
        background: var(--bg-light);
        border-color: var(--primary-color);
        transform: translateY(-1px);
    }

    /* 6. COMPONENTS (Badges, Avatars, etc) */
    .status-badge {
        padding: 4px 12px;
        border-radius: 50px;
        font-size: 0.75em;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-active { background: #dcfce7; color: var(--success); }
    .status-inactive, .status-suspended { background: #fee2e2; color: #b91c1c; }
    .status-pending { background: #fef3c7; color: var(--warning); }

    .bot-avatar { 
        width: 40px; height: 40px; border-radius: 10px; 
        object-fit: cover; background: var(--bg-disabled); border: 1px solid var(--border-color);
    }

    .table-responsive { width: 100%; overflow-x: auto; }

    /* 7. RESPONSIVE BREAKPOINTS */
    @media (max-width: 900px) {
        .grid-3, .grid-4 { grid-template-columns: repeat(2, 1fr); }
    }

    @media (max-width: 600px) {
        .settings-grid, .grid-inner, .grid-2, .grid-3, .grid-4 { 
            grid-template-columns: 1fr; 
        }
        .action-bar { flex-direction: column; }
        .card-full { grid-column: auto; }
    }
</style>

<?php include "footer.php"; ?>