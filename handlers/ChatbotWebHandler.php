<?php
class ChatbotWebHandler
{
    public static function handle(int $user_id, array $product): never
    {
        $ai_config_id = (int) ($_POST['ai_config_id'] ?? 0);

        if ($ai_config_id <= 0) die('AI Configuration tidak valid');

        $exists = DB::fetch(
            "SELECT id FROM ai_configs WHERE id=? AND status='active'",
            [$ai_config_id]
        );

        if (!$exists) die('AI Configuration tidak ditemukan');

        DB::begin();

        try {

            $avatar = uploadAsset('bot_avatar','avatar',['jpg','jpeg','png'],1024*1024);
            $icon   = uploadAsset('widget_icon','icon',['svg','png'],200*1024);
            $sound  = uploadAsset('notif_sound','sound',['mp3'],2*1024*1024);

            $init = ClientService::initialStatus($product);

            DB::execute("
                INSERT INTO clients (
                    user_id, product_id, name, domain,
                    service, provider, credentials,
                    ai_config_id,
                    prompt, bot_name, bot_desc, bot_avatar, bot_greeting,
                    widget_icon, widget_background,
                    notif_badge, notif_popup, notif_sound_enabled, notif_sound,
                    api_key, status, expired_at, meta
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ", [
                $user_id,
                $product['id'],
                $_POST['name'],
                $_POST['domain'],
                'web',
                'internal',
                json_encode([]),

                $ai_config_id,

                $_POST['prompt'],
                $_POST['bot_name'],
                $_POST['bot_desc'],
                $avatar,
                $_POST['bot_greeting'],
                $icon,
                $_POST['widget_background'],

                isset($_POST['notif_badge']) ? 1 : 0,
                isset($_POST['notif_popup']) ? 1 : 0,
                isset($_POST['notif_sound_enabled']) ? 1 : 0,
                $sound,

                ClientService::apiKey(),
                $init['status'],
                $init['expired_at'],
                json_encode($init['meta'])
            ]);

            $id = DB::lastId();
            DB::commit();

            redirect("client-detail.php?id={$id}");
            exit;

        } catch (Throwable $e) {
            DB::rollback();
            die($e->getMessage());
        }
    }
}