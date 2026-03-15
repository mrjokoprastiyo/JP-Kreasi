<?php

class DesignOrderHandler
{
    public static function handle(int $user_id, array $product): never
    {
        DB::begin();

        try {
            $referenceFile = uploadAsset(
                'reference_file',
                'design-reference',
                ['jpg','jpeg','png','pdf','zip','rar'],
                5 * 1024 * 1024
            );

            // $init = determineInitialStatus($product);
            $init = ClientService::initialStatus($product);

            DB::execute("
                INSERT INTO clients (
                    user_id, product_id, name,
                    service, provider, credentials,
                    status, expired_at, meta, api_key
                ) VALUES (?,?,?,?,?,?,?,?,?,?)
            ", [
                $user_id,
                $product['id'],
                $_POST['customer_name'].' - '.$_POST['design_type'],
                $product['service'],
                'manual',
                json_encode($_POST + ['reference_file' => $referenceFile], JSON_UNESCAPED_SLASHES),
                $init['status'],
                $init['expired_at'],
                json_encode($init['meta']),
                ClientService::apiKey()
            ]);

            $clientId = DB::lastId();
            DB::commit();

            $msg = "
            🧾 ORDER DESAIN VISUAL
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

            $wa  = setting('admin-contact-whatsapp');

            redirect(
                "https://api.whatsapp.com/send?phone={$wa}&text=" .
                rawurlencode($msg)
            );
            exit;

        } catch (Throwable $e) {
            DB::rollback();
            die($e->getMessage());
        }
    }
}