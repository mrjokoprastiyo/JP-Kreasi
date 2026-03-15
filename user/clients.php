<?php
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();

$user_id = $_SESSION['user']['id'];

include 'header.php';
include 'sidebar.php';

/* ===============================
   HANDLE DELETE
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id'];

    DB::execute(
        "DELETE FROM clients WHERE id = ? AND user_id = ?",
        [$id, $user_id]
    );

    header("Location: clients.php?deleted=1");
    exit;
}

/* ===============================
   FETCH CLIENTS
================================ */
$clients = DB::fetchAll(
    "SELECT id, name, service, provider, status, created_at
     FROM clients
     WHERE user_id = ?
     ORDER BY created_at DESC",
    [$user_id]
);
?>

<main class="user-content">
<h1>My Clients</h1>

<a href="products.php" class="btn primary">➕ Tambah Client</a>

<table class="table">
<thead>
<tr>
    <th>Nama</th>
    <th>Service</th>
    <th>Provider</th>
    <th>Status</th>
    <th>Dibuat</th>
    <th>Aksi</th>
</tr>
</thead>
<tbody>
<?php foreach ($clients as $c): ?>
<tr>
    <td><?= htmlspecialchars($c['name']) ?></td>
    <td><?= strtoupper($c['service']) ?></td>
    <td><?= htmlspecialchars($c['provider']) ?></td>
    <td><span class="status <?= $c['status'] ?>">
        <?= strtoupper($c['status']) ?>
    </span></td>
    <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>
    <td class="actions">
        <a class="btn small" href="client-edit.php?id=<?= $c['id'] ?>">✏️ Edit</a>

        <form method="POST" onsubmit="return confirm('Hapus client ini?')">
            <input type="hidden" name="delete_id" value="<?= $c['id'] ?>">
            <button class="btn danger small">🗑 Hapus</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</main>

<style>
.table{width:100%;border-collapse:collapse;margin-top:20px}
.table th,.table td{padding:12px;border-bottom:1px solid #e5e7eb}
.actions{display:flex;gap:8px}
.btn.small{padding:6px 10px;font-size:13px}
.btn.danger{background:#dc2626;color:#fff}
.status.active{color:green}
.status.pending{color:orange}
.status.expired{color:red}
</style>

<?php include 'footer.php'; ?>