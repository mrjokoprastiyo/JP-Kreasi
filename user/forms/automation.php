<?php
if ($flow !== 'AUTOMATION_NOTIFICATION') die('Flow tidak valid');

$service      = $product['service'];
$productType  = $product['product_type'] ?? 'system';

$metaLogin = $_SESSION['meta_login'] ?? null;
$isConnected = $metaLogin &&
    $metaLogin['product_id'] == $product['id'] &&
    $metaLogin['channel'] === $service;

// Ambil data PSID/participants jika sudah connect
$senderDatas = [];
$psidWarning = '';
if ($isConnected && in_array($service, ['messenger', 'whatsapp'])) {
    $access_token = $metaLogin['access_token'] ?? $metaLogin['user_token'] ?? null;
    $page_id = $metaLogin['page_id'] ?? null;

    if ($access_token && $page_id) {
        $senderDatas = getSenderData($page_id, $access_token);

        if (empty($senderDatas)) {
            $psidWarning = "⚠️ Tidak ada PSID aktif. Silakan interaksi terlebih dahulu dengan Page <b>{$metaLogin['page_name']}</b> agar muncul sebagai target.";
        }
    }
}

// Fungsi helper untuk ambil sender dari FB Graph API
function getSenderData($page_id, $access_token) {
    $url = "https://graph.facebook.com/v22.0/me/conversations?fields=participants&access_token={$access_token}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        return [];
    }
    curl_close($ch);

    $conversations = json_decode($response, true);

    if (isset($conversations['error'])) {
        return [];
    }

    $sender_datas = [];
    if (!empty($conversations['data'])) {
        foreach ($conversations['data'] as $conversation) {
            foreach ($conversation['participants']['data'] as $participant) {
                if ($participant['id'] != $page_id) {
                    $sender_datas[] = [
                        'participant_id'   => $participant['id'],
                        'participant_name' => $participant['name'],
                        'conversation_id'  => $conversation['id']
                    ];
                }
            }
        }
    }
    return $sender_datas;
}
?>

