<?php
require_once __DIR__ . '/../../core/base.php';
require_once __DIR__ . '/../../core/settings-loader.php';
require_once __DIR__ . '/../../core/auth.php';

Auth::check();

$client_id = (int)($_GET['id'] ?? 0);

$client = DB::fetch("SELECT * FROM clients WHERE id=? AND user_id=?", [
    $client_id,
    $_SESSION['user']['id']
]);

if (!$client) {
    die("Channel tidak ditemukan.");
}

$cred = json_decode($client['credentials'], true) ?? [];
$meta = json_decode($client['meta'], true) ?? [];

$aiEngine = $meta['ai_engine'] ?? [
    'provider' => '',
    'model'    => ''
];

$service = $client['service'];

$aiRows = DB::fetchAll("
    SELECT provider_slug, provider_name, model
    FROM ai_configs
    WHERE status='active'
    ORDER BY provider_name ASC
");

$aiProviders = [];
foreach ($aiRows as $row) {
    $aiProviders[$row['provider_slug']]['name'] = $row['provider_name'];
    $aiProviders[$row['provider_slug']]['models'][] = $row['model'];
}
?>

<main class="user-content">
<header class="page-header">
    <h1>Edit Chatbot Channel</h1>
</header>

<form method="POST" action="chatbot-channel-update.php" class="form-grid">
<input type="hidden" name="client_id" value="<?= $client_id ?>">

<section class="card">
    <h3>Informasi Bot</h3>

    <label>Nama Bot</label>
    <input type="text" name="name"
           value="<?= htmlspecialchars($client['name']) ?>" required>

    <label>Channel</label>
    <input type="text" value="<?= strtoupper($service) ?>" disabled>
</section>

<?php if (in_array($service,['messenger','comment'])): ?>

<section class="card">
    <h3>Koneksi Facebook Page</h3>

    <label>Page Name</label>
    <input type="text"
           value="<?= htmlspecialchars($cred['page_name'] ?? '-') ?>" disabled>

    <label>Page ID</label>
    <input type="text"
           value="<?= htmlspecialchars($cred['page_id'] ?? '-') ?>" disabled>

    <a href="<?= BASE_URL ?>/user/meta-start.php?mode=refresh&client_id=<?= $client_id ?>&channel=<?= $service ?>"
       class="btn secondary">
       🔄 Generate / Refresh Token
    </a>

    <p class="helper-text">
        Identity tidak dapat diganti. Hanya token diperbarui.
    </p>
</section>

<?php elseif ($service === 'whatsapp'): ?>

<section class="card">
    <h3>Koneksi WhatsApp</h3>

    <label>Phone Number ID</label>
    <input type="text"
           value="<?= htmlspecialchars($cred['phone_number_id'] ?? '-') ?>" disabled>

    <label>WABA ID</label>
    <input type="text"
           value="<?= htmlspecialchars($cred['waba_id'] ?? '-') ?>" disabled>

    <a href="<?= BASE_URL ?>/user/meta-start.php?mode=refresh&client_id=<?= $client_id ?>&channel=whatsapp ?>"
       class="btn secondary">
       🔄 Generate / Refresh Token
    </a>
</section>

<?php elseif ($service === 'telegram'): ?>

<section class="card">
    <h3>Koneksi Telegram</h3>

    <label>Bot Token</label>
    <input type="text"
           name="bot_token"
           value="<?= htmlspecialchars($cred['bot_token'] ?? '') ?>">
</section>

<?php endif; ?>

<section class="card">
    <h3>AI Engine</h3>

    <label>AI Provider</label>
    <select name="ai_provider" id="aiProvider" required onchange="updateModels()">
        <?php foreach ($aiProviders as $slug => $p): ?>
            <option value="<?= htmlspecialchars($slug) ?>"
                <?= $aiEngine['provider']===$slug?'selected':'' ?>>
                <?= htmlspecialchars($p['name']) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <label>AI Model</label>
    <select name="ai_model" id="aiModel" required></select>
</section>

<section class="card highlight">
    <button type="submit" class="btn primary">
        💾 Simpan Perubahan
    </button>
</section>

</form>
</main>

<script>
const aiData = <?= json_encode($aiProviders) ?>;
const currentModel = "<?= htmlspecialchars($aiEngine['model']) ?>";

function updateModels(){
    const provider = document.getElementById('aiProvider').value;
    const modelSelect = document.getElementById('aiModel');
    modelSelect.innerHTML = '';

    if(aiData[provider]){
        aiData[provider].models.forEach(model=>{
            let opt=document.createElement('option');
            opt.value=model;
            opt.innerHTML=model;
            if(model===currentModel) opt.selected=true;
            modelSelect.appendChild(opt);
        });
    }
}

updateModels();
</script>