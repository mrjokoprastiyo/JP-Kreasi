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
        "DELETE FROM clients 
         WHERE id = ? AND user_id = ?",
        [$id, $user_id]
    );

    header("Location: dashboard.php?deleted=1");
    exit;
}

/* ===============================
   FETCH CLIENTS + PRODUCT DATA
================================ */
$clients = DB::fetchAll(
    "SELECT 
        c.id,
        c.name,
        c.service,
        c.provider,
        c.status,
        c.created_at,

        p.category,
        p.sub_category,
        p.service AS product_service

     FROM clients c
     JOIN products p ON p.id = c.product_id
     WHERE c.user_id = ?
     ORDER BY c.created_at DESC",
    [$user_id]
);

/**
 * Resolve edit flow (SAMA seperti create)
 */
function resolveEditFlow(array $c): string
{
    if ($c['category'] === 'desain') {
        return 'DESIGN_ORDER';
    }

    if ($c['category'] === 'automation') {

        if ($c['sub_category'] === 'chatbot' && $c['product_service'] === 'website') {
            return 'CHATBOT_WEB';
        }

        if ($c['sub_category'] === 'chatbot') {
            return 'CHATBOT_CHANNEL';
        }

        if ($c['sub_category'] === 'notification') {
            return 'AUTOMATION_NOTIFICATION';
        }
    }

    return 'UNKNOWN';
}
?>

<main class="user-content">
<div class="page-header">
  <h1>My Clients</h1>
  <a href="../products.php" class="btn primary">➕ Tambah Client</a>
</div>

<?php if (isset($_GET['deleted'])): ?>
    <div class="alert success">Client berhasil dihapus</div>
<?php endif; ?>

<section class="card">

<table class="table">
<thead>
<tr>
    <th>Nama</th>
    <th>Produk</th>
    <th>Service</th>
    <th>Status</th>
    <th>Dibuat</th>
    <th>Aksi</th>
</tr>
</thead>

<tbody>
<?php foreach ($clients as $c): ?>
<?php $flow = resolveEditFlow($c); ?>

<tr>
    <td><?= htmlspecialchars($c['name']) ?></td>

    <td>
        <?= strtoupper($c['category']) ?>
        <?php if ($c['sub_category']): ?>
            <small>(<?= $c['sub_category'] ?>)</small>
        <?php endif; ?>
    </td>

    <td><?= strtoupper($c['service']) ?></td>

    <td>
        <a href="client-detail.php?id=<?= $c['id'] ?>"
           class="status status-link <?= $c['status'] ?>"
           title="Lihat detail client">
            <?= strtoupper($c['status']) ?>
        </a>
    </td>

    <td><?= date('d M Y', strtotime($c['created_at'])) ?></td>

    <td class="actions">

        <?php if ($flow !== 'UNKNOWN'): ?>
            <a class="btn small"
               href="client-edit.php?id=<?= $c['id'] ?>">
                ✏️ Edit
            </a>
        <?php else: ?>
            <span class="muted">N/A</span>
        <?php endif; ?>

        <form method="POST"
              onsubmit="return confirm('Hapus client ini?')">
            <input type="hidden" name="delete_id"
                   value="<?= $c['id'] ?>">
            <button class="btn danger small">
                🗑 Hapus
            </button>
        </form>

    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</section>
</main>

<style>
.page-header {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
}

.page-header h1 {
  margin: 0;
  font-size: 1.5rem;
}

.card {
  background: #ffffff;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 4px 14px rgba(0,0,0,.06);
  overflow-x: auto;
}

.table {
  width: 100%;
  border-collapse: collapse;
  min-width: 720px;
}

.table thead {
  background: #f8fafc;
}

.table th {
  text-align: left;
  font-size: 13px;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: #64748b;
}

.table th,
.table td {
  padding: 14px 16px;
  border-bottom: 1px solid #e5e7eb;
}

.table tbody tr:hover {
  background: #f9fafb;
}

.status {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 600;
  text-decoration: none;
}

.status.active {
  background: #dcfce7;
  color: #166534;
}

.status.pending {
  background: #fef3c7;
  color: #92400e;
}

.status.expired {
  background: #fee2e2;
  color: #991b1b;
}

.status-link {
  position: relative;
  /* padding-right: 18px; */
}

.status-link::after {
  content: "↗";
  position: absolute;
  top: -4px;
  right: -2px;
  font-size: 14px;
  color: #222;
  /* opacity: .7; */
}

.actions {
  display: flex;
  gap: 8px;
  align-items: center;
}

.btn.small {
  padding: 6px 10px;
  font-size: 12px;
  border-radius: 6px;
}

.btn.danger {
  background: #dc2626;
  color: #fff;
}

.btn.danger:hover {
  background: #b91c1c;
}

.alert.success {
  background: #ecfeff;
  border: 1px solid #67e8f9;
  color: #155e75;
  padding: 12px 14px;
  border-radius: 8px;
  margin-bottom: 16px;
}

@media (max-width: 768px) {
  .table {
    min-width: 100%;
  }

  .actions {
    flex-direction: column;
    align-items: stretch;
  }

  .btn.small {
    width: 100%;
    text-align: center;
  }

  .page-header {
    flex-direction: column;
    align-items: stretch;
  }
}
</style>
<?php include 'footer.php'; ?>