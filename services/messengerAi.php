<?php

require_once __DIR__ . '/../ai.php';

class MessengerAI
{

    public static function reply(
        int $client_id,
        string $sender_id,
        string $message
    ): string
    {

        $user = DB::fetch("
            SELECT ai_history
            FROM user_data_tanicerdas
            WHERE client_id=? AND sender_id=?
        ", [
            $client_id,
            $sender_id
        ]);


        $history = [];

        if (!empty($user['ai_history']))
        {
            $history = json_decode(
                $user['ai_history'],
                true
            );
        }



        $reply = ai_reply(
            client_id: $client_id,
            prompt: null,
            model: null,
            message: $message,
            history: $history,
            provider: null
        );



        // append history
        $history[] = [
            "role"=>"user",
            "message"=>$message
        ];

        $history[] = [
            "role"=>"assistant",
            "message"=>$reply
        ];


        // limit history
        $history = array_slice($history, -20);



        DB::exec("
            UPDATE user_data_tanicerdas
            SET ai_history=?
            WHERE client_id=? AND sender_id=?
        ", [

            json_encode($history, JSON_UNESCAPED_UNICODE),

            $client_id,
            $sender_id

        ]);


        return $reply;
    }

}