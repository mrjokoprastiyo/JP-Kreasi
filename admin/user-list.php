<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

// --- CONFIG SEARCH & PAGINATION ---
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 10;
$page  = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$whereClause = "1=1";
$params = [];

if ($search !== '') {
    $whereClause = "(username LIKE ? OR email LIKE ? OR fullname LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}

// Total untuk Pagination
$totalItems = DB::fetchColumn("SELECT COUNT(*) FROM users WHERE $whereClause", $params);
$totalPages = ceil($totalItems / $limit);

// Ambil Data
$users = DB::fetchAll("SELECT * FROM users WHERE $whereClause ORDER BY created_at DESC LIMIT $limit OFFSET $offset", $params);

include "header.php";
include "sidebar.php";
?>

<main class="user-content">
<div class="settings-container">
    <div class="page-header">
        <div>
            <h2 style="margin:0;">👥 User Management</h2>
            <p style="color:var(--text-muted); font-size:0.85em;">Kelola akses akun admin dan pengguna sistem.</p>
        </div>
        <a href="user-create.php" class="btn-create">+ Create New User</a>
    </div>

    <section class="card" style="margin-bottom: 20px; padding: 15px;">
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                   placeholder="Cari username, nama, atau email..." style="flex:1;">
            <button type="submit" class="btn-save" style="margin:0; width:auto; padding:0 20px; height:40px;">Cari</button>
            <?php if($search): ?> <a href="user-list.php" class="btn-create" style="background:#e2e8f0; color:#475569;">Reset</a> <?php endif; ?>
        </form>
    </section>

    <section class="card" style="padding: 0; overflow: hidden;">
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.9em;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
                        <th style="padding: 15px;">User Info</th>
                        <th style="padding: 15px;">Role</th>
                        <th style="padding: 15px;">Status</th>
                        <th style="padding: 15px;">Last Login</th>
                        <th style="padding: 15px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 15px;">
                            <div style="font-weight:700; color:var(--text-main);"><?= htmlspecialchars($u['fullname']) ?></div>
                            <div style="font-size:0.8em; color:var(--text-muted);">@<?= htmlspecialchars($u['username']) ?> • <?= htmlspecialchars($u['email']) ?></div>
                        </td>
                        <td style="padding: 15px;">
                            <span class="category-tag" style="background: <?= $u['role'] == 'admin' ? '#ffe4e6; color:#e11d48;' : '#f1f5f9;' ?>">
                                <?= strtoupper($u['role']) ?>
                            </span>
                        </td>
                        <td style="padding: 15px;">
                            <span class="status-badge <?= $u['status'] == 'active' ? 'status-active' : 'status-inactive' ?>">
                                <?= $u['status'] ?>
                            </span>
                        </td>
                        <td style="padding: 15px; font-size:0.85em; color:var(--text-muted);">
                            <?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : 'Never' ?>
                        </td>
                        <td style="padding: 15px; text-align: center;">
                            <div style="display:flex; gap:8px; justify-content:center;">
                                <a href="user-edit.php?id=<?= $u['id'] ?>" class="btn-sm">✏️ Edit</a>
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                    <a href="user-delete.php?id=<?= $u['id'] ?>" class="btn-sm btn-danger" onclick="return confirm('Hapus user ini?')">🗑️</a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
<?php if ($totalPages > 1): ?>
<?php 
    $searchQuery = $search !== '' ? '&search=' . urlencode($search) : ''; 
    $range = 2; // Menampilkan 2 angka di kiri dan kanan halaman aktif
?>
<div style="padding: 15px 20px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 15px;">
    <div style="font-size: 0.8em; color: #64748b;">
        Showing <strong><?= $offset + 1 ?></strong> - <strong><?= min($offset + $limit, $totalItems) ?></strong> of <strong><?= $totalItems ?></strong> Users
    </div>
    
    <div style="display: flex; gap: 5px; align-items: center;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?= $searchQuery ?>" class="pagination-link" title="First Page">«</a>
            <a href="?page=<?= $page - 1 ?><?= $searchQuery ?>" class="pagination-link">Prev</a>
        <?php endif; ?>

        <?php
        for ($i = 1; $i <= $totalPages; $i++) {
            // Tampilkan halaman pertama, terakhir, dan range sekitar halaman aktif
            if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                $activeClass = ($i == $page) ? 'pagination-active' : '';
                echo "<a href='?page=$i$searchQuery' class='pagination-link $activeClass'>$i</a>";
            } 
            // Tambahkan ellipsis jika ada jarak
            elseif ($i == $page - $range - 1 || $i == $page + $range + 1) {
                echo "<span style='color:#94a3b8; padding: 0 5px;'>...</span>";
            }
        }
        ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?= $page + 1 ?><?= $searchQuery ?>" class="pagination-link">Next</a>
            <a href="?page=<?= $totalPages ?><?= $searchQuery ?>" class="pagination-link" title="Last Page">»</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

    </section>
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