<main class="user-content">
    <header class="page-header">
        <h1>Setup Automation <?= ucfirst($service) ?></h1>
    </header>

    <form method="POST" class="form-grid">

        <section class="card">
            <h3>Status Integrasi</h3>
            <p><strong>Channel:</strong> <?= strtoupper($service) ?></p>
            <div class="hint <?= $productType ?>-hint">
                <?= $productType === 'system'
                    ? '🛡️ <b>System Managed:</b> Kami menangani infrastruktur pengiriman.'
                    : '🔑 <b>Client Owned:</b> Gunakan kredensial API Anda sendiri.' ?>
            </div>
        </section>

        <section class="card">
            <h3>Informasi Dasar</h3>

            <label>Nama Automation</label>
            <input type="text" name="name" required>

            <label>Sumber Data (Google Sheets)</label>
            <div class="input-group">
                <input type="text" name="source[spreadsheet_id]" placeholder="ID Spreadsheet" required>
                <input type="text" name="source[gid]" placeholder="GID (Default 0)" value="0">
            </div>
        </section>

        <section class="card">
            <h3>Konfigurasi Tujuan (<?= ucfirst($service) ?>)</h3>

            <?php if (in_array($service, ['messenger','whatsapp'])): ?>

                <?php if (!$isConnected): ?>
                    <a href="meta-start.php?product_id=<?= $product['id'] ?>&channel=<?= $service ?>"
                       class="btn secondary">
                       🔗 Connect Facebook Page
                    </a>
                <?php else: ?>

                    <label>Pilih Facebook Page</label>
                    <select name="page_id" id="page_id" required>
                        <option value="">-- Pilih Page --</option>
                        <?php foreach ($metaPages as $page): ?>
                            <option value="<?= $page['id'] ?>"
                                    data-token="<?= $page['access_token'] ?>">
                                <?= htmlspecialchars($page['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="hidden" name="page_access_token" id="page_access_token">

                    <script>
                    const psidSelect = document.getElementById('psid_list');
                    const psidWarningDiv = document.getElementById('psid_warning');

                    document.getElementById('page_id').addEventListener('change', function(){
                        let token = this.options[this.selectedIndex].dataset.token;
                        document.getElementById('page_access_token').value = token;

                        // Refresh PSID dropdown
                        fetch('fetch-sender.php?page_id=' + this.value + '&token=' + token)
                            .then(res => res.json())
                            .then(data => {
                                psidSelect.innerHTML = '<option value="">-- Pilih PSID --</option>';
                                psidWarningDiv.innerHTML = '';
                                if (data.warning) {
                                    psidWarningDiv.innerHTML = data.warning;
                                }
                                if (Array.isArray(data) && data.length > 0) {
                                    data.forEach(item => {
                                        let opt = document.createElement('option');
                                        opt.value = item.participant_id;
                                        opt.text = item.participant_name;
                                        psidSelect.appendChild(opt);
                                    });
                                }
                            })
                            .catch(err => {
                                psidWarningDiv.innerHTML = '⚠️ Gagal mengambil daftar PSID. Coba refresh halaman.';
                            });
                    });
                    </script>

                    <label>Pilih PSID Tujuan</label>
                    <select name="target[destination][psid]" id="psid_list" required>
                        <option value="">-- Pilih PSID --</option>
                        <?php foreach ($senderDatas as $sender): ?>
                            <option value="<?= $sender['participant_id'] ?>">
                                <?= htmlspecialchars($sender['participant_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <?php if ($psidWarning): ?>
                        <div id="psid_warning" class="manual-note"><?= $psidWarning ?></div>
                    <?php else: ?>
                        <div id="psid_warning" class="manual-note"></div>
                    <?php endif; ?>

                <?php endif; ?>

            <?php elseif ($service === 'telegram'): ?>

                <label>Bot Token</label>
                <input type="text" name="target[credentials][bot_token]" required>
                <label>Chat ID Tujuan</label>
                <input type="text" name="target[destination][chat_id]" required>

            <?php elseif ($service === 'email'): ?>

                <label>Email Penerima</label>
                <input type="email" name="target[destination][to]" required>

            <?php elseif ($service === 'website'): ?>

                <div class="manual-note">
                    ⚠️ Automation Website diproses manual oleh admin.
                </div>

                <label>Endpoint</label>
                <input type="url" name="target[destination][endpoint]" required>

                <label>Metode</label>
                <select name="target[destination][method]">
                    <option value="POST">POST</option>
                    <option value="GET">GET</option>
                </select>

            <?php endif; ?>
        </section>

        <section class="card">
            <h3>Format Output</h3>
            <select name="file[type]">
                <option value="pdf">PDF</option>
                <option value="xlsx">Excel</option>
                <option value="csv">CSV</option>
            </select>
        </section>

        <section class="card highlight">
            <button type="submit" class="btn primary">
                <?= ($service === 'website') ? '📩 Kirim ke Admin' : '🚀 Aktifkan Automation' ?>
            </button>
        </section>

    </form>
</main>


<style>
/* ===============================
   FORM GRID
================================ */
.form-grid {
max-width:900px;
  display: grid;
  grid-template-columns: 1fr;
  gap: 20px;
}

/* desktop */
@media (min-width: 1024px) {
  .form-grid {
    grid-template-columns: 1fr 1fr;
    align-items: start;
  }

  .highlight {
    grid-column: 1 / -1;
  }
}

/* ===============================
   CARD
================================ */
.card {
  background: #ffffff;
  padding: 16px 18px;
  border-radius: 12px;
  box-shadow: 0 8px 24px rgba(0,0,0,.04);
}

.card h3 {
  font-size: 16px;
  margin-bottom: 12px;
  color: #111827;
}

/* ===============================
   FORM ELEMENT
================================ */
label {
  display: block;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 6px;
}

input,
select,
textarea {
  width: 100%;
  padding: 9px 11px;
  border-radius: 8px;
  border: 1px solid #d1d5db;
  font-size: 14px;
  margin-bottom: 14px;
}

input:focus,
select:focus {
  outline: none;
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37,99,235,.15);
}

/* ===============================
   HINT / NOTE
================================ */
.hint {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-top: 6px;
}

.note {
  margin-top: 8px;
  font-size: 13px;
  color: #4b5563;
}

.manual-note {
  background: #fff7ed;
  border: 1px solid #fed7aa;
  color: #9a3412;
  padding: 12px;
  border-radius: 8px;
  font-size: 13px;
  margin-bottom: 14px;
  line-height: 1.5;
}

/* system vs client */
.system-only,
.client-only {
  display: none;
}

/* ===============================
   TARGET / CONDITIONAL BLOCK
================================ */
.target {
  display: none;
}

.target h3 {
  margin-bottom: 14px;
}

/* ===============================
   WHATSAPP PROVIDER BLOCK
================================ */
.wa {
  display: none;
  margin-top: 10px;
  padding: 12px;
  border-radius: 8px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
}

.wa label {
  margin-top: 8px;
}

/* ===============================
   PRODUCT INFO CARD
================================ */
.system-only.hint,
.client-only.hint {
  margin-top: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 12px;
}

.system-only.hint {
  background: #ecfeff;
  border: 1px solid #67e8f9;
  color: #075985;
}

.client-only.hint {
  background: #fff7ed;
  border: 1px solid #fed7aa;
  color: #9a3412;
}

/* ===============================
   SUBMIT AREA
================================ */
.card.highlight {
  background: linear-gradient(to right, #f8fafc, #f1f5f9);
  border: 1px dashed #c7d2fe;
  text-align: center;
}

.card.highlight .btn.primary {
  min-width: 260px;
  padding: 12px 18px;
  font-size: 15px;
  border-radius: 10px;
}

/* mobile */
@media (max-width: 640px) {
  .card.highlight .btn.primary {
    width: 100%;
  }
}
</style>
