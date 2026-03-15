<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

/* ===============================
   FILTER & QUERY BUILDER
================================ */

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 10;
$offset = ($page - 1) * $limit;

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = "(c.name LIKE ? 
              OR c.bot_name LIKE ? 
              OR c.domain LIKE ? 
              OR c.api_key LIKE ? 
              OR u.username LIKE ?)";
    array_push($params,
        "%$search%", "%$search%", "%$search%", "%$search%", "%$search%"
    );
}

if ($status !== '' && in_array($status, ['active','pending','suspended','expired'])) {
    $where[] = "c.status = ?";
    $params[] = $status;
}

$whereSQL = $where ? implode(' AND ', $where) : '1=1';

/* ===============================
   FETCH TOTAL & DATA
================================ */

$totalItems = DB::fetchColumn("
    SELECT COUNT(*) 
    FROM clients c 
    JOIN users u ON u.id = c.user_id
    WHERE $whereSQL
", $params);

$totalPages = ceil($totalItems / $limit);

$clients = DB::fetchAll("
    SELECT 
        c.*,
        u.username,
        u.fullname,
        TIMESTAMPDIFF(HOUR, c.last_message_at, NOW()) AS idle_hours
    FROM clients c
    JOIN users u ON u.id = c.user_id
    WHERE $whereSQL
    ORDER BY c.created_at DESC
    LIMIT $limit OFFSET $offset
", $params);

/* ===============================
   HELPERS
================================ */

function statusClass($s) {
    return match ($s) {
        'active'    => 'status-active',
        'pending'   => 'status-pending',
        'suspended' => 'status-suspended',
        'expired'   => 'status-expired',
        default     => ''
    };
}

function healthBadge($row) {
    if (!$row['last_message_at']) return '🔴 Dead';
    if ($row['idle_hours'] <= 24) return '🟢 Active';
    if ($row['idle_hours'] <= 168) return '🟡 Idle';
    return '🔴 Dead';
}

include "header.php";
include "sidebar.php";
?>

<main class="user-content">
<div class="settings-container">

<header class="page-header">
    <div>
        <h2>🏢 Client Instances</h2>
        <p>Master kontrol seluruh bot & service client</p>
    </div>
    <a href="../user/products.php" class="btn-create">+ Deploy Client</a>
</header>

<section class="card" style="margin-bottom:20px;">
<form method="GET" style="display:flex;gap:10px;flex-wrap:wrap">

<input type="text" name="search" value="<?= e($search) ?>"
       placeholder="Search name, domain, key, user…">

<div style="display:flex;gap:6px">
<?php
$filters = [''=>'All','active'=>'Active','pending'=>'Pending','suspended'=>'Suspended','expired'=>'Expired'];
foreach ($filters as $k=>$v):
?>
<a href="?status=<?= $k ?>&search=<?= urlencode($search) ?>"
   class="btn-sm <?= $status===$k?'pagination-active':'' ?>">
   <?= $v ?>
</a>
<?php endforeach; ?>
</div>

</form>
</section>

<section class="card" style="padding:0">
<div class="table-responsive">
<table>
<thead>
<tr>
<th>Client</th>
<th>Owner</th>
<th>Tech</th>
<th>Service</th>
<th>Activity</th>
<th>Status</th>
<th>Actions</th>
</tr>
</thead>

<tbody>
<?php if (!$clients): ?>
<tr><td colspan="7" class="empty">No clients found</td></tr>
<?php endif; ?>

<?php foreach ($clients as $c):
$meta = json_decode($c['meta'] ?? '{}', true);
?>
<tr>

<td>
<div class="row-flex">
<img src="<?= $c['bot_avatar'] ?: 'assets/img/default-bot.png' ?>">
<div>
<strong><?= e($c['name']) ?></strong>
<small><?= e($c['bot_name'] ?: '-') ?></small>
</div>
</div>
</td>

<td>
<strong><?= e($c['username']) ?></strong>
<small>PID <?= $c['product_id'] ?> (<?= $c['product_type'] ?>)</small>
</td>

<td>
🌐 <?= e($c['domain'] ?: '-') ?><br>
<code><?= substr($c['api_key'],0,10) ?>…</code>
</td>

<td>
<span class="badge"><?= $c['service'] ?></span><br>
<small><?= strtoupper($c['provider']) ?></small>
</td>

<td>
💬 <?= number_format($c['message_count']) ?><br>
<small><?= healthBadge($c) ?></small>
<?php if (!empty($meta['last_error'])): ?>
<div class="error">⚠ <?= e($meta['last_error']) ?></div>
<?php endif; ?>
</td>

<td>
<span class="status-badge <?= statusClass($c['status']) ?>">
<?= strtoupper($c['status']) ?>
</span>
<small>
<?= $c['expired_at']
    ? 'Exp: '.date('d/m/Y',strtotime($c['expired_at']))
    : 'Lifetime' ?>
</small>
</td>

<td class="actions">
<a href="client-edit.php?id=<?= $c['id'] ?>">⚙️</a>
<a href="client-toggle.php?id=<?= $c['id'] ?>">⛔</a>
<a href="client-key.php?id=<?= $c['id'] ?>">🔑</a>
<a href="client-delete.php?id=<?= $c['id'] ?>"
   onclick="return confirm('Delete client?')">🗑️</a>
</td>

</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>

<?php if ($totalPages > 1): ?>
<nav class="pagination">
<?php for ($i=1;$i<=$totalPages;$i++): ?>
<a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $status ?>"
   class="<?= $i==$page?'pagination-active':'' ?>">
<?= $i ?>
</a>
<?php endfor; ?>
</nav>
<?php endif; ?>

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

   small {
        display: block;
        margin-top: 5px;
        color: var(--text-muted);
        font-size: 0.75em;
    }

    hr {
        border: 0;
        border-top: 1px solid #f1f5f9;
        margin: 20px 0;
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

    /* 8. PAGINATION ENHANCEMENT */
    .pagination-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 35px;
        height: 35px;
        padding: 0 8px;
        background: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        color: var(--text-main);
        font-size: 0.85em;
        font-weight: 600;
        text-decoration: none; /* Menghapus garis bawah */
        transition: all 0.2s ease;
    }

    .pagination-link:hover {
        background: var(--bg-light);
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateY(-1px);
    }

    .pagination-active {
        background: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
        color: #ffffff !important;
        box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.2);
    }

    /* Responsive Pagination */
    @media (max-width: 600px) {
        .pagination-link {
            min-width: 30px;
            height: 30px;
            font-size: 0.75em;
        }
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