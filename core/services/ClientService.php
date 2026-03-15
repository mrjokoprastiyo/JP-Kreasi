<?php
class ClientService {

    public static function initialStatus(array $product): array {
        if (!empty($product['tier'])) {
            return [
                'status' => 'pending',
                'expired_at' => null,
                'meta' => [
                    'trial' => [
                        'started_at' => date('Y-m-d H:i:s'),
                        'ended_at' => date('Y-m-d H:i:s', strtotime("+{$product['tier']} days"))
                    ]
                ]
            ];
        }
        return ['status'=>'pending','expired_at'=>null,'meta'=>[]];
    }

    public static function apiKey(): string {
        return bin2hex(random_bytes(24));
    }
}