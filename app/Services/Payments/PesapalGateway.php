<?php
namespace App\Services\Payments;
use Illuminate\Support\Facades\{Cache,Http};
use RuntimeException;
class PesapalGateway implements PaymentGatewayInterface {
    private function base(): string { return rtrim((string)config('restaurant.gateways.pesapal.base_url'),'/'); }
    private function token(): string {
        return Cache::remember('pesapal_access_token:' . sha1($this->base() . '|' . config('restaurant.gateways.pesapal.consumer_key')), 240, function () {
            $key=config('restaurant.gateways.pesapal.consumer_key'); $secret=config('restaurant.gateways.pesapal.consumer_secret');
            if(!$key || !$secret) throw new RuntimeException('Pesapal is not configured.');
            $json=Http::timeout(20)->acceptJson()->post($this->base().'/api/Auth/RequestToken',['consumer_key'=>$key,'consumer_secret'=>$secret])->throw()->json();
            return (string)($json['token'] ?? throw new RuntimeException('Pesapal token missing.'));
        });
    }
    public function initiate(array $data): array { return Http::timeout(20)->withToken($this->token())->acceptJson()->post($this->base().'/api/Transactions/SubmitOrderRequest',$data)->throw()->json(); }
    public function verify(string $reference): array { return Http::timeout(20)->withToken($this->token())->acceptJson()->get($this->base().'/api/Transactions/GetTransactionStatus',['orderTrackingId'=>$reference])->throw()->json(); }
}
