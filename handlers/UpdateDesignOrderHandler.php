<?php

class DesignOrderHandler
{
    // =====================================================
    // UPDATE DESIGN ORDER
    // =====================================================
    public static function update(int $user_id, array $product, int $clientId): never
    {
        DB::begin();

        try {

            // ==========================
            // VALIDASI CLIENT MILIK USER
            // ==========================
            $client = DB::fetch(
                "SELECT * FROM clients WHERE id = ? AND user_id = ?",
                [$clientId, $user_id]
            );

            if (!$client) {
                throw new Exception("Order desain tidak ditemukan.");
            }

            $oldCredentials = json_decode($client['credentials'], true) ?? [];

            // ==========================
            // HANDLE UPLOAD FILE BARU (OPSIONAL)
            // ==========================
            $referenceFile = $oldCredentials['reference_file'] ?? null;

            if (!empty($_FILES['reference_file']['name'])) {

                $referenceFile = uploadAsset(
                    'reference_file',
                    'design-reference',
                    ['jpg','jpeg','png','pdf','zip','rar'],
                    5 * 1024 * 1024
                );
            }

            // ==========================
            // BUILD DATA BARU
            // ==========================
            $newCredentials = array_merge($_POST, [
                'reference_file' => $referenceFile
            ]);

            // ==========================
            // UPDATE DATABASE
            // ==========================
            DB::execute("
                UPDATE clients SET
                    name        = ?,
                    credentials = ?,
                    updated_at  = NOW()
                WHERE id = ? AND user_id = ?
            ", [
                $_POST['customer_name'].' - '.$_POST['design_type'],
                json_encode($newCredentials, JSON_UNESCAPED_SLASHES),
                $clientId,
                $user_id
            ]);

            DB::commit();

            // ==========================
            // NOTIFIKASI UPDATE KE ADMIN
            // ==========================
            $msg = "
🛠 UPDATE ORDER DESAIN
━━━━━━━━━━━━━━
Produk: {$product['name']}

Nama: {$_POST['customer_name']}
Email: {$_POST['customer_email']}
WA: {$_POST['customer_wa']}

Jenis: {$_POST['design_type']}
Ukuran: {$_POST['size']}
Referensi: {$_POST['reference']}

Deskripsi:
{$_POST['description']}

Deadline: {$_POST['deadline']}
Catatan:
{$_POST['note']}

Client ID: {$clientId}
━━━━━━━━━━━━━━
";

            $wa = setting('admin-contact-whatsapp');

            redirect(
                "https://api.whatsapp.com/send?phone={$wa}&text=" .
                rawurlencode($msg)
            );
            exit;

        } catch (Throwable $e) {

            DB::rollback();
            die("Update Design Order Error: " . $e->getMessage());
        }
    }
}