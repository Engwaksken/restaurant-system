<?php
namespace App\Services\Payments;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class FlutterwaveGateway implements PaymentGatewayInterface {
    private function client() { $key=config('restaurant.gateways.flutterwave.secret_key'); if(!$key) throw new RuntimeException('Flutterwave is not configured.'); return Http::timeout(20)->withToken($key)->acceptJson(); }
    public function initiate(array $data): array { return $this->client()->post(config('restaurant.gateways.flutterwave.base_url').'/payments',$data)->throw()->json(); }
    public function verify(string $reference): array { return $this->client()->get(config('restaurant.gateways.flutterwave.base_url').'/transactions/'.$reference.'/verify')->throw()->json(); }
}
