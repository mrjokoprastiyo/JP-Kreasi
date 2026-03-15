<?php
session_start();

require_once '../config.php';
require_once '../core/auth.php';
require_once '../core/db.php';

Auth::check();
$user_id = $_SESSION['user']['id'];

$id = (int) ($_GET['id'] ?? 0);

$client = DB::fetch(
    "SELECT * FROM clients WHERE id = ? AND user_id = ?",
    [$id, $user_id]
);

if (!$client) {
    die('Client tidak ditemukan');
}

/* ===============================
   HANDLE UPDATE
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    DB::execute("
        UPDATE clients SET
            name = ?,
            domain = ?,
            prompt = ?,
            bot_name = ?,
            bot_desc = ?,
            bot_greeting = ?,
            widget_background = ?,
            updated_at = NOW()
        WHERE id = ? AND user_id = ?
    ", [
        $_POST['name'],
        $_POST['domain'],
        $_POST['prompt'],
        $_POST['bot_name'],
        $_POST['bot_desc'],
        $_POST['bot_greeting'],
        $_POST['widget_background'],
        $id,
        $user_id
    ]);

    header("Location: client-detail.php?id=$id&updated=1");
    exit;
}

include 'header.php';
include 'sidebar.php';
?>

<main class="user-content">
<h1>Edit Client</h1>

<form method="POST" class="form-grid">

<section class="card">
<label>Nama Client</label>
<input type="text" name="name" required value="<?= htmlspecialchars($client['name']) ?>">

<label>Domain</label>
<input type="text" name="domain" value="<?= htmlspecialchars($client['domain']) ?>">
</section>

<section class="card">
<h3>Identitas Bot</h3>

<label>Nama Bot</label>
<input type="text" name="bot_name" value="<?= htmlspecialchars($client['bot_name']) ?>">

<label>Deskripsi Bot</label>
<input type="text" name="bot_desc" value="<?= htmlspecialchars($client['bot_desc']) ?>">

<label>Greeting</label>
<textarea name="bot_greeting"><?= htmlspecialchars($client['bot_greeting']) ?></textarea>
</section>

<section class="card">
<h3>Prompt</h3>
<textarea name="prompt" rows="6"><?= htmlspecialchars($client['prompt']) ?></textarea>
</section>

<section class="card">
<label>Widget Background</label>
<input type="color" name="widget_background"
       value="<?= htmlspecialchars($client['widget_background'] ?: '#2563eb') ?>">
</section>

<section class="card highlight">
<button class="btn primary">💾 Simpan Perubahan</button>
<a href="clients.php" class="btn">⬅ Kembali</a>
</section>

</form>
</main>

<style>
.form-grid{max-width:800px;display:grid;gap:20px}
.card{background:#fff;padding:20px;border-radius:10px}
.highlight{border:1px dashed #d1d5db}
</style>

<?php include 'footer.php'; ?>