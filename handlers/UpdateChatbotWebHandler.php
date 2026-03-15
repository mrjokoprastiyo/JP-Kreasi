<?php
class UpdateChatbotWebHandler
{
    public static function handle(int $user_id): never
    {
        DB::begin();

        try {

            $client_id = (int)($_GET['id'] ?? 0);

            $client = DB::fetch("SELECT * FROM clients WHERE id=? AND user_id=?", [
                $client_id,
                $user_id
            ]);

            if (!$client) throw new Exception("Client tidak ditemukan.");

            $ai_config_id = (int) ($_POST['ai_config_id'] ?? 0);

            $exists = DB::fetch(
                "SELECT id FROM ai_configs WHERE id=? AND status='active'",
                [$ai_config_id]
            );

            if (!$exists) throw new Exception("AI Configuration tidak valid.");

            // Upload hanya jika ada file baru
            $avatar = uploadAsset('bot_avatar','avatar',['jpg','jpeg','png'],1024*1024)
                      ?: $client['bot_avatar'];

            $icon   = uploadAsset('widget_icon','icon',['svg','png'],200*1024)
                      ?: $client['widget_icon'];

            $sound  = uploadAsset('notif_sound','sound',['mp3'],2*1024*1024)
                      ?: $client['notif_sound'];

            DB::execute("
                UPDATE clients SET
                    name=?,
                    domain=?,
                    ai_config_id=?,
                    prompt=?,
                    bot_name=?,
                    bot_desc=?,
                    bot_avatar=?,
                    bot_greeting=?,
                    widget_icon=?,
                    widget_background=?,
                    notif_badge=?,
                    notif_popup=?,
                    notif_sound_enabled=?,
                    notif_sound=?,
                    updated_at=NOW()
                WHERE id=?
            ", [
                $_POST['name'],
                $_POST['domain'],
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

                $client_id
            ]);

            DB::commit();

            redirect("client-detail.php?id={$client_id}");
            exit;

        } catch (Throwable $e) {
            DB::rollback();
            die($e->getMessage());
        }
    }
}