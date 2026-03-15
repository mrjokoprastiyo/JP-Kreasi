<?php
require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

$id = $_GET['id'] ?? 0;
$user = DB::fetch("SELECT * FROM users WHERE id = ?", [$id]);

if (!$user) die("User tidak ditemukan.");

include "header.php";
include "sidebar.php";
?>

<main class="user-content">
<div class="settings-container">
    <div class="page-header">
        <div>
            <h2 style="margin:0;">👤 Edit User Account</h2>
            <p style="color:var(--text-muted); font-size:0.85em;">Update kredensial dan status akses pengguna.</p>
        </div>
        <a href="user-list.php" class="btn-create" style="background:#64748b;">← Kembali</a>
    </div>

    <form action="user-update.php" method="POST">
        <input type="hidden" name="id" value="<?= $user['id'] ?>">
        
        <div class="settings-grid">
            <section class="card">
                <h3>🆔 Account Profile</h3>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled style="background:#f1f5f9;">
                    <small>Username tidak dapat diubah untuk integritas data.</small>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="fullname" value="<?= htmlspecialchars($user['fullname']) ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                </div>
            </section>

            <section class="card">
                <h3>🔐 Security & Status</h3>
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="user" <?= $user['role'] == 'user' ? 'selected' : '' ?>>Standard User</option>
                        <option value="admin" <?= $user['role'] == 'admin' ? 'selected' : '' ?>>Administrator</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Account Status</label>
                    <select name="status">
                        <option value="active" <?= $user['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="blocked" <?= $user['status'] == 'blocked' ? 'selected' : '' ?>>Blocked / Suspend</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reset Password</label>
                    <input type="password" name="password" placeholder="Kosongkan jika tidak ingin diubah">
                    <small>Minimal 8 karakter jika diisi.</small>
                </div>
            </section>
        </div>

        <button type="submit" class="btn-save">💾 Update User Data</button>
    </form>
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

    @media (max-width: 600px) {
        .settings-grid, .grid-inner, .grid-2, .grid-3, .grid-4 { 
            grid-template-columns: 1fr; 
        }
        .action-bar { flex-direction: column; }
        .card-full { grid-column: auto; }
    }
</style>

<?php include "footer.php"; ?>