<?php
require_once __DIR__.'/messengerAi.php';

class CommentHandler
{
    private static $client;
    private static $cred;
    private static $page_id;
    private static $page_token;

    public static function handle($input,$client)
    {
        self::$client = $client;
        self::$cred   = json_decode($client['credentials'],true);

        self::$page_id   = self::$cred['page_id'];
        self::$page_token= self::$cred['access_token'];

        if (empty($input['entry'][0]['changes']))
            return;

        foreach ($input['entry'][0]['changes'] as $change)
        {
            self::process($change['value']);
        }
    }

    private static function process($value)
    {
        if (($value['item'] ?? '') !== 'comment')
            return;

        if (($value['verb'] ?? '') !== 'add')
            return;

        $sender_id = $value['from']['id'] ?? null;

        if (!$sender_id || $sender_id==self::$page_id)
            return;

        $comment_id = $value['comment_id'];
        $comment    = $value['message'] ?? '';

        $reply = MessengerAI::reply(
            self::$client['id'],
            $sender_id,
            $comment
        );

        self::graph("$comment_id/likes",[]);

        self::graph("$comment_id/comments",[
            "message"=>$reply
        ]);

        DB::exec(
            "UPDATE user_data_tanicerdas
             SET comments_total=comments_total+1,
                 ai_comment_reply=?
             WHERE client_id=? AND sender_id=?",
            [$reply,self::$client['id'],$sender_id]
        );
    }

    private static function graph($endpoint,$data)
    {
        $url="https://graph.facebook.com/v22.0/$endpoint?access_token=".self::$page_token;

        $ch=curl_init($url);

        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_POST=>true,
            CURLOPT_HTTPHEADER=>["Content-Type: application/json"],
            CURLOPT_POSTFIELDS=>json_encode($data)
        ]);

        $res=curl_exec($ch);
        curl_close($ch);

        return json_decode($res,true);
    }
}