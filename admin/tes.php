<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/db.php';

Auth::check();

/* ===============================
   FETCH PROVIDERS
================================ */
// PROVIDERS
$providers = DB::fetchAll("
  SELECT id, provider_name, status
  FROM ai_configs
  WHERE post_type = 'ai_provider'
  ORDER BY provider_name ASC
");

/* ===============================
   FETCH MODELS
================================ */
// MODELS
$models = DB::fetchAll("
  SELECT id, provider_name, model, status
  FROM ai_configs
  WHERE post_type = 'ai_model'
  ORDER BY model ASC
");

/* ===============================
   SUMMARY COUNTS
================================ */
$aiProviderCount = is_array($providers) ? count($providers) : 0;
$aiModelCount    = is_array($models) ? count($models) : 0;

/* ===============================
   MESSAGE PROVIDERS
================================ */
$msgProviders = DB::fetchAll("
  SELECT id FROM message_providers
");
$msgProviderCount = is_array($msgProviders) ? count($msgProviders) : 0;

/* ===============================
   SETTINGS COUNT
================================ */
$settingsCount = (int) DB::fetchColumn("SELECT COUNT(*) FROM settings");

/* ===============================
   GROUP MODELS BY PROVIDER
================================ */
$modelsByProvider = [];

foreach ($models as $m) {
    if (!empty($m['provider_name'])) {
        $modelsByProvider[$m['provider_name']][] = $m;
    }
}

include __DIR__ . '/layout/header.php';
?>

<div class="dashboard">

<!-- ===================== -->  <!-- SUMMARY CARDS -->  <!-- ===================== -->  

<div class="cards">
  <div class="card">
    <h3>AI Providers</h3>
    <p><?= $aiProviderCount ?></p>
    <small><?= $aiModelCount ?> Models</small>
  </div>

  <div class="card">
    <h3>Message Providers</h3>
    <p><?= $msgProviderCount ?></p>
  </div>

  <div class="card">
    <h3>System Settings</h3>
    <p><?= $settingsCount ?></p>
  </div>
</div>

<section class="panel">
  <div class="panel-header">
    <h2>AI Providers</h2>
    <a href="ai-editor.php" class="btn">+ Provider</a>
  </div>

  <table>
    <thead>
      <tr>
        <th>Provider</th>
        <th>Models</th>
        <th>Status</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>

<?php foreach ($providers as $p): ?>
<tr>
  <td><strong><?= htmlspecialchars($p['provider_name']) ?></strong></td>

  <td>
    <?php if (!empty($modelsByProvider[$p['id']])): ?>
      <ul class="model-list">
        <?php foreach ($modelsByProvider[$p['id']] as $m): ?>
          <li>
            <?= htmlspecialchars($m['model']) ?>

            <a href="ai-editor.php?id=<?= $m['id'] ?>" class="btn-sm">Edit</a>

            <a href="ai-delete.php?id=<?= $m['id'] ?>"
               class="btn-sm btn-danger"
               onclick="return confirm('Hapus model ini?')">
               Hapus
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <em class="muted">No models</em>
    <?php endif; ?>
  </td>

  <td>
    <span class="status <?= $p['status'] ?>"><?= $p['status'] ?></span>
  </td>

  <td>
    <a href="ai-editor.php?id=<?= $p['id'] ?>" class="btn-sm">Edit</a>
    <a href="ai-editor.php?provider_id=<?= $p['id'] ?>" class="btn-sm">+ Model</a>
  </td>
</tr>
<?php endforeach; ?>

    </tbody>
  </table>
</section>

<!-- ===================== -->  <!-- MESSAGE PROVIDERS -->  <!-- ===================== -->  <section class="panel">
    <div class="panel-header">
      <h2>Message Providers</h2>
      <a href="message-providers.php" class="btn">+ Add</a>
    </div>
    <table>
      <thead>
        <tr>
          <th>Channel</th>
          <th>Provider</th>
          <th>Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($msgProviders as $mp): ?>
        <tr>
          <td><?= ucfirst($mp['channel']) ?></td>
          <td><?= htmlspecialchars($mp['provider_name']) ?></td>
          <td><span class="status <?= $mp['status'] ?>"><?= $mp['status'] ?></span></td>
          <td>
            <a href="message-providers.php?id=<?= $mp['id'] ?>" class="btn-sm">Edit</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </section>  <!-- ===================== -->  <!-- SYSTEM SETTINGS -->  <!-- ===================== -->  <section class="panel">
    <div class="panel-header">
      <h2>System Settings</h2>
      <a href="system-settings.php" class="btn">Open</a>
    </div>
    <p>Total settings stored: <strong><?= $settingsCount ?></strong></p>
  </section>

</div>

<style>
.dashboard { padding: 20px; }
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px,1fr));
  gap: 16px;
  margin-bottom: 24px;
}
.card {
  background: #fff;
  padding: 20px;
  border-radius: 10px;
  box-shadow: 0 2px 8px rgba(0,0,0,.05);
}
.card h3 { margin-bottom: 10px; font-size: 14px; color: #555; }
.card p { font-size: 28px; font-weight: bold; }

.panel {
  background: #fff;
  padding: 20px;
  border-radius: 10px;
  margin-bottom: 24px;
}
.panel-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 16px;
}

.table-wrapper { overflow-x: auto; }

table {
  width: 100%;
  border-collapse: collapse;
}
th, td {
  padding: 10px;
  border-bottom: 1px solid #eee;
  text-align: left;
}
th { font-size: 13px; color: #555; }

.status.active { color: green; font-weight: 600; }
.status.inactive { color: red; font-weight: 600; }

.btn {
  background: #2563eb;
  color: #fff;
  padding: 8px 14px;
  border-radius: 6px;
  text-decoration: none;
}
.btn-sm {
  background: #111827;
  color: #fff;
  padding: 6px 10px;
  border-radius: 5px;
  text-decoration: none;
  font-size: 13px;
}
.btn-provider-edit { background:#2563eb; }
.btn-provider-delete { background:#dc2626; }

.btn-model-edit { background:#059669; }
.btn-model-delete { background:#b91c1c; }

.btn-add-model { background:#7c3aed; }

.model-list {
  list-style:none;
  padding:0;
}
.model-list li {
  display:flex;
  gap:8px;
  align-items:center;
  margin-bottom:6px;
}

.muted { color:#9ca3af; }
</style>

<script>
function deleteProvider(id, status) {
  if (status === 'active') {
    alert('Provider masih aktif. Nonaktifkan dulu.');
    return;
  }

  if (!confirm('Hapus provider dan semua modelnya?')) return;

  fetch('ajax/ai-delete.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'id='+id+'&type=provider'
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      document.getElementById('provider-row-'+id).remove();
    } else {
      alert(res.error);
    }
  });
}

function deleteModel(id, status) {
  if (status === 'active') {
    alert('Model masih aktif. Nonaktifkan dulu.');
    return;
  }

  if (!confirm('Hapus model ini?')) return;

  fetch('ajax/ai-delete.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'id='+id+'&type=model'
  })
  .then(r => r.json())
  .then(res => {
    if (res.success) {
      document.getElementById('model-row-'+id).remove();
    } else {
      alert(res.error);
    }
  });
}
</script>
<?php include __DIR__ . '/layout/footer.php'; ?